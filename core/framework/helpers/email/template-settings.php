<?php
/**
 * These settings map the various sent emails to settings, templates, and content.
 *
 * Created by PhpStorm.
 * User: ccarter
 * Date: 8/30/16
 * Time: 2:25 AM
 */

class EmailTypesTemplateSettings {

    public static $email_type_templates = [

        // Account Registration Confirmation Email
        EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION => [
            'id' => EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/registration-confirmation.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Please confirm your email address!'
            //'title' => 'Account: Activate your membership, {recipient.name}'
        ],

        // Account Registration Confirmation Resend Email
        EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_RESEND => [
            'id' => EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_RESEND,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/registration-confirmation.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Activate your membership'
        ],

        // Forgot Password Email
        EmailTypesManager::TYPE_SYSTEM_FORGOT_PASSWORD => [
            'id' => EmailTypesManager::TYPE_SYSTEM_FORGOT_PASSWORD,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/password-reset.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Confirm password reset'
        ],

        // New Password Email
        EmailTypesManager::TYPE_SYSTEM_NEW_PASSWORD => [
            'id' => EmailTypesManager::TYPE_SYSTEM_NEW_PASSWORD,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/new-password.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => '[IMPORTANT] Your new account password'
        ],

        // Admin Invite User
        EmailTypesManager::TYPE_SYSTEM_ADMIN_USER_INVITE => [
            'id' => EmailTypesManager::TYPE_SYSTEM_ADMIN_USER_INVITE,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/admin/admin-user-invite.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_ADMIN_INVITE,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'You have been invited to ESC Games'
        ],

        // User Registration Shake
        EmailTypesManager::TYPE_SYSTEM_REGISTRATION_SHAKE => [
            'id' => EmailTypesManager::TYPE_SYSTEM_REGISTRATION_SHAKE,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/registration-shake.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Activate your ESC Games Trial',
        ],

        // Account Registration Confirmation Email
        EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_PLAY => [
            'id' => EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_PLAY,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/system/registration-confirmation-play.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_REGISTRATION,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Thank you for playing!'
            //'title' => 'Account: Activate your membership, {recipient.name}'
        ],

        // Admin Invite User
        EmailTypesManager::TYPE_SYSTEM_ADMIN_TEAM_INVITE => [
            'id' => EmailTypesManager::TYPE_SYSTEM_ADMIN_TEAM_INVITE,
            'bone_template' => EmailGenerator::EMAIL_BONE_GAMEDAY_PILOT,
            'content_template' => 'emails/{lang}/admin/admin-team-invite.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_ADMIN_INVITE,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'You have been invited to join a team on ESC Games'
        ],

        // Admin Invite User
        EmailTypesManager::TYPE_SYSTEM_TEAM_USER_INVITE => [
            'id' => EmailTypesManager::TYPE_SYSTEM_TEAM_USER_INVITE,
            'bone_template' => EmailGenerator::EMAIL_BONE_GAMEDAY_PILOT,
            'content_template' => 'emails/{lang}/organizations/team-user-invite.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_ADMIN_INVITE,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'You have been invited to join a team on ESC Games'
        ],

        // Customer Contact Form Email
        EmailTypesManager::TYPE_CUSTOMER_CONTACT => [
            'id' => EmailTypesManager::TYPE_CUSTOMER_CONTACT,
            'bone_template' => EmailGenerator::EMAIL_BONE_LIGHTWEIGHT,
            'content_template' => 'emails/{lang}/custom/customer-contact.twig',
            'acq_source' => EmailTypesManager::UTM_SOURCE_SYSTEM,
            'acq_campaign' => EmailTypesManager::UTM_CAMPAIGN_WWW,
            'sender_name' => EmailGenerator::SENDER_ESC,
            'title' => 'Hey, can you help me?'
            //'title' => 'Account: Activate your membership, {recipient.name}'
        ],

    ];
}
