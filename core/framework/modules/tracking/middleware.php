<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 5/19/18
 * Time: 11:06 PM
 */

class TrackingMiddleware extends Middleware {

    public $processResponseOnServerError = true;

    /**
     * @param Request $request
     * @return string|void
     */
    public function process_request(Request $request)
    {
        $guest = new Guest($request, $request->user->id);
        $session = new Session($request, $guest, $request->user->id);

        $request->user->setGuest($guest)->setSession($session);
    }

    /**
     * @param Request $request
     * @param HttpResponse $response
     * @return string|void
     */
    public function process_response(Request $request, HttpResponse $response)
    {
        $requestsManager = $request->managers->requests();
        $apiLogManager = $request->managers->apiLog();

        if (isset($request->user->session)) {

            if (!$requestsManager->request_is_tracked() && stripos($request->path, '/static') !== 0 and stripos($request->path, '/debug') !== 0 and stripos($request->path, '/no-rt') !== 0) {
                $requestsManager->trackRequest($request, $response);
            }

            if ($response instanceof ApiV1Response || ($request->app == 'api' && $response instanceof HttpResponseRedirect)) {
                $rawResponse = [];

                if ($response instanceof ApiV1Response)
                    $rawResponse = $response->getRawResponse();

                $apiLogManager->insertApiLog($request, $rawResponse);
            }

            if ($response instanceof GameAssetResponse) {
                $apiLogManager->insertApiLog($request, ['File Data Not Recorded']);
            }
        }
    }

}