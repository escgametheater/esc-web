<?php

//require "vendor/autoload.php";

use Mailgun\Mailgun;


class Net_Gearman_Job_send_email extends Task
{
    protected $email_record = [];
    protected $args = [];
    protected $name = 'send_email';
    protected $request;

    public function process(Request $request, $args)
    {
        $headers = array_key_exists('headers', $args) ? $args['headers'] : [];
        if (array_get($args, 'html', false)) {
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-type: text/html; charset=utf-8";
        }

        if (!array_key_exists('title', $args) ||
            !array_key_exists('body', $args) ||
            !array_key_exists('from', $args) ||
            !array_key_exists('dest', $args) ||
            !is_array($headers))
            throw new Net_Gearman_Job_Exception('Invalid/Missing arguments');

        $headers[] = 'From: '.$this->getArg('from');
        $title = $this->getArg('title');
        $dest = $this->getArg('dest');
        $from = $this->getArg('from');
        $body = $this->getArg('body');

        $etId = $this->getArg(DBField::ET_ID);

        $emailTrackingManager = $request->managers->emailTracking();

        $email = $emailTrackingManager->getEmailRecordById($request, $etId);

        //$emailHistoryManager = $request->managers->oldSentEmail();

        // If this node is configured to send emails, let's handle that case first.
        if ($request->config['send_emails']) {

            // Let's check if we should send this email. If the email record exists already and is marked as sent, we
            // should not try to send it again. This allows us to replay tasks without spamming users with the same
            // email over and over.
            if ($email) {

                if (!$email->is_sent()) {

                    std_log('sending email to '.$dest);

                    # Instantiate the client.
                    $mgClient = new Mailgun($request->config['mailgun']['api_key']);


                    if ($request->settings()->is_dev())
                        $domain = $request->config['mailgun']['local_email_domain'];
                    else
                        $domain = $request->config['mailgun']['email_domain'];

                    std_log("from: {$from}");


                    # Make the call to the client.
                    $r = $mgClient->sendMessage($domain, array(
                        'from'    => $from,
                        'to'      => $dest,
                        //'cc'      => 'baz@example.com',
                        //'bcc'     => 'bar@example.com',
                        'subject' => $title,
                        'text'    => 'N/A',
                        'html'    => $body
                    ));

                    std_log(json_encode($r));

                    // If the email sent successfully and we have an associated email tracking ID, let's mark it as sent.
                    if ($r)
                        $emailTrackingManager->markEmailAsSent($request, $email);
                    else
                        // If we tried to send the email, but it failed, we should throw an exception because something is
                        // wrong and we need to look at the logs to investigate.
                        throw new Net_Gearman_Job_Exception('Failed to send email');

                } else {
                    $this->logEmailAlreadySent();
                }

            } else {
                std_log('Email ' .$this->args[DBField::ET_ID] .' not found.');
            }

        } else {
            // If this host is not configured to send emails, we handle email sending to stdout instead of using the
            // mailer package.
            if ($email && !$email->is_sent()) {
                std_log("Title: ${title}");
                std_log("Headers:");
                foreach ($headers as $h)
                    std_log($h);
                std_log("Body: (DISABLED)");

                // If we have an email tracking ID, let's mark it as sent.
                $emailTrackingManager->markEmailAsSent($request, $email);
            } else {
                $this->logEmailAlreadySent();
            }
        }
    }

    public function logEmailAlreadySent()
    {
        std_log('Email ' .$this->args[DBField::ET_ID] .' was already sent.');
    }
}
