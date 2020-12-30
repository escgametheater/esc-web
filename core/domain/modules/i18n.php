<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 5/20/18
 * Time: 6:16 PM
 */

class T {
    /**
     * Constants used for translation strings
     */

    // Auth Section Messages
    const AUTH_ACCOUNT_VERIFICATION_SUCCESS_MESSAGE = 'auth.account-verification.success-message';
    const AUTH_ACCOUNT_VERIFICATION_FAILURE_MESSAGE = 'auth.account-verification.failure-message';
    const AUTH_ACCOUNT_VERIFICATION_ALREADY_VERIFIED_MESSAGE = 'auth.account-verification.already-verified';
    const AUTH_ACCOUNT_VERIFICATION_FAILURE_BAD_EMAIL = 'auth.account-verification.invalid-email';

    // Login
    const AUTH_LOGIN_INCORRECT_CREDENTIALS = 'auth.login.incorrect-credentials';
    const AUTH_LOGIN_TOO_MANY_ATTEMPTS = 'auth.login.account-locked-too-many-attempts';

    // Generic Flash Messages
    const FLASH_MESSAGE_SETTINGS_SAVED = 'flash-message.settings-saved';

    // Form Field Descriptions
    const ACCOUNT_DESCRIPTION_BETA = 'account.description.beta-access';
    const ACCOUNT_DESCRIPTION_UI_LANGUAGE = 'account.description.ui-language';

    // Form Field Help Texts
    const ACCOUNT_HELP_TEXT_USERNAME = 'account.help-text.username';
    const ACCOUNT_HELP_TEXT_BETA = 'account.help-text.beta-access';
    const ACCOUNT_HELP_TEXT_DEVELOPER = 'account.help-text.is-developer';
    const ACCOUNT_HELP_TEXT_UI_LANGUAGE = 'account.help-text.ui-language';
    const ACCOUNT_HELP_TEXT_EMAIL_ADDRESS = 'account.help-text.email-address';

    const ACCOUNT_FORM_ERROR_USERNAME_EXISTS = 'account.form.error.username-exists';
    const ACCOUNT_AUTH_PASSWORD_CHANGE_SUCCESS = 'account.form.success.password-changed';

    const ACCOUNT_AUTH_RESET_PASSWORD_INVALID_EMAIL = 'auth.reset-password.invalid-email';

    /**
     * This array contains lookup / default strings for i18n keys shared across the web-apps
     *
     * @var array $dictionary
     */
    protected static $dictionary = [

        // Auth Section Messages
        self::AUTH_ACCOUNT_VERIFICATION_SUCCESS_MESSAGE => 'Your account has been verified - hooray!',
        self::AUTH_ACCOUNT_VERIFICATION_FAILURE_MESSAGE => 'Invalid activation link',
        self::AUTH_ACCOUNT_VERIFICATION_ALREADY_VERIFIED_MESSAGE => 'Account already verified',
        self::AUTH_ACCOUNT_VERIFICATION_FAILURE_BAD_EMAIL => 'Invalid Email Address',

        // Login
        self::AUTH_LOGIN_INCORRECT_CREDENTIALS => 'Incorrect Login Credentials',
        self::AUTH_LOGIN_TOO_MANY_ATTEMPTS => 'Account locked for 15 minutes - too many failed attempts',

        // Flash Messages
        self::FLASH_MESSAGE_SETTINGS_SAVED => 'Settings Saved',

        // Account Auth
        self::ACCOUNT_AUTH_PASSWORD_CHANGE_SUCCESS => 'Password Changed Successfully',
        self::ACCOUNT_AUTH_RESET_PASSWORD_INVALID_EMAIL => 'Invalid email address.',

        // Account Management Form Field Titles
        self::ACCOUNT_DESCRIPTION_BETA => 'Opt-in for beta features',
        self::ACCOUNT_DESCRIPTION_UI_LANGUAGE => 'Website/Interface Language',

        // Account Management Form Field Help Text
        self::ACCOUNT_HELP_TEXT_USERNAME => 'Your username is associated with your public identity and profile.',
        self::ACCOUNT_HELP_TEXT_BETA => 'When checked, enables beta testing features for your account.',
        self::ACCOUNT_HELP_TEXT_DEVELOPER => 'When checked, the user can access developer portal.',
        self::ACCOUNT_HELP_TEXT_UI_LANGUAGE => 'Controls the language the website is displayed in.',
        self::ACCOUNT_HELP_TEXT_EMAIL_ADDRESS => 'Your email address is used to log into your account and as our primary means of communicating with you.',

        // Account Management Form Field Errors
        self::ACCOUNT_FORM_ERROR_USERNAME_EXISTS => 'Username is already taken.',

    ];

    public static function get_dictionary()
    {
        return self::$dictionary;
    }
}