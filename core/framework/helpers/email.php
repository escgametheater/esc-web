<?php

// Sent Email Templates + Settings
require('email/template-settings.php');

/**
 * Class EmailGenerator
 * @param $email_type_id
 * @param $recipient
 * @param $request
 * @param $checksum
 * @param $object
 * @param $object_creator
 * @param null $object_source
 * @param null $object_source_context
 * @param null $activity_id
 */

class EmailGenerator {

    const EMAIL_BONE_LIGHTWEIGHT = 'emails/global/email-bone.twig';
    const EMAIL_BONE_LIGHTWEIGHT_DARK = 'emails/global/email-bone-dark.twig';
    const EMAIL_BONE_GAMEDAY_PILOT = 'emails/global/email-bone-gameday-pilot.twig';
    const EMAIL_BODY_PLACEHOLDER = '';

    const SENDER_ESC = 'ESC Games';
    const SENDER_CHRISTOPHER = '<<<REDACTED>>>';

    const SENDER_ADDRESS_NO_REPLY = 'no-reply';
    const SENDER_ADDRESS_CCARTER = 'christopher';

    /**
     *
     * New Properties
     *
     */

    /** @var  EmailTrackingManager */
    protected $emailTrackingManager;
    /** @var  EmailHistoryManager  */
    protected $emailHistoryManager;
    /** @var  EmailTrackingEntity|array */
    protected $email = [];
    /** @var  EmailRecordEntity|array */
    protected $emailRecord = [];
    /** @var int $emailTypeId */
    protected $emailTypeId;

    protected $viewData = [];

    /**
     *
     * Old Properties
     *
     */

    // Object Stores
    /** @var Request $request */
    protected $request;
    /** @var array|UserEntity $recipient */
    protected $recipient = [];
    /** @var array|DBManagerEntity */
    protected $entity = [];

    protected $object_source = [];
    protected $object_source_context = [];
    protected $object_creator = [];
    protected $artist = [];
    protected $comic = [];
    protected $fetched_email_record = [];

    // Data Stores
    protected $useHtml = true;
    protected $et_id = false;
    protected $cta_link;
    protected $checksum;
    protected $utm_medium = EmailTypesManager::UTM_MEDIUM_EMAIL;
    protected $utm_source;
    protected $utm_campaign;
    protected $utm_term;
    protected $sender_email;
    protected $recipient_email;
    protected $sender_name;
    protected $sender_address;
    protected $activityId;
    protected $email_subject = 'Placeholder Title';
    protected $emailType;
    protected $emailLangId = LanguagesManager::LANGUAGE_ENGLISH;

    /**
     * @param Request $request
     * @param $recipientEmail
     * @param $emailTypeId
     * @param $checksum
     * @param null $activityId
     * @param string $language
     */
    public function __construct(Request $request, $recipientEmail, $emailTypeId, $checksum, $activityId = null,
                                $language = LanguagesManager::LANGUAGE_ENGLISH)
    {
        // Refactored Manager / Entities
        $this->emailTrackingManager = $request->managers->emailTracking();
        $this->emailHistoryManager = $request->managers->emailHistory();

        $this->activityId = $activityId;
        $this->request = $request;
        $this->emailTypeId = $emailTypeId;

        $this->emailType = $this->getEmailTypeData($emailTypeId);
        $this->email_subject = $this->getTypeTitle($this->emailType);
        $this->sender_name = $this->getTypeSenderName($this->emailType);
        $this->sender_address = $this->getTypeSenderEmailAddress($this->emailType);
        $this->utm_source = $this->getUtmSource($this->emailType);
        $this->utm_campaign = $this->getUtmCampaign($this->emailType);
        $this->utm_medium = EmailTypesManager::UTM_MEDIUM_EMAIL;

        $this->recipient_email = $recipientEmail;
        $this->checksum = $checksum;


        $this->emailLangId = $language;

        $this->viewData = [
            TemplateVars::REQUEST => $request,
            TemplateVars::WWW_URL => $request->getWwwUrl(),
            TemplateVars::PLAY_URL => $request->getPlayUrl(),
            TemplateVars::DEVELOP_URL => $request->getDevelopUrl(),
            TemplateVars::IMAGES_URL => $request->settings()->getImagesUrl(),
            TemplateVars::STATIC_URL => $request->settings()->getStaticUrl(),
            TemplateVars::I18N => $request->translations,
            TemplateVars::WEBSITE_DOMAIN => $request->settings()->getWebsiteDomain(),
            TemplateVars::EMAIL_DOMAIN => $request->settings()->getEmailDomain(),
            TemplateVars::WEBSITE_NAME => $request->settings()->getWebsiteName(),
            TemplateVars::YEAR_NOW => date("Y"),
            TemplateVars::CHECKSUM => $checksum,
            TemplateVars::CURRENT_DATE => date("F j, Y, g:i a")
        ];
    }

    /**
     * @param array $viewData
     * @return $this
     */
    public function assignViewData($viewData = [])
    {
        $this->viewData = array_merge($this->viewData, $viewData);
        return $this;
    }

    /**
     * @return array|EmailTrackingEntity
     */
    public function getEmailTrackingRecord()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    protected function createSender()
    {
        return "{$this->sender_name} <{$this->sender_address}@{$this->request->settings()->getEmailDomain()}>";
    }

    /**
     * @param $utmTerm
     * @return $this
     */
    public function setUtmTerm($utmTerm)
    {
        $this->utm_term = $utmTerm;
        return $this;
    }

    /**
     * @param UserEntity $user
     * @return $this
     */
    public function setRecipientUser(UserEntity $user)
    {
        $this->recipient = $user;
        return $this;
    }

    /**
     * @param $template_path
     * @return mixed
     */
    private function getLocalizedTemplatePath($template_path)
    {
        return str_replace('{lang}', $this->emailLangId, $template_path);
    }

    /**
     * @return EmailTrackingEntity
     */
    protected function createEmailTrackingRecord()
    {
        $userId = $this->recipient ? $this->recipient->getPk() : null;
        $entityId = $this->entity ? $this->entity->getPk() : null;

        return $this->emailTrackingManager->trackEmail(
            $this->request,
            $this->checksum,
            $this->recipient_email,
            $this->emailTypeId,
            $userId,
            $entityId,
            null,
            $this->emailLangId,
            $this->utm_source,
            $this->utm_campaign,
            $this->activityId
        );
    }

    /**
     * @return Template
     */
    protected function initTemplate()
    {
        $request = $this->request;

        $templateDefaults = array_merge($this->viewData, [
            TemplateVars::TRACKING_PIXEL_URL => $this->emailTrackingManager->getEmailTrackingPixelUrl($request, $this->email),
            TemplateVars::ET_ID => $this->et_id,
            TemplateVars::EMAIL => $this->email,
            TemplateVars::RECIPIENT => $this->recipient,
            TemplateVars::WWW_URL => $request->getWwwUrl(),
            TemplateVars::PLAY_URL => $request->getPlayUrl(),
            TemplateVars::IMAGES_URL => $request->settings()->getImagesUrl(),
            TemplateVars::STATIC_URL => $request->settings()->getStaticUrl(),
            TemplateVars::EMAIL_GENERATOR => $this,
        ]);

        return new Template($templateDefaults);
    }

    /**
     * @param EmailTrackingEntity $email
     * @return string
     */
    public function renderEmail(EmailTrackingEntity $email)
    {
        $this->email = $email;

        $template = $this->initTemplate();
        $contentHtml = $template->render_template($this->getTypeContentTemplate($this->emailType));

        $template['email_body'] = $contentHtml;

        $boneTemplate = $this->getTypeBoneTemplate($this->emailType);

        $html = $template->render_template($boneTemplate);
        $html = $this->emailTrackingManager->addTrackingParamsToLinks($html, $email, $this->utm_term);

        return $html;
    }

    /**
     * Sends Email based on current object data
     *
     * @return bool
     */
    public function sendEmail()
    {
        $request = $this->request;

        if (!$this->email)
            $this->email = $this->createEmailTrackingRecord();

        if ($this->email) {

            $body = $this->renderEmail($this->email);

            $sender = $this->createSender();
            $title = $this->email_subject;
            $emailRecord = $this->emailHistoryManager->createNewEmail($request, $this->email, $sender, $title, $body);
            $this->emailHistoryManager->sendEmail($emailRecord, $this->useHtml);
            return true;
        }
    }

    /**
     * @return string
     */
    protected function getEmailSettingsUrl()
    {
        return $this->request->getWwwUrl("/account/notifications");
    }

    /**
     * @param $email_type_id
     * @return null
     */
    protected function getEmailTypeData($email_type_id)
    {
        $types = EmailTypesTemplateSettings::$email_type_templates;
        foreach ($types as $type) {
            if ($type[DBField::ID] === $email_type_id)
                return $type;
        }

        return null;
    }

    public function getTypeBoneTemplate($type)
    {
        return array_get($type, 'bone_template', null);
    }

    public function getTypeSenderName($type)
    {
        return array_get($type, 'sender_name', null);
    }
    public function getTypeSenderEmailAddress($type)
    {
        return array_get($type, 'sender_address', 'no-reply');
    }

    public function getTypeContentTemplate($type)
    {
        return isset($type['content_template']) ? $this->getLocalizedTemplatePath($type['content_template']) : null;
    }

    public function getTypeTitle($type)
    {
        return array_get($type, DBField::TITLE, null);
    }

    public function getUtmSource($type)
    {
        return array_get($type, DBField::ACQ_SOURCE, null);
    }

    public function getUtmCampaign($type) {
        return array_get($type, DBField::ACQ_CAMPAIGN, null);
    }
}