<?php

class RedirectedUrlsMiddleware extends Middleware
{
    /**
     * @param Request $request
     * @return HttpResponseRedirect
     */
    public function process_request(Request $request)
    {
        $redirectedUrls = $request->settings()->getRedirectUrls();

        if (isset($redirectedUrls[$request->path])) {

            $next = $redirectedUrls[$request->path];

            if ($request->get->hasParams())
                $next .= $request->get->buildQuery();

            return new HttpResponseRedirect($next, 303);
        }
    }
}

Http::register_middleware(new RedirectedUrlsMiddleware());

