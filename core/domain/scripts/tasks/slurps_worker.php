<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/27/19
 * Time: 11:21 PM
 */

require "vendor/autoload.php";

class Net_Gearman_Job_slurps_worker extends Task
{
    protected $args = [];
    protected $name = 'slurps_worker';
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
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments');

        $hostInstancesManager = $request->managers->hostsInstances();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $gamesManager = $request->managers->games();
        $gamesBuildsManager = $request->managers->gamesBuilds();

        std_log("* {$request->getCurrentSqlTime()} - INFO - Starting SlurpsWorker Spawning For Game Instance ID: {$gameInstanceId}");

        Modules::load_helper(Helpers::PUBNUB);

        $gameInstance = $gamesInstancesManager->getGameInstanceById($request, $gameInstanceId, false);

        if ($gameInstance && $gameInstance->has_ended()) {

            $hostInstance = $hostInstancesManager->getHostInstanceById($request, $gameInstance->getHostInstanceId(), false, false);

            $game = $gamesManager->getGameById($request, $gameInstance->getGameId());
            $gameBuild = $gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
            $gameInstance->setGameBuild($gameBuild);
            $game->setGameBuild($gameBuild);

            $gameInstance->setGame($game);

            $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

            foreach ($pubNubHelper->getHostGameInstancePubNubChannels() as $pubNubChannel) {
                if ($pubNubChannel && $pubNubChannel->getChannelType() !== PubNubHelper::CHANNEL_OFFLINE_BROADCAST) {
                    std_log("* {$request->getCurrentSqlTime()} - INFO - Triggering SlurperTask For Game Instance ID: {$gameInstanceId}, Type {$pubNubChannel->getChannelType()}, Channel {$pubNubChannel->getChannelName()}");
                    $gamesInstancesManager->triggerChannelSlurperTask($gameInstance, $pubNubChannel->getChannelName(), $pubNubChannel->getChannelType());
                }
            }

            std_log("* {$request->getCurrentSqlTime()} - INFO - Finished Spawning Slurpers For Game Instance ID: {$gameInstanceId}");

        } else {
            std_log("* {$request->getCurrentSqlTime()} - INFO - Game Instance Not Available or Ended: {$gameInstanceId}");
        }
    }
}
