<?php
/**
 * Sentry
 * Exception system handling.
 *
 * @see http://getsentry.com
 *
 */
function init()
{
    global $CONFIG;

    if (!$CONFIG['sentry_server']) {
        return;
    }

    $client = new Raven_Client($CONFIG['sentry_server']);
    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
}

function log_exception_in_sentry($exception, Request $request = null)
{
    global $CONFIG;

    if (!$CONFIG['sentry_server']) {
        return;
    }

    $client = new Raven_Client($CONFIG['sentry_server']);

    if ($request) {
        $client->user_context([
            'post' => $request->post,
            'server' => $request->server
        ]);
    }

    if ($request and property_exists($request, 'user')) {
        $client->user_context([
            'real_ip' => $request->getRealIp(),
            'path' => $request->path,
            'post_data' => $request->post,
            'get_data' => $request->get->params(),
            'request_id' => $request->requestId
        ]);

        if ($request->user instanceof User) {
            $userContext = [
                'username' => $request->user->username,
                'user_id' => $request->user->id,
                'is_bot' => $request->user->is_bot,
                'is_staff' => $request->user->is_staff,
                'is_banned' => $request->user->is_banned(),
                'is_authenticated' => $request->user->is_authenticated,
                'is_creator' => $request->user->is_creator,
                'is_verified' => $request->user->is_verified,
                'groups' => $request->user->groups,
                'is_superadmin' => $request->user->is_superadmin,
            ];

            if (isset($request->user->session)) {
                $userContext['session_id'] = $request->user->session->getSessionId();
            }

            if (isset($request->user->guest)) {
                $userContext['guest_id'] = $request->user->guest->getGuestId();
            }

            if (isset($request->user->permissions)) {
                $userContext['permissions'] = $request->user->permissions->getRawList();
            }

            $client->user_context($userContext);
        }
    }
    $client->captureException($exception);
}

function log_error_in_sentry($code, $message, $file = '', $line = 0, $context = [])
{
    global $CONFIG;

    if (!$CONFIG['sentry_server']) {
        return;
    }

    $client = new Raven_Client($CONFIG['sentry_server']);
    $client->handleError($code, $message, $file, $line, $context);
}

init();
