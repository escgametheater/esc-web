<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 9/14/18
 * Time: 11:36 AM
 */

class HostAutoUpdateApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = false;
    const REQUIRES_AUTH = false;

    /** @var HostBuildsManager $manager */
    protected $manager;

    /** @var ActivityManager $activityManager */
    protected $activityManager;

    /** @var LocationsManager $locationsManager */
    protected $locationsManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',
        'latest-mac.yml' => 'handle_update_latest',
        'latest-win.yml' => 'handle_update_latest',
        'latest.yml' => 'handle_update_latest',
        'latest-file-mac.zip' => 'handle_latest_file',
        'latest-file-win.exe' => 'handle_latest_file',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->activityManager = $request->managers->activity();
        $this->locationsManager = $request->managers->locations();
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request): HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }

    /**
     * @param Request $request
     * @return YamlResponse
     */
    public function handle_update_latest(Request $request): YamlResponse
    {
        $slugSource = str_replace('.yml', '', $request->getSlugForEntity($this->url_key));

        $slugSource = explode('-', $slugSource);
        if (isset($slugSource[1]))
            $platformSlug = $slugSource[1];
        else
            $platformSlug = PlatformsManager::SLUG_WINDOWS;

        $hostBuild = $this->manager->getLatestHostBuildByPlatform($request, $platformSlug);

        $template = $this->templateFactory->get();

        $viewData = [
            TemplateVars::HOST_BUILD => $hostBuild,
            TemplateVars::PLATFORM_SLUG => $platformSlug,
        ];

        $template->assign($viewData);

        if ($hostBuild)
            $statusCode = HttpResponse::HTTP_OK;
        else
            $statusCode = HttpResponse::HTTP_NO_CONTENT;

        return new YamlResponse($template->render_template('v1/host-auto-update/latest-host-version.yml.twig'), $statusCode);
    }


    /**
     * @param Request $request
     * @return ApiV1Response|HostAssetResponse
     */
    public function handle_latest_file(Request $request)
    {
        $rawUrlSlug = $request->getSlugForEntity($this->url_key);

        $extension = explode('.', $rawUrlSlug)[1];
        $platformSlug = explode('-', str_replace(".{$extension}", "", $rawUrlSlug))[2];

        $hostBuild = $this->manager->getLatestHostBuildByPlatform($request, $platformSlug);

        if ($hostBuildAsset = $hostBuild->getInstallerFileAsset([$extension])) {
            $hostAssetFile = $request->s3->readIntoMemory($hostBuildAsset->getBucket(), $hostBuildAsset->getBucketKey());
            return new HostAssetResponse($hostBuildAsset, $hostAssetFile);
        } else {
            return $this->renderApiV1Response($request);
        }
    }
}