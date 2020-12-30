<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 6:30 PM
 */

class HostsApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var HostsManager $manager */
    protected $manager;

    /** @var LocationsManager $locationsManager */
    protected $locationsManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',

        // CRUD Endpoints
        'get' => 'handle_get',
        'list' => 'handle_list',
        'create' => 'handle_create',
        'update' => 'handle_update',
        'delete' => 'handle_delete',
        'auto-update-app' => 'handle_auto_update_app',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->locationsManager = $request->managers->locations();
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
            $host = $this->manager->getHostById($request, $this->form->getPkValue());
            $this->setResults($host);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_list(Request $request) : ApiV1Response
    {
        $defaults = [
            'page' => 1
        ];

        $fields = [
            new IntegerField('page', 'Page Number', false),
        ];

        $this->perPage = 10;

        $this->form = $this->buildGetEntityForm($request, $fields, $defaults);

        if ($this->form->is_valid()) {

            $page = $this->form->getCleanedValue('page');

            $hosts = $this->manager->getHostsByUserId($request, $request->user->id, $page, $this->perPage);
            $this->totalResults = $this->manager->getHostCountByUserId($request, $request->user->id);

            $this->setResults($hosts);
        }

        return $this->renderApiV1Response($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request) : ApiV1Response
    {
        $locations = $this->locationsManager->getAllLocations($request);

        $fields = $this->manager->getFormFields($locations, false);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $locationId = $this->form->getCleanedValue(DBField::LOCATION_ID);
            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);

            $host = $this->manager->createNewHost($request, $locationId, $displayName);
            $this->setResults($host);
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

            $locations = $this->locationsManager->getAllLocations($request);
            $host = $this->manager->getHostById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields($locations);
            $this->form = new ApiV1PostForm($fields, $request, $host);

            if ($this->form->is_valid()) {
                $host->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($host);
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

            $host = $this->manager->getHostById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $host);
            $this->setResults($host);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

}