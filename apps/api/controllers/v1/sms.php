<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/12/18
 * Time: 1:23 PM
 */

use \Twilio\Twiml;

class SmsApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = true;
    const REQUIRES_AUTH = false;

    /** @var SmsManager $manager */
    protected $manager;

    protected $pages = [
        'inbound' => 'inbound'
    ];

    protected function inbound(Request $request)
    {
        $response = new Twiml();

        $inboundMessage = $request->readPostParam('Body');
        $sourceNumber = $request->readPostParam('From');

        $messageParts = explode(' ', $inboundMessage);

        $gameInstanceId = null;
        $message = $response->message();

        if (count($messageParts) > 1 && strtolower($messageParts[0]) == 'play') {

            $hostSlug = strtolower($messageParts[1]);
            if (is_numeric($hostSlug))
                $gameInstanceId = $hostSlug;

            $responseBody = $this->getResponseText($request, $sourceNumber, $hostSlug, $gameInstanceId);

        } elseif (count($messageParts) == 1) {

            $hostSlug = strtolower(array_shift($messageParts));
            if (is_numeric($hostSlug))
                $gameInstanceId = $hostSlug;

            $responseBody = $this->getResponseText($request, $sourceNumber, $hostSlug, $gameInstanceId);

        } else {
            $responseBody = 'Text "<code>" to get a invitation link.';
        }



        $message->body($responseBody);

        return new TwimlResponse($response);

    }

    /**
     * @param Request $request
     * @param $sourceNumber
     * @param null $hostSlug
     * @param null $gameInstanceId
     * @return string
     */
    protected function getResponseText(Request $request, $sourceNumber, $hostSlug = null, $gameInstanceId = null)
    {

        $hostsManager = $request->managers->hosts();
        $hostsInstancesManager = $request->managers->hostsInstances();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $usersManager = $request->managers->users();
        $shortUrlsManager = $request->managers->shortUrls();

        $user = $usersManager->getUserByPhoneNumber($request, $sourceNumber);

        $hostInstance = [];
        $gameInstance = [];
        $host = [];

        if ($hostSlug)
            $host = $hostsManager->getHostBySlug($request, $hostSlug);

        $responseBody = "No active hosts found.";

        if ($gameInstanceId) {

            $gameInstance = $gamesInstancesManager->getGameInstanceById($request, $gameInstanceId, true);
            if ($gameInstance) {
                $hostInstance = $hostsInstancesManager->getHostInstanceById($request, $gameInstance->getHostInstanceId());
            }


        } elseif ($hostSlug) {
            if ($host = $hostsManager->getHostBySlug($request, $hostSlug)) {
                $hostInstance = $hostsInstancesManager->getActiveHostInstanceByHostId($request, $host->getPk(), true);
                if ($hostInstance)
                    $gameInstance = $hostInstance->getActiveGameInstance();
            }
        }


        if ($user) {

            $expiration = strtotime($request->getCurrentSqlTime()) + FIFTEEN_MINUTES;

            $url = '';

            if ($host)
                $url = $host->getUrl();

            $userLoginParams = $usersManager->generateMagicLoginUrlParamsForUser($request, $user, $expiration, $url);
        } else {
            $userLoginParams = [];
        }

        if ($gameInstance) {

            $params = [
                GetRequest::PARAM_UTM_MEDIUM => 'sms',
                GetRequest::PARAM_UTM_SOURCE => 'text',
                GetRequest::PARAM_UTM_CAMPAIGN => 'player-invite',
                GetRequest::PARAM_UTM_TERM => "game-instance-{$gameInstance->getPk()}",
            ];

            $fullUrl = $host->getUrl($request->buildQuery(array_merge($userLoginParams, $params)));

            $shortUrl = $shortUrlsManager->createNewShortUrl($request, $fullUrl);
            if ($user) {
                if ($user->getFirstName())
                    $inviteMessage = "[ESC GAMES] Welcome back {$user->getFirstName()}!. You've been invited to join {$gameInstance->getGame()->getDisplayName()}: {$shortUrl->getUrl()}";
                else
                    $inviteMessage = "[ESC GAMES] Welcome back {$user->getUsername()}! You've been invited to join {$gameInstance->getGame()->getDisplayName()}: {$shortUrl->getUrl()}";
            }
            else
                $inviteMessage = "[ESC GAMES] You've been invited to join {$gameInstance->getGame()->getDisplayName()}: {$shortUrl->getUrl()}";

        } else if ($hostInstance) {

            $params = [
                GetRequest::PARAM_UTM_MEDIUM => 'sms',
                GetRequest::PARAM_UTM_SOURCE => 'text',
                GetRequest::PARAM_UTM_CAMPAIGN => 'player-invite',
                GetRequest::PARAM_UTM_TERM => "host-instance-{$hostInstance->getPk()}",
            ];

            $fullUrl = $host->getUrl($request->buildQuery(array_merge($userLoginParams, $params)));

            $shortUrl = $shortUrlsManager->createNewShortUrl($request, $fullUrl);

            if ($user) {
                if ($user->getFirstName())
                    $inviteMessage = "[ESC GAMES] Welcome back {$user->getFirstName()}. You've been invited to join host: {$shortUrl->getUrl()}";
                else
                    $inviteMessage = "[ESC GAMES] Welcome back {$user->getUsername()}. You've been invited to join host: {$shortUrl->getUrl()}";
            } else
                $inviteMessage = "[ESC GAMES] You've been invited to join host: {$shortUrl->getUrl()}";
        } else {
            if ($host) {

                $params = [
                    GetRequest::PARAM_UTM_MEDIUM => 'sms',
                    GetRequest::PARAM_UTM_SOURCE => 'text',
                    GetRequest::PARAM_UTM_CAMPAIGN => 'player-invite',
                    GetRequest::PARAM_UTM_TERM => "host-{$host->getSlug()}",
                ];

                $fullUrl = $host->getUrl($request->buildQuery(array_merge($userLoginParams, $params)));

                $shortUrl = $shortUrlsManager->createNewShortUrl($request, $fullUrl);

                if ($user) {
                    if ($user->getFirstName())
                        $inviteMessage = "[ESC GAMES] Welcome back {$user->getFirstName()}. You've been invited to join host: {$shortUrl->getUrl()}";
                    else
                        $inviteMessage = "[ESC GAMES] Welcome back {$user->getUsername()}. You've been invited to join host: {$shortUrl->getUrl()}";
                } else
                    $inviteMessage = "[ESC GAMES] You've been invited to join host: {$shortUrl->getUrl()}";
            }

        }


        if ($hostInstance || $gameInstance || $host) {
            $responseBody = $inviteMessage;
        }

        return $responseBody;

    }
}