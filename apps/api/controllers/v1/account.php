<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/15/18
 * Time: 7:15 PM
 */


class AccountApiV1Controller extends BaseApiV1Controller {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var UsersManager $manager */
    protected $manager;
    /** @var AddressesManager $addressesManager */
    protected $addressesManager;
    /** @var UserProfilesManager $usersProfilesManager */
    protected $usersProfilesManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',

        // User Profile
        'profile' => 'handle_profile',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->addressesManager = $request->managers->addresses();
        $this->usersProfilesManager = $request->managers->usersProfiles();
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
    public function handle_profile(Request $request) : ApiV1Response
    {
        $this->form = new ApiV1PostForm([], $request);

        if ($this->form->is_valid()) {
            $this->setResults($request->user->getEntity());
        }

        return $this->renderApiV1Response($request);
    }

}