<?php
/**
 * Database Tables
 * Allows to change table names easily
 * Allows to add a prefix to all tables easily.
 *
 */

// Used in places that generate table names
// like comments
define('SQL_PREFIX', '');

final class Table
{
    const Settings = 'setting';
    const Vars = 'var';
    const Logs = 'log';
    const Tasks = 'task';
    const TasksHistory = 'task_history';
    const FlashMessages = 'flash_message';
    const AdminLog = 'admin_log';
    const Uploads = 'upload';
    const Languages = 'language';
    const Countries = 'country';
    const GeoRegions = 'geo_region';
    const i18n = 'phrase';
    const i18nLangs = 'phrases_langs';
    const i18nSuggestions = 'phrases_suggestions';
    const GeoIpMap = 'geo_ip_map';
    const Currencies = 'currencies';

    // Users
    const Rights = 'right';
    const RightsGroups = 'right_group';
    const GroupRights = 'usergroup_right';
    const Users = 'esc_user';
    const UsersProfiles = 'user_profile';
    const UserMeta = 'user_meta';
    const UserGroups = 'usergroup';
    const UsersUserGroups = 'user_usergroup';
    const LoginAttempts = 'login_attempt';
    const PasswordResetAttempts = 'password_reset_attempt';

    // Email
    const EmailTypes = 'email_type';
    const EmailHistory = 'email_history';
    const EmailTracking = 'email_tracking';
    const EmailSettingsGroups = 'email_setting_group';
    const EmailSettings = 'email_setting';
    const EmailSettingsHistory = 'email_setting_history';

    // Tracking
    const ActivityTypes = 'activity_type';
    const ActivityTracking = 'activity'; // Activity Tracking
    const GuestTracking = 'guest'; // Guest ID / Hash Tracking
    const SessionTracking = 'session'; // Session Hash Tracking
    const BotGuestTracking = 'bot_guest'; // Guest ID / Hash Tracking
    const BotSessionTracking = 'bot_session'; // Session Hash Tracking
    const BotRequest = 'bot_request';
    const Request = 'request';
    const SentEmail = 'sent_email';

    const Addresses = 'address';
    const AddressType = 'address_type';

    // Venues
    const Host = 'host';
    const Location = 'location';
    const Screen = 'screen';
    const Network = 'network';
    const HostDevice = 'host_device';
    const HostDeviceComponent = 'host_device_component';

    // Organizations
    const Organization = 'organization';
    const OrganizationActivity = 'organization_activity';
    const OrganizationMeta = 'organization_meta';
    const OrganizationType = 'organization_type';
    const OrganizationBaseRole = 'organization_base_role';
    const OrganizationBaseRight = 'organization_base_right';
    const OrganizationBasePermission = 'organization_base_permission';
    const OrganizationRole = 'organization_role';
    const OrganizationUser = 'organization_user';
    const OrganizationPermission = 'organization_permission';
    const OrganizationRight = 'organization_right';
    const OrganizationGameLicense = 'organization_game_license';
    const OrganizationGameModLicense = 'organization_game_mod_license';
    const OrganizationUserStatus = 'organization_user_status';
    const OrganizationUserInvite = 'organization_user_invite';

    // Platforms
    const Platform = 'platform';
    const PlatformVersion = 'platform_version';

    // SDK Platforms
    const SdkPlatform = 'sdk_platform';
    const SdkPlatformVersion = 'sdk_platform_version';

    // Games
    const Game = 'game';
    const GameBuild = 'game_build';
    const GameActiveBuild = 'game_active_build';
    const GameType = 'game_type';
    const GameEngine = 'game_engine';
    const GameCategory = 'game_category';
    const GameController = 'game_controller';
    const GameControllerType = 'game_controller_type';
    const GameBuildController = 'game_build_controller';
    const GameAsset = 'game_asset';
    const GameActiveCustomAsset = 'game_active_custom_asset';
    const GameXPlatform = 'game_x_platform';
    const ContextXGameAsset = 'context_x_game_asset';
    const GameInstance = 'game_instance';
    const GameInstanceRound = 'game_instance_round';
    const GameInstanceRoundPlayer = 'game_instance_round_player';
    const GameInstanceRoundEvent = 'game_instance_round_event';
    const GameInstanceRoundEventProperty = 'game_instance_round_event_property';
    const GameInstanceLog = 'game_instance_log';
    const GameInstanceLogStatus = 'game_instance_log_status';
    const GameLicense = 'game_x_user';

    // Game Data
    const GameData = 'game_data';
    const GamePlayerStat = 'game_player_stat';
    const GamePlayerStatType = 'game_player_stat_type';
    const GameDataSheet = 'game_data_sheet';
    const GameDataSheetColumn = 'game_data_sheet_column';
    const GameDataSheetModType = 'game_data_sheet_mod_type';
    const GameDataSheetRow = 'game_data_sheet_row';

    // Games Mods
    const GameMod = 'game_mod';
    const GameModBuild = 'game_mod_build';
    const GameModData = 'game_mod_data';
    const GameModDataSheet = 'game_mod_data_sheet';
    const GameModDataSheetColumn = 'game_mod_data_sheet_column';
    const GameModDataSheetRow = 'game_mod_data_sheet_row';
    const GameModActiveCustomAsset = 'game_mod_active_custom_asset';
    const GameModActiveBuild = 'game_mod_active_build';
    const GameModLicense = 'game_mod_license';

    // SDK Assets
    const SdkAsset = 'sdk_asset';
    const SdkBuild = 'sdk_build';
    const SdkBuildActive = 'sdk_build_active';
    const SdkVersion = 'sdk_version';
    const SdkVersionPlatformChannel = 'sdk_version_platform_channel';
    const ContextXSdkAsset = 'context_x_sdk_asset';


    // Host Assets
    const HostAsset = 'host_asset';
    const HostBuild = 'host_build';
    const HostBuildActive = 'host_build_active';
    const HostVersion = 'host_version';
    const HostController = 'host_controller';
    const HostVersionPlatformChannel = 'host_version_platform_channel';
    const ContextXHostAsset = 'context_x_host_asset';

    // Host Instances
    const HostInstance = 'host_instance';
    const HostInstanceType = 'host_instance_type';
    const HostInstanceDevice = 'host_instance_device';
    const HostInstanceInvite = 'host_instance_invite';
    const HostInstanceInviteType = 'host_instance_invite_type';

    // Device Tracking
    const Device = 'device';
    const DeviceType = 'device_type';
    const ClientSession = 'client_session';
    const ApiLog = 'api_log';

    // SMS
    const Sms = 'sms';
    const SmsType = 'sms_type';

    // Applications
    const Application = 'application';
    const ApplicationUser = 'application_user';
    const ApplicationUserAccessToken = 'application_user_access_token';

    // SSO
    const SSOService = 'sso_service';
    const UserSSOService = 'user_sso_service';


    // Invoice Transactions
    const InvoiceTransactionType = 'invoice_transaction_type';

    // Payments
    const Payment = 'payment';
    const PaymentStatus = 'payment_status';
    const PaymentService = 'payment_service';
    const PaymentInvoice = 'payment_invoice';
    const PaymentInvoiceTransaction = 'payment_invoice_transaction';
    const PaymentFeeInvoice = 'payment_fee_invoice';
    const PaymentFeeInvoiceTransaction = 'payment_fee_invoice_transaction';

    // Users Payments
    const OwnerPaymentService = 'owner_payment_service';
    const OwnerPaymentServiceToken = 'owner_payment_service_token';
    const OwnerPaymentServiceTokenLog = 'owner_payment_service_token_log';

    // Payouts
    const Payout = 'payout';
    const PayoutService = 'payout_service';
    const PayoutServiceToken = 'payout_service_token';
    const PayoutServiceTokenHistory = 'payout_service_token_history';
    const PayoutStatus = 'payout_status';
    const PayoutInvoice = 'payout_invoice';
    const PayoutInvoiceTransaction = 'payout_invoice_transaction';
    const PayoutFeeInvoice = 'payout_fee_invoice';
    const PayoutFeeInvoiceTransaction = 'payout_fee_invoice_transaction';

    // Income
    const Income = 'income';
    const IncomeType = 'income_type';
    const IncomeStatus = 'income_status';
    const IncomeContentSummary = 'income_content_summary';

    // Accounting
    const AccountingStatus = 'accounting_status';

    // Orders
    const Order = 'order';
    const OrderStatus = 'order_status';
    const OrderItem = 'order_item';
    const OrderItemQuantum = 'order_item_quantum';
    const OrderItemStatus = 'order_item_status';
    const OrderItemType = 'order_item_type';

    // Short Urls
    const ShortUrl = 'short_url';

    // Images
    const ImageAsset = 'image_asset';
    const Image = 'image';
    const ImageType = 'image_type';
    const ImageTypeSize = 'image_type_size';

    const ServiceAccessToken = 'service_access_token';
    const ServiceAccessTokenType = 'service_access_token_type';
    const ServiceAccessTokenTypeGroup = 'service_access_token_type_group';
    const ServiceAccessTokenTypeCategory = 'service_access_token_type_category';
    const ServiceAccessTokenInstance = 'service_access_token_instance';


    // Stats
    const KpiSummary = 'kpi_summary';
    const KpiSummaryType = 'kpi_summary_type';

    // Activations
    const Activation = 'activation';
    const ActivationGroup = 'activation_group';
    const ActivationType = 'activation_type';
    const ActivationStatus = 'activation_status';

    // Coins
    const CoinAwardType = 'coin_award_type';
    const UserCoin = 'user_coin';
    const GuestCoin = 'guest_coin';

    // Incentives
    const Incentive = 'incentive';
    const IncentiveType = 'incentive_type';
    const IncentiveInstance = 'incentive_instance';

}

final class TableAlias
{
    const Settings = 's';
    const Styles = 'st';
    const Vars = 'v';
    const Logs = 'lo';
    const Tasks = 't';
    const TasksHistory = 'th';
    const FlashMessages = 'm';
    const AdminLog = 'al';
    const Uploads = 'u';
    const Langs = 'l';
    const Countries = 'cn';
    const Currencies = 'cu';
    const GeoRegions = 'gr';
    const i18n = 'ph';
    const i18nLangs = 'phl';
    const i18nSuggestions = 'phs';
    const GeoData = 'gd';
    const GeoIpMap = 'gim';

    // Users
    const Rights = 'r';
    const RightsGroups = 'rg';
    const GroupRights = 'ugr';
    const Users = 'eu';
    const UsersProfiles = 'up';
    const UserMeta = 'um';
    const UserGroups = 'ug';
    const UsersUserGroups = 'uug';
    const LoginAttempts = 'la';
    const PasswordResetAttempts = 'pra';

    // Email
    const EmailTypes = 'et';
    const EmailHistory = 'eh';
    const EmailTracking = 'e';
    const EmailSettingsGroups = 'esg';
    const EmailSettings = 'ues';
    const EmailSettingsHistory = 'uesh';

    // Tracking
    const ActivityTypes = 'actt';
    const ActivityTracking = 'act'; // Activity Tracking
    const GuestTracking = 'g'; // Guest ID / Hash Tracking
    const SessionTracking = 's'; // Session Hash Tracking
    const BotGuestTracking = 'bg'; // Guest ID / Hash Tracking
    const BotSessionTracking = 'bs'; // Session Hash Tracking
    const SentEmail = 'se';
    const GlobalSearchTracking = 'usqh';

    const Addresses = 'ad';
    const AddressType = 'adt';

    // Venues
    const Host = 'ho';
    const Location = 'loc';
    const Screen = 'scr';
    const Network = 'net';
    const HostDevice = 'hod';
    const HostDeviceComponent = 'hodc';


    // Games
    const Game = 'ga';
    const GameBuild = 'gb';
    const GameActiveBuild = 'gab';
    const GameCategory = 'gca';
    const GameEngine = 'ge';
    const GameType = 'gt';

    const Platform = 'gp';
    const PlatformVersion = 'gpv';


    const SdkPlatform = 'sgp';
    const SdkPlatformVersion = 'sgpv';

    const GameController = 'gc';
    const GameControllerType = 'gct';
    const GameBuildController = 'gbc';
    const GameAsset = 'ga';
    const GameActiveCustomAsset = 'gaca';
    const GameXPlatform = 'gxp';
    const GameControllerXGameAsset = 'gcxga';

    const ContextXGameAsset = 'cxga';
    const ContextXHostAsset = 'cxha';

    const GameInstance = 'gai';
    const GameInstanceRound = 'gair';
    const GameInstanceRoundPlayer = 'girp';
    const GameInstanceRoundEvent = 'gire';
    const GameInstanceRoundEventProperty = 'girep';
    const GameInstanceLog = 'gil';
    const GameInstanceLogStatus = 'gils';

    const GameLicense = 'gxu';


    // Game Data
    const GameData = 'gd';
    const GameDataSheet = 'gds';
    const GameDataSheetColumn = 'gdsc';
    const GameDataSheetModType = 'gdsmt';
    const GameDataSheetRow = 'gdsr';

    // Games Mods
    const GameMod = 'gm';
    const GameModBuild = 'gmb';
    const GameModData = 'gmd';
    const GameModDataSheet = 'gmds';
    const GameModDataSheetColumn = 'gmdsc';
    const GameModDataSheetRow = 'gmdsr';
    const GameModActiveBuild = 'gmab';
    const GameModActiveCustomAsset = 'gmaca';
    const GameModLicense = 'gml';


    const GamePlayerStat = 'gps';
    const GamePlayerStatType = 'gpst';

    // SDK Assets
    const SdkAsset = 'sa';
    const SdkBuild = 'sb';
    const SdkBuildActive = 'sba';
    const SdkVersion = 'sv';
    const SdkVersionPlatformChannel = 'svpc';
    
    // Host Assets
    const HostAsset = 'haa';
    const HostBuild = 'hb';
    const HostBuildActive = 'hba';
    const HostVersion = 'hv';
    const HostController = 'hc';
    const HostVersionPlatformChannel = 'hvpc';

    // Host Instances
    const HostInstance = 'hi';
    const HostInstanceType = 'hit';
    const HostInstanceDevice = 'hid';
    const HostInstanceInvite = 'hii';
    const HostInstanceInviteType = 'hiit';

    // Organizations
    const Organization = 'org';
    const OrganizationActivity = 'orga';
    const OrganizationMeta = 'orgm';
    const OrganizationType = 'orgt';
    const OrganizationBaseRole = 'orgbr';
    const OrganizationBaseRight = 'orgbri';
    const OrganizationBasePermission = 'orgbp';
    const OrganizationRole = 'orgr';
    const OrganizationRight = 'orgri';
    const OrganizationUser = 'orgu';
    const OrganizationPermission = 'orgp';
    const OrganizationGameLicense = 'orgl';
    const OrganizationGameModLicense = 'orgml';
    const OrganizationUserStatus = 'orgus';
    const OrganizationUserInvite = 'orgui';


    // Apps
    const Application = 'app';
    const ApplicationUser = 'appu';
    const ApplicationUserAccessToken = 'appuat';

    // SSO
    const SSOService = 'sso';
    const UserSSOService = 'usso';

    // Device Tracking
    const Device = 'dev';
    const DeviceType = 'devt';
    const ClientSession = 'cs';
    const Request = 'req';
    const ApiLog = 'al';

    // SMS
    const Sms = 'sms';
    const SmsType = 'smst';



    // Invoice Transactions
    const InvoiceTransactionType = 'itt';

    // Payments
    const Payment = 'p';
    const PaymentStatus = 'pst';
    const PaymentService = 'ps';
    const PaymentInvoice = 'pi';
    const PaymentInvoiceTransaction = 'pit';
    const PaymentFeeInvoice = 'pfi';
    const PaymentFeeInvoiceTransaction = 'pfit';

    // Users Payments
    const OwnerPaymentService = 'ops';
    const OwnerPaymentServiceToken = 'opst';
    const OwnerPaymentServiceTokenLog = 'opstl';

    // Payouts
    const Payout = 'po';
    const PayoutService = 'pos';
    const PayoutServiceToken = 'postk';
    const PayoutServiceTokenHistory = 'postkh';
    const PayoutStatus = 'post';
    const PayoutInvoice = 'poi';
    const PayoutInvoiceTransaction = 'poit';
    const PayoutFeeInvoice = 'pofi';
    const PayoutFeeInvoiceTransaction = 'pofit';

    // Income
    const Income = 'i';
    const IncomeType = 'it';
    const IncomeStatus = 'is';
    const IncomeContentSummary = 'ics';

    // Accounting
    const AccountingStatus = 'acs';

    // Orders
    const Order = 'o';
    const OrderStatus = 'os';
    const OrderItem = 'oi';
    const OrderItemQuantum = 'oiq';
    const OrderItemStatus = 'ois';
    const OrderItemType = 'oit';

    // Short Urls
    const ShortUrl = 'su';

    const ImageAsset = 'imga';
    const Image = 'img';
    const ImageType = 'imgt';
    const ImageTypeSize = 'imgts';


    const ServiceAccessToken = 'sat';
    const ServiceAccessTokenType = 'satt';
    const ServiceAccessTokenTypeGroup = 'sattg';
    const ServiceAccessTokenTypeCategory = 'sattc';
    const ServiceAccessTokenInstance = 'sati';

    // Stats
    const KpiSummary = 'ks';
    const KpiSummaryType = 'kst';

    // Activations
    const Activation = 'act';
    const ActivationGroup = 'actg';
    const ActivationType = 'actt';
    const ActivationStatus = 'acts';

    // Coins
    const CoinAwardType = 'cat';
    const UserCoin = 'uc';
    const GuestCoin = 'gc';

    // Incentive
    const Incentive = 'inc';
    const IncentiveType = 'inct';
    const IncentiveInstance = 'inci';

}



final class EntityType
{
    const USER = 1;
    const GAME = 2;
    const GAME_BUILD = 3;
    const GAME_CONTROLLER = 4;
    const HOST_BUILD = 5;
    const HOST_CONTROLLER = 6;
    const ORGANIZATION = 7;
    const ORDER = 8;
    const ORDER_ITEM = 9;
    const INCOME = 10;
    const INCOME_CONTENT = 11;
    const SERVICE_ACCESS_TOKEN = 12;
    const SERVICE_ACCESS_TOKEN_INSTANCE = 13;
    const LOCATION = 14;
    const HOST = 15;
    const HOST_INSTANCE = 16;
    const CUSTOM_GAME_ASSET = 17;
    const GAME_MOD = 18;
    const GAME_MOD_BUILD = 19;
    const GAME_INSTANCE_LOG = 20;
    const GAME_DATA_SHEET_ROW = 21;
    const GAME_MOD_DATA_SHEET_ROW = 22;
    const SDK_BUILD = 23;
}

/**
 * Entity DB Fields - Normalized across all tables.
 */
abstract class BaseDataFields
{
    // Generic Field Names
    const ID = 'id';
    const MD5 = 'md5';
    const SHA512 = 'sha512';
    const NAME = 'name';
    const PASSWORD = 'password';
    const PASSWORD_CONFIRM = 'password_confirm';
    const UPLOAD_ID = 'upload_id';
    const FILENAME = 'filename';
    const COMPUTED_FILENAME = 'computed_filename';
    const FILE_SIZE = 'file_size';
    const EXTENSION = 'extension';
    const MIME_TYPE =  'mime_type';
    const FOLDER_PATH = 'folder_path';
    const BUCKET = 'bucket';
    const BUCKET_PATH = 'bucket_path';
    const SLUG = 'slug';
    const TITLE = 'title';
    const TYPE = 'type';
    const LANGUAGE_ID = 'language_id';
    const PHRASE_ID = 'phrase_id';
    const TEXT = 'text';
    const VALUE = 'value';
    const ORIG_LANG = 'orig_lang';
    const TIMEZONE_OFFSET = 'timezone_offset';
    const CONTEXT = 'context';
    const TASK_ID = 'task_id';
    const TASK_HISTORY_ID = 'task_history_id';
    const ERROR_CODE = 'error_code';
    const ERROR_MSG = 'error_msg';
    const ERROR_MESSAGE = 'error_message';
    const STACK_TRACE = 'stack_trace';
    const DESCRIPTION = 'description';
    const GEO_REGION_ID = 'geo_region_id';
    const CURRENCY_ID = 'currency_id';
    const DEFAULT_LANGUAGE_ID = 'default_language_id';
    const UI_LANGUAGE_ID = 'ui_language_id';
    const PLURAL_NAME = 'plural_name';
    const CHECKSUM = 'checksum';
    const FAILED = 'failed';
    const RUNNING = 'running';
    const PRIORITY = 'priority';


    // Generic Objects
    const OBJECT_SOURCE_ID = 'object_source_id';
    const OBJECT_SOURCE_TITLE = 'object_source_title';
    const OBJECT_SOURCE_SLUG = 'object_source_slug';
    const OBJECT_ID = 'object_id';
    const OBJECT_LANG = 'object_lang';
    const OBJECT_TYPE = 'object_type';
    const ENTITY_TYPE = 'entity_type';
    const DATA = 'data';
    const OWNER_USER_ID = 'owner_user_id';

    const CREATOR_USER_UI_LANG = 'creator_user_ui_lang';
    const FILE = 'file';
    const FUNC = 'func';
    const ARGS = 'args';

    // Tracking
    const ACTIVITY_ID = 'activity_id';
    const ACTIVITY_TYPE_ID = 'activity_type_id';
    const GUEST_ID = 'guest_id';
    const GUEST_HASH = 'guest_hash';
    const SESSION_ID = 'session_id';
    const SESSION_HASH = 'session_hash';
    const COUNTRY = 'country';
    const COUNTRY_ID = 'country_id';
    const ISO3 = 'iso3';
    const PHONE_CODE = 'phone_code';
    const GEO_IP_MAP_ID = 'geo_ip_map_id';
    const IP_FROM = 'ip_from';
    const IP_TO = 'ip_to';
    const START_IP = 'start_ip';
    const DEVICE_TYPE_ID = 'device_type_id';
    const FIRST_USER_ID = 'first_user_id';
    const FIRST_SESSION_HASH = 'first_session_hash';
    const FIRST_GUEST_ID = 'first_guest_id';
    const ORIGINAL_REFERRER = 'original_ref';
    const ORIGINAL_URL = 'original_uri';
    const ET_ID = 'et_id';
    const SENT_TIME = 'sent_time';
    const IP = 'ip';
    const HTTP_USER_AGENT = 'http_user_agent';
    const IS_BOT = 'is_bot';
    const USER_LANG = 'user_lang';
    const QUERY = 'query';
    const QUERY_ID = 'query_id';
    const REF = 'ref';

    // Image Revision Counters
    const PICTURE_REVISION = 'picture_revision';
    const BANNER_REVISION = 'banner_revision';

    // Content Specific
    const ENTITY_ID = 'entity_id';
    const CONTEXT_ENTITY_ID = 'context_entity_id';
    const CONTEXT_ENTITY_TYPE_ID = 'context_entity_type_id';
    const DELETER_ID = 'deleter_id';
    const CREATOR_ID = 'creator_id';
    const UPDATER_ID = 'updater_id';
    const POSTER_ID = 'poster_id';
    const CREATOR_USER_ID = 'creator_user_id';
    const MODERATOR_ID = 'moderator_id';


    // Dates
    const POST_DATE = 'post_date';
    const DATEHOUR = 'datehour';

    const CREATE_TIME = 'create_time';
    const DELETE_TIME = 'delete_time';
    const EDIT_DATE = 'edit_date';
    const DELETED_DATE = 'deleted_date';
    const DELETE_DATE = 'delete_date';
    const OPENED_DATE = 'opened_date';
    const OPENED_TIME = 'opened_time';
    const PUBLISHED_DATE = 'published_date';
    const PUBLISHED_TIME = 'published_time';
    const UPDATE_DATE = 'update_date';
    const UPDATE_TIME = 'update_time';
    const APPROVAL_DATE = 'approval_date';
    const JOIN_DATE = 'join_date';
    const LAST_LOGIN = 'last_login';
    const CLICKED_TIME = 'clicked_time';
    const START_TIME = 'start_time';
    const END_TIME = 'end_time';

    // Boolean Fields
    const I18N_ACTIVE = 'i18n_active';
    const I18N_PUBLIC = 'i18n_public';
    const IS_VISIBLE = 'is_visible';
    const IS_CLICKED = 'is_clicked';
    const IS_OPENED = 'is_opened';
    const IS_DELETED = 'is_deleted';
    const IS_OPEN = 'is_open';
    const IS_MODERATED = 'is_moderated';
    const IS_ACTIVE = 'is_active';
    const IS_PUBLIC = 'is_public';
    const HAS_PICTURE = 'has_picture';
    const IS_VERIFIED = 'is_verified';
    const DNS_IS_ACTIVE = 'dns_is_active';
    const IS_DOWNLOADABLE = 'is_downloadable';
    const IS_WAN_ENABLED = 'is_wan_enabled';
    const IS_AGGREGATE_GAME = 'is_aggregate_game';

    // User Specific
    const USER_ID = 'user_id';
    const USER_META_ID = 'user_meta_id';
    const USERNAME = 'username';
    const USERGROUP_ID = 'usergroup_id';
    const USER_USERGROUP_ID = 'user_usergroup_id';
    const DISPLAY_NAME = 'display_name';
    const FIRSTNAME = 'firstname';
    const FIRST_NAME = 'first_name';
    const LASTNAME = 'lastname';
    const LAST_NAME = 'last_name';
    const FULL_NAME = 'full_name';
    const GENDER = 'gender';
    const GENDER_ID = 'gender_id';
    const HAS_BETA_ACCESS = 'has_beta_access';
    const TOTAL_ORDERS = 'total_orders';
    const TOTAL_GAMES = 'total_games';
    const TOTAL_MEMBERS = 'total_members';
    const TOTAL_TIME_PLAYED = 'total_time_played';
    const FIRST_SESSION_ID = 'first_session_id';
    const RIGHT_ID = 'right_id';
    const RIGHT_GROUP_ID = 'right_group_id';
    const PARENT_RIGHT_ID = 'parent_right_id';
    const ACCESS_LEVEL = 'access_level';
    const SALT = 'salt';
    const EMAIL = 'email';
    const EMAIL_ADDRESS = 'email_address';
    const EMAIL_TITLE = 'email_title';
    const EMAIL_BODY = 'email_body';
    const PASSWORD_HASH = 'password_hash';
    const FROM = 'from';
    const DEST = 'dest';
    const HTML = 'html';
    const EMAIL_OPEN_GUEST_ID = 'email_open_guest_id';
    const USED = 'used';
    const BIRTHDAY = 'birthday';
    const FBP_URL = 'fbp_url';
    const ALTERNATE_URL = 'alternate_url';
    const HIDE_ADS = 'hide_ads';
    const LOCALE = 'locale';
    const DEFAULT_SETTING = 'default_setting';
    const SETTING_GROUP_ID = 'setting_group_id';
    const SETTING = 'setting';
    const EMAIL_TYPE_ID = 'email_type_id';
    const EMAIL_TRACKING_ID = 'email_tracking_id';
    const SENDER = 'sender';
    const BODY = 'body';
    const BETA_ACCESS = 'beta_access';
    const OWNER_ID = 'owner_id';
    const OWNER_TYPE_ID = 'owner_type_id';
    const UUID = 'uuid';

    // Addresses
    const ADDRESS_ID = 'address_id';
    const ADDRESS_STAMP_ID = 'address_stamp_id';
    const ADDRESS_TYPE_ID = 'address_type_id';
    const IS_PRIMARY = 'is_primary';
    const PHONE_NUMBER = 'phone_number';
    const ADDRESS_LINE1 = 'address_line1';
    const ADDRESS_LINE2 = 'address_line2';
    const ADDRESS_LINE3 = 'address_line3';
    const CITY = 'city';
    const STATE = 'state';
    const STATE_CODE = 'state_code';
    const STREET_NAME = 'street_name';
    const STREET_NUMBER = 'street_number';
    const ZIP = 'zip';
    const ZIP_CODE = 'zip_code';
    const POSTAL_CODE_LOW = 'postal_code_low';
    const REGION_NAME = 'region_name';
    const CITY_NAME = 'city_name';
    const LATITUDE = 'latitude';
    const LONGITUDE = 'longitude';
    const TIME_ZONE = 'time_zone';

    const CONTENT = 'content';
    const OPTIONS = 'options';



    /*
     * NEW 06/02/2018
     */

    // Request Tracking Audit Fields
    const CREATED_BY = 'created_by';
    const MODIFIED_BY = 'modified_by';
    const DELETED_BY = 'deleted_by';

    // Email
    const EMAIL_SETTING_GROUP_ID = 'email_setting_group_id';
    const EMAIL_SETTING_ID = 'email_setting_id';
    const EMAIL_SETTING_HISTORY_ID = 'email_setting_history_id';
    const EMAIL_HISTORY_ID = 'email_history_id';
    
    // New Tracking
    const REQUEST_ID = 'request_id';
    const IS_FIRST_SESSION_OF_GUEST = 'is_first_session_of_guest';
    const CLIENT_SESSION_ID = 'client_session_id';
    const DEVICE_ID = 'device_id';
    const SCHEME = 'scheme';
    const METHOD = 'method';
    const HOST = 'host';
    const APP = 'app';
    const URI = 'uri';
    const PARAMS = 'params';
    const HEADERS = 'headers';
    const FILES = 'files';
    const RESPONSE = 'response';
    const REFERRER = 'referrer';
    const ACQ_MEDIUM = 'acq_medium';
    const ACQ_SOURCE = 'acq_source';
    const ACQ_CAMPAIGN = 'acq_campaign';
    const ACQ_TERM = 'acq_term';
    const RESPONSE_TIME = 'response_time';
    const RESPONSE_CODE = 'response_code';
    const API_LOG_ID = 'api_log_id';

    // Venues, Locations, Screens, Network
    const HOST_ID = 'host_id';
    const LOCATION_ID = 'location_id';
    const SCREEN_ID = 'screen_id';
    const NETWORK_ID = 'network_id';
    const IP_ADDRESS = 'ip_address';
    const DISPLAY_ORDER = 'display_order';
    const LOCATION_HASH = 'location_hash';
    const SSID = 'ssid';
    const URL = 'url';
    const HAS_CUSTOM_SLUG = 'has_custom_slug';
    const IS_PROD = 'is_prod';
    const OFFLINE_GAME_ID = 'offline_game_id';
    const OFFLINE_GAME_MOD_ID = 'offline_game_mod_id';
    const HOST_DEVICE_ID = 'host_device_id';
    const HOST_DEVICE_COMPONENT_ID = 'host_device_component_id';

    // Platforms
    const PLATFORM_ID = 'platform_id';
    const PLATFORM_VERSION_ID = 'platform_version_id';
    const PLATFORM_SLUG = 'platform_slug';

    // SDK Platforms
    const SDK_PLATFORM_ID = 'sdk_platform_id';
    const SDK_PLATFORM_VERSION_ID = 'sdk_platform_version_id';
    const SDK_PLATFORM_SLUG = 'sdk_platform_slug';

    // Games
    const GAME_ID = 'game_id';
    const GAME_BUILD_ID = 'game_build_id';
    const GAME_TYPE_ID = 'game_type_id';
    const GAME_CATEGORY_ID = 'game_category_id';
    const GAME_ENGINE_ID = 'game_engine_id';
    const GAME_BUILD_VERSION = 'game_build_version';
    const PUBLISHED_GAME_BUILD_ID = 'published_game_build_id';
    const GAME_ACTIVE_BUILD_ID = 'game_active_build_id';
    const GAME_BUILD_CONTROLLER_ID = 'game_build_controller_id';
    const GAME_CONTROLLER_VERSION = 'game_controller_version';
    const GAME_CONTROLLER_TYPE_ID = 'game_controller_type_id';
    const GAME_CONTROLLER_ID = 'game_controller_id';
    const GAME_ASSET_ID = 'game_asset_id';
    const GAME_ACTIVE_CUSTOM_ASSET_ID = 'game_active_custom_asset_id';
    const GAME_X_PLATFORM_ID = 'game_x_platform_id';
    const CONTEXT_X_GAME_ASSET_ID = 'context_x_game_asset_id';
    const CONTEXT_X_SDK_ASSET_ID = 'context_x_sdk_asset_id';
    const CONTEXT_X_HOST_ASSET_ID = 'context_x_host_asset_id';
    const VERSION_HASH = 'version_hash';
    const UPDATE_CHANNEL = 'update_channel';
    const GAME_PLAYER_STAT_ID = 'game_player_stat_id';
    const GAME_PLAYER_STAT_TYPE_ID = 'game_player_stat_type_id';

    const LATEST_GAME_BUILD_ID = 'latest_game_build_id';
    const ACTIVE_TEST_GAME_BUILD_ID = 'active_test_game_build_id';
    const THUMBNAIL_IMAGE_SUMMARIES = 'thumbnail_image_summaries';

    // GameInstances
    const GAME_INSTANCE_ID = 'game_instance_id';
    const GAME_INSTANCE_ROUND_ID = 'game_instance_round_id';
    const GAME_INSTANCE_ROUND_PLAYER_ID = 'game_instance_round_player_id';
    const GAME_INSTANCE_ROUND_EVENT_ID = 'game_instance_round_event_id';
    const GAME_INSTANCE_ROUND_EVENT_PROPERTY_ID = 'game_instance_round_event_property_id';
    const GAME_INSTANCE_LOG_ID = 'game_instance_log_id';
    const GAME_INSTANCE_LOG_STATUS_ID = 'game_instance_log_status_id';
    const PROCESSING_TIME = 'processing_time';
    const MESSAGE_COUNT = 'message_count';

    const LAST_PING_TIME = 'last_ping_time';
    const EXIT_STATUS = 'exit_status';
    const LOCAL_IP_ADDRESS = 'local_ip_address';
    const PUBLIC_IP_ADDRESS = 'public_ip_address';
    const PUBLIC_HOST_NAME = 'public_host_name';
    const PUBLIC_HOST_DOMAIN = 'public_host_domain';
    const LOCAL_PORT = 'local_port';
    const PUB_SUB_CHANNEL = 'pub_sub_channel';
    const PUB_SUB_CHANNEL_TYPE = 'pub_sub_channel_type';
    const DNS_ID = 'dns_id';
    const MINIMUM_PLAYERS = 'minimum_players';
    const MAXIMUM_PLAYERS = 'maximum_players';
    const PLAYER_REQUEST_ID = 'player_request_id';

    const EVENT_KEY = 'event_key';

    // Host Assets
    const HOST_ASSET_ID = 'host_asset_id';
    const HOST_BUILD_ID = 'host_build_id';
    const HOST_BUILD_ACTIVE_ID = 'host_build_active_id';
    const HOST_UPDATE_CHANNEL = 'host_update_channel';
    const IS_DEPRECATED = 'is_deprecated';
    const HOST_VERSION_ID = 'host_version_id';
    const MIN_HOST_VERSION_ID = 'min_host_version_id';
    const VERSION = 'version';
    const BUILD_VERSION = 'build_version';
    const HOST_VERSION = 'host_version';
    const HOST_CONTROLLER_ID = 'host_controller_id';
    const HOST_VERSION_PLATFORM_CHANNEL_ID = 'host_version_platform_channel';

    // SDK Assets
    const SDK_ASSET_ID = 'sdk_asset_id';
    const SDK_BUILD_ID = 'sdk_build_id';
    const SDK_BUILD_ACTIVE_ID = 'sdk_build_active_id';
    const SDK_UPDATE_CHANNEL = 'sdk_update_channel';
    const SDK_VERSION_ID = 'sdk_version_id';
    const MIN_SDK_VERSION_ID = 'min_sdk_version_id';
    const SDK_VERSION = 'sdk_version';
    const SDK_VERSION_PLATFORM_CHANNEL_ID = 'sdk_version_platform_channel';

    // Host Instances
    const HOST_INSTANCE_ID = 'host_instance_id';
    const HOST_INSTANCE_TYPE_ID = 'host_instance_type_id';
    const HOST_INSTANCE_INVITE_ID = 'host_instance_invite_id';
    const INVITE_HASH = 'invite_hash';
    const HOST_INSTANCE_INVITE_TYPE_ID = 'host_instance_invite_type_id';
    const INVITE_RECIPIENT = 'invite_recipient';
    const HOST_INSTANCE_DEVICE_ID = 'host_instance_device_id';
    const DEVICE_HASH = 'device_hash';

    // Game Data
    const GAME_DATA_ID = 'game_data_id';
    const GAME_DATA_SHEET_ID = 'game_data_sheet_id';
    const GAME_DATA_SHEET_COLUMN_ID = 'game_data_sheet_column_id';
    const GAME_DATA_SHEET_MOD_TYPE_ID = 'game_data_sheet_mod_type_id';
    const GAME_DATA_SHEET_ROW_ID = 'game_data_sheet_row_id';
    const CAN_MOD = 'can_mod';

    // Game Mods
    const GAME_MOD_ID = 'game_mod_id';
    const GAME_MOD_LICENSE_ID = 'game_mod_license_id';
    const GAME_MOD_BUILD_ID = 'game_mod_build_id';
    const PUBLISHED_GAME_MOD_BUILD_ID = 'published_game_mod_build_id';
    const FIRST_ACTIVE_GAME_BUILD_ID = 'first_active_game_build_id';
    const GAME_MOD_DATA_ID = 'game_mod_data_id';
    const GAME_MOD_DATA_SHEET_ID = 'game_mod_data_sheet_id';
    const GAME_MOD_DATA_SHEET_COLUMN_ID = 'game_mod_data_sheet_column_id';
    const GAME_MOD_DATA_SHEET_ROW_ID = 'game_mod_data_sheet_row_id';
    const GAME_MOD_ACTIVE_CUSTOM_ASSET_ID = 'game_mod_active_custom_asset_id';
    const GAME_MOD_ACTIVE_BUILD_ID = 'game_mod_active_build_id';

    const KEY = 'key';
    const INDEX_KEY = 'index_key';

    const GAME_LICENSE_ID = 'game_x_user_id';

    // Companies and Apps
    const ORGANIZATION_ID = 'organization_id';
    const ORGANIZATION_ACTIVITY_ID = 'organization_activity_id';
    const ORGANIZATION_META_ID = 'organization_meta_id';
    const ORGANIZATION_TYPE_ID = 'organization_type_id';
    const ORGANIZATION_ROLE_ID = 'organization_role_id';
    const ORGANIZATION_BASE_ROLE_ID = 'organization_base_role_id';
    const ORGANIZATION_BASE_RIGHT_ID = 'organization_base_right_id';
    const ORGANIZATION_BASE_PERMISSION_ID = 'organization_base_permission_id';
    const ORGANIZATION_USER_ID = 'organization_user_id';
    const ORGANIZATION_PERMISSION_ID = 'organization_permission_id';
    const ORGANIZATION_RIGHT_ID = 'organization_right_id';
    const ORGANIZATION_GAME_LICENSE_ID = 'organization_game_license_id';
    const ORGANIZATION_GAME_MOD_LICENSE_ID = 'organization_game_mod_license_id';
    const ORGANIZATION_USER_STATUS_ID = 'organization_user_status_id';
    const ORGANIZATION_USER_INVITE_ID = 'organization_user_invite_id';

    // SMS
    const SMS_ID = 'sms_id';
    const SMS_TYPE_ID = 'sms_type_id';
    const FROM_NUMBER = 'from_number';
    const TO_NUMBER = 'to_number';
    const IS_SENT = 'is_sent';
    const SCHEDULE_TIME = 'schedule_time';

    // Applications
    const APPLICATION_ID = 'application_id';
    const APPLICATION_USER_ID = 'application_user_id';
    const APPLICATION_USER_ACCESS_TOKEN_ID = 'application_user_access_token_id';
    const TOKEN = 'token';
    const EXPIRES_ON = 'expires_on';
    const API_KEY = 'api_key';

    // SSO
    const REFRESH_TOKEN = 'refresh_token';
    const SSO_SERVICE_ID = 'sso_service_id';
    const SSO_ACCOUNT_ID = 'sso_account_id';
    const SCOPE = 'scope';
    const USER_SSO_SERVICE_ID = 'user_sso_service_id';


    // Payments
    const INVOICE_TRANSACTION_TYPE_ID = 'invoice_transaction_type_id';
    const PAYMENT_ID = 'payment_id';
    const PAYMENT_STATUS_ID = 'payment_status_id';
    const PAYMENT_SERVICE_ID = 'payment_service_id';
    const PAYMENT_SERVICE_CUSTOMER_KEY = 'payment_service_customer_key';
    const PAYMENT_INVOICE_ID = 'payment_invoice_id';
    const PAYMENT_INVOICE_TRANSACTION_ID = 'payment_invoice_transaction_id';
    const PAYMENT_FEE_INVOICE_ID = 'payment_fee_invoice_id';
    const PAYMENT_FEE_INVOICE_TRANSACTION_ID = 'payment_fee_invoice_transaction_id';
    const PAYMENT_AMOUNT = 'payment_amount';
    const TRANSACTION_FEE = 'transaction_fee';
    const PAYMENT_DATE = 'payment_date';
    const PAYMENT_MESSAGE = 'payment_message';
    const TRANSACTION_ID = 'transaction_id';
    const AUTHORIZATION_ID = 'authorization_id';
    const TRANSACTION_NUMBER = 'transaction_number';
    const ERROR = 'error';
    const DEBIT_CREDIT = 'debit_credit';
    const LINE_TYPE = 'line_type';
    const NET_AMOUNT = 'net_amount';
    const MAX_AMOUNT = 'max_amount';
    const AMOUNT = 'amount';
    const CODE = 'code';
    const DIM_1 = 'dim_1';
    const DIM_2 = 'dim_2';
    const INVOICE_NO = 'invoice_no';
    const ACCOUNTING_STATUS_ID = 'accounting_status_id';
    const OWNER_PAYMENT_SERVICE_ID = 'owner_payment_service_id';
    const OWNER_PAYMENT_SERVICE_TOKEN_ID = 'owner_payment_service_token_id';
    const OWNER_PAYMENT_SERVICE_TOKEN_LOG_ID = 'owner_payment_service_token_log_id';
    const FINGERPRINT = 'fingerprint';
    const CLIENT_SECRET = 'client_secret';
    const RAW_META = 'raw_meta';
    const IS_SUCCESSFUL = 'is_successful';
    const DIRECTION = 'direction';

    // Orders
    const ORDER_ID = 'order_id';
    const ORDER_STATUS_ID = 'order_status_id';
    const ORDER_ITEM_ID = 'order_item_id';
    const ORDER_ITEM_QUANTUM_ID = 'order_item_quantum_id';
    const ORDER_ITEM_TYPE_ID = 'order_item_type_id';
    const ORDER_ITEM_STATUS_ID = 'order_item_status_id';
    const MARKUP_OWNER_TYPE_ID = 'markup_owner_type_id';
    const MARKUP_OWNER_ID = 'markup_owner_id';
    const NET_PRICE = 'net_price';
    const NET_MARKUP = 'net_markup';
    const TAX_RATE = 'tax_rate';
    const QUANTITY = 'quantity';
    const PRICE = 'price';
    const NOTE = 'note';

    // Income
    const INCOME_ID = 'income_id';
    const INCOME_TYPE_ID = 'income_type_id';
    const INCOME_STATUS_ID = 'income_status_id';
    const INCOME_CONTENT_SUMMARY_ID = 'income_content_summary_id';

    // Payouts
    const PAYPAL_EMAIL = 'paypal_email';
    const PAYOUT_ID = 'payout_id';
    const PAYOUT_SERVICE_ID = 'payout_service_id';
    const PAYOUT_SERVICE_TOKEN_ID = 'payout_service_token_id';
    const PAYOUT_SERVICE_TOKEN_HISTORY_ID = 'payout_service_token_history_id';
    const PAYOUT_STATUS_ID = 'payout_status_id';
    const PAYOUT_AMOUNT = 'payout_amount';
    const PAYOUT_DATE = 'payout_date';
    const PAYOUT_MESSAGE = 'payout_message';
    const PAYOUT_INVOICE_ID = 'payout_invoice_id';
    const PAYOUT_INVOICE_TRANSACTION_ID = 'payout_invoice_transaction_id';
    const PAYOUT_FEE_INVOICE_ID = 'payout_fee_invoice_id';
    const PAYOUT_FEE_INVOICE_TRANSACTION_ID = 'payout_fee_invoice_transaction_id';

    // Payouts Related
    const MINIMUM_PAYOUT_AMOUNT = 'minimum_payout_amount';
    const BANK_ACCOUNT_OWNER = 'bank_account_owner';
    const BANK_ROUTING_NUMBER = 'bank_routing_number';
    const BANK_ACCOUNT_NUMBER = 'bank_account_number';
    const BANK_IBAN = 'bank_iban';
    const BANK_SWIFT = 'bank_swift';

    // Short Urls
    const SHORT_URL_ID = 'short_url_id';
    const TOTAL_VIEWS = 'total_views';


    // Images
    const IMAGE_ASSET_ID = 'image_asset_id';
    const IMAGE_ID = 'image_id';
    const IMAGE_TYPE_ID = 'image_type_id';
    const IMAGE_TYPE_SIZE_ID = 'image_type_size_id';
    const WIDTH = 'width';
    const HEIGHT = 'height';
    const DPI_X = 'dpi_x';
    const DPI_Y = 'dpi_y';
    const ASPECT_X = 'aspect_x';
    const QUALITY = 'quality';


    // Service Access Tokens
    const SERVICE_ACCESS_TOKEN_ID = 'service_access_token_id';
    const SERVICE_ACCESS_TOKEN_INSTANCE_ID = 'service_access_token_instance_id';
    const SERVICE_ACCESS_TOKEN_TYPE_ID = 'service_access_token_type_id';
    const SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID = 'service_access_token_type_group_id';
    const SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID = 'service_access_token_type_category_id';
    const ORIGINAL_USES = 'original_uses';
    const REMAINING_USES = 'remaining_uses';
    const MAX_SEATS = 'max_seats';
    const DURATION = 'duration';
    const IS_BUYABLE = 'is_buyable';
    const IS_ORGANIZATION_CREATABLE = 'is_organization_creatable';


    // BI / Stats
    const DATE_RANGE_ID = 'date_range_id';
    const DAY_DATE_RANGE_ID = 'day_date_range_id';
    const DATE_RANGE_TYPE_ID = 'date_range_type_id';
    const DATE_RANGE_X_DAILY_DATE_RANGE_ID = 'date_range_x_daily_date_range_id';
    const KPI_SUMMARY_ID = 'kpi_summary_id';
    const KPI_SUMMARY_TYPE_ID = 'kpi_summary_type_id';
    const VAL_INT = 'val_int';
    const VAL_FLOAT = 'val_float';
    const VAL_CURRENCY = 'val_currency';
    const SUMMARY_FIELD = 'summary_field';

    const DISPLAY_IN_DASHBOARD = 'display_in_dashboard';

    // Activations
    const ACTIVATION_ID = 'activation_id';
    const ACTIVATION_GROUP_ID = 'activation_group_id';
    const ACTIVATION_TYPE_ID = 'activation_type_id';
    const ACTIVATION_STATUS_ID = 'activation_status_id';

    // Coins

    const USER_COIN_ID = 'user_coin_id';
    const GUEST_COIN_ID = 'guest_coin_id';
    const COIN_AWARD_TYPE_ID = 'coin_award_type_id';

    // Incentives
    const INCENTIVE_ID = 'incentive_id';
    const INCENTIVE_TYPE_ID = 'incentive_type_id';
    const INCENTIVE_INSTANCE_ID = 'incentive_instance_id';
    const DISCOUNT_PERCENTAGE = 'discount_percentage';
}


/**
 * Entity Virtual Fields -- Not sourced directly from source DB columns.
 *
 * Class VField
 */
final class VField
{
    const CURRENT_TIMESTAMP = 'current_timestamp';
    const IS_STAFF = 'is_staff';
    const NEXT = 'next';

    const OFFSET = 'offset';
    const COUNT = 'count';
    const CONTEXT = 'context';
    const ENTITY = 'entity';

    const GAME_SLUG = 'game_slug';
    const ORGANIZATION_SLUG = 'organization_slug';
    const HOST_SLUG = 'host_slug';
    const HOST_SLUG_SANDBOX = 'host_slug_sandbox';
    const LBE_GAMES = 'lbe_games';
    const CLOUD_GAMES = 'cloud_games';
    const GAMEDAY_LICENSE = 'gameday_license';
    const CMS_SEAT_LICENSE = 'cms_seat_license';
    const GAMEDAY_COUNT = 'gameday_count';
    const CMS_SEAT_COUNT = 'cms_seat_count';
    const DISCOUNT = 'discount';

    const SUMMARY = 'summary';
    const AVG = 'avg';

    const V_PK = '_pk';
    const V_ENTITY_TYPE_ID = '_entityTypeId';
    const V_POSITION = '_position';

    const PHONE_NUMBERS = 'phone_numbers';
    const EMAIL_ADDRESSES = 'email_addresses';

    // URLs
    const URL = 'url';
    const EFFECTIVE_URL = 'effective_url';
    const PUBLIC_URL = 'public_url';
    const TARGET_URL = 'target_url';
    const LOCAL_URL = 'local_url';
    const FOLDER_PATH = 'folder_path';
    const CONTROLLER_URL = 'controller_url';
    const EDIT_URL = 'edit_url';
    const CLONE_URL = 'clone_url';
    const ACTIVATIONS_URL = 'activations_url';
    const GAMES_URL = 'games_url';
    const MODS_URL = 'mods_url';
    const ADMIN_EDIT_URL = 'admin_edit_url';
    const ADMIN_CANCEL_URL = 'admin_cancel_url';
    const ADMIN_URL = 'admin_url';
    const ADMIN_CAPTURE_URL = 'admin_capture_url';
    const DELETE_URL = 'delete_url';
    const EDIT_PERMISSIONS_URL = 'edit_permissions_url';
    const IMAGE_URL = 'image_url';
    const AVATAR_URL = 'avatar_url';
    const AVATAR_SMALL_URL = 'avatar_small_url';
    const AVATAR_TINY_URL = 'avatar_tiny_url';

    // Images

    const HAS_AVATAR = 'has_avatar';
    const AVATAR = 'avatar';
    const IMAGES = 'images';

    // Entities

    const SESSION = 'session';
    const SESSIONS = 'sessions';

    const COUNTRIES = 'countries';
    const COUNTRY = 'country';

    const LOCATION = 'location';
    const LOCATIONS = 'locations';

    const HOST = 'host';
    const HOSTS = 'hosts';
    const HOST_INSTANCE_TYPE = 'host_instance_type';

    const SCREENS = 'screens';
    const SCREEN = 'screen';

    const NETWORK = 'network';
    const NETWORKS = 'networks';

    const SMS_NUMBER = 'sms_number';

    const PERMISSIONS = 'permissions';
    const RIGHT = 'right';
    const A_RIGHTS = 'a_rights';
    const M_RIGHTS = 'm_rights';
    const U_RIGHTS = 'u_rights';

    const GAME = 'game';
    const GAME_MOD = 'game_mod';
    const GAME_MODS = 'game_mods';
    const GAMES = 'games';
    const OWNER_DISPLAY_NAME = 'owner_display_name';
    const LATEST_GAME_VERSIONS = 'latest_game_versions';
    const LATEST_MOD_VERSIONS = 'latest_mod_versions';
    const UPDATE_CHANNEL_NAME = 'update_channel_name';
    const GAME_VIDEOS = 'game_videos';
    const GAME_CATEGORY = 'game_category';
    const GAME_LICENSES = 'game_licenses';
    const GAME_BUILD = 'game_build';
    const GAME_MOD_BUILD = 'game_mod_build';
    const GAME_BUILDS = 'game_builds';
    const GAME_TYPE = 'game_type';
    const GAME_ENGINE = 'game_engine';
    const GAME_ASSET = 'game_asset';
    const GAME_MOD_ASSET = 'game_mod_asset';
    const GAME_ASSETS = 'game_assets';
    const GAME_MOD_ASSETS = 'game_mod_assets';
    const GAME_MOD_LICENSES = 'game_mod_licenses';
    const GAME_CONTROLLERS = 'game_controllers';
    const GAME_CONTROLLER_TYPE = 'game_controller_type';
    const GAME_CONTROLLER_ASSETS = 'game_controller_assets';
    const GAME_INSTANCES = 'game_instances';
    const GAME_INSTANCE_ROUNDS = 'game_instance_rounds';
    const GAME_INSTANCE_ROUND_PLAYERS = 'game_instance_round_players';
    const PUBLISHED_GAME_BUILD = 'published_game_build';
    const GAME_BUILD_IS_ACTIVE = 'game_build_is_active';
    const GAME_MOD_BUILDS = 'game_mod_builds';
    const INHERIT = 'inherit';
    const GAME_INSTANCE_LOGS = 'game_instance_logs';
    const GAME_INSTANCE_LOG_STATUS = 'game_instance_log_status';
    const GAME_INSTANCE_LOG_ASSET = 'game_instance_log_asset';
    const GAME_INSTANCE_LOG_ASSETS = 'game_instance_log_assets';

    const USER_IS_HOST_ADMIN = 'user_is_host_admin';

    const NICE_FILE_SIZE = 'nice_file_size';
    const POSITION = 'position';

    const CREATOR_USER = 'creator_user';
    const OWNER_USER = 'owner_user';

    const PLATFORM = 'platform';
    const SDK_PLATFORM = 'sdk_platform';
    const ORGANIZATION = 'organization';
    const ORGANIZATIONS = 'organizations';
    const ORGANIZATION_ROLE = 'organization_role';
    const ORGANIZATION_RIGHT = 'organization_right';
    const ORGANIZATION_RIGHTS = 'organization_rights';
    const ORGANIZATION_ROLES = 'organization_roles';
    const ORGANIZATION_USERS = 'organization_users';
    const ORGANIZATION_USER = 'organization_user';
    const ORGANIZATION_USER_STATUS = 'organization_user_status';
    const ORGANIZATION_BASE_ROLE = 'organization_base_role';
    const ORGANIZATION_BASE_RIGHT = 'organization_base_right';
    const ORGANIZATION_PERMISSION = 'organization_permission';
    const ORGANIZATION_PERMISSIONS = 'organization_permissions';

    const HOST_DEVICE = 'host_device';
    const HOST_DEVICE_COMPONENTS = 'host_device_components';
    const HOST_ASSETS = 'host_assets';
    const HOST_VERSION = 'host_version';
    const HOST_BUILDS = 'host_builds';
    const HOST_BUILD = 'host_build';
    const HOST_CONTROLLER = 'host_controller';
    const HOST_CONTROLLER_ASSETS = 'host_controller_assets';
    const HOST_BUILD_ASSET_ID = 'host_build_asset_id';
    const HOST_CONTROLLER_ASSET_ID = 'host_controller_asset_id';
    const HOST_APP_ARCHIVE = 'host_app_archive';
    const HOST_APP_CONTROLLER_ARCHIVE = 'host_app_controller_archive';


    const SDK_ASSETS = 'sdk_assets';
    const SDK_VERSION = 'sdk_version';
    const SDK_BUILDS = 'sdk_builds';
    const SDK_BUILD = 'sdk_build';
    const SDK_BUILD_ASSET_ID = 'sdk_build_asset_id';
    const SDK_ARCHIVE = 'sdk_archive';

    // Aliased DBFields

    const GAME_CONTROLLER_ASSET_ID = 'game_controller_asset_id';
    const GAME_BUILD_ASSET_ID = 'game_build_asset_id';
    const CUSTOM_GAME_ASSET_ID = 'custom_game_asset_id';
    const CUSTOM_GAME_MOD_ASSET_ID = 'custom_game_mod_asset_id';
    const GAME_INSTANCE_LOG_ASSET_ID = 'game_instance_log_asset_id';

    const CUSTOM_GAME_ASSET = 'custom_game_asset';
    const CUSTOM_GAME_ASSETS = 'custom_game_assets';
    const CUSTOM_ASSETS = 'custom_assets';
    const GAME_ACTIVE_CUSTOM_ASSET = 'game_active_custom_asset';

    const CUSTOM_DATA = 'custom_data';

    const PLAYERS = 'players';
    const EVENTS = 'events';
    const PROPERTIES = 'properties';

    const USER = 'user';

    const GAME_BUILD_ARCHIVE = 'game_build_archive';
    const GAME_BUILD_CONTROLLER_ARCHIVE = 'game_build_controller_archive';
    const GAME_ASSET_FILE = 'game_asset_file';

    const REPLACE = 'replace';

    const DEVICE_TYPE_NAME = 'device_type_name';
    const COUNTRY_NAME = 'country_name';
    const GEO_REGION_NAME = 'geo_region_name';
    const RIGHT_GROUP_NAME = 'right_group_name';

    const PARSED_DESCRIPTION = 'parsed_description';

    const GUEST = 'guest';
    const USER_GROUP = 'user_group';

    const ADDRESS = 'address';


    // ORDERS & Payments
    const META = 'meta';
    const ORDER_ITEMS = 'order_items';
    const ORDER_ITEM_TYPE = 'order_item_type';
    const ORDER_ITEMS_QUANTUM = 'order_items_quantum';
    const PAYMENT = 'payment';
    const PAYMENT_STATUS = 'payment_status';
    const PAYMENT_SERVICE = 'payment_service';
    const OWNER_PAYMENT_SERVICE = 'owner_payment_service';
    const OWNER_PAYMENT_SERVICE_TOKEN = 'owner_payment_service_token';
    const OWNER_PAYMENT_SERVICE_TOKENS = 'owner_payment_service_tokens';
    const PAYMENT_INVOICE = 'payment_invoice';
    const PAYMENT_INVOICE_TRANSACTIONS = 'payment_invoice_transactions';
    const PAYMENT_FEE_INVOICE = 'payment_fee_invoice';
    const PAYMENT_FEE_INVOICE_TRANSACTIONS = 'payment_fee_invoice_transactions';
    const INCENTIVES = 'incentives';
    const INCENTIVE = 'incentive';
    const INCENTIVE_TYPE = 'incentive_type';

    const PAYOUT_SERVICE = 'payout_service';
    const PAYOUT_SERVICE_TOKEN = 'payout_service_token';
    const INCOME_RECORDS = 'income_records';
    const INCOME_TYPE = 'income_type';
    const INCOME_STATUS = 'income_status';
    const PAYOUT_INVOICE = 'payout_invoice';
    const PAYOUT_INVOICE_TRANSACTIONS = 'payout_invoice_transactions';
    const PAYOUT_FEE_INVOICE = 'payout_fee_invoice';
    const PAYOUT_FEE_INVOICE_TRANSACTIONS = 'payout_fee_invoice_transactions';

    // Images
    const SIZES = 'sizes';
    const IMAGE_TYPE_SIZES = 'image_type_sizes';
    const IMAGE_TYPE = 'image_type';

    // PubNub
    const PUB_NUB_CHANNELS = 'pub_nub_channels';
    const PUB_NUB_CONFIG = 'pub_nub_config';


    // Service Access Tokens
    const SERVICE_ACCESS_TOKEN = 'service_access_token';
    const SERVICE_ACCESS_TOKEN_TYPE = 'service_access_token_type';
    const SERVICE_ACCESS_TOKEN_TYPE_GROUP = 'service_access_token_type_group';
    const SERVICE_ACCESS_TOKEN_TYPE_CATEGORY = 'service_access_token_type_category';


    const GAME_DATA_SHEETS = 'game_data_sheets';
    const GAME_DATA_SHEET_ROWS = 'game_data_sheet_rows';
    const GAME_MOD_DATA_SHEETS = 'game_mod_data_sheets';
    const GAME_MOD_DATA_SHEET_ROWS = 'game_mod_data_sheet_rows';
    const GAME_DATA_SHEET_COLUMNS = 'game_data_sheet_columns';
    const GAME_MOD_DATA_SHEET_COLUMNS = 'game_mod_data_sheet_columns';
    const SHEETS = 'sheets';
    const COLUMNS = 'columns';
    const PROCESSED_VALUES = 'processed_values';
    const AWARDS = 'awards';

    const TIME_FRAME = 'time_frame';

    const START_DATE = 'start_date';
    const END_DATE = 'end_date';


    // Timezone Local Fields
    const LOCAL_CREATE_TIME = 'local_create_time';
    const CREATE_TIME_AGO = 'create_time_ago';
    const LOCAL_DELETE_TIME = 'local_delete_time';
    const LOCAL_START_TIME = 'local_start_time';
    const LOCAL_END_TIME = 'local_end_time';
    const LOCAL_LAST_PING_TIME = 'local_last_ping_time';
    const LOCAL_JOIN_DATE = 'local_join_date';
    const LOCAL_OPENED_TIME = 'local_opened_time';
    const LOCAL_CLICKED_TIME = 'local_clicked_time';
    const LOCAL_SENT_TIME = 'local_sent_time';

    // User preferences
    const NOTIFY_SMS = "notify_sms";

    const ACTIVATION_STATUS = 'activation_status';
    const ACTIVATION_TYPE = 'activation_type';
    const ACTIVATION = 'activation';
    const ACTIVATIONS = 'activations';
    const ACTIVATION_GROUP = 'activation_group';

    const PRIMARY_COLOR = 'primary_color';
    const SECONDARY_COLOR = 'secondary_color';

    const ACTIVITY = 'activity';
}
