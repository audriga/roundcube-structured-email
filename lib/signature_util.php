<?php

/**
 * Provides utility functions, related to structured data for email signatures
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */

class signature_util
{
    public static function create_identity_action()
    {
        $signature = rcube_utils::get_input_value(
            '_signature',
            rcube_utils::INPUT_POST
        );

        $rcmail = rcmail::get_instance();
        $rcmail->user->insert_identity(
            [
                'name' => 'My New Identity',
                'organization' => '',
                'email' => $rcmail->user->get_username(),
                'signature' => $signature,
                'html_signature' => '0'
            ]
        );
    }
}
