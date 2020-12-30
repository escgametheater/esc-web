<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/18/19
 * Time: 2:59 PM
 */

class Net_Gearman_Job_coin_girp_processor extends Task
{
    protected $args = [];
    protected $name = 'coin_girp_processor';
    protected $request;


    /**
     * @param Request $request
     * @param $args
     * @throws Net_Gearman_Job_Exception
     */
    public function process(Request $request, $args)
    {
        $userCoinsManager = $request->managers->userCoins();
        $guestCoinsManager = $request->managers->guestCoins();
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();

        $gameInstanceRoundId = $this->getArg(DBField::GAME_INSTANCE_ROUND_ID);

        $userCoinIdsSummary = $this->getArg('user_coin_ids');
        $guestCoinIdsSummary = $this->getArg('guest_coin_ids');

        $userCoinCount = count($userCoinIdsSummary);
        $guestCoinCount = count($guestCoinIdsSummary);

        std_log("* {$request->getCurrentSqlTime()} - INFO - Starting Coin Girp Processor for : {$gameInstanceRoundId}, Guest Coins: {$guestCoinCount} - User Coins: {$userCoinCount}");

        $userCoins = [];
        $guestCoins = [];

        if (!$userCoinIdsSummary && !$guestCoinIdsSummary)
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments');

        $userCoinIds = array_keys($userCoinIdsSummary);
        $guestCoinIds = array_keys($guestCoinIdsSummary);

        if ($userCoinIdsSummary) {
            $userCoins = $userCoinsManager->getUserCoinsByIds($request, $userCoinIds);
        }

        if ($guestCoinIdsSummary) {
            $guestCoins = $guestCoinsManager->getGuestCoinsByIds($request, $guestCoinIds);
        }

        $gameInstanceRound = $gamesInstancesRoundsManager->getGameInstanceRoundById($request, $gameInstanceRoundId, null, false);

        if ($gameInstanceRound && ($guestCoins || $userCoins)) {

            std_log("* {$request->getCurrentSqlTime()} - INFO - Starting Transaction");

            $dbConnection = $request->db->get_connection(SQLN_SITE);
            $dbConnection->begin();

            foreach ($guestCoins as $guestCoin) {

                if (!$guestCoin->getGameInstanceRoundPlayerId()) {
                    $sessionId = $guestCoinIdsSummary[$guestCoin->getPk()];

                    $girp = $gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayerByGameInstanceRoundIdAndSessionId(
                        $request,
                        $gameInstanceRoundId,
                        $sessionId,
                        false
                    );

                    if (!$girp) {

                        $girp = $gamesInstancesRoundsPlayersManager->createNewGameInstanceRoundPlayer(
                            $request,
                            $gameInstanceRoundId,
                            $sessionId,
                            $gameInstanceRound->getStartTime(),
                            null,
                            $guestCoin->getCreateTime(),
                            $guestCoin->getCreateTime(),
                            GamesInstancesRoundsPlayersManager::EXIT_STATUS_QUIT,
                            null,
                            false
                        );

                        std_log("* {$request->getCurrentSqlTime()} - INFO - Created Guest Girp: {$girp->getPk()}");

                    } else {
                        std_log("* {$request->getCurrentSqlTime()} - INFO - Found Guest Girp: {$girp->getPk()}");
                    }

                    $guestCoin->updateField(DBField::GAME_INSTANCE_ROUND_PLAYER_ID, $girp->getPk());
                    $guestCoin->saveEntityToDb($request);

                    std_log("* {$request->getCurrentSqlTime()} - INFO - Updated Guest Coin {$guestCoin->getPk()} with Girp: {$girp->getPk()}");

                }
            }

            foreach ($userCoins as $userCoin) {

                if (!$userCoin->getGameInstanceRoundPlayerId()) {
                    $sessionId = $userCoinIdsSummary[$userCoin->getPk()];

                    $girp = $gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayerByGameInstanceRoundIdAndSessionId(
                        $request,
                        $gameInstanceRoundId,
                        $sessionId,
                        false
                    );

                    if (!$girp) {
                        $girp = $gamesInstancesRoundsPlayersManager->createNewGameInstanceRoundPlayer(
                            $request,
                            $gameInstanceRoundId,
                            $sessionId,
                            $gameInstanceRound->getStartTime(),
                            $userCoin->getUserId(),
                            $userCoin->getCreateTime(),
                            $userCoin->getCreateTime(),
                            GamesInstancesRoundsPlayersManager::EXIT_STATUS_QUIT,
                            null,
                            false
                        );
                        std_log("* {$request->getCurrentSqlTime()} - INFO - Created User Girp: {$girp->getPk()}");
                    } else {
                        std_log("* {$request->getCurrentSqlTime()} - INFO - Found User Girp: {$girp->getPk()}");
                    }

                    $userCoin->updateField(DBField::GAME_INSTANCE_ROUND_PLAYER_ID, $girp->getPk());
                    $userCoin->saveEntityToDb($request);

                    std_log("* {$request->getCurrentSqlTime()} - INFO - Updated Guest Coin {$userCoin->getPk()} with Girp: {$girp->getPk()}");
                }
            }

            $dbConnection->commit();

            std_log("* {$request->getCurrentSqlTime()} - INFO - Committed Transaction -- Processing Complete");
        }
    }
}
