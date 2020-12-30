<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/5/18
 * Time: 2:22 PM
 */

class ScreensApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var ScreensManager $manager */
    protected $manager;

    /** @var NetworksManager $networksManager */
    protected $networksManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',

        // CRUD Endpoints
        'get' => 'handle_get',
        'create' => 'handle_create',
        'update' => 'handle_update',
        'delete' => 'handle_delete'
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->networksManager = $request->managers->networks();
        $this->hostsManager = $request->managers->hosts();
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request) : HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $screen = $this->manager->getScreenById($request, $this->form->getPkValue());
            $this->setResults($screen);
        }

        return $this->renderApiV1Response($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request) : ApiV1Response
    {
        $hosts = $this->hostsManager->getHostsByLocationId($request, 1);

        if ($hosts) {
            $defaultHostId = $hosts[0]->getPk();
            $networks = $this->networksManager->getNetworksByHostId($request, $defaultHostId);

        } else {
            $networks = [];
        }

        $fields = $this->manager->getFormFields($hosts, $networks, false);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $hostId = $this->form->getCleanedValue(DBField::HOST_ID);
            $networkId = $this->form->getCleanedValue(DBField::NETWORK_ID);
            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);

            $screen = $this->manager->createNewScreen($request, $hostId, $networkId, $displayName);

            $this->setResults($screen);
        }

        return $this->renderApiV1Response($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_update(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $hosts = $this->hostsManager->getHostsByLocationId($request, 1);

            if ($hosts) {
                $defaultHostId = $hosts[0]->getPk();
                $networks = $this->networksManager->getNetworksByHostId($request, $defaultHostId);

            } else {
                $networks = [];
            }

            $screen = $this->manager->getScreenById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields($hosts, $networks);
            $this->form = new ApiV1PostForm($fields, $request, $screen);

            if ($this->form->is_valid()) {
                $screen->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($screen);
            }

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_delete(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $screen = $this->manager->getScreenById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $screen);

            $this->setResults($screen);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

}