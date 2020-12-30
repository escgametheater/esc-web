<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 8/28/16
 * Time: 4:03 PM
 */

class FlashMessageEntity extends DBDataEntity implements JsonSerializable {

    /** @var  FlashMessageOptionsEntity */
    protected $options;

    use
        hasUserIdField,
        hasContentField,
        hasTypeField;

    public function __construct($rawData)
    {
        $contentData = json_decode($rawData[DBField::CONTENT], true);

        $messageData = [
            DBField::USER_ID => $rawData[DBField::USER_ID],
            DBField::TYPE => $rawData[DBField::TYPE],
            DBField::CONTENT => $contentData[DBField::BODY],
            DBField::OPTIONS => $contentData[DBField::OPTIONS],
            //VField::TYPE_DISPLAY => FlashMessagesManager::getNotyTypeFromMessageCode($rawData[DBField::TYPE])
        ];

        $this->options = new FlashMessageOptionsEntity($contentData[DBField::OPTIONS]);

        parent::__construct($messageData);
    }

    /**
     * @return null
     */
    public function getNotyType()
    {
        return FlashMessagesManager::getNotyTypeFromMessageCode($this->getTypeId());
    }

    /**
     * @return FlashMessageOptionsEntity
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->dataArray;
    }

}

class FlashMessageOptionsEntity extends DBDataEntity {

    public function __construct($data)
    {
        if (is_string($data))
            $data = json_decode($data, true);

        parent::__construct($data);

    }

    public function getTimeout()
    {
        return $this->hasField(VField::TIMEOUT) && $this->field(VField::TIMEOUT) ? $this->field(VField::TIMEOUT) : false;
    }

    public function getModal()
    {
        return $this->hasField(VField::TIMEOUT) && $this->field(VField::TIMEOUT) ? true : false;
    }

    public function getCta()
    {
        return $this->hasField(VField::CTA) ? $this->field(VField::CTA) : null;
    }

    public function getCtaUrl()
    {
        return $this->hasField(VField::CTA_URL) ? $this->field(VField::CTA_URL) : null;
    }
}