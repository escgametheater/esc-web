<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/10/19
 * Time: 3:30 PM
 */

class OrganizationOnboardingController extends BaseContent
{
    protected $url_key = 3;
    /** @var OrganizationEntity $activeOrganization */
    protected $activeOrganization = [];
    protected $user;

    /** @var OrganizationsMetaManager $organizationsMetaManager */
    protected $organizationsMetaManager;
    /** @var AddressesManager $addressesManager */
    protected $addressesManager;
    /** @var ServicesAccessTokensManager $servicesAccessTokensManager */
    protected $servicesAccessTokensManager;
    /** @var ServicesAccessTokensInstancesManager $servicesAccessTokensInstancesManager */
    protected $servicesAccessTokensInstancesManager;
    /** @var OrdersManager $ordersManager */
    protected $ordersManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var OrganizationsUsersManager $organizationsUsersManager */
    protected $organizationsUsersManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;

    /** @var PaymentsManager $paymentsManager */
    protected $paymentsManager;
    /** @var PaymentsServicesManager $paymentsServicesManager */
    protected $paymentsServicesManager;
    /** @var OwnerPaymentsServicesManager $ownerPaymentsServicesManager */
    protected $ownerPaymentsServicesManager;
    /** @var OwnersPaymentsServicesTokensManager $ownerPaymentsServicesTokensManager */
    protected $ownerPaymentsServicesTokensManager;

    protected $pages = [

        // Index
        '' => 'handle_step1',
        'members' => 'handle_step2',
        'confirm' => 'handle_step3'
    ];


    /**
     * ManageGameModController constructor.
     * @param null $template_factory
     * @param GameModEntity $gameMod
     * @param array $organizations
     */
    public function __construct($template_factory = null, OrganizationEntity $organization, UserEntity $user)
    {
        parent::__construct($template_factory);

        $this->activeOrganization = $organization;
        $this->user = $user;
    }

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HtmlResponse|HttpResponseRedirect
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        if (!$request->user->is_authenticated()) {
            return $this->redirectToLogin($request);
        } else {
            $user = $request->user->getEntity();
        }

        if (!$this->activeOrganization)
            return $this->render_404($request);

        $this->url_key = $url_key;

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        $this->hostsManager = $request->managers->hosts();
        $this->imagesManager = $request->managers->images();
        $this->ordersManager = $request->managers->orders();
        $this->addressesManager = $request->managers->addresses();
        $this->imagesTypesManager = $request->managers->imagesTypes();
        $this->organizationsMetaManager = $request->managers->organizationsMeta();
        $this->servicesAccessTokensManager = $request->managers->servicesAccessTokens();
        $this->servicesAccessTokensInstancesManager = $request->managers->servicesAccessTokensInstances();

        $this->organizationsUsersManager = $request->managers->organizationsUsers();
        $this->activityManager = $request->managers->activity();

        $this->paymentsManager = $request->managers->payments();
        $this->paymentsServicesManager = $request->managers->paymentsServices();
        $this->ownerPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $this->ownerPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        if ($this->organizationsMetaManager->checkBoolMetaKey($this->activeOrganization->getPk(), OrganizationsMetaManager::KEY_ONBOARDED))
            return $this->redirect($this->activeOrganization->getUrl());

        return $this->$func($request, $user, $this->activeOrganization);
    }

    /**
     * @param Request $request
     * @param $step
     * @return string
     */
    private function buildStepUrl(Request $request, $step)
    {
        return $request->getDevelopUrl("/teams/{$this->activeOrganization->getSlug()}/onboarding/{$step}");
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_step1(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $organizationAddress = $this->addressesManager->getAddressByContextOwnerAndType(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk(),
            AddressesTypesManager::ID_BUSINESS
        );

        $fields = [
            new CharField(DBField::TITLE, 'Team Name', 65, true),
            new CharField(DBField::DESCRIPTION, 'Primary Business Address', 128, true, 'This will be used for billing and tax purposes, and never shared with external parties without prior consent.', 'enter address...'),
            new HiddenField(DBField::ADDRESS_LINE1, "Address Line 1", 0, true),
            new HiddenField(DBField::ADDRESS_LINE2, "Address Line 2: Area", 0, false),
            new HiddenField(DBField::ADDRESS_LINE3, "Address Line 3: Location Name", 0, false),
            new HiddenField(DBField::CITY_NAME, "City Name", 0, false),
            new HiddenField(DBField::ZIP_CODE, "Zip Code", 0, true),
            new HiddenField(DBField::STATE_CODE, "State Short Code", 0, false),
            new HiddenField(DBField::STATE, "State Name", 0, true),
            new HiddenField(DBField::COUNTRY_ID, "Country Id", 3, true),
        ];

        $defaults = [
            DBField::NAME => $organizationAddress ? $organizationAddress->getDisplayName() : null,
            DBField::TITLE => $activeOrganization->getDisplayName()
        ];

        if ($organizationAddress) {
            $defaults = array_merge($defaults, [

                DBField::DESCRIPTION => $organizationAddress->getDisplayName(),
                DBField::FIRSTNAME => $organizationAddress->getFirstName(),
                DBField::LASTNAME => $organizationAddress->getLastName(),
                DBField::PHONE_NUMBER => $organizationAddress->getPhoneNumber(),
                DBField::ADDRESS_LINE1 => $organizationAddress->getAddressLine1(),
                DBField::ADDRESS_LINE2 => $organizationAddress->getAddressLine2(),
                DBField::ADDRESS_LINE3 => $organizationAddress->getAddressLine3(),
                DBField::CITY => $organizationAddress->getCity(),
                DBField::STATE => $organizationAddress->getState(),
                DBField::ZIP_CODE => $organizationAddress->getZip(),
                DBField::COUNTRY_ID => $organizationAddress->getCountryId(),
            ]);
        }

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::PREVIOUS => $request->getReferer() ? $request->getReferer() : $request->getRedirectBackUrl()
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-onboarding-step-1.twig');

        if ($form->is_valid()) {

            $displayName = $form->getCleanedValue(DBField::TITLE);
            $activeOrganization->updateField(DBField::DISPLAY_NAME, $displayName)->saveEntityToDb($request);

            $addressDescription = $form->getCleanedValue(DBField::DESCRIPTION);
            $addressLine1 = $form->getCleanedValue(DBField::ADDRESS_LINE1);
            $addressLine2 = $form->getCleanedValue(DBField::ADDRESS_LINE2);
            $addressLine3 = $form->getCleanedValue(DBField::ADDRESS_LINE3);
            $city = $form->getCleanedValue(DBField::CITY_NAME);
            $zipCode = $form->getCleanedValue(DBField::ZIP_CODE);
            $stateCode = $form->getCleanedValue(DBField::STATE_CODE);
            $state = $form->getCleanedValue(DBField::STATE);
            $countryId = $form->getCleanedValue(DBField::COUNTRY_ID);

            $ownerUser = $activeOrganization->getCreatorUser();

            if (!$organizationAddress) {
                $organizationAddress = $this->addressesManager->createNewAddress(
                    $request,
                    EntityType::ORGANIZATION,
                    $activeOrganization->getPk(),
                    $countryId,
                    AddressesTypesManager::ID_BUSINESS,
                    1,
                    $addressDescription,
                    $ownerUser->getFirstName(),
                    $ownerUser->getLastName(),
                    $ownerUser->getPhoneNumber(),
                    $addressLine1,
                    $addressLine2,
                    $addressLine3,
                    $city,
                    $state,
                    $zipCode
                );
            } else {
                $data = [
                    DBField::IS_PRIMARY => 1,
                    DBField::IS_ACTIVE => 1,
                    DBField::DISPLAY_NAME => $addressDescription,
                    DBField::FIRSTNAME => $ownerUser->getFirstName(),
                    DBField::LASTNAME => $ownerUser->getLastName(),
                    DBField::PHONE_NUMBER => $ownerUser->getPhoneNumber(),
                    DBField::ADDRESS_LINE1 => $addressLine1,
                    DBField::ADDRESS_LINE2 => $addressLine2,
                    DBField::ADDRESS_LINE3 => $addressLine3,
                    DBField::CITY => $city,
                    DBField::STATE => $state,
                    DBField::ZIP => $zipCode,
                    DBField::COUNTRY_ID => $countryId,
                ];

                $organizationAddress->assign($data)->saveEntityToDb($request);
            }


            $request->user->sendFlashMessage('Team Profile Saved');

            return $form->handleRenderJsonSuccessResponse($this->buildStepUrl($request, 'members'));
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Profile - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Profile - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-onboarding-profile',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::FORM => $form,
            TemplateVars::DISPLAY_ACCOUNT_HEADER => false,
            TemplateVars::DISPLAY_FOOTER => false,
            TemplateVars::USE_CROPPIE => true,
        ];

        return $this->renderPageResponse($request, $viewData, 'account/onboarding-steps.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_step2(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $cmsSeatAccessTokenInstance = $this->servicesAccessTokensInstancesManager->getActiveServiceAccessTokenInstanceByOwnerAndTypeCategory(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk(),
            ServicesAccessTokensTypesCategoriesManager::ID_TEAM_SEATS
        );

        if ($cmsSeatAccessTokenInstance)
            $maxUsers = $cmsSeatAccessTokenInstance->getServiceAccessToken()->getMaxSeats();
        else
            $maxUsers = 0;

        $remainingUsers = $maxUsers - $activeOrganization->getOrganizationUsersCount();

        $organizationUserInvites = $this->organizationsUsersManager->getInvitedOrganizationUsersByOrganizationId($request, $activeOrganization->getPk());
        foreach ($organizationUserInvites as $organizationUserInvite) {
            $organizationRole = $activeOrganization->getOrganizationRoleById($organizationUserInvite->getOrganizationRoleId());
            $organizationUserInvite->setOrganizationRole($organizationRole);
        }

        $fields = [
            new HiddenField('send_invites', "")
        ];

        for ($i = 1; $i<= $remainingUsers; $i++) {
            if ($request->is_post())
                $requireRole = $request->readPostParam("email_{$i}") ? true : false;
            else
                $requireRole = false;

            $fields[] = new EmailField("email_{$i}", '', false, '', "name{$i}@example.com", null, false);
            $fields[] = new SelectField("role_{$i}", '', $activeOrganization->getOrganizationRoles(), $requireRole);
        }

        $defaults = [];

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::MAX_USES => $maxUsers,
            TemplateVars::ORGANIZATION_USERS_INVITES => $organizationUserInvites
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-onboarding-step-2.twig');

        if ($form->is_valid()) {

            $sendInvites = $form->getCleanedValue('send_invites');
            if ($sendInvites) {
                $countInvited = 0;

                for ($i = 1; $i<= $remainingUsers; $i++) {

                    $emailFieldName = "email_{$i}";
                    $roleFieldName = "role_{$i}";
                    if ($form->has_field($emailFieldName) && $form->has_field($roleFieldName)) {
                        if ($emailAddress = $form->getCleanedValue($emailFieldName)) {
                            $roleId = $form->getCleanedValue($roleFieldName);

                            $this->organizationsUsersManager->inviteUserToTeam($request, $activeOrganization, $user, $emailAddress, $roleId);

                            $countInvited++;
                        }
                    }
                }


                $request->user->sendFlashMessage("{$countInvited} Members Invited");
            }

            $next = $sendInvites ? $request->getRedirectBackUrl() : $this->buildStepUrl($request, 'confirm');

            return $form->handleRenderJsonSuccessResponse($next);
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Invite Members - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Invite Members - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-onboarding-members',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::FORM => $form,
            TemplateVars::DISPLAY_ACCOUNT_HEADER => false,
            TemplateVars::DISPLAY_FOOTER => false
        ];

        return $this->renderPageResponse($request, $viewData, 'account/onboarding-steps.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_step3(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $pilotOrderIdMeta = $this->organizationsMetaManager->getOrganizationMetaByKey($request, $activeOrganization->getPk(), OrganizationsMetaManager::KEY_PILOT_ORDER_ID);
        $order = [];

        if ($pilotOrderIdMeta)
            $order = $this->ordersManager->getOrderById($request, $pilotOrderIdMeta->getValue(), $activeOrganization->getPk(), EntityType::ORGANIZATION);

        $fields = [];

        $defaults = [];

        $hosts = $this->hostsManager->getHostsByOrganizationId($request, $activeOrganization->getPk(), 1, 10, false);

        $prodHost = [];

        foreach ($hosts as $host) {
            if ($host->is_prod())
                $prodHost = $host;
        }

        $serviceAccessTokens = $this->servicesAccessTokensManager->getServiceAccessTokensByOrganizationInstances($request, $activeOrganization->getPk());

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::ORDER => $order,
            TemplateVars::HOST => $prodHost,
            TemplateVars::SERVICE_ACCESS_TOKENS => $serviceAccessTokens,
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-onboarding-step-3.twig');

        if ($form->is_valid()) {

            try {
                $dbConnection = $request->db->get_connection();
                $dbConnection->begin();

                if ($order) {

                    if ($order->is_new())
                        $this->ordersManager->markOrderAsAccepted($request, $order);

                    if (!$payment = $order->getPayment()) {
                        $ownerTypeId = EntityType::ORGANIZATION;

                        $paymentService = $this->paymentsServicesManager->getPaymentServiceById($request, PaymentsServicesManager::SERVICE_INTERNAL, false);
                        $paymentServiceHandler = $paymentService->getPaymentServiceHandler();

                        $token = $this->paymentsServicesManager->generateInternalPaymentServiceToken($ownerTypeId, $activeOrganization->getPk());

                        if (!$ownerPaymentServiceToken = $this->ownerPaymentsServicesTokensManager->getOwnerPaymentServiceTokenByToken($request, $ownerTypeId, $activeOrganization->getPk(), $token))
                            $ownerPaymentServiceToken = $paymentServiceHandler->createPaymentSource($request, $activeOrganization, $token);

                        // Do not try to capture (claim payment) at this time if there's pending balance.
                        // Todo: update when using credit cards
                        if ($order->getTotalAmountDueAsFloat() > 0) {
                            $shouldCapture = false;
                        } else {
                            $shouldCapture = true;
                        }

                        $payment = $this->paymentsManager->createNewPayment(
                            $request,
                            $ownerTypeId,
                            $activeOrganization->getPk(),
                            $order,
                            $ownerPaymentServiceToken,
                            $shouldCapture
                        );
                    }

                    if ($payment->is_paid())
                        $this->ordersManager->markOrderAsPaid($request, $order);
                }

                $this->organizationsMetaManager->createUpdateOrganizationMeta($request, $activeOrganization->getPk(), OrganizationsMetaManager::KEY_ONBOARDED, "1");

                $dbConnection->commit();

            } catch (DBException $e) {
                $dbConnection->rollback();
                return $form->handleRenderJsonErrorResponse();
            }


            $request->user->sendFlashMessage('Welcome to ESC Pilot');

            return $form->handleRenderJsonSuccessResponse($activeOrganization->getUrl());
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Join Pilot - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Join Pilot - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-onboarding-confirm',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::FORM => $form,
            TemplateVars::DISPLAY_ACCOUNT_HEADER => false,
            TemplateVars::DISPLAY_FOOTER => false
        ];

        return $this->renderPageResponse($request, $viewData, 'account/onboarding-steps.twig');
    }
}