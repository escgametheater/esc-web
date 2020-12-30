<?php
class DownloadSdkApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = true;
    const REQUIRES_AUTH = false;

    /** @var SdkVersionsManager */
    protected $manager;

    protected $pages = [
        'download' => "download_sdk",
        'version' => "get_sdk_version",
    ];

    /**
     * @param Request $request
     * @return HttpResponse|HttpResponseRedirect
     * @throws PermissionsException
     * @throws ObjectNotFound
     * @throws BaseEntityException
     */
    public function download_sdk(Request $request)
    {
        if (!$request->user->permissions->has(RightsManager::RIGHT_SDK, Rights::USE)) {
            return $this->render_404($request);
        }

        $sdkBuildsManager = $request->managers->sdkBuilds();
        $activityManager = $request->managers->activity();

        $platformSlug = $request->getSlugForEntity($this->url_key + 1);

        $sdkBuild = $sdkBuildsManager->getLatestSdkBuildBySdkPlatform($request, $platformSlug);

        if ($sdkBuild == null)
            return $this->render_404($request);


        if ($sdkBuildAsset = $sdkBuild->getAutoUpdateZipFileAsset()) {
            $sdkAssetFile = $request->s3->readIntoMemory($sdkBuildAsset->getBucket(), $sdkBuildAsset->getBucketKey());

            $activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_SDK_BUILD,
                $sdkBuild->getPk(),
                $sdkBuildAsset->getPk(),
                $request->getUiLang(),
                $request->user->getEntity()
            );

            return new SdkAssetResponse($sdkBuildAsset, $sdkAssetFile);
        } else {
            return $this->render_404($request);
        }
    }

    /**
     * @param Request $request
     * @return HttpResponse|HttpResponseRedirect
     * @throws PermissionsException
     * @throws ObjectNotFound
     * @throws BaseEntityException
     */
    public function get_sdk_version(Request $request)
    {
        if (!$request->user->permissions->has(RightsManager::RIGHT_SDK, Rights::USE)) {
            return $this->render_404($request);
        }

        $sdkBuildsManager = $request->managers->sdkBuilds();

        $platformSlug = $request->getSlugForEntity($this->url_key + 1);

        $sdkBuild = $sdkBuildsManager->getLatestSdkBuildBySdkPlatform($request, $platformSlug, SdkVersionsManager::UPDATE_CHANNEL_LIVE, false);

        if ($sdkBuild == null)
            return $this->render_404($request);

        $this->setResults($sdkBuild);
        $this->form = new ApiV1PostForm([], $request, []);
        return $this->renderApiV1Response($request);
    }


}