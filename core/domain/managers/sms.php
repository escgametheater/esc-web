<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/15/18
 * Time: 12:20 PM
 */

Entities::uses('sms');
class SmsManager extends BaseEntityManager {

    protected $entityClass = SmsEntity::class;
    protected $table = Table::Sms;
    protected $table_alias = TableAlias::Sms;
    protected $pk = DBField::SMS_ID;

    protected $foreign_managers = [
    ];

    public static $fields = [
        DBField::SMS_ID,
        DBField::SMS_TYPE_ID,
        DBField::IS_SENT,
        DBField::SCHEDULE_TIME,
        DBField::USER_ID,
        DBField::TO_NUMBER,
        DBField::FROM_NUMBER,
        DBField::BODY,
        DBField::SENT_TIME,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param SmsEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::SCREENS, []);
    }


    /**
     * @param Request $request
     * @param string $toNumber
     * @param string $fromNumber
     * @param string $body
     * @param null|string $scheduleTime
     * @param null|int $userId
     * @return SmsEntity
     */
    public function createNewSms(Request $request, $smsTypeId, $toNumber, $fromNumber, $body, $scheduleTime = null, $userId = null)
    {
        if (!$scheduleTime)
            $scheduleTime = $request->getCurrentSqlTime();

        $data = [
            DBField::SMS_TYPE_ID => $smsTypeId,
            DBField::IS_SENT => 0,
            DBField::SCHEDULE_TIME => $scheduleTime,
            DBField::USER_ID => $userId,
            DBField::FROM_NUMBER => $fromNumber,
            DBField::TO_NUMBER => $toNumber,
            DBField::BODY => $body,
            DBField::CREATE_TIME => $request->getCurrentSqlTime()
        ];

        /** @var SmsEntity $sms */
        $sms = $this->query($request->db)->createNewEntity($request, $data);

        return $sms;
    }


    /**
     * @param Request $request
     * @param $smsTypeId
     * @param $toNumber
     * @param $body
     * @param null $scheduleTime
     * @param null $userId
     * @return SmsEntity
     */
    public function scheduleSms(Request $request, $smsTypeId, $toNumber, $body, $scheduleTime = null, $userId = null)
    {
        if ($request->settings()->is_dev()) {
            $fromNumber = $request->config['twilio']['test']['from_number'];
        } else {
            $fromNumber = $fromNumber = $request->config['twilio']['live']['from_number'];
        }

        $sms = $this->createNewSms($request, $smsTypeId, $toNumber, $fromNumber, $body, $scheduleTime, $userId);

        if (time_gte($request->getCurrentSqlTime(), $sms->getScheduleTime())) {
            $this->triggerSendSmsTask($sms);
        }

        return $sms;
    }

    /**
     * @param Request $request
     * @param SmsEntity $sms
     */
    public function triggerSendSmsTask(SmsEntity $sms)
    {
        TasksManager::add(TasksManager::TASK_ADD_SEND_SMS, [
            DBField::SMS_ID => $sms->getPk()
        ]);
    }

    /**
     * @param Request $request
     * @param $smsId
     * @return array|SmsEntity
     */
    public function getMessageById(Request $request, $smsId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($smsId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $scheduleTime
     * @return SmsEntity[]
     */
    public function getUnsentMessagesByScheduleTime(Request $request, $scheduleTime)
    {
        return $this->query($request->db)
            ->filter($this->filters->isNotSent())
            ->filter($this->filters->byScheduleTimeLte($scheduleTime))
            ->get_entities($request);
    }

}

class SmsTypesManager extends BaseEntityManager
{
    const ID_GAME_INSTANCE_ADMIN = 1;
    const ID_GAME_INSTANCE_PLAYER = 2;
    const ID_HOST_INSTANCE_ADMIN = 3;
    const ID_HOST_INSTANCE_PLAYER = 4;

    protected $entityClass = SmsTypeEntity::class;
    protected $table = Table::SmsType;
    protected $table_alias = TableAlias::SmsType;
    protected $pk = DBField::SMS_TYPE_ID;

    protected $foreign_managers = [

    ];

    public static $fields = [
        DBField::SMS_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];
}