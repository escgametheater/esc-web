<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/18/18
 * Time: 11:07 AM
 */


class GamesInstancesManager extends BaseEntityManager {

    const EXIT_STATUS_KILLED = 'killed';
    const EXIT_STATUS_TIMED_OUT = 'timed-out';
    const EXIT_STATUS_STOPPED = 'stopped';

    protected $entityClass = GameInstanceEntity::class;
    protected $table = Table::GameInstance;
    protected $table_alias = TableAlias::GameInstance;
    protected $pk = DBField::GAME_INSTANCE_ID;

    protected $foreign_managers = [
        GamesManager::class => DBField::GAME_ID,
        HostsInstancesManager::class => DBField::HOST_INSTANCE_ID
    ];

    public static $fields = [
        DBField::GAME_INSTANCE_ID,
        DBField::GAME_ID,
        DBField::GAME_BUILD_ID,
        DBField::GAME_MOD_BUILD_ID,
        DBField::ACTIVATION_ID,
        DBField::HOST_INSTANCE_ID,
        DBField::PUB_SUB_CHANNEL,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::LAST_PING_TIME,
        DBField::EXIT_STATUS,
        DBField::MINIMUM_PLAYERS,
        DBField::MAXIMUM_PLAYERS,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameInstanceEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $urlHost = $request->config['hosts']['play'];
        $url = "{$request->scheme}://{$urlHost}/i/{$data->getHostInstanceId()}/{$data->getPk()}/";

        $data->updateField(VField::URL, $url);
        $data->updateField(VField::PUB_NUB_CHANNELS, []);
        $data->updateField(VField::GAME, []);
        $data->updateField(VField::GAME_BUILD, []);
        $data->updateField(VField::GAME_MOD, []);
        $data->updateField(VField::GAME_MOD_BUILD, []);
        $data->updateField(VField::GAME_CONTROLLERS, []);
        $data->updateField(VField::GAME_INSTANCE_LOGS, []);
        $data->updateField(VField::GAME_INSTANCE_ROUNDS, []);
        $data->updateField(VField::ACTIVATION, []);
    }

    /**
     * @param Request $request
     * @param $gameInstances
     */
    protected function postProcessGameInstances(Request $request, $gameInstances, $expand = true)
    {
        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();
        $activationsManager = $request->managers->activations();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $gamesControllersManager = $request->managers->gamesControllers();

        /** @var GameInstanceEntity[] $gameInstances */
        if ($gameInstances = $this->preProcessResultAsResultArray($gameInstances)) {

            $gameIds = unique_array_extract(DBField::GAME_ID, $gameInstances);
            $gameBuildIds = unique_array_extract(DBField::GAME_BUILD_ID, $gameInstances);
            $gameModBuildIds = unique_array_extract(DBField::GAME_MOD_BUILD_ID, $gameInstances, false);
            $activationIds = unique_array_extract(DBField::ACTIVATION_ID, $gameInstances, false);

            $games = $gamesManager->getGamesByIds($request, $gameIds);
            /** @var GameEntity[] $games */
            $games = array_index($games, $gamesManager->getPkField());

            $gameBuilds = $gamesBuildsManager->getGameBuildsByIds($request, $gameBuildIds, $expand);
            /** @var GameBuildEntity[] $gameBuilds */
            $gameBuilds = array_index($gameBuilds, $gamesBuildsManager->getPkField());

            $gameControllers = $gamesControllersManager->getGameControllersByGameBuildId($request, $gameBuildIds, $expand);

            /** @var GameModBuildEntity[] $gameModBuilds */
            $gameModBuilds = [];
            /** @var GameModEntity[] $gameMods */
            $gameMods = [];
            if ($gameModBuildIds) {
                $gameModBuilds = $gamesModsBuildsManager->getGameModBuildsByIds($request, $gameModBuildIds);
                $gameModBuilds = $gamesModsBuildsManager->index($gameModBuilds);

                $gameModIds = unique_array_extract(DBField::GAME_MOD_ID, $gameModBuilds);
                $gameMods = $gamesModsManager->getGameModsByIds($request, $gameModIds);
                $gameMods = $gamesModsManager->index($gameMods);

            }

            $activations = [];
            if ($activationIds) {
                $activations = $activationsManager->getActivationsByIds($request, $activationIds);
                $activations = $activationsManager->index($activations);
            }

            foreach ($gameInstances as $gameInstance) {

                if ($gameInstance->getActivationId() && array_key_exists($gameInstance->getActivationId(), $activations)) {
                    $gameInstance->setActivation($activations[$gameInstance->getActivationId()]);
                }

                if (array_key_exists($gameInstance->getGameId(), $games)) {
                    $gameInstance->setGame($games[$gameInstance->getGameId()]);
                }

                if (array_key_exists($gameInstance->getGameBuildId(), $gameBuilds)) {
                    $gameInstance->setGameBuild($gameBuilds[$gameInstance->getGameBuildId()]);
                }

                if ($gameInstance->getGameModBuildId() && array_key_exists($gameInstance->getGameModBuildId(), $gameModBuilds)) {
                    $gameModBuild = $gameModBuilds[$gameInstance->getGameModBuildId()];
                    $gameInstance->setGameMod($gameMods[$gameModBuild->getGameModId()]);
                    $gameInstance->setGameModBuild($gameModBuild);
                }

                foreach ($gameControllers as $gameController) {
                    if ($gameInstance->getGameBuildId() == $gameController->getGameBuildId())
                        $gameInstance->setGameController($gameController);
                }
            }
        }

    }


    /**
     * @param Request $request
     * @param GameInstanceLogEntity $gameInstanceLog
     * @param $channel
     * @return string
     */
    public function generateLogDestinationFolder(Request $request, GameInstanceLogEntity $gameInstanceLog, $channel)
    {
        $channel = str_replace('.', '_', $channel);
        return "{$request->settings()->getMediaDir()}/game-instances/{$gameInstanceLog->getPk()}/{$channel}";
    }

    /**
     * @param GameInstanceEntity $gameInstance
     */
    public function triggerGameInstanceSlurpsWorkerTask(Request $request, GameInstanceEntity $gameInstance)
    {
        $gamesInstancesManager = $request->managers->gamesInstances();
        $hostInstancesManager = $request->managers->hostsInstances();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesManager = $request->managers->games();

        Modules::load_helper(Helpers::PUBNUB);

        $gameInstance = $gamesInstancesManager->getGameInstanceById($request, $gameInstance->getPk(), false);

        if ($gameInstance && $gameInstance->has_ended()) {

            $hostInstance = $hostInstancesManager->getSlimHostInstanceById($request, $gameInstance->getHostInstanceId(), false, false);


            if ($hostInstance->getHostInstanceTypeId() == HostsInstancesTypesManager::ID_ESC_WAN_CLOUD) {
                return;
            }

            $game = $gamesManager->getGameById($request, $gameInstance->getGameId());
            $gameBuild = $gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
            $gameInstance->setGameBuild($gameBuild);
            $game->setGameBuild($gameBuild);

            $gameInstance->setGame($game);

            $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

            foreach ($pubNubHelper->getHostGameInstancePubNubChannels() as $pubNubChannel) {
                if ($pubNubChannel && $pubNubChannel->getChannelType() !== PubNubHelper::CHANNEL_OFFLINE_BROADCAST) {
                    //std_log("* {$request->getCurrentSqlTime()} - INFO - Triggering SlurperTask For Game Instance ID: {$gameInstance->getPk()}, Type {$pubNubChannel->getChannelType()}, Channel {$pubNubChannel->getChannelName()}");
                    $gamesInstancesManager->triggerChannelSlurperTask($gameInstance, $pubNubChannel->getChannelName(), $pubNubChannel->getChannelType());
                }
            }

        } else {

        }
    }

    /**
     * @param GameInstanceEntity $gameInstance
     * @param $pubSubChannel
     */
    public function triggerChannelSlurperTask(GameInstanceEntity $gameInstance, $pubSubChannel, $pubSubChannelType)
    {
        TasksManager::add(TasksManager::TASK_CHANNEL_SLURPER, [
            DBField::GAME_INSTANCE_ID => $gameInstance->getPk(),
            DBField::PUB_SUB_CHANNEL => $pubSubChannel,
            DBField::PUB_SUB_CHANNEL_TYPE => $pubSubChannelType
        ]);
    }


    /**
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields()
    {
        $fields = [
            // new IntegerField(DBField::ID, 'Game Instance ID', $requirePk),
            new IntegerField(DBField::GAME_ID, 'Game ID', true),
            new IntegerField(DBField::GAME_BUILD_ID, 'Game Build ID', false),
            new IntegerField(DBField::GAME_MOD_BUILD_ID, 'Game Mod Build ID', false),
            new IntegerField(DBField::ACTIVATION_ID, 'ActivationId', false),
            new IntegerField(DBField::HOST_INSTANCE_ID, 'Host Instance ID', true),
//            new CharField(DBField::LOCAL_IP_ADDRESS, 'Local IP Address', 64, false),
//            new IntegerField(DBField::LOCAL_PORT, 'Local Port', 64, false),
            new BooleanField(VField::NOTIFY_SMS, 'Notify user via sms', false),
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $offlineGameId
     * @param $gameBuildId
     * @param $hostPubSubChannel
     * @return GameInstanceEntity
     */
    public function generateOfflineGameInstance(Request $request, $offlineGameId, $gameBuildId, $gameModBuildId, $hostPubSubChannel, $activationId = null)
    {
        $gameInstanceData = [
            DBField::GAME_INSTANCE_ID => -1,
            DBField::GAME_ID => $offlineGameId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::ACTIVATION_ID => $activationId,
            DBField::HOST_INSTANCE_ID => -1,
            DBField::PUB_SUB_CHANNEL => $hostPubSubChannel,
            DBField::START_TIME => $request->getCurrentSqlTime(),
            DBField::END_TIME => null,
            DBField::LAST_PING_TIME => $request->getCurrentSqlTime(),
            DBField::EXIT_STATUS => null,
            DBField::MINIMUM_PLAYERS => 25,
            DBField::MAXIMUM_PLAYERS => 80000,

            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
            DBField::MODIFIED_BY => null,
            DBField::DELETED_BY => null
        ];

        /** @var GameInstanceEntity $gameInstance */
        $gameInstance = $this->createEntity($gameInstanceData, $request);

        return $gameInstance;
    }

    /**
     * @param Request $request
     * @param $displayName
     * @param null $ownerTypeId
     * @param null $ownerId
     * @return GameInstanceEntity
     */
    public function createNewGameInstance(Request $request, $gameId, $gameBuildId, $hostInstanceId, $minimumPlayers,
                                          $maximumPlayers, $gameModBuildId = null, $activationId = null, $startTime = null, $endTime = null)
    {
        $uuIdV4HostName = uuidV4HostName();

        $pubSubChannelHash = sha1("{$gameId}-{$gameBuildId}-{$startTime}-{$uuIdV4HostName}");
        $pubSubChannel = "game-{$pubSubChannelHash}";

        $data = [
            DBField::GAME_ID => $gameId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::ACTIVATION_ID => $activationId,
            DBField::HOST_INSTANCE_ID => $hostInstanceId,
            DBField::PUB_SUB_CHANNEL => $pubSubChannel,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::LAST_PING_TIME => $startTime,
            DBField::MINIMUM_PLAYERS => $minimumPlayers,
            DBField::MAXIMUM_PLAYERS => $maximumPlayers,
            DBField::IS_ACTIVE => 1
        ];

        /** @var GameInstanceEntity $gameInstance */
        $gameInstance = $this->query($request->db)->createNewEntity($request, $data);

        $this->postProcessGameInstances($request, $gameInstance);

        return $gameInstance;
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @return array|GameInstanceEntity
     */
    public function getGameInstanceById(Request $request, $gameInstanceId, $expand = false)
    {
        /** @var GameInstanceEntity $gameInstance */
        $gameInstance = $this->query($request->db)
            ->filter($this->filters->byPk($gameInstanceId))
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameInstances($request, $gameInstance);

        return $gameInstance;
    }

    /**
     * @param Request $request
     * @return GameInstanceEntity[]
     */
    public function getExpiredGameInstancesForClosedHostInstances(Request $request)
    {
        $hostsInstancesManager = $request->managers->hostsInstances();

        /** @var GameInstanceEntity[] $gameInstances */
        $gameInstances = $this->query($request->db)
            ->inner_join($hostsInstancesManager)
            ->filter($hostsInstancesManager->filters->IsNotNull(DBField::END_TIME))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $gameInstances;
    }

    /**
     * @param Request $request
     * @param $hostInstanceIds
     * @param bool $expand
     * @return GameInstanceEntity[]
     */
    public function getGameInstancesByHostInstanceIds(Request $request, $hostInstanceIds, $expand = false)
    {
        /** @var GameInstanceEntity[] $gameInstances */
        $gameInstances = $this->query($request->db)
            ->filter($this->filters->byHostInstanceId($hostInstanceIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessGameInstances($request, $gameInstances, $expand);

        return $gameInstances;
    }

    /**
     * @param Request $request
     * @param $hostInstanceId
     * @param bool $expand
     * @return GameInstanceEntity
     */
    public function getRunningGameInstanceByHostInstanceId(Request $request, $hostInstanceId, $expand = false, $postProcess = true)
    {
        /** @var GameInstanceEntity $gameInstances */
        $gameInstances = $this->query($request->db)
            ->filter($this->filters->byHostInstanceId($hostInstanceId))
            ->filter($this->filters->IsNull(DBField::EXIT_STATUS))
            ->filter($this->filters->isActive())
            ->sort_desc($this->getPkField())
            ->get_entity($request);

        if ($postProcess)
            $this->postProcessGameInstances($request, $gameInstances, $expand);

        return $gameInstances;
    }


    /**
     * @param Request $request
     * @param GameInstanceEntity $gameInstance
     * @param string $exitStatus
     */
    public function stopGameInstance(Request $request, GameInstanceEntity $gameInstance, $exitStatus = self::EXIT_STATUS_STOPPED)
    {
        $hostInstancesManager = $request->managers->hostsInstances();
        $gamesManager = $request->managers->games();
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesBuildsManager = $request->managers->gamesBuilds();

        if (!$gameInstance->getGame()) {
            $game = $gamesManager->getGameById($request, $gameInstance->getGameId());
            $gameInstance->setGame($game);
        }

        $gameInstanceRounds = $gamesInstancesRoundsManager->getGameInstanceRoundsByGameInstanceId($request, $gameInstance->getPk());

        $currentTime = $request->getCurrentSqlTime();

        $hostInstance = $hostInstancesManager->getSlimHostInstanceById($request, $gameInstance->getHostInstanceId(), false, false);

        foreach ($gameInstanceRounds as $gameInstanceRound) {

            $playerExitStatus = GamesInstancesRoundsPlayersManager::EXIT_STATUS_QUIT;

            if ($exitStatus == self::EXIT_STATUS_TIMED_OUT)
                $playerExitStatus = GamesInstancesRoundsPlayersManager::EXIT_STATUS_TIMED_OUT;

            foreach ($gameInstanceRound->getGameInstanceRoundPlayers() as $player) {
                if (!$player->has_ended()) {
                    $player->assign([
                        DBField::END_TIME => $currentTime,
                        DBField::EXIT_STATUS => $playerExitStatus
                    ]);
                    $player->saveEntityToDb($request);
                }
            }

            if (!$gameInstanceRound->has_ended()) {
                /** @var GameInstanceRoundEntity $gameInstanceRound */
                $gameInstanceRound->updateField(DBField::END_TIME, $currentTime)->saveEntityToDb($request);

            }
        }

        if (!$gameInstance->has_ended()) {

            $gameInstance->assign([
                DBField::END_TIME =>  $currentTime,
                DBField::EXIT_STATUS => $exitStatus
            ]);

            $gameInstance->saveEntityToDb($request);

            $this->triggerGameInstanceSlurpsWorkerTask($request, $gameInstance);

            $hostInstances = $hostInstancesManager->getActivePrioritizedHostInstancesByHostId($request, $hostInstance->getHostId(), true);

            $lbeHostInstance = [];
            $cloudHostInstance = [];

            $activeGameInstanceRoundId = false;
            $activeGameInstanceId = false;

            foreach ($hostInstances as $activeHostInstance) {
                if ($activeHostInstance->is_type_esc_host_app()) {
                    $lbeHostInstance = $activeHostInstance;
                } else {
                    $cloudHostInstance = $activeHostInstance;
                }

                if ($activeGameInstance = $activeHostInstance->getActiveGameInstance()) {

                    $activeGameInstanceRound = $gamesInstancesRoundsManager->getActiveGameInstanceRoundByGameInstanceId($request, $activeGameInstance->getPk());

                    if ($activeGameInstanceRound) {
                        $activeGameInstanceId = $activeGameInstance->getPk();
                        $activeGameInstanceRoundId = $activeGameInstanceRound->getPk();
                        break;
                    }
                }
            }

            // If there's a LBE host instance running and this is a cloud round, don't publish offline data.
            if ($lbeHostInstance && $cloudHostInstance && ($cloudHostInstance->getPk() == $hostInstance->getPk())) {
                $shouldUpdateOfflineData = false;
            } else {
                $shouldUpdateOfflineData = true;
            }

            if ($shouldUpdateOfflineData) {

                $gameBuild = $gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
                $gameInstance->setGameBuild($gameBuild);
                $game->setGameBuild($gameBuild);

                Modules::load_helper(Helpers::PUBNUB);
                $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);
                $pubnub = PubNubLoader::getPubNubInstance($request);
                $pubNubChannel = $pubNubHelper->getHostGameInstancePubNubChannels()[PubNubHelper::CHANNEL_OFFLINE_BROADCAST];
                $hostAndGameStatus = [
                    'host_running' => $hostInstances ? true : false,
                    'game_running' => $activeGameInstanceRoundId ? true : false,
                    'next_url' => $hostInstance->getHost()->getUrl(),
                    'game_instance_id' => $activeGameInstanceId,
                    'game_instance_round_id' => $activeGameInstanceRoundId

                ];

                PubNubApiHelper::updateOfflineGameData($pubnub, $pubNubChannel->getChannelName(), $hostAndGameStatus);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameInstanceIds
     * @return array
     */
    public function getGameInstanceSummaryStatsByGameInstanceIds(Request $request, $gameInstanceIds)
    {
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();
        $sessionsManager = $request->managers->sessionTracking();

        $fields = [
            $this->createPkField(),
            new CountDBField('count_rounds', $gamesInstancesRoundsManager->createPkField(), $gamesInstancesRoundsManager->getTable(), true),
            new CountDBField('count_player_sessions', $gamesInstancesRoundsPlayersManager->createPkField(), $gamesInstancesRoundsPlayersManager->getTable(), true),
            new CountDBField('count_unique_players', $sessionsManager->field(DBField::GUEST_ID), $gamesInstancesRoundsPlayersManager->getTable(), true)
        ];

        $joinGamesInstancesRoundsFilter = $gamesInstancesRoundsManager->filters->byGameInstanceId($this->createPkField());
        $joinGamesInstancesRoundsPlayersFilter = $gamesInstancesRoundsPlayersManager->filters->byGameInstanceRoundId($gamesInstancesRoundsManager->createPkField());
        $joinSessionsFilter = $sessionsManager->filters->byPk($gamesInstancesRoundsPlayersManager->field(DBField::SESSION_ID));

        $summaryStats = $this->query($request->db)
            ->set_connection($request->db->get_connection(SQLN_BI))
            ->fields($fields)
            ->filter($this->filters->byPk($gameInstanceIds))
            ->left_join($gamesInstancesRoundsManager, $joinGamesInstancesRoundsFilter)
            ->left_join($gamesInstancesRoundsPlayersManager, $joinGamesInstancesRoundsPlayersFilter)
            ->left_join($sessionsManager, $joinSessionsFilter)
            ->group_by(1)
            ->get_list();

        $results = [];

        if ($summaryStats) {
            foreach ($summaryStats as $summaryStat) {
                $pk = $summaryStat[$this->getPkField()];
                unset($summaryStat[$this->getPkField()]);

                $results[$pk] = $summaryStat;
            }
        }

        return $results;
    }
}



class GamesInstancesRoundsManager extends BaseEntityManager {

    protected $entityClass = GameInstanceRoundEntity::class;
    protected $table = Table::GameInstanceRound;
    protected $table_alias = TableAlias::GameInstanceRound;
    protected $pk = DBField::GAME_INSTANCE_ROUND_ID;

    public static $fields = [
        DBField::GAME_INSTANCE_ROUND_ID,
        DBField::GAME_INSTANCE_ID,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameInstanceRoundEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PLAYERS, []);
        $data->updateField(VField::EVENTS, []);
    }

    /**
     * @param Request $request
     * @param bool $requirePk
     * @return array
     */
    public function getFormFields($requirePk = false)
    {
        $fields = [
            new IntegerField(DBField::GAME_INSTANCE_ID, 'Game Instance ID', true),
            new DateTimeField(DBField::START_TIME, 'Start Time', false)
        ];

        if ($requirePk)
            $fields[] = new IntegerField(DBField::ID, 'Primary Key', true);

        return $fields;
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @param null $startTime
     * @param null $endTime
     * @return GameInstanceRoundEntity
     */
    public function createNewGameInstanceRound(Request $request, $gameInstanceId, $startTime = null, $endTime = null)
    {
        if (!$startTime)
            $startTime = $request->getCurrentSqlTime();

        $data = [
            DBField::GAME_INSTANCE_ID => $gameInstanceId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::IS_ACTIVE => 1
        ];

        /** @var GameInstanceRoundEntity $gameInstanceRound */
        $gameInstanceRound = $this->query($request->db)->createNewEntity($request, $data);

        return $gameInstanceRound;
    }

    /**
     * @param Request $request
     * @param $gameInstanceRounds GameInstanceRoundEntity[]|GameInstanceRoundEntity
     */
    protected function postProcessGameInstanceRounds(Request $request, $gameInstanceRounds)
    {
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();

        if ($gameInstanceRounds instanceof GameInstanceRoundEntity)
            $gameInstanceRounds = [$gameInstanceRounds];

        if ($gameInstanceRounds) {
            /** @var GameInstanceRoundEntity[] $gameInstanceRounds */
            $gameInstanceRounds = array_index($gameInstanceRounds, $this->getPkField());
            $gameInstanceRoundIds = array_keys($gameInstanceRounds);

            $gameInstanceRoundPlayers = $gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayersByGameInstanceRoundIds($request, $gameInstanceRoundIds);

            foreach ($gameInstanceRoundPlayers as $gameInstanceRoundPlayer) {
                $gameInstanceRounds[$gameInstanceRoundPlayer->getGameInstanceRoundId()]->setGameInstanceRoundPlayer($gameInstanceRoundPlayer);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param null $gameInstanceId
     * @return array|GameInstanceRoundEntity
     */
    public function getGameInstanceRoundById(Request $request, $gameInstanceRoundId, $gameInstanceId = null, $postProcess = true)
    {
        $gameInstanceRound = $this->query($request->db)
            ->filter($this->filters->byPk($gameInstanceRoundId))
            ->filter($this->filters->byGameInstanceId($gameInstanceId))
            ->get_entity($request);

        if ($postProcess)
            $this->postProcessGameInstanceRounds($request, $gameInstanceRound);

        return $gameInstanceRound;
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @return GameInstanceRoundEntity[]
     */
    public function getGameInstanceRoundsByGameInstanceId(Request $request, $gameInstanceId, $expand = true)
    {
        $gameInstanceRounds = $this->query($request->db)
            ->filter($this->filters->byGameInstanceId($gameInstanceId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameInstanceRounds($request, $gameInstanceRounds);

        return $gameInstanceRounds;
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @return array|GameInstanceRoundEntity
     */
    public function getActiveGameInstanceRoundByGameInstanceId(Request $request, $gameInstanceId, $expand = false)
    {
        $gameInstanceRounds = $this->query($request->db)
            ->filter($this->filters->byGameInstanceId($gameInstanceId))
            ->filter($this->filters->isActive())
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->sort_desc($this->getPkField())
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameInstanceRounds($request, $gameInstanceRounds);

        return $gameInstanceRounds;
    }

    /**
     * @param Request $request
     * @param GameInstanceRoundEntity $gameInstanceRound
     */
    public function stopGameInstanceRound(Request $request, GameInstanceRoundEntity $gameInstanceRound)
    {
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();

        $currentTime = $request->getCurrentSqlTime();

        foreach ($gameInstanceRound->getGameInstanceRoundPlayers() as $gameInstanceRoundPlayer) {
            if (!$gameInstanceRoundPlayer->has_ended()) {
                $gamesInstancesRoundsPlayersManager->stopGameInstanceRoundPlayer($request, $gameInstanceRoundPlayer, $currentTime);
            }
        }

        if (!$gameInstanceRound->has_ended()) {
            $gameInstanceRound->updateField(DBField::END_TIME, $currentTime)->saveEntityToDb($request);
        }
    }
}


class GamesInstancesRoundsPlayersManager extends BaseEntityManager {

    const EXIT_STATUS_QUIT = 'quit';
    const EXIT_STATUS_TIMED_OUT = 'timed-out';
    const EXIT_STATUS_KICKED = 'kicked';
    const EXIT_STATUS_BUMPED = 'bumped';

    protected $entityClass = GameInstanceRoundPlayerEntity::class;
    protected $table = Table::GameInstanceRoundPlayer;
    protected $table_alias = TableAlias::GameInstanceRoundPlayer;
    protected $pk = DBField::GAME_INSTANCE_ROUND_PLAYER_ID;

    public static $fields = [
        DBField::GAME_INSTANCE_ROUND_PLAYER_ID,
        DBField::GAME_INSTANCE_ROUND_ID,
        DBField::SESSION_ID,
        DBField::USER_ID,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::LAST_PING_TIME,
        DBField::EXIT_STATUS,
        DBField::PLAYER_REQUEST_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesInstancesRoundsPlayersManager::class => DBField::GAME_INSTANCE_ROUND_ID
    ];

    const GNS_KEY_PREFIX =  GNS_ROOT.'.girp';

    protected $exitStatuses = [
        ['id' => self::EXIT_STATUS_TIMED_OUT, 'name' => "Timed Out"],
        ['id' => self::EXIT_STATUS_KICKED, 'name' => "Player Kicked"],
        ['id' => self::EXIT_STATUS_QUIT, 'name' => "Game Quit"]
    ];

    /**
     * @param GameInstanceRoundPlayerEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @return array
     */
    public function getExitStatuses()
    {
        return $this->exitStatuses;
    }

    /**
     * @param bool $requirePk
     * @return array
     */
    public function getFormFields($requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::GAME_INSTANCE_ROUND_ID, 'Game Instance Round ID', true, '', 1),
            new CharField(DBField::SESSION_HASH, 'Session Hash', 40),
            new IntegerField(DBField::USER_ID, 'User ID', false),
            new DateTimeField(DBField::START_TIME, 'Player Start Time', false),
            new CharField(DBField::PLAYER_REQUEST_ID, 'Player Request Id', 64, false)
        ];

        if ($requirePk)
            $fields[] = new IntegerField(DBField::ID, 'Primary Key');

        return $fields;
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param $sessionId
     * @param null $startTime
     * @param null $userId
     * @param null $endTime
     * @param null $lastPingTime
     * @param null $exitStatus
     * @return GameInstanceRoundPlayerEntity
     */
    public function createNewGameInstanceRoundPlayer(Request $request, $gameInstanceRoundId, $sessionId, $startTime = null, $userId = null,
                                                     $endTime = null, $lastPingTime = null, $exitStatus = null, $playerRequestId = null, $fetch = true)
    {
        if (!$startTime)
            $startTime = $request->getCurrentSqlTime();

        if (!$lastPingTime)
            $lastPingTime = $startTime;

        $data = [
            DBField::GAME_INSTANCE_ROUND_ID => $gameInstanceRoundId,
            DBField::SESSION_ID => $sessionId,
            DBField::USER_ID => $userId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::LAST_PING_TIME => $lastPingTime,
            DBField::EXIT_STATUS => $exitStatus,
            DBField::PLAYER_REQUEST_ID => $playerRequestId,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer */
        $gameInstanceRoundPlayer = $this->query($request->db)->createNewEntity($request, $data, $fetch);

        return $gameInstanceRoundPlayer;
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundPlayerId
     * @param null $gameInstanceRoundId
     * @return array|GameInstanceRoundPlayerEntity
     */
    public function getGameInstanceRoundPlayerById(Request $request, $gameInstanceRoundPlayerId, $gameInstanceRoundId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($gameInstanceRoundPlayerId))
            ->filter($this->filters->byGameInstanceRoundId($gameInstanceRoundId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundIds
     * @return GameInstanceRoundPlayerEntity[]
     */
    public function getGameInstanceRoundPlayersByGameInstanceRoundIds(Request $request, $gameInstanceRoundIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameInstanceRoundId($gameInstanceRoundIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }

    /**
     * @param $gameInstanceRoundId
     * @param $sessionId
     * @return string
     */
    public function generateActivePlayerRecordCacheKey($gameInstanceRoundId, $sessionId)
    {
        return self::GNS_KEY_PREFIX.".player.{$gameInstanceRoundId}-{$sessionId}";
    }

    public function generateActivePlayerRecordExistsCacheKey($gameInstanceRoundId, $sessionId)
    {
        return self::GNS_KEY_PREFIX.".player-exists.{$gameInstanceRoundId}-{$sessionId}";
    }


    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param $sessionId
     * @return GameInstanceRoundPlayerEntity
     */
    public function getGameInstanceRoundPlayerByGameInstanceRoundIdAndSessionId(Request $request, $gameInstanceRoundId, $sessionId, $cache = true)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byGameInstanceRoundId($gameInstanceRoundId))
            ->filter($this->filters->bySessionId($sessionId))
            ->filter($this->filters->isActive());

        if ($cache)
            $queryBuilder->cache($this->generateActivePlayerRecordCacheKey($gameInstanceRoundId, $sessionId), FIVE_MINUTES, false);

        return $queryBuilder->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param $sessionId
     * @return GameInstanceRoundPlayerEntity
     */
    public function checkTrackGameInstanceRoundPlayer(Request $request, $gameInstanceRoundId, $sessionId)
    {
        try {
            $gameInstanceRoundPlayer = $request->cache[$this->generateActivePlayerRecordCacheKey($gameInstanceRoundId, $sessionId)];
            /** @var GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer */
            $gameInstanceRoundPlayer = $this->createEntity($gameInstanceRoundPlayer, $request);

        } catch (CacheEntryNotFound $c) {
            $gameInstanceRoundPlayer = $this->createNewGameInstanceRoundPlayer(
                $request,
                $gameInstanceRoundId,
                $sessionId,
                $request->getCurrentSqlTime(),
                $request->user->id,
                null,
                null,
                null,
                $request->requestId,
                false
            );

            $c->set($gameInstanceRoundPlayer->getDataArray(true), FIFTEEN_MINUTES);
        }

        return $gameInstanceRoundPlayer;
    }

    /**
     * @param Request $request
     * @param GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer
     * @param null $currentTime
     */
    public function stopGameInstanceRoundPlayer(Request $request, GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer, $currentTime = null)
    {
        if (!$currentTime)
            $currentTime = $request->getCurrentSqlTime();

        $gameInstanceRoundPlayer->assign([
            DBField::END_TIME => $currentTime,
            DBField::EXIT_STATUS => GamesInstancesRoundsPlayersManager::EXIT_STATUS_QUIT
        ]);
        $gameInstanceRoundPlayer->saveEntityToDb($request);

    }
}


class GamesInstancesRoundsEventsManager extends BaseEntityManager {

    protected $entityClass = GameInstanceRoundEventEntity::class;
    protected $table = Table::GameInstanceRoundEvent;
    protected $table_alias = TableAlias::GameInstanceRoundEvent;
    protected $pk = DBField::GAME_INSTANCE_ROUND_EVENT_ID;

    public static $fields = [
        DBField::GAME_INSTANCE_ROUND_EVENT_ID,
        DBField::GAME_INSTANCE_ROUND_ID,
        DBField::GAME_ID,
        DBField::EVENT_KEY,
        DBField::GAME_INSTANCE_ROUND_PLAYER_ID,
        DBField::VALUE,
        DBField::CREATE_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameInstanceRoundEventEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PROPERTIES, []);
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param $gameId
     * @param $eventKey
     * @param null $keyValues
     * @param null $gameInstanceRoundPlayerId
     * @param null $createTime
     * @return GameInstanceRoundEventEntity
     */
    public function trackEvent(Request $request, $gameInstanceRoundId, $gameId, $eventKey, $keyValues = [], $gameInstanceRoundPlayerId = null, $createTime = null)
    {
        $gamesInstancesRoundsEventsPropertiesManager = $request->managers->gamesInstancesRoundsEventsProperties();

        if (!$createTime)
            $createTime = $request->getCurrentSqlTime();

        $data = [
            DBField::GAME_INSTANCE_ROUND_ID => $gameInstanceRoundId,
            DBField::GAME_ID => $gameId,
            DBField::EVENT_KEY => $eventKey,
            DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $gameInstanceRoundPlayerId,
            DBField::VALUE => serialize($keyValues),
            DBField::CREATE_TIME => $createTime,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var GameInstanceRoundEventEntity $gameInstanceRoundEvent */
        $gameInstanceRoundEvent = $this->query($request->db)->createNewEntity($request, $data);

        if (is_array($keyValues)) {
            foreach ($keyValues as $key => $value) {
                $property = $gamesInstancesRoundsEventsPropertiesManager->trackEventProperty(
                    $request,
                    $gameInstanceRoundEvent->getPk(),
                    $key,
                    $value
                );
                $gameInstanceRoundEvent->setProperty($property);
            }
        }

        return $gameInstanceRoundEvent;
    }
}

class GamesInstancesRoundsEventsPropertiesManager extends BaseEntityManager {

    protected $entityClass = GameInstanceRoundEventPropertyEntity::class;
    protected $table = Table::GameInstanceRoundEventProperty;
    protected $table_alias = TableAlias::GameInstanceRoundEventProperty;
    protected $pk = DBField::GAME_INSTANCE_ROUND_EVENT_PROPERTY_ID;

    public static $fields = [
        DBField::GAME_INSTANCE_ROUND_EVENT_PROPERTY_ID,
        DBField::GAME_INSTANCE_ROUND_EVENT_ID,
        DBField::KEY,
        DBField::VALUE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameInstanceRoundEventPropertyEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundEventId
     * @param $key
     * @param $value
     * @return GameInstanceRoundEventPropertyEntity
     */
    public function trackEventProperty(Request $request, $gameInstanceRoundEventId, $key, $value)
    {
        $data = [
            DBField::GAME_INSTANCE_ROUND_EVENT_ID => $gameInstanceRoundEventId,
            DBField::KEY => $key,
            DBField::VALUE => $value
        ];

        /** @var GameInstanceRoundEventPropertyEntity $gameInstanceRoundEventProperty */
        $gameInstanceRoundEventProperty = $this->query($request->db)->createNewEntity($request, $data);

        return $gameInstanceRoundEventProperty;
    }
}


class GamesInstancesLogsManager extends BaseEntityManager
{
    protected $entityClass = GameInstanceLogEntity::class;
    protected $table = Table::GameInstanceLog;
    protected $table_alias = TableAlias::GameInstanceLog;
    protected $pk = DBField::GAME_INSTANCE_LOG_ID;

    public static $fields = [
        DBField::GAME_INSTANCE_LOG_ID,
        DBField::GAME_INSTANCE_ID,
        DBField::GAME_INSTANCE_LOG_STATUS_ID,
        DBField::PUB_SUB_CHANNEL_TYPE,
        DBField::PUB_SUB_CHANNEL,
        DBField::PROCESSING_TIME,
        DBField::MESSAGE_COUNT,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $foreign_managers = [
        GamesInstancesLogsStatusesManager::class => DBField::GAME_INSTANCE_LOG_STATUS_ID
    ];

    /**
     * @param GameInstanceLogEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param GameInstanceLogEntity|GameInstanceLogEntity[] $gameInstanceLogs
     */
    public function postProcessGameInstanceLogs(Request $request, $gameInstanceLogs)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        if ($gameInstanceLogs) {
            if ($gameInstanceLogs instanceof GameInstanceLogEntity)
                $gameInstanceLogs = [$gameInstanceLogs];

            /** @var GameInstanceLogEntity[] $gameInstanceLogs */
            $gameInstanceLogs = $this->index($gameInstanceLogs);
            $gameInstanceLogIds = array_keys($gameInstanceLogs);

            $gameInstanceLogAssets = $gamesAssetsManager->getGameInstanceLogsAssetsByGameInstanceLogIds($request, $gameInstanceLogIds);

            foreach ($gameInstanceLogAssets as $gameInstanceLogAsset) {
                $gameInstanceLogs[$gameInstanceLogAsset->getGameInstanceLogId()]->setGameInstanceLogAsset($gameInstanceLogAsset);
            }

        }
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    public function queryJoinStatuses(Request $request)
    {
        $gamesInstancesLogsStatusesManager = $request->managers->gamesInstancesLogsStatuses();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($gamesInstancesLogsStatusesManager))
            ->inner_join($gamesInstancesLogsStatusesManager);
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @return array|GameInstanceLogEntity
     */
    public function getGameInstanceLog(Request $request, $gameInstanceId, $channelType, $channel)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameInstanceId($gameInstanceId))
            ->filter($this->filters->byPubSubChannelType($channelType))
            ->filter($this->filters->byPubSubChannel($channel))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameInstanceId
     * @param int $gameInstanceLogStatusId
     * @return GameInstanceLogEntity
     */
    public function createNewGameInstanceLog(Request $request, $gameInstanceId, $channelType, $channel)
    {
        $data = [
            DBField::GAME_INSTANCE_ID => $gameInstanceId,
            DBField::GAME_INSTANCE_LOG_STATUS_ID => GamesInstancesLogsStatusesManager::ID_PENDING,
            DBField::PUB_SUB_CHANNEL_TYPE => $channelType,
            DBField::PUB_SUB_CHANNEL => $channel,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
            DBField::MESSAGE_COUNT => 0,
            DBField::PROCESSING_TIME => 0,
        ];

        /** @var GameInstanceLogEntity $gameInstanceLog */
        $gameInstanceLog = $this->query($request->db)->createNewEntity($request, $data);

        return $gameInstanceLog;
    }

    /**
     * @param Request $request
     * @param $gameInstanceIds
     * @return GameInstanceLogEntity[]
     */
    public function getGameInstanceLogsByGameInstanceIds(Request $request, $gameInstanceIds)
    {
        $gameInstanceLogs = $this->queryJoinStatuses($request)
            ->filter($this->filters->byGameInstanceId($gameInstanceIds))
            ->get_entities($request);

        $this->postProcessGameInstanceLogs($request, $gameInstanceLogs);

        return $gameInstanceLogs;
    }
}


class GamesInstancesLogsStatusesManager extends BaseEntityManager
{
    protected $entityClass = GameInstanceLogStatusEntity::class;
    protected $table = Table::GameInstanceLogStatus;
    protected $table_alias = TableAlias::GameInstanceLogStatus;
    protected $pk = DBField::GAME_INSTANCE_LOG_STATUS_ID;

    const ID_PENDING = 1;
    const ID_PROCESSING = 2;
    const ID_COMPLETED = 3;
    const ID_FAILED = 4;

    public static $fields = [
        DBField::GAME_INSTANCE_LOG_STATUS_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameInstanceLogEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }
}