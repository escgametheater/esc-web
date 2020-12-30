<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/29/19
 * Time: 11:28 AM
 */

use
    \GuzzleHttp\Client;

class SlackHelper
{
    const ESC_WEBHOOK_VTT = '/<<<REDACTED>>>';

    /** @var array  */
    protected static $config = [
        'base_uri' => 'https://hooks.slack.com'
    ];

    /** @var array  */
    protected static $httpHeaders = [
        'Content-Type' => 'application/json',
    ];

    /**
     * SlackHelper constructor.
     */
    protected function __construct()
    {

    }

    /**
     * @param SlackCard $card
     * @param $uri
     * @return array|mixed|\Psr\Http\Message\ResponseInterface
     */
    public static function sendCard($message, $attachments = [], $uri = null)
    {
        if ($attachments instanceof SlackAttachment)
            $attachments = [$attachments];

        $card = new SlackCard($message, $attachments);

        if (!$uri)
            $uri = self::ESC_WEBHOOK_VTT;

        $client = new Client(self::$config);

        $loginResult = $client->request('POST', $uri, [
            'headers' => self::$httpHeaders,
            'body' => json_encode($card->toArray())
        ]);

        return $loginResult;
    }
}

class SlackCard
{
    /** @var string  */
    protected $textMessage = '';
    /** @var SlackAttachment[]  */
    protected $attachMents = [];

    public function __construct($textMessage, $attachments = [])
    {
        $this->textMessage = $textMessage;
        $this->attachMents = $attachments;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [
            'text' => $this->textMessage
        ];

        if ($this->attachMents) {
            $data['attachments'] = [];
            foreach ($this->attachMents as $attachment)
                $data['attachments'][] = $attachment->toArray();
        }

        return $data;
    }
}


class SlackAttachment
{
    protected $authorName;
    protected $authorIconUrl;
    protected $title;
    protected $titleLinkUrl;
    protected $text;

    /** @var SlackAction[]  */
    protected $actions = [];
    /** @var SlackField[] */
    protected $fields = [];

    protected $color = '#111';

    /**
     * SlackAttachment constructor.
     * @param UserEntity $user
     * @param $title
     * @param $titleLink
     * @param null $text
     * @param SlackField[]|SlackAction[] ...$context
     */
    public function __construct(UserEntity $user, $title, $titleLink, $text = null, ...$context)
    {
        $this->authorName = $user->getUsername() ? $user->getUsername() : $user->getEmailAddress();
        $this->authorIconUrl = $user->getAvatarUrl();
        $this->title = $title;
        $this->titleLinkUrl = $titleLink;
        $this->text = $text;

        foreach ($context as $contextAttachment) {
            if ($contextAttachment instanceof SlackAction)
                $this->actions[] = $contextAttachment;
            if ($contextAttachment instanceof SlackField)
                $this->fields[] = $contextAttachment;
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [
            'fallback' => 'Required plaintext summary',
        ];

        if ($this->authorName)
            $data['author_name'] = $this->authorName;

        if ($this->authorIconUrl)
            $data['author_icon'] = $this->authorIconUrl;

        if ($this->title)
            $data['title'] = $this->title;

        if ($this->titleLinkUrl)
            $data['title_link'] = $this->titleLinkUrl;

        if ($this->actions) {
            $data['actions'] = [];
            foreach ($this->actions as $action)
                $data['actions'][] = $action->toArray();
        }

        if ($this->text)
            $data['text'] = $this->text;

        if ($this->fields) {
            $data['fields'] = [];
            foreach ($this->fields as $field)
                $data['fields'][] = $field->toArray();
        }

        return $data;
    }
}

abstract class SlackAction
{
    protected $type = '';
    protected $text = '';
    protected $url = '';

    /**
     * SlackAction constructor.
     * @param $type
     * @param $text
     * @param $url
     */
    public function __construct($text, $url, $type = null)
    {
        $this->text = $text;
        $this->url = $url;
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'url' => $this->url
        ];
    }
}

class SlackActionButton extends SlackAction
{
    public function __construct($text, $url)
    {
        parent::__construct($text, $url, 'button');
    }
}

class SlackField
{
    protected $title = '';
    protected $value = '';

    /**
     * SlackField constructor.
     * @param $title
     * @param $value
     */
    public function __construct($title, $value)
    {
        $this->title = $title;
        $this->value = $value;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'title' => $this->title,
            'value' => $this->value
        ];
    }
}