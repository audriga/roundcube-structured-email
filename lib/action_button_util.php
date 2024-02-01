<?php

/**
 * Provides utility functions, related to action buttons
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */

class action_button_util
{
    public static function on_messages_list_hook($args)
    {
        $rcmail = rcmail::get_instance();

        // Obtain the message headers from the hook
        $messages = $args['messages'];

        $messageActions = [];
        $messagesFromTrustedSenders = [];
        foreach ($messages as $message) {
            // Create a message objec from the message UID of each message
            $messageObject = new rcube_message($message->uid);

            // Check if we have a "text/html" part in the message
            foreach ($messageObject->parts as $part) {
                if ($part->ctype_primary === 'text'
                    && $part->ctype_secondary === 'html'
                ) {
                    // Get the HTML part of the message's body
                    $htmlBody = $messageObject->get_part_body($part->mime_id, true);
                    
                    // Try to extract JSON-LD from the HTML part
                    $jsonLd = structured_data_util::html2jsonld($htmlBody);

                    // Get the message's "from" header
                    $from = $messageObject->get_header('from');

                    /**
                     * Try to extract a JSON-LD once more if there's no JSON-LD so far
                     * and if the "from" header contains "@aohostels.com"
                     */
                    if (empty($jsonLd) && strpos($from, '@aohostels.com') !== false) {
                        $jsonLd = structured_data_util::extract_json_ld(
                            $message->uid
                        );
                    }

                    $jsonLd = json_decode($jsonLd, true);

                    if (isset($jsonLd) && !empty($jsonLd) && isset($jsonLd['potentialAction'])) {
                        $messageActions[$message->uid] = $jsonLd;
                    }
                }
            }

            $showStructuredEmailForTrustedSenders = $rcmail->config->get(
                'showStructuredEmailForTrustedSenders',
                false
            );

            /**
             * For each message, keep track if it's from a trusted sender.
             * We need this in the UI in order to always show action buttons
             * for messages from trusted senders
             */
            if ($showStructuredEmailForTrustedSenders
                && $rcmail->contact_exists(
                    $messageObject->sender['mailto'],
                    rcube_addressbook::TYPE_TRUSTED_SENDER
                )
            ) {
                $messagesFromTrustedSenders[$message->uid] = true;
            } else {
                $messagesFromTrustedSenders[$message->uid] = false;
            }
        }

        // Add an env variable that is sent to the UI
        // This env variable contains a mapping of message UID to JSON-LD objects
        $rcmail->output->set_env("messageActions", $messageActions);

        /**
         * Add an env variable with the messages,
         * flagged as stemming from trusted or untrusted senders
         */
        $rcmail->output->set_env(
            "messagesFromTrustedSenders",
            $messagesFromTrustedSenders
        );
    }

    public static function action_button_handler_action()
    {
        // Get all POST parameters from the frontend
        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $url = rcube_utils::get_input_value('_url', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $type = rcube_utils::get_input_value('_type', rcube_utils::INPUT_POST);
        
        $actionStatus = false;
        $rcmail = rcmail::get_instance();

        if (isset($uid) && isset($url)) {
            // If the URL is a "mailto:" one,
            // send a programmatic email to the URL's email target
            if (strpos($url, 'mailto:') !== false) {
                $recipient = explode(':', $url)[1];
                $actionStatus = self::send_programmatic_email(
                    $mbox,
                    $recipient,
                    $uid
                );
            } else {
                // In the other case (currently never taken as a branch),
                // we send a cURL HTTP POST request to the URL,
                // specified in the "url" POST param from the frontend
                $actionStatus = self::send_post_request($url);
            }
            
            // Send the information about the action status back to the frontend
            $rcmail->output->command(
                'plugin.action_callback',
                array(
                    'uid' => $uid,
                    'url' => $url,
                    'mbox' => $mbox,
                    'actionStatus' => $actionStatus,
                    'type' => $type
                )
            );
        }
    }

    /**
     * A function for sending an email message programmatically
     *
     * @param string $from The mailbox (email address) of the sender
     * @param string $to   The mailbox (email address) of the receiver
     * @param string $msgUid The UID of the message which has the structured data
     *
     * @return boolean True on successful message send, false otherwise
     */
    private static function send_programmatic_email($from, $to, $msgUid)
    {
        $rcmail = rcmail::get_instance();

        // Initialize an rcmail_sendmail object which we use for sending the message
        $SENDMAIL = new rcmail_sendmail(
            [],
            [
                'sendmail' => true,
                'saveonly' => false,
                'savedraft' => false,
                'error_handler' => function (...$args) use ($rcmail) {
                    call_user_func_array(
                        [$rcmail->output, 'show_message'],
                        $args
                    );
                    $rcmail->output->send('iframe');
                },
                'charset' => 'UTF-8',
                'keepformatting' => false,

                /**
                 * Note: currently the message is sent from the currently
                 * logged-in user in Roundcube to themselves.
                 * That's why 'from' and 'mailto' below are the same
                 */
                'from' => $from,
                'mailto' => $to
            ]
        );

        // Define the message headers
        $headers = [
            'Date' => $rcmail->user_date(),
            'From' => $SENDMAIL->email_input_format($from, true),
            'To' => $SENDMAIL->email_input_format($to, true),
            'Subject' => 'Test programmatic email send',
            'Reply-To' => $SENDMAIL->email_input_format($msgUid, true),
            'Message-ID' => $rcmail->gen_message_id($from),
            'X-Sender' => $SENDMAIL->email_input_format($from, true)
        ];
        
        // Define the message body
        $body = "
            <html>
                <head>
                    <title>A test HTML title</title>
                </head>
                <body>
                    <p>A test programmatic send</p>
                </body>
                </html>
        ";

        // Specify that the message has an HTML body
        $isHtml = true;

        // Create a message object
        $MAIL_MIME = $SENDMAIL->create_message($headers, $body, $isHtml, []);

        // Deliver the message
        $sendStatus = $SENDMAIL->deliver_message($MAIL_MIME);

        // Return back the status of sending the message
        return $sendStatus;
    }

    /**
     * A function for sending a POST HTTP request to a given URL
     *
     * @param string $url The URL to send the POST request to
     *
     * @return boolean True if the POST request receives a success
     *                 or a redirect status code back, false otherwise
     */
    private static function send_post_request($url)
    {
        $ch = curl_init();

        $postfields = array(
            'confirmed' => 'Approved'
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Note: we need to URL-decode the "url" POST param we get
        // from the frontend, since the frontend sends it URL-encoded
        curl_setopt($ch, CURLOPT_URL, urldecode($url));

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

        $result = curl_exec($ch);

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            rcube::raise_error("curl error: $error", true, false);
        }

        // If the HTTP status code is set to "false", then something was wrong
        if ($httpStatusCode === false) {
            return false;
        }

        // In case we get an HTTP status code between 200 and 399, then
        // we consider the action of the clicked button to be successful
        if ($httpStatusCode < 200 || $httpStatusCode > 399) {
            return false;
        } else {
            return true;
        }
    }
}
