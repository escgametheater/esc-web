<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 12/26/15
 * Time: 12:48 AM
 */


final class ManagerLocator extends ServiceLocator {

    /** @var DB $default_db */
    protected $default_db;

    /** @var  Request $request */
    protected $request;

    /**
     * ManagerLocator constructor.
     */
    public function __construct(Request $request = null)
    {
        if ($request) {
            $this->default_db = $request->db;
            $this->request = $request;

        }
    }

    /**
     * @param string $manager_class
     */
    public function getManager($manager_class)
    {
        /** @var BaseEntityManager $manager_class */
        return $manager_class::getInstance()->setDataSource($this->default_db);
    }


    public function setDefaultDB($db)
    {
        $this->default_db = $db;
    }


    /**
     * USERS + Related
     *
     * @return UsersManager
     */
    public function users()
    {
        return $this->getManager(UsersManager::class);
    }

    /**
     * @return UserProfilesManager
     */
    public function usersProfiles()
    {
        return $this->getManager(UserProfilesManager::class);
    }

    /**
     * @return UsersMetaManager
     */
    public function usersMeta()
    {
        return $this->getManager(UsersMetaManager::class);
    }

    /**
     * @return UserGroupsManager
     */
    public function userGroups()
    {
        return $this->getManager(UserGroupsManager::class);
    }

    /**
     * @return UserGroupsRightsManager
     */
    public function userGroupsRights()
    {
        return $this->getManager(UserGroupsRightsManager::class);
    }

    /**
     * @return UsersUserGroupsManager
     */
    public function usersUserGroups()
    {
        return $this->getManager(UsersUserGroupsManager::class);
    }

    /**
     * @return LoginAttemptsManager
     */
    public function loginAttempts()
    {
        return $this->getManager(LoginAttemptsManager::class);
    }

    /**
     * @return PasswordResetAttemptsManager
     */
    public function passwordResetAttempts()
    {
        return $this->getManager(PasswordResetAttemptsManager::class);
    }


    /**
     * @return UploadsManager
     */
    public function uploads()
    {
        return $this->getManager(UploadsManager::class);
    }

    /**
     * TRACKING + Related
     *
     * @return GuestTrackingManager
     */
    public function guestTracking()
    {
        return $this->getManager(GuestTrackingManager::class);
    }

    /**
     * @return SessionTrackingManager
     */
    public function sessionTracking()
    {
        return $this->getManager(SessionTrackingManager::class);
    }

    /**
     * @return ActivityManager
     */
    public function activity()
    {
        return $this->getManager(ActivityManager::class);
    }

    /**
     * @return ActivityTypesManager
     */
    public function activityTypes()
    {
        return $this->getManager(ActivityTypesManager::class);
    }

    /**
     * @return RequestsManager
     */
    public function requests()
    {
        return $this->getManager(RequestsManager::class);
    }

    /**
     * @return ApiLogsManager
     */
    public function apiLog()
    {
        return $this->getManager(ApiLogsManager::class);
    }

    /**
     * @return ApplicationsManager
     */
    public function applications()
    {
        return $this->getManager(ApplicationsManager::class);
    }

    /**
     * @return ApplicationsUsersManager
     */
    public function applicationsUsers()
    {
        return $this->getManager(ApplicationsUsersManager::class);
    }

    /**
     * @return ApplicationsUsersAccessTokensManager
     */
    public function applicationsUsersAccessTokens()
    {
        return $this->getManager(ApplicationsUsersAccessTokensManager::class);
    }

    /**
     * @return EmailTrackingManager
     */
    public function emailTracking()
    {
        return $this->getManager(EmailTrackingManager::class);
    }

    /**
     * @return EmailHistoryManager
     */
    public function emailHistory()
    {
        return $this->getManager(EmailHistoryManager::class);
    }


    /**
     * @return EmailSettingsManager
     */
    public function emailSettings()
    {
        return $this->getManager(EmailSettingsManager::class);
    }

    /**
     * @return EmailSettingsHistoryManager
     */
    public function emailSettingsHistory()
    {
        return $this->getManager(EmailSettingsHistoryManager::class);
    }

    /**
     * @return EmailTypesManager
     */
    public function emailTypes()
    {
        return $this->getManager(EmailTypesManager::class);
    }

    /**
     * @return EmailSettingsGroupsManager
     */
    public function emailSettingsGroups()
    {
        return $this->getManager(EmailSettingsGroupsManager::class);
    }


    /**
     * @return GeoRegionsManager
     */
    public function geoRegions()
    {
        return $this->getManager(GeoRegionsManager::class);
    }

    /**
     * @return LanguagesManager
     */
    public function languages()
    {
        return $this->getManager(LanguagesManager::class);
    }

    /**
     * @return RightsManager
     */
    public function rights()
    {
        return $this->getManager(RightsManager::class);
    }

    /**
     * @return RightsGroupsManager
     */
    public function rightsGroups()
    {
        return $this->getManager(RightsGroupsManager::class);
    }

    /**
     * @return FlashMessagesManager
     */
    public function flashMessages()
    {
        /** @var FlashMessagesManager $manager */
        $manager = $this->getManager(FlashMessagesManager::class);

        return $manager->setSessionObject($this->request->user->session);
    }

    /**
     * @return GeoIpMapperManager
     */
    public function geoIpMapper()
    {
        return $this->getManager(GeoIpMapperManager::class);
    }

    /**
     * @return SettingsManager
     */
    public function settings()
    {
        return $this->getManager(SettingsManager::class);
    }

    /**
     * @return VarsManager
     */
    public function vars()
    {
        return $this->getManager(VarsManager::class);
    }

    /**
     * @return TasksManager
     */
    public function tasks()
    {
        return $this->getManager(TasksManager::class);
    }

    /**
     * @return TasksHistoryManager
     */
    public function tasksHistory()
    {
        return $this->getManager(TasksHistoryManager::class);
    }

    /**
     * @return SettingsManager
     */
    public function domainSettings()
    {
        return $this->getManager(SettingsManager::class);
    }


    /**
     * @return CountriesManager
     */
    public function countries()
    {
        return $this->getManager(CountriesManager::class);
    }

    /**
     * @return CurrenciesManager
     */
    public function currencies()
    {
        return $this->getManager(CurrenciesManager::class);
    }

    /**
     * @return i18nManager
     */
    public function i18n()
    {
        return $this->getManager(i18nManager::class);
    }

    /**
     * @return AddressesManager
     */
    public function addresses()
    {
        return $this->getManager(AddressesManager::class);
    }

    /**
     * @return AddressesTypesManager
     */
    public function addressesTypes()
    {
        return $this->getManager(AddressesTypesManager::class);
    }


    /*
     * New Managers ESC
     */

    /**
     * @return HostAssetsManager
     */
    public function hostAssets()
    {
        return $this->getManager(HostAssetsManager::class);
    }

    /**
     * @return HostVersionsManager
     */
    public function hostVersions()
    {
        return $this->getManager(HostVersionsManager::class);
    }

    /**
     * @return HostBuildsManager
     */
    public function hostBuilds()
    {
        return $this->getManager(HostBuildsManager::class);
    }

    /**
     * @return HostBuildsActiveManager
     */
    public function hostBuildsActive()
    {
        return $this->getManager(HostBuildsActiveManager::class);
    }

    /**
     * @return SdkAssetsManager
     */
    public function sdkAssets()
    {
        return $this->getManager(SdkAssetsManager::class);
    }

    /**
     * @return SdkVersionsManager
     */
    public function sdkVersions()
    {
        return $this->getManager(SdkVersionsManager::class);
    }

    /**
     * @return SdkBuildsManager
     */
    public function sdkBuilds()
    {
        return $this->getManager(SdkBuildsManager::class);
    }

    /**
     * @return SdkBuildsActiveManager
     */
    public function sdkBuildsActive()
    {
        return $this->getManager(SdkBuildsActiveManager::class);
    }


    /**
     * @return HostsControllersManager
     */
    public function hostControllers()
    {
        return $this->getManager(HostsControllersManager::class);
    }


    /**
     * @return HostsManager
     */
    public function hosts()
    {
        return $this->getManager(HostsManager::class);
    }

    /**
     * @return HostsDevicesManager
     */
    public function hostsDevices()
    {
        return $this->getManager(HostsDevicesManager::class);
    }

    /**
     * @return HostsDevicesComponentsManager
     */
    public function hostsDevicesComponents()
    {
        return $this->getManager(HostsDevicesComponentsManager::class);
    }

    /**
     * @return LocationsManager
     */
    public function locations()
    {
        return $this->getManager(LocationsManager::class);
    }

    /**
     * @return ScreensManager
     */
    public function screens()
    {
        return $this->getManager(ScreensManager::class);
    }

    /**
     * @return NetworksManager
     */
    public function networks()
    {
        return $this->getManager(NetworksManager::class);
    }


    // Games

    /**
     * @return HostsInstancesManager
     */
    public function hostsInstances()
    {
        return $this->getManager(HostsInstancesManager::class);
    }

    /**
     * @return HostsInstancesTypesManager
     */
    public function hostsInstancesTypes()
    {
        return $this->getManager(HostsInstancesTypesManager::class);
    }

    /**
     * @return HostsInstancesDevicesManager
     */
    public function hostsInstancesDevices()
    {
        return $this->getManager(HostsInstancesDevicesManager::class);
    }

    /**
     * @return HostsInstancesInvitesManager
     */
    public function hostsInstancesInvites()
    {
        return $this->getManager(HostsInstancesInvitesManager::class);
    }

    /**
     * @return HostsInstancesInvitesTypesManager
     */
    public function hostsInstanceInvitesTypes()
    {
        return $this->getManager(HostsInstancesInvitesTypesManager::class);
    }

    /**
     * @return GamesManager
     */
    public function games()
    {
        return $this->getManager(GamesManager::class);
    }

    /**
     * @return GamesTypesManager
     */
    public function gamesTypes()
    {
        return $this->getManager(GamesTypesManager::class);
    }

    /**
     * @return GamesCategoriesManager
     */
    public function gamesCategories()
    {
        return $this->getManager(GamesCategoriesManager::class);
    }

    /**
     * @return GamesBuildsManager
     */
    public function gamesBuilds()
    {
        return $this->getManager(GamesBuildsManager::class);
    }

    /**
     * @return GamesActiveBuildsManager
     */
    public function gamesActiveBuilds()
    {
        return $this->getManager(GamesActiveBuildsManager::class);
    }

    /**
     * @return GamesEnginesManager
     */
    public function gamesEngines()
    {
        return $this->getManager(GamesEnginesManager::class);
    }

    /**
     * @return GamesInstancesManager
     */
    public function gamesInstances()
    {
        return $this->getManager(GamesInstancesManager::class);
    }

    /**
     * @return GamesInstancesRoundsManager
     */
    public function gamesInstancesRounds()
    {
        return $this->getManager(GamesInstancesRoundsManager::class);
    }

    /**
     * @return GamesInstancesRoundsPlayersManager
     */
    public function gamesInstancesRoundsPlayers()
    {
        return $this->getManager(GamesInstancesRoundsPlayersManager::class);
    }

    /**
     * @return GamesInstancesRoundsEventsManager
     */
    public function gamesInstancesRoundsEvents()
    {
        return $this->getManager(GamesInstancesRoundsEventsManager::class);
    }

    /**
     * @return GamesInstancesRoundsEventsPropertiesManager
     */
    public function gamesInstancesRoundsEventsProperties()
    {
        return $this->getManager(GamesInstancesRoundsEventsPropertiesManager::class);
    }

    /**
     * @return GamesInstancesLogsManager
     */
    public function gamesInstancesLogs()
    {
        return $this->getManager(GamesInstancesLogsManager::class);
    }

    /**
     * @return GamesInstancesLogsStatusesManager
     */
    public function gamesInstancesLogsStatuses()
    {
        return $this->getManager(GamesInstancesLogsStatusesManager::class);
    }

    /**
     * @return GamesAssetsManager
     */
    public function gamesAssets()
    {
        return $this->getManager(GamesAssetsManager::class);
    }

    /**
     * @return ContextXGamesAssetsManager
     */
    public function contextXGamesAssets()
    {
        return $this->getManager(ContextXGamesAssetsManager::class);
    }

    /**
     * @return ContextXSdkAssetsManager
     */
    public function contextXSdkAssets()
    {
        return $this->getManager(ContextXSdkAssetsManager::class);
    }

    /**
     * @return ContextXHostAssetsManager
     */
    public function contextXHostAssets()
    {
        return $this->getManager(ContextXHostAssetsManager::class);
    }

    /**
     * @return GamesControllersManager
     */
    public function gamesControllers()
    {
        return $this->getManager(GamesControllersManager::class);
    }

    /**
     * @return GamesControllersTypesManager
     */
    public function gamesControllersTypes()
    {
        return $this->getManager(GamesControllersTypesManager::class);
    }

    /**
     * @return GamesActiveCustomAssetsManager
     */
    public function gamesActiveCustomAssets()
    {
        return $this->getManager(GamesActiveCustomAssetsManager::class);
    }

    /**
     * @return GamesPlayersStatsManager
     */
    public function gamesPlayersStats()
    {
        return $this->getManager(GamesPlayersStatsManager::class);
    }

    /**
     * @return GamesPlayersStatsTypesManager
     */
    public function gamesPlayersStatsTypes()
    {
        return $this->getManager(GamesPlayersStatsTypesManager::class);
    }

    /**
     * @return PlatformsManager
     */
    public function platforms()
    {
        return $this->getManager(PlatformsManager::class);
    }

    /**
     * @return SdkPlatformsManager
     */
    public function sdkPlatforms()
    {
        return $this->getManager(SdkPlatformsManager::class);
    }

    /**
     * @return PlatformsVersionsManager
     */
    public function platformsVersions()
    {
        return $this->getManager(PlatformsVersionsManager::class);
    }

    /**
     * @return GamesXPlatformsManager
     */
    public function gamesXPlatforms()
    {
        return $this->getManager(GamesXPlatformsManager::class);
    }

    /**
     * @return GamesDataManager
     */
    public function gamesData()
    {
        return $this->getManager(GamesDataManager::class);
    }

    /**
     * @return GamesDataSheetsManager
     */
    public function gamesDataSheets()
    {
        return $this->getManager(GamesDataSheetsManager::class);
    }

    /**
     * @return GamesDataSheetsColumnsManager
     */
    public function gamesDataSheetsColumns()
    {
        return $this->getManager(GamesDataSheetsColumnsManager::class);
    }

    /**
     * @return GamesDataSheetsRowsManager
     */
    public function gamesDataSheetsRows()
    {
        return $this->getManager(GamesDataSheetsRowsManager::class);
    }

    /**
     * @return GamesDataSheetsModsTypesManager
     */
    public function gamesDataSheetsModTypes()
    {
        return $this->getManager(GamesDataSheetsModsTypesManager::class);
    }

    /**
     * @return GamesModsManager
     */
    public function gamesMods()
    {
        return $this->getManager(GamesModsManager::class);
    }

    /**
     * @return GamesModsLicensesManager
     */
    public function gamesModsLicenses()
    {
        return $this->getManager(GamesModsLicensesManager::class);
    }

    /**
     * @return GamesModsBuildsManager
     */
    public function gamesModsBuilds()
    {
        return $this->getManager(GamesModsBuildsManager::class);
    }

    /**
     * @return GamesModsDataManager
     */
    public function gamesModsData()
    {
        return $this->getManager(GamesModsDataManager::class);
    }

    /**
     * @return GamesModsDataSheetsManager
     */
    public function gamesModsDataSheets()
    {
        return $this->getManager(GamesModsDataSheetsManager::class);
    }

    /**
     * @return GamesModsDataSheetsColumnsManager
     */
    public function gamesModsDataSheetsColumns()
    {
        return $this->getManager(GamesModsDataSheetsColumnsManager::class);
    }

    /**
     * @return GamesModsDataSheetsRowsManager
     */
    public function gamesModsDataSheetsRows()
    {
        return $this->getManager(GamesModsDataSheetsRowsManager::class);
    }

    /**
     * @return GamesModsActiveBuildsManager
     */
    public function gamesModsActiveBuilds()
    {
        return $this->getManager(GamesModsActiveBuildsManager::class);
    }

    /**
     * @return GamesModsActiveCustomAssetsManager
     */
    public function gamesModsActiveCustomAssets()
    {
        return $this->getManager(GamesModsActiveCustomAssetsManager::class);
    }


    /**
     * @return GameLicensesManager
     */
    public function gameLicenses()
    {
        return $this->getManager(GameLicensesManager::class);
    }


    /**
     * @return SmsManager
     */
    public function sms()
    {
        return $this->getManager(SmsManager::class);
    }

    /**
     * @return SmsTypesManager
     */
    public function smsTypes()
    {
        return $this->getManager(SmsTypesManager::class);
    }

    /**
     * @return OrganizationsManager
     */
    public function organizations()
    {
        return $this->getManager(OrganizationsManager::class);
    }

    /**
     * @return OrganizationsActivityManager
     */
    public function organizationsActivities()
    {
        return $this->getManager(OrganizationsActivityManager::class);
    }

    /**
     * @return OrganizationsUsersInvitesManager
     */
    public function organizationsUsersInvites()
    {
        return $this->getManager(OrganizationsUsersInvitesManager::class);
    }

    /**
     * @return OrganizationsMetaManager
     */
    public function organizationsMeta()
    {
        return $this->getManager(OrganizationsMetaManager::class);
    }

    /**
     * @return OrganizationsUsersStatusesManager
     */
    public function organizationsUsersStatuses()
    {
        return $this->getManager(OrganizationsUsersStatusesManager::class);
    }

    /**
     * @return OrganizationsGamesLicensesManager
     */
    public function organizationsGamesLicenses()
    {
        return $this->getManager(OrganizationsGamesLicensesManager::class);
    }

    /**
     * @return OrganizationsGamesModsLicensesManager
     */
    public function organizationsGamesModsLicenses()
    {
        return $this->getManager(OrganizationsGamesModsLicensesManager::class);
    }

    /**
     * @return OrganizationsBaseRolesManager
     */
    public function organizationsBaseRoles()
    {
        return $this->getManager(OrganizationsBaseRolesManager::class);
    }

    /**
     * @return OrganizationsBaseRightsManager
     */
    public function organizationsBaseRights()
    {
        return $this->getManager(OrganizationsBaseRightsManager::class);
    }

    /**
     * @return OrganizationsBasePermissionsManager
     */
    public function organizationsBasePermissions()
    {
        return $this->getManager(OrganizationsBasePermissionsManager::class);
    }

    /**
     * @return OrganizationsRolesManager
     */
    public function organizationsRoles()
    {
        return $this->getManager(OrganizationsRolesManager::class);
    }

    /**
     * @return OrganizationsUsersManager
     */
    public function organizationsUsers()
    {
        return $this->getManager(OrganizationsUsersManager::class);
    }

    /**
     * @return OrganizationsRightsManager
     */
    public function organizationsRights()
    {
        return $this->getManager(OrganizationsRightsManager::class);
    }

    /**
     * @return OrganizationsPermissionsManager
     */
    public function organizationsPermissions()
    {
        return $this->getManager(OrganizationsPermissionsManager::class);
    }


    /**
     * Incentives
     */

    /**
     * @return IncentivesManager
     */
    public function incentivesManager()
    {
        return $this->getManager(IncentivesManager::class);
    }

    /**
     * @return IncentivesTypesManager
     */
    public function incentivesTypesManager()
    {
        return $this->getManager(IncentivesTypesManager::class);
    }

    /**
     * @return IncentivesInstancesManager
     */
    public function incentivesInstancesManager()
    {
        return $this->getManager(IncentivesInstancesManager::class);
    }

    /**
     * Orders / Payments
     */


    /**
     * @return OrdersManager
     */
    public function orders()
    {
        return $this->getManager(OrdersManager::class);
    }

    /**
     * @return OrdersStatusesManager
     */
    public function ordersStatuses()
    {
        return $this->getManager(OrdersStatusesManager::class);
    }

    /**
     * @return OrdersItemsManager
     */
    public function ordersItems()
    {
        return $this->getManager(OrdersItemsManager::class);
    }

    /**
     * @return OrdersItemsQuantumManager
     */
    public function ordersItemsQuantum()
    {
        return $this->getManager(OrdersItemsQuantumManager::class);
    }

    /**
     * @return OrdersItemsStatusesManager
     */
    public function ordersItemsStatuses()
    {
        return $this->getManager(OrdersItemsStatusesManager::class);
    }

    /**
     * @return OrdersItemsTypesManager
     */
    public function ordersItemsTypes()
    {
        return $this->getManager(OrdersItemsTypesManager::class);
    }

    /**
     * @return PaymentsManager
     */
    public function payments()
    {
        return $this->getManager(PaymentsManager::class);
    }

    /**
     * @return PaymentsStatusesManager
     */
    public function paymentsStatuses()
    {
        return $this->getManager(PaymentsStatusesManager::class);
    }

    /**
     * @return PaymentsInvoicesManager
     */
    public function paymentsInvoices()
    {
        return $this->getManager(PaymentsInvoicesManager::class);
    }

    /**
     * @return PaymentsInvoicesTransactionsManager
     */
    public function paymentsInvoicesTransactions()
    {
        return $this->getManager(PaymentsInvoicesTransactionsManager::class);
    }

    /**
     * @return InvoicesTransactionsTypesManager
     */
    public function invoicesTransactionsTypes()
    {
        return $this->getManager(InvoicesTransactionsTypesManager::class);
    }

    /**
     * @return PaymentsServicesManager
     */
    public function paymentsServices()
    {
        return $this->getManager(PaymentsServicesManager::class);
    }

    /**
     * @return PaymentsFeesInvoicesManager
     */
    public function paymentsFeesInvoices()
    {
        return $this->getManager(PaymentsFeesInvoicesManager::class);
    }

    /**
     * @return PaymentsFeesInvoicesTransactionsManager
     */
    public function paymentsFeesInvoicesTransactions()
    {
        return $this->getManager(PaymentsFeesInvoicesTransactionsManager::class);
    }

    /**
     * @return IncomeManager
     */
    public function income()
    {
        return $this->getManager(IncomeManager::class);
    }

    /**
     * @return IncomeTypesManager
     */
    public function incomeTypes()
    {
        return $this->getManager(IncomeTypesManager::class);
    }

    /**
     * @return IncomeStatusesManager
     */
    public function incomeStatuses()
    {
        return $this->getManager(IncomeStatusesManager::class);
    }

    /**
     * @return IncomeContentSummaryManager
     */
    public function incomeContentSummary()
    {
        return $this->getManager(IncomeContentSummaryManager::class);
    }

    /**
     * @return PayoutsManager
     */
    public function payouts()
    {
        return $this->getManager(PayoutsManager::class);
    }

    /**
     * @return PayoutsServicesManager
     */
    public function payoutsServices()
    {
        return $this->getManager(PayoutsServicesManager::class);
    }

    /** @return PayoutsServicesTokensManager */
    public function payoutsServicesTokens()
    {
        return $this->getManager(PayoutsServicesTokensManager::class);
    }

    /** @return PayoutsServicesTokensHistoryManager */
    public function payoutsServicesTokensHistory()
    {
        return $this->getManager(PayoutsServicesTokensHistoryManager::class);
    }

    /**
     * @return PayoutsStatusesManager
     */
    public function payoutsStatuses()
    {
        return $this->getManager(PayoutsStatusesManager::class);
    }

    /**
     * @return PayoutsFeesInvoicesManager
     */
    public function payoutsFeesInvoices()
    {
        return $this->getManager(PayoutsFeesInvoicesManager::class);
    }

    /**
     * @return PayoutsFeesInvoicesTransactionsManager
     */
    public function payoutsFeesInvoicesTransactions()
    {
        return $this->getManager(PayoutsFeesInvoicesTransactionsManager::class);
    }

    /**
     * @return PayoutsInvoicesManager
     */
    public function payoutsInvoices()
    {
        return $this->getManager(PayoutsInvoicesManager::class);
    }

    /**
     * @return PayoutsInvoicesTransactionsManager
     */
    public function payoutsInvoicesTransactions()
    {
        return $this->getManager(PayoutsInvoicesTransactionsManager::class);
    }


    /**
     * @return OwnerPaymentsServicesManager
     */
    public function ownersPaymentsServices()
    {
        return $this->getManager(OwnerPaymentsServicesManager::class);
    }

    /**
     * @return OwnersPaymentsServicesTokensManager
     */
    public function ownersPaymentsServicesTokens()
    {
        return $this->getManager(OwnersPaymentsServicesTokensManager::class);
    }

    /**
     * @return OwnersPaymentsServicesTokensLogsManager
     */
    public function ownersPaymentsServicesTokensLogs()
    {
        return $this->getManager(OwnersPaymentsServicesTokensLogsManager::class);
    }

    /**
     * @return AccountingStatusesManager
     */
    public function accountingStatuses()
    {
        return $this->getManager(AccountingStatusesManager::class);
    }

    /**
     * @return ShortUrlsManager
     */
    public function shortUrls()
    {
        return $this->getManager(ShortUrlsManager::class);
    }


    /**
     * @return ImagesAssetsManager
     */
    public function imagesAssets()
    {
        return $this->getManager(ImagesAssetsManager::class);
    }

    /**
     * @return ImagesManager
     */
    public function images()
    {
        return $this->getManager(ImagesManager::class);
    }

    /**
     * @return ImagesTypesManager
     */
    public function imagesTypes()
    {
        return $this->getManager(ImagesTypesManager::class);
    }

    /**
     * @return ImagesTypesSizesManager
     */
    public function imagesTypesSizes()
    {
        return $this->getManager(ImagesTypesSizesManager::class);
    }

    /**
     * @return SSOServicesManager
     */
    public function ssoServices()
    {
        return $this->getManager(SSOServicesManager::class);
    }

    /**
     * @return UsersSSOServicesManager
     */
    public function userSSOServices()
    {
        return $this->getManager(UsersSSOServicesManager::class);
    }

    /**
     * @return ServicesAccessTokensManager
     */
    public function servicesAccessTokens()
    {
        return $this->getManager(ServicesAccessTokensManager::class);
    }

    /**
     * @return ServicesAccessTokensTypesManager
     */
    public function servicesAccessTokensTypes()
    {
        return $this->getManager(ServicesAccessTokensTypesManager::class);
    }

    /**
     * @return ServicesAccessTokensTypesGroupsManager
     */
    public function servicesAccessTokensTypesGroups()
    {
        return $this->getManager(ServicesAccessTokensTypesGroupsManager::class);
    }

    /**
     * @return ServicesAccessTokensTypesCategoriesManager
     */
    public function servicesAccessTokensTypesCategories()
    {
        return $this->getManager(ServicesAccessTokensTypesCategoriesManager::class);
    }

    /**
     * @return ServicesAccessTokensInstancesManager
     */
    public function servicesAccessTokensInstances()
    {
        return $this->getManager(ServicesAccessTokensInstancesManager::class);
    }

    /**
     * @return DateRangesManager
     */
    public function dateRanges()
    {
        return $this->getManager(DateRangesManager::class);
    }

    /**
     * @return BaseEntityManager
     */
    public function dateRangesXDayDateRanges()
    {
        return $this->getManager(DateRangesXDateRangesManager::class);
    }

    /**
     * @return DateRangesTypesManager
     */
    public function dateRangesTypes()
    {
        return $this->getManager(DateRangesTypesManager::class);
    }

    /**
     * @return KpiSummariesManager
     */
    public function kpiSummaries()
    {
        return $this->getManager(KpiSummariesManager::class);
    }

    /**
     * @return KpiSummariesTypesManager
     */
    public function kpiSummariesTypes()
    {
        return $this->getManager(KpiSummariesTypesManager::class);
    }

    /**
     * @return ActivationsManager
     */
    public function activations()
    {
        return $this->getManager(ActivationsManager::class);
    }

    /**
     * @return ActivationsGroupsManager
     */
    public function activationsGroups()
    {
        return $this->getManager(ActivationsGroupsManager::class);
    }

    /**
     * @return ActivationsTypesManager
     */
    public function activationsTypes()
    {
        return $this->getManager(ActivationsTypesManager::class);
    }

    /**
     * @return ActivationsStatusesManager
     */
    public function activationsStatuses()
    {
        return $this->getManager(ActivationsStatusesManager::class);
    }

    /**
     * @return CoinsAwardsTypesManager
     */
    public function coinAwardTypes()
    {
        return $this->getManager(CoinsAwardsTypesManager::class);
    }

    /**
     * @return UserCoinsManager
     */
    public function userCoins()
    {
        return $this->getManager(UserCoinsManager::class);
    }

    /**
     * @return GuestCoinsManager
     */
    public function guestCoins()
    {
        return $this->getManager(GuestCoinsManager::class);
    }

}