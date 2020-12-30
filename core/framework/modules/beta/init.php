<?php

class BetaMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        if ($request->user->is_authenticated())
            $request->user->beta_access = $request->user->getEntity()->has_beta_access();
        else
            $request->user->beta_access = false;


        $betaPageRouteConfigs = $request->config['beta_public_pages'];

        $isPublicPage = false;

        if ($request->path == '/') {

            if (array_key_exists($request->path, $betaPageRouteConfigs)) {
                $betaPageRouteConfig = $betaPageRouteConfigs[$request->path];
                $isPublicPage = array_get($betaPageRouteConfig, 'is_public', false);
            }

        } else {
            foreach ($betaPageRouteConfigs as $routeSetting => $betaPageRouteConfig) {
                $isMatch = stripos($request->path, $routeSetting) === 0
                    && (($routeSetting != '/')
                        || ($routeSetting == '/' && array_get($betaPageRouteConfig, 'wildcard', false))
                    );

                if ($isMatch) {

                    $isPublicPage = array_get($betaPageRouteConfig, 'is_public', false);
                    $isWildCard = array_get($betaPageRouteConfig, 'wildcard', false);

                    if (!$isWildCard && $request->path != $routeSetting)
                        $isPublicPage = false;

                    break;
                }

            }
        }

        if ($request->user->beta_access || $request->user->is_staff() || $request->user->is_superadmin()) {
            $hasBetaAccess = true;
        } else {
            $hasBetaAccess = false;
        }

        if (!$isPublicPage && !$hasBetaAccess) {
            $targetUrl = base64_encode($request->getFullUrl());
            return new HttpResponseRedirect($request->getWwwUrl("/auth/login?next={$targetUrl}"), 303);
        }
    }
}


Http::register_middleware(new BetaMiddleware());
