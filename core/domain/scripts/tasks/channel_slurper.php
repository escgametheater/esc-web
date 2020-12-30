<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/2/19
 * Time: 1:01 AM
 */

require "vendor/autoload.php";

class Net_Gearman_Job_channel_slurper extends Task
{
    protected $args = [];
    protected $name = 'channel_slurper';
    protected $request;

    /**
     * @param Request $request
     * @param $args
     * @throws Net_Gearman_Job_Exception
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function process(Request $request, $args)
    {
        if (!$gameInstanceId = $this->getArg(DBField::GAME_INSTANCE_ID))
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments: GameInstanceId');

        if (!$pubSubChannel = $this->getArg(DBField::PUB_SUB_CHANNEL))
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments: PubSubChannel');

        if (!$pubSubChannelType = $this->getArg(DBField::PUB_SUB_CHANNEL_TYPE))
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments: PubSubChannelType');

        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $hostsInstancesManager = $request->managers->hostsInstances();
        $gamesInstancesLogsManager = $request->managers->gamesInstancesLogs();
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();

        std_log("* {$request->getCurrentSqlTime()} - INFO - Starting Channel Slurper For Game Instance ID: {$gameInstanceId}, Type: {$pubSubChannelType}, Channel: {$pubSubChannel}");

        Modules::load_helper(Helpers::PUBNUB);

        $gameInstance = $gamesInstancesManager->getGameInstanceById($request, $gameInstanceId, false);
        $hostInstance = $hostsInstancesManager->getHostInstanceById($request, $gameInstance->getHostInstanceId(), false);


        if ($hostInstance->getHostInstanceTypeId() == HostsInstancesTypesManager::ID_ESC_WAN_CLOUD) {
            std_log("* {$request->getCurrentSqlTime()} - INFO - Skipping slurp of CLOUD game");
            return;
        }

        /** @var GameBuildEntity $gameBuild */
        $gameBuild = $gamesBuildsManager->query($request->db)->filter($gameInstance->getGameBuildId())->get_entity($request);
        $gameInstance->setGameBuild($gameBuild);

        $pubNub = PubNubLoader::getPubNubInstance($request);

        if (!$gameInstanceLog = $gamesInstancesLogsManager->getGameInstanceLog($request, $gameInstance->getPk(), $pubSubChannelType, $pubSubChannel))
            $gameInstanceLog = $gamesInstancesLogsManager->createNewGameInstanceLog($request, $gameInstance->getPk(), $pubSubChannelType, $pubSubChannel);

        $gamesAssetsManager = $request->managers->gamesAssets();

        $rawData = [];

        $isWorking = true;
        $startTime = null;
        $endTime = null;

        $activeCount = 0;
        $countFetch = 0;

        $gameInstanceLog->updateField(DBField::GAME_INSTANCE_LOG_STATUS_ID, GamesInstancesLogsStatusesManager::ID_PROCESSING)->saveEntityToDb($request);

        $startProcessingTime = time();

        $startQueueTime = strtotime($gameInstance->getStartTime())*10000000;

        $countPerFetch = 100;
        $maxCount = 0;
        $count = 0;

        while ($isWorking) {

            try {

                $results = $pubNub->history()
                    ->channel($pubSubChannel)
                    ->count($countPerFetch)
                    ->start($startTime)
                    ->end($startQueueTime)
                    ->includeTimetoken(true)
                    ->sync();

                $countFetch++;
                $count = count($results->getMessages());

                std_log("* {$request->getCurrentSqlTime()} - INFO - API Call {$countFetch} - Slurped Game Instance ID: {$gameInstanceId}, Type: {$pubSubChannelType}, Channel: {$pubSubChannel}, Messages: {$count}");

            } catch (\PubNub\Exceptions\PubNubServerException $exception) {
                std_log("Message: " . $exception->getMessage() . "\n");
                std_log("Status: " . $exception->getStatusCode() . "\n");
                std_log("Original message: ");
                std_log(json_encode($exception->getBody()));
                $results = [];
            }




            if ($count) {
                $startTime = $results->getStartTimetoken();
                //$startTime = $results->getEndTimetoken();

                foreach (array_reverse($results->getMessages()) as $message) {
                    $data = [
                        'timetoken' => (string) $message->getTimetoken(),
                        'entry' => $message->getEntry()
                    ];

                    array_unshift($rawData, $data);

                    $activeCount++;
                }

                if ($startTime < $startQueueTime)
                    $isWorking = false;

                if ($count < $countPerFetch) {
                    $isWorking = false;
                }

                if ($maxCount && $activeCount >= $maxCount) {
                    $isWorking = false;
                }

            } else {
                $isWorking = false;
            }
            //unset($results);

        }

        $endProcessingTime = time();

        $updatedLogData = [
            DBField::PROCESSING_TIME => $endProcessingTime-$startProcessingTime,
            DBField::MESSAGE_COUNT => count($rawData)
        ];

        $statusId = GamesInstancesLogsStatusesManager::ID_COMPLETED;


        $fileName = "{$pubSubChannel}.json";
        $zipFileName = "{$pubSubChannel}.zip";
        $destinationFolder = $gamesInstancesManager->generateLogDestinationFolder($request, $gameInstanceLog, $pubSubChannel);
        $sourceFile = "{$destinationFolder}/{$zipFileName}";

        if ($rawData) {
            $canUpload = true;
            if (!is_dir($destinationFolder)) {
                if (mkdir($destinationFolder, 0777, true)) {
                } else {
                    $canUpload = false;
                }
            } else {
                $canUpload = true;
            }

            if ($canUpload) {

                // Zip Up Channel Log to save massive amounts of space!
                $zipArchive = new ZipArchive();
                $zipArchive->open($sourceFile, ZipArchive::CREATE);
                $zipArchive->addFromString($fileName, json_encode($rawData));
                $zipArchive->close();

                if (is_file($sourceFile)) {

                    try {
                        $gameInstanceLogAsset = $gamesAssetsManager->handleGameInstanceLogAssetUpload(
                            $request,
                            $sourceFile,
                            md5_file($sourceFile),
                            $zipFileName,
                            $gameInstance->getGameId(),
                            null,
                            $gameInstance->getGameBuild()->getUpdateChannel(),
                            $gameInstanceLog->getPk()
                        );

                        unset($zipArchive);
                        unlink($sourceFile);
                        //FilesToolkit::clear_directory($destinationFolder);
                        unset($rawData);


                    } catch (Exception $e) {
                        $statusId = GamesInstancesLogsStatusesManager::ID_FAILED;
                        $updatedLogData[DBField::GAME_INSTANCE_LOG_STATUS_ID] = $statusId;
                        $gameInstanceLog->assign($updatedLogData)->saveEntityToDb($request);
                        throw $e;
                    }

                } else {
                    $statusId = GamesInstancesLogsStatusesManager::ID_FAILED;
                }
            } else {
                $statusId = GamesInstancesLogsStatusesManager::ID_FAILED;
            }
        }

        $updatedLogData[DBField::GAME_INSTANCE_LOG_STATUS_ID] = $statusId;

        $gameInstanceLog->assign($updatedLogData)->saveEntityToDb($request);

        std_log("* {$request->getCurrentSqlTime()} - INFO - Finished Channel Slurper For Game Instance ID: {$gameInstanceId}, Channel: {$pubSubChannel}");
    }
}