<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/5/18
 * Time: 2:44 PM
 */

class NetworksApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var NetworksManager $manager */
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
            $network = $this->manager->getNetworkById($request, $this->form->getPkValue());
            $this->setResults($network);
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

        $fields = $this->manager->getFormFields($hosts, false);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $hostId = $this->form->getCleanedValue(DBField::HOST_ID);
            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);
            $ssid = $this->form->getCleanedValue(DBField::SSID);
            $password = $this->form->getCleanedValue(DBField::PASSWORD);

            $network = $this->manager->createNewNetwork($request, $hostId, $displayName, $ssid, $password);

            $this->setResults($network);
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

            $network = $this->manager->getNetworkById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields($hosts, $hosts);
            $this->form = new ApiV1PostForm($fields, $request, $network);

            if ($this->form->is_valid()) {
                $network = $network->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($network);
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

            $network = $this->manager->getNetworkById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $network);

            $this->setResults($network);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

}