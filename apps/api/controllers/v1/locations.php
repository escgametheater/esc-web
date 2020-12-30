<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 9:13 PM
 */

class LocationsApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var LocationsManager $manager */
    protected $manager;
    /** @var AddressesManager $addressesManager */
    protected $addressesManager;

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
        $this->addressesManager = $request->managers->addresses();
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
            $location = $this->manager->getLocationById($request, $this->form->getPkValue());
            $this->setResults($location);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request): ApiV1Response
    {
        $addresses = $this->addressesManager->getAddressById($request, 1);

        $fields = $this->manager->getFormFields([$addresses], false);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $addressId = $this->form->getCleanedValue(DBField::ADDRESS_ID);
            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);

            $location = $this->manager->createNewLocation($request, $addressId, $displayName);

            $this->setResults($location);
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

            $addresses = $this->addressesManager->getAddressById($request, 1);
            $location = $this->manager->getLocationById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields([$addresses]);
            $this->form = new ApiV1PostForm($fields, $request, $location);

            if ($this->form->is_valid()) {
                $location->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($location);
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

            $location = $this->manager->getLocationById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $location);

            $this->setResults($location);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

}