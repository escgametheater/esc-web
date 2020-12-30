<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/14/18
 * Time: 12:46 PM
 */

require "vendor/autoload.php";

use Twilio\Rest\Client;


class Net_Gearman_Job_send_sms extends Task
{
    protected $email_record = [];
    protected $args = [];
    protected $name = 'send_sms';
    protected $request;

    /**
     * @param Request $request
     * @param $args
     * @throws Net_Gearman_Job_Exception
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function process(Request $request, $args)
    {
        if (!$smsId = $this->getArg(DBField::SMS_ID))
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments');

        $smsManager = $request->managers->sms();

        $sms = $smsManager->getMessageById($request, $smsId);

        // Your Account SID and Auth Token from twilio.com/console
        $accountSid = $request->config['twilio']['test']['sid'];
        $token = $request->config['twilio']['test']['token'];

        $client = new Client($accountSid, $token);

        try {
            $message = [
                'from' => $sms->getFromNumber(),
                'body' => $sms->getBody()
            ];

            $client->messages->create($sms->getToNumber(), $message);

            $smsData = [
                DBField::SENT_TIME => $request->getCurrentSqlTime(),
                DBField::IS_SENT => 1
            ];

            $sms->assign($smsData)->saveEntityToDb($request);

        } catch (\Twilio\Exceptions\TwilioException $e) {

        }

    }
}
