<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/3/19
 * Time: 3:34 PM
 */

class CoinsApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var UserCoinsManager $userCoinsManager */
    protected $userCoinsManager;
    /** @var GuestCoinsManager $guestCoinsManager */
    protected $guestCoinsManager;
    /** @var SessionTrackingManager $sessionsManager */
    protected $sessionsManager;
    /** @var GamesInstancesRoundsManager $gamesInstancesRoundsManager */
    protected $gamesInstancesRoundsManager;
    /** @var GamesInstancesRoundsPlayersManager $gamesInstancesRoundsPlayersManager */
    protected $gamesInstancesRoundsPlayersManager;
    /** @var GamesInstancesManager $gamesInstancesManager */
    protected $gamesInstancesManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var HostsInstancesManager $hostsInstancesManager */
    protected $hostsInstancesManager;
    /** @var GamesManager $gamesManager */
    protected $gamesManager;

    protected $pages = [
        'award' => 'handle_award',
        'round-leaderboard' => 'handle_round_leaderboard',
        'host-leaderboard' => 'handle_host_leaderboard',
    ];

    const TIMEFRAME_1_DAY = '1d';
    const TIMEFRAME_1_WEEK = '1w';
    const TIMEFRAME_1_MONTH = '1m';
    const TIMEFRAME_3_MONTHS = '3m';
    const TIMEFRAME_6_MONTHS = '6m';
    const TIMEFRAME_1_YEAR = '1y';
    const TIMEFRAME_ALL_TIME = 'a';

    protected $timeFrames = [
        self::TIMEFRAME_1_DAY => '-1 day',
        self::TIMEFRAME_1_WEEK => '-1 week',
        self::TIMEFRAME_1_MONTH => '-1 month',
        self::TIMEFRAME_3_MONTHS => '-3 month',
        self::TIMEFRAME_6_MONTHS => '-6 month',
        self::TIMEFRAME_1_YEAR => '-1 year',
        self::TIMEFRAME_ALL_TIME => 'all'
    ];

    public function pre_handle(Request $request)
    {
        $this->userCoinsManager = $request->managers->userCoins();
        $this->guestCoinsManager = $request->managers->guestCoins();
        $this->sessionsManager = $request->managers->sessionTracking();
        $this->hostsManager = $request->managers->hosts();
        $this->gamesManager = $request->managers->games();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $this->gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();
        $this->gamesInstancesManager = $request->managers->gamesInstances();
        $this->hostsInstancesManager = $request->managers->hostsInstances();
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_award(Request $request): ApiV1Response
    {
        $fields = [
            new IntegerField(DBField::COIN_AWARD_TYPE_ID, 'Coin Award Type Id', true),
            new CoinAwardsFormField(VField::AWARDS, 'Coin Awards Array', true, ';'),
            new IntegerField(DBField::HOST_ID, 'Host Id', false),
            new IntegerField(DBField::GAME_BUILD_ID, 'Game Build Id', false),
            new IntegerField(DBField::CONTEXT_ENTITY_TYPE_ID, 'Context Entity Type Id', false),
            new IntegerField(DBField::CONTEXT_ENTITY_ID, 'Context Entity Id', false),
            new IntegerField(DBField::GAME_INSTANCE_ROUND_ID, 'Game Instance Round Id', false),
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            /** @var CoinAwardEntity[] $awards */
            $awards = $this->form->getCleanedValue(VField::AWARDS);
            $coinAwardTypeId = $this->form->getCleanedValue(DBField::COIN_AWARD_TYPE_ID);

            $hostId = $this->form->getCleanedValue(DBField::HOST_ID);
            $gameBuildId = $this->form->getCleanedValue(DBField::GAME_BUILD_ID);
            $contextEntityTypeId = $this->form->getCleanedValue(DBField::CONTEXT_ENTITY_TYPE_ID);
            $contextEntityId = $this->form->getCleanedValue(DBField::CONTEXT_ENTITY_ID);
            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_ID);

            $deleteCacheKeys = [];
            $sessionHashes = [];

            foreach ($awards as $award) {
                if (!in_array($award->getSessionHash(), $sessionHashes))
                    $sessionHashes[] = $award->getSessionHash();
            }

            $sessions = $this->sessionsManager->getSessionsByHashes($request, $sessionHashes);
            $sessions = $this->sessionsManager->index($sessions, DBField::SESSION_HASH);

            $results = [];
            $userCoinIds = [];
            $guestCoinIds = [];

            foreach ($awards as $award) {

                /** @var SessionEntity $session */
                if (array_key_exists($award->getSessionHash(), $sessions) && $session = $sessions[$award->getSessionHash()]) {

                    $gameInstanceRoundPlayerId = $award->getGameInstanceRoundPlayerId();

                    if (!$gameInstanceRoundPlayerId) {
                        $gameInstanceRoundPlayer = $this->gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayerByGameInstanceRoundIdAndSessionId(
                            $request,
                            $gameInstanceRoundId,
                            $session->getPk(),
                            false
                        );

                        if ($gameInstanceRoundPlayer)
                            $gameInstanceRoundPlayerId = $gameInstanceRoundPlayer->getPk();
                    }


                    if ($session->getFirstUserId()) {
                        $userCoinId = $this->userCoinsManager->insertNewUserCoin(
                            $request,
                            $coinAwardTypeId,
                            $session->getFirstUserId(),
                            $award->getValue(),
                            $gameBuildId,
                            $hostId,
                            $contextEntityTypeId,
                            $contextEntityId,
                            $award->getName(),
                            $gameInstanceRoundId,
                            $gameInstanceRoundPlayerId
                        );

                        if (!$gameInstanceRoundPlayerId)
                            $userCoinIds[$userCoinId] = $session->getPk();

                        $deleteCacheKeys[] = $this->userCoinsManager->generateTotalUserCoinsCacheKey($session->getFirstUserId());
                        $deleteCacheKeys[] = $this->userCoinsManager->generateTotalUserCoinsCacheKey($session->getFirstUserId(), $hostId);

                    } else {

                        $guestCoinId = $this->guestCoinsManager->insertNewGuestCoin(
                            $request,
                            $coinAwardTypeId,
                            $session->getGuestId(),
                            $award->getValue(),
                            $gameBuildId,
                            $hostId,
                            $contextEntityTypeId,
                            $contextEntityId,
                            $award->getName(),
                            $gameInstanceRoundId,
                            $gameInstanceRoundPlayerId
                        );

                        if (!$gameInstanceRoundPlayerId)
                            $guestCoinIds[$guestCoinId] = $session->getPk();

                        $deleteCacheKeys[] = $this->guestCoinsManager->generateTotalGuestCoinsCacheKey($session->getGuestId());
                        $deleteCacheKeys[] = $this->guestCoinsManager->generateTotalGuestCoinsCacheKey($session->getGuestId(), $hostId);
                    }

                    $results[$award->getSessionHash()] = true;
                } else {
                    $results[$award->getSessionHash()] = false;
                }
            }

            if ($gameInstanceRoundId) {
                $gameInstanceRound = $this->gamesInstancesRoundsManager->getGameInstanceRoundById($request, $gameInstanceRoundId, null, false);

                if ($gameInstanceRound) {
                    if ($guestCoinIds || $userCoinIds) {
                        TasksManager::add(
                            TasksManager::TASK_COIN_GIRP_PROCESSOR,
                            [
                                DBField::GAME_INSTANCE_ROUND_ID => $gameInstanceRoundId,
                                'user_coin_ids' => $userCoinIds,
                                'guest_coin_ids' => $guestCoinIds
                            ]
                        );
                    }

                    $gameInstance = $this->gamesInstancesManager->getGameInstanceById($request, $gameInstanceRound->getGameInstanceId(), false);
                    $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
                    $game = $this->gamesManager->getGameById($request, $gameInstance->getGameId());
                    $game->setGameBuild($gameBuild);
                    $gameInstance->setGame($game);
                    $gameInstance->setGameBuild($gameBuild);

                    $hostInstance = $this->hostsInstancesManager->getSlimHostInstanceById($request, $gameInstance->getHostInstanceId());

                    Modules::load_helper(Helpers::PUBNUB);
                    $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

                    $pubnub = PubNubLoader::getPubNubInstance($request);
                    $coinBroadCastChannel = $pubNubHelper->getHostGameInstancePubNubChannels()[PubNubHelper::CHANNEL_COIN_BROADCAST];

                    if ($coinBroadCastChannel) {
                        //$this->sendCoinAwards($pubnub, $coinBroadCastChannel->getChannelName(), $awards);
                        PubNubApiHelper::sendCoinAwards($pubnub, $coinBroadCastChannel->getChannelName(), $awards);
                    }

                }
            }

            $this->setResults([$results]);

            if ($deleteCacheKeys) {
                Cache::get_cache()->deleteKeys($deleteCacheKeys, true);
            }

        }


        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_round_leaderboard(Request $request): ApiV1Response
    {
        $fields = [
            new IntegerField(DBField::GAME_INSTANCE_ROUND_ID, 'Game Instance Round ID'),
            new IntegerField(DBField::GAME_INSTANCE_ID, 'Game Instance ID'),
            new IntegerField(VField::COUNT, 'Count of results, defaults to 20', false)
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_ID);
            $gameInstanceId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ID);

            $count = $this->form->getCleanedValue(VField::COUNT, 20);

            $gameInstanceRound = $this->gamesInstancesRoundsManager->getGameInstanceRoundById($request, $gameInstanceRoundId, $gameInstanceId);

            if ($gameInstanceRound) {

                $results = $this->guestCoinsManager->getGameInstanceRoundLeaderboard($request, $gameInstanceRoundId, $count);
                $this->setResults($results);

            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_host_leaderboard(Request $request): ApiV1Response
    {
        $timeFrames = [];

        foreach ($this->timeFrames as $id => $value) {
            $timeFrames[] = [
                DBField::ID => $id,
                DBField::DISPLAY_NAME => $value
            ];
        }

        $fields = [
            new SlugField(VField::HOST_SLUG, 'Host Slug'),
            new SelectField(VField::TIME_FRAME, 'Time Frame, defaults to all time', $timeFrames, false),
            new IntegerField(VField::COUNT, 'Count of results, defaults to 20', false)
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            $hostSlug = $this->form->getCleanedValue(VField::HOST_SLUG);
            $timeFrame = $this->form->getCleanedValue(VField::TIME_FRAME, self::TIMEFRAME_ALL_TIME);

            $count = $this->form->getCleanedValue(VField::COUNT, 20);

            if ($host = $this->hostsManager->getHostBySlug($request, $hostSlug)) {

                $results = $this->userCoinsManager->getLeaderboardForHost($request, $host->getPk(), $count, $this->getTimeFrame($timeFrame));
                $this->setResults($results);

            } else {
                $this->form->set_error('Host Not Found', DBField::HOST_ID);
            }

        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param $timeFrame
     * @return string
     */
    private function getTimeFrame($timeFrame)
    {
        if (array_key_exists($timeFrame, $this->timeFrames)) {

            $offset = $this->timeFrames[$timeFrame];

            if ($offset == 'all')
                $offset = "-10 year";

            return $offset;
        }
        else
            return $this->timeFrames[self::TIMEFRAME_1_MONTH];
    }
}