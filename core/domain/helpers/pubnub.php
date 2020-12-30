<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 1/16/19
 * Time: 10:41 AM
 */

use PubNub\PNConfiguration;
use PubNub\PubNub;
use \GuzzleHttp\Client;

class PubNubLoader
{
    /**
     * @param Request $request
     * @return PNConfiguration
     */
    public static function getPubNubConfiguration(Request $request)
    {
        $publishKey = $request->config['pubnub']['publish_key'];
        $subscribeKey = $request->config['pubnub']['subscribe_key'];
        $secretKey = $request->config['pubnub']['secret_key'];

        $pnConfiguration = new PNConfiguration();

        // subscribeKey from admin panel
        $pnConfiguration->setSubscribeKey($subscribeKey); // required
        // publishKey from admin panel (only required if publishing)
        $pnConfiguration->setPublishKey($publishKey);
        // secretKey (only required for modifying/revealing access permissions)
        $pnConfiguration->setSecretKey($secretKey);
        // use SSL (enabled by default)
        $pnConfiguration->setSecure(true);
        // UUID to be used as a device identifier, a default UUID is generated
        // if not passsed
        $pnConfiguration->setUuid($request->requestId);

        // if cipherKey is passed, all communicatons to/from pubnub will be encrypted
        //$pnConfiguration->setCipherKey("my_cipherKey");

//        // if Access Manager is utilized, client will use this authKey in all restricted
//        // requests
//        $pnConfiguration->setAuthKey("my_auth_key");


        // how long to wait before giving up connection to client
        $pnConfiguration->setConnectTimeout(5);

        // how long to keep the subscribe loop running before disconnect
        $pnConfiguration->setSubscribeTimeout(310);

        // on non subscribe operations, how long to wait for server response
        $pnConfiguration->setNonSubscribeRequestTimeout(300);

        // PSV2 feature to subscribe with a custom filter expression
//        $pnConfiguration->setFilterExpression("such = wow");

        return $pnConfiguration;
    }

    /**
     * @param Request $request
     * @param PNConfiguration|null $pnConfiguration
     * @return PubNub
     */
    public static function getPubNubInstance(Request $request, PNConfiguration $pnConfiguration = null)
    {
        if (!$pnConfiguration)
            $pnConfiguration = self::getPubNubConfiguration($request);

        $pubnub = new PubNub($pnConfiguration);

        //$pubnub->getLogger()->pushHandler(new \Monolog\Handler\Nullhandler());

        return $pubnub;
    }

}

class PubNubApiHelper {

    protected $sharedHeaders = [
        'X-Session-Token' => null
    ];

    protected $postHeaders = [
        'Content-Type' => 'application/json',
    ];





    /**
     * @return array|mixed|\Psr\Http\Message\ResponseInterface
     */
    public static function login()
    {
        $guz = new \GuzzleHttp\Client([
            'base_uri' => 'https://admin.pubnub.com',
        ]);

        try {
            $loginResult = $guz->request('POST', '/api/me', [
                'email' => 'mail@example.com',
                'password' => '',
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {

            dump($e->getMessage());
            $loginResult = [];
        }

        return $loginResult;
    }

    /**
     * @param PubNub $pubNub
     * @param PubNubChannel $channel
     * @param $updateValue
     */
    public static function updateOfflineGameData(PubNub $pubNub, $channelName, $updateValue)
    {
        $message = [
            'eventName' =>  'offline-game-update',
            'body' => [
                'data' => $updateValue
            ]
        ];

        $result = $pubNub->publish()
            ->channel($channelName)
            ->message($message)
            ->usePost(true)
            ->sync();
    }

    /**
     * @param PubNub $pubnub
     * @param string $channel
     * @param CoinAwardEntity[] $awards
     */
    public static function sendCoinAwards(PubNub $pubnub, $channel, $awards)
    {
        // 700 awards to 40-character sessionHashes is roughly the max for a pubnub message, which is limited to 32kB
        // But pubnub charges in 2kB increments, so it may be better to keep the messages below that limit
        $maxAwardsPerMessage = 35 ;

        $chunks = array_chunk($awards, $maxAwardsPerMessage);

        foreach ($chunks as $chunk) {
            /** @var CoinAwardEntity[] $chunk */

            $pubNubSessionHashList = [];
            $pubNubValueList = [];

            foreach ($chunk as $award) {
                $pubNubValueList[] = $award->getValue();
                $pubNubSessionHashList[] = $award->getSessionHash();
            }

            // Todo: use shorter sessionHashes -- but need to ensure uniqueness. Or maybe switch to using session_id
            // To save more space, group by coin values. Can separate coin value groups with "~"
            // For example, this will send 1 coin to a,b,c,d and 2 to e,f,g,h:
            //     "coins" => "_1_~_2_"
            //     "ids" => "_a_b_c_d_~_e_f_g_h_"
            // (Note: the reason we are using "_" and "~" instead of some other separators is because they do not get
            // expanded by encodeURIComponent)
            // Currently, we're using around 45 bytes per award. By shortening ids and grouping we can reduce that to
            // less than 10 if cost or performance is an issue.

            $itemSeparatorChar = "_" ;
            $meta = [
                "coins" => $itemSeparatorChar . implode($itemSeparatorChar, $pubNubValueList) . $itemSeparatorChar,
                "ids" => $itemSeparatorChar . implode($itemSeparatorChar, $pubNubSessionHashList) . $itemSeparatorChar
            ];

            $pubnub->publish()
                ->channel($channel)
                ->message("")
                ->meta($meta)
                ->usePost(true)
                ->sync();
        }
    }

}

class PubNubChannel extends JSDataEntity
{
    const PUBLISH_AUTH_KEY = 'publish_auth_key';
    const SUBSCRIBE_AUTH_KEY = 'subscribe_auth_key';
    const CHANNEL_NAME = 'channel_name';
    const CHANNEL_TYPE = 'channel_type';

    /**
     * PubNubChannel constructor.
     * @param null $channelName
     * @param null $publishAuthKey
     * @param null $subscribeAuthKey
     */
    public function __construct($channelType = null, $channelName = null, $publishAuthKey = null, $subscribeAuthKey = null)
    {
        $data = [
            self::CHANNEL_TYPE => $channelType,
            self::CHANNEL_NAME => $channelName,
            self::PUBLISH_AUTH_KEY => $publishAuthKey,
            self::SUBSCRIBE_AUTH_KEY => $subscribeAuthKey
        ];
        parent::__construct($data);
    }

    /**
     * @return string
     */
    public function getChannelType()
    {
        return $this->dataArray[self::CHANNEL_TYPE];
    }

    /**
     * @return string
     */
    public function getChannelName()
    {
        return $this->dataArray[self::CHANNEL_NAME];
    }

    /**
     * @return string
     */
    public function getPublishAuthKey()
    {
        return $this->dataArray[self::PUBLISH_AUTH_KEY];
    }

    /**
     * @return string
     */
    public function getSubscribeAuthKey()
    {
        return $this->dataArray[self::SUBSCRIBE_AUTH_KEY];
    }

    /**
     * @return bool
     */
    public function can_subscribe()
    {
        return $this->getSubscribeAuthKey() ? true : false;
    }

    /**
     * @return bool
     */
    public function can_publish()
    {
        return $this->getPublishAuthKey() ? true : false;
    }
}

class PubNubHelper {

    const CHANNEL_AGGREGATE_PUB = 'game_aggregate_pub';
    const CHANNEL_AGGREGATE_SUB = 'game_aggregate_sub';
    const CHANNEL_BROADCAST = 'game_broadcast';
    const CHANNEL_OFFLINE_BROADCAST = 'offline_broadcast';
    const CHANNEL_COIN_BROADCAST = 'coin_broadcast';
    const CHANNEL_GAME_CONFIG = 'game_config';
    const CHANNEL_GAME_DIRECT = 'game_direct';
    const CHANNEL_PLAYER_DIRECT = 'game_player_direct';
    const CHANNEL_ROUND_CONFIG = 'game_round_config';
    const CHANNEL_GAME_TRACKING = 'game_tracking';
    const CHANNEL_GAME_ERROR_LOGS = 'game_error_logs';

    /** @var GameInstanceEntity $gameInstance */
    protected $gameInstance;
    /** @var HostInstanceEntity $hostInstance */
    protected $hostInstance;
    /** @var string $controllerTypeSlug */
    protected $controllerTypeSlug;
    /** @var User $user */
    protected $user;
    /** @var bool $isAggregatorGame */
    protected $isAggregatorGame = false;

    protected $serverSalt = 'serverSalt';
    protected $userSalt = 'userSalt';

    protected $channelGrants = [

    ];

    protected $errors = [
        'host' => [],
        'player' => []
    ];

    /**
     * PubNubHelper constructor.
     */
    public function __construct(User $user, HostInstanceEntity $hostInstance, GameInstanceEntity $gameInstance, $controllerTypeSlug = null)
    {
        $this->user = $user;
        $this->hostInstance = $hostInstance;
        $this->gameInstance = $gameInstance;
        if ($controllerTypeSlug != null && strlen($controllerTypeSlug) > 0) {
            $this->controllerTypeSlug = $controllerTypeSlug;
        }
        else {
            $this->controllerTypeSlug = GamesControllersTypesManager::SLUG_PLAYER;
        }

        $this->isAggregateGame = $gameInstance->getGame()->is_aggregate_game();

        if (!$this->isAggregateGame && $gameInstance->getGameBuild()->is_aggregate_game())
            $this->isAggregateGame = $gameInstance->getGameBuild()->is_aggregate_game();
    }


    /**
     * @param string $prefix
     * @return string
     */
    protected function generateHostGameCombineAuthKey($prefix = '', $userSalt = '')
    {
        $salt = "{$userSalt}-{$this->user->getGameSalt()}-{$this->hostInstance->getCreatedBy()}-{$this->gameInstance->getCreatedBy()}";

        $authKey = sha1("{$this->hostInstance->getPubSubChannel()}_{$this->gameInstance->getPubSubChannel()}_{$salt}");

        if ($prefix)
            $authKey = $prefix .'.'.$authKey;

        return $authKey;
    }

    /**
     * @param string $prefix
     * @return string
     */
    protected function generateHostAuthKey($prefix = '', $userSalt = '')
    {
        $salt = "{$userSalt}-{$this->user->getGameSalt()}-{$this->hostInstance->getCreatedBy()}";

        $authKey = sha1("{$this->hostInstance->getHost()->getPubSubChannel()}_{$this->hostInstance->getHost()->getSlug()}_{$salt}");

        if ($prefix)
            $authKey = $prefix .'.'.$authKey;

        return $authKey;
    }


    /**
     * @return PubNubChannel[]|bool[]
     */
    public function getHostGameInstancePubNubChannels()
    {
        $aggregatePub = $this->isAggregatorGame ? true : false;
        $aggregateSub = $this->isAggregatorGame ? true : false;
        $broadcast = $this->isAggregatorGame ? true : true;
        $gameConfig = $this->isAggregatorGame ? true : true;
        $gameDirect = $this->isAggregatorGame ? true : true;
        $roundConfig = $this->isAggregatorGame ? true : true;
        $playerDirect = $this->isAggregatorGame ? null : null;
        $gameTracking = $this->isAggregatorGame ? true : true;
        $offlineBroadcast = true;
        $coinBroadcast = true;
        $logs = $this->isAggregatorGame ? true : true;

        $pubNubChannels = [
            self::CHANNEL_AGGREGATE_PUB => $aggregatePub,
            self::CHANNEL_AGGREGATE_SUB => $aggregateSub,
            self::CHANNEL_BROADCAST => $broadcast,
            self::CHANNEL_GAME_CONFIG => $gameConfig,
            self::CHANNEL_GAME_DIRECT => $gameDirect,
            self::CHANNEL_ROUND_CONFIG => $roundConfig,
            self::CHANNEL_PLAYER_DIRECT => $playerDirect,
            self::CHANNEL_GAME_TRACKING => $gameTracking,
            self::CHANNEL_OFFLINE_BROADCAST => $offlineBroadcast,
            self::CHANNEL_COIN_BROADCAST => $coinBroadcast,
            self::CHANNEL_GAME_ERROR_LOGS => $logs
        ];

        if ($aggregatePub) {
            $channelName = "aggregate.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostGameCombineAuthKey("aggregate.", $this->serverSalt);
            $pubNubChannels[self::CHANNEL_AGGREGATE_PUB] = new PubNubChannel(
                self::CHANNEL_AGGREGATE_PUB,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($aggregateSub) {
            $channelName = "aggregation.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostGameCombineAuthKey("aggregation.", $this->serverSalt);
            $pubNubChannels[self::CHANNEL_AGGREGATE_SUB] = new PubNubChannel(
                self::CHANNEL_AGGREGATE_SUB,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($broadcast) {
            $channelName = "broadcast.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey('broadcast.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_BROADCAST] = new PubNubChannel(
                self::CHANNEL_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameConfig) {
            $channelName = "game_config.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey('game_config.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_GAME_CONFIG] = new PubNubChannel(
                self::CHANNEL_GAME_CONFIG,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameDirect) {
            $channelName = "direct.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->hostInstance->getHostInstanceAdminPubSubKey("_sub");
            $pubNubChannels[self::CHANNEL_GAME_DIRECT] = new PubNubChannel(
                self::CHANNEL_GAME_DIRECT,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($playerDirect) {
            // This will never be true for this one.
        }

        if ($roundConfig) {
            $channelName = "round_config.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey('round_config.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_ROUND_CONFIG] = new PubNubChannel(
                self::CHANNEL_ROUND_CONFIG,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameTracking) {
            $channelName = "gameTracking.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey('gameTracking.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_GAME_TRACKING] = new PubNubChannel(
                self::CHANNEL_GAME_TRACKING,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($offlineBroadcast) {
            $channelName = "offline_broadcast.{$this->hostInstance->getHost()->getPubSubChannel()}";
            $publishKey = $this->generateHostAuthKey('offline_broadcast.', $this->serverSalt);;
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_OFFLINE_BROADCAST] = new PubNubChannel(
                self::CHANNEL_OFFLINE_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($coinBroadcast) {
            $channelName = "coin.{$this->hostInstance->getHost()->getPubSubChannel()}";
            $publishKey = $this->generateHostAuthKey('coin_broadcast.', $this->serverSalt);;
            $subscribeKey = $this->generateHostAuthKey('coin_broadcast.', $this->serverSalt);
            $pubNubChannels[self::CHANNEL_COIN_BROADCAST] = new PubNubChannel(
                self::CHANNEL_COIN_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($logs) {
            $channelName = "logs.{$this->gameInstance->getPk()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostAuthKey('logs.', $this->serverSalt);
            $pubNubChannels[self::CHANNEL_GAME_ERROR_LOGS] = new PubNubChannel(
                self::CHANNEL_GAME_ERROR_LOGS,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }


        return $pubNubChannels;
    }

    /**
     * @return array
     */
    public function getJsonPlayerGameInstancePubNubChannels()
    {
        return DBDataEntity::extractJsonDataArrays($this->getPlayerGameInstancePubNubChannels());
    }

    /**
     * @return PubNubChannel[]|bool[]
     * @throws EntityFieldAccessException
     */
    public function getPlayerGameInstancePubNubChannels()
    {
        $aggregatePub = $this->isAggregatorGame ;
        $aggregateSub = false;
        $broadcast = true;
        $gameConfig = true;
        $gameDirect = ! $this->isAggregatorGame ;
        $roundConfig = true;
        $playerDirect = true;
        $gameTracking = true;
        $offlineBroadcast = true;
        $coinBroadcast = true;
        $logs = true;

        $pubNubChannels = [
            self::CHANNEL_AGGREGATE_PUB => $aggregatePub,
            self::CHANNEL_AGGREGATE_SUB => $aggregateSub,
            self::CHANNEL_BROADCAST => $broadcast,
            self::CHANNEL_GAME_CONFIG => $gameConfig,
            self::CHANNEL_GAME_DIRECT => $gameDirect,
            self::CHANNEL_ROUND_CONFIG => $roundConfig,
            self::CHANNEL_PLAYER_DIRECT => $playerDirect,
            self::CHANNEL_GAME_TRACKING => $gameTracking,
            self::CHANNEL_OFFLINE_BROADCAST => $offlineBroadcast,
            self::CHANNEL_COIN_BROADCAST => $coinBroadcast,
            self::CHANNEL_GAME_ERROR_LOGS => $logs
        ];

        if ($aggregatePub) {
            $channelName = "aggregate.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey("aggregate.", $this->userSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_AGGREGATE_PUB] = new PubNubChannel(
                self::CHANNEL_AGGREGATE_PUB,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($aggregateSub) {
            $channelName = "aggregation.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_AGGREGATE_SUB] = new PubNubChannel(
                self::CHANNEL_AGGREGATE_SUB,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($broadcast) {
            $channelName = "broadcast.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostGameCombineAuthKey('broadcast.', $this->userSalt);
            $pubNubChannels[self::CHANNEL_BROADCAST] = new PubNubChannel(
                self::CHANNEL_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameConfig) {
            $channelName = "game_config.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostGameCombineAuthKey('game_config.', $this->userSalt);
            $pubNubChannels[self::CHANNEL_GAME_CONFIG] = new PubNubChannel(
                self::CHANNEL_GAME_CONFIG,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameDirect) {
            $channelName = "direct.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->hostInstance->getHostInstanceAdminPubSubKey("_pub");
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_GAME_DIRECT] = new PubNubChannel(
                self::CHANNEL_GAME_DIRECT,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($playerDirect) {
            $channelName = "player.{$this->controllerTypeSlug}-{$this->gameInstance->getPubSubChannel()}.-{$this->user->guest->getGuestHash()}_direct";
//            $publishKey = $this->generatePlayerDirectKey('_direct_pub_key');
//            $subscribeKey = $this->generatePlayerDirectKey('_direct_sub_key');
            $publishKey = null;
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_PLAYER_DIRECT] = new PubNubChannel(
                self::CHANNEL_PLAYER_DIRECT,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($roundConfig) {
            $channelName = "round_config.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostGameCombineAuthKey('round_config.', $this->userSalt);
            $pubNubChannels[self::CHANNEL_ROUND_CONFIG] = new PubNubChannel(
                self::CHANNEL_ROUND_CONFIG,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($gameTracking) {
            $channelName = "gameTracking.{$this->gameInstance->getPubSubChannel()}";
            $publishKey = $this->generateHostGameCombineAuthKey('gameTracking.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_GAME_TRACKING] = new PubNubChannel(
                self::CHANNEL_GAME_TRACKING,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($offlineBroadcast) {
            $channelName = "offline_broadcast.{$this->hostInstance->getHost()->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostAuthKey('offline_broadcast.', $this->serverSalt);
            $pubNubChannels[self::CHANNEL_OFFLINE_BROADCAST] = new PubNubChannel(
                self::CHANNEL_OFFLINE_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }


        if ($coinBroadcast) {
            $channelName = "coin.{$this->hostInstance->getHost()->getPubSubChannel()}";
            $publishKey = null;
            $subscribeKey = $this->generateHostAuthKey('coin_broadcast.', $this->serverSalt);
            $pubNubChannels[self::CHANNEL_COIN_BROADCAST] = new PubNubChannel(
                self::CHANNEL_COIN_BROADCAST,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        if ($logs) {
            $channelName = "logs.{$this->gameInstance->getPk()}";
            $publishKey = $this->generateHostAuthKey('logs.', $this->serverSalt);
            $subscribeKey = null;
            $pubNubChannels[self::CHANNEL_GAME_ERROR_LOGS] = new PubNubChannel(
                self::CHANNEL_GAME_ERROR_LOGS,
                $channelName,
                $publishKey,
                $subscribeKey
            );
        }

        return $pubNubChannels;
    }

    /**
     * @param PubNub $pubnub
     * @param PubNubChannel $pubNubChannel
     * @param $ttlMins
     * @return bool
     */
    public function issueGrant(PubNub $pubnub, PubNubChannel $pubNubChannel, $ttlMins = 30)
    {
        $authKeys = [];

        $channelsAuthed = true;

        if ($pubNubChannel->can_subscribe())
            $authKeys[] = $pubNubChannel->getSubscribeAuthKey();

        if ($pubNubChannel->can_publish())
            $authKeys[] = $pubNubChannel->getPublishAuthKey();

        try {
            $result = $pubnub->grant()
                ->channels($pubNubChannel->getChannelName())
                ->authKeys($authKeys)
                ->read($pubNubChannel->can_subscribe())
                ->write($pubNubChannel->can_publish())
                ->ttl($ttlMins)
                ->sync();

        } catch (\PubNub\Exceptions\PubNubServerException $exception) {
//                            print_r("Message: " . $exception->getMessage() . "\n");
//                            print_r("Status: " . $exception->getStatusCode() . "\n");
//                            echo "Original message: "; print_r($exception->getBody());

            $channelsAuthed = false;
        } catch (\PubNub\Exceptions\PubNubException $exception) {
//                            print_r("Message: " . $exception->getMessage());
            $channelsAuthed = false;
        }

        return $channelsAuthed;
    }

}