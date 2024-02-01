<?php

/**
 * Provides utility functions, related to the sending of email messages
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */

class message_send_util
{
    /**
     * A handler for the "message_before_send" hook
     * We use it to turn a hidden JSON-LD from a "div" into a "script" tag
     * (used when composing messages with structured data via our plugin)
     *
     * @param $args array The hook arguments that we receive
     *
     * @return array The (potentially) modified hook arguments
     */
    public static function on_message_before_send_hook($args)
    {
        // Move structured data from a hidden div to a script tag
        $args = self::fix_structured_data_before_send($args);

        return $args;
    }

    /**
     * Helper function for moving structured data from a hidden div to a script tag
     *
     * @param array $args The arguments of the "message_before_send" hook
     *
     * @return array The updated arguments of the "message_before_send" hook
     */
    private static function fix_structured_data_before_send($args)
    {
        $jsonDivOpeningTag = '<div id="jsonDivBeforeSend" style="display: none;">';
        $jsonDivClosingTag = '</div>';
        $message = $args['message'];

        $htmlBody = $message->getHTMLBody();

        // Extract the "div" that contains the hidden JSON-LD
        $startDivTag = strpos($htmlBody, $jsonDivOpeningTag);
        $endDivTag = strpos($htmlBody, $jsonDivClosingTag);

        $extractedJsonLd = '';
        if ($startDivTag && $endDivTag) {
            $extractedJsonLd = substr(
                $htmlBody,
                $startDivTag + strlen($jsonDivOpeningTag),
                $endDivTag - ($startDivTag + strlen($jsonDivOpeningTag))
            );
        }

        // Turn the "div" with JSON-LD into a "script" tag with JSON-LD
        if (isset($extractedJsonLd) && !empty($extractedJsonLd)) {
            $htmlBody = str_replace(
                $jsonDivOpeningTag . $extractedJsonLd . $jsonDivClosingTag,
                '<script type="application/ld+json">'
                . $extractedJsonLd
                . '</script>',
                $htmlBody
            );

            $message->setHTMLBody($htmlBody);
        }

        // Send the now modified message
        $args['message'] = $message;
        return $args;
    }
}
