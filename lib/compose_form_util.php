<?php

/**
 * Provides utility functions, related to our custom compose form,
 * displayed in the "compose_form" task
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */

class compose_form_util
{
    /**
     * A utility function which renders our custom compose form
     *
     * @return void
     */
    public function render_compose_form()
    {
        $rcmail = rcmail::get_instance();

        if ($rcmail->task === 'compose_form') {
            $rcmail->output->set_pagetitle('Compose Form');
            
            $rcmail->output->add_handlers(
                array(
                    'subjectfield' => array($this, 'subjectfield'),
                    'composeheaders' => array($this, 'composeheaders'),
                    'composerequest' => array($this, 'composerequest'),
                    'structuredform' => array($this, 'structuredform'),
                    'sendbtn' => array($this, 'sendbtn')
                )
            );

            // Need to generate a unique compose ID
            // and provide it to the client as an env var
            $compose_id = uniqid(mt_rand());
            $rcmail->output->set_env('compose_id', $compose_id);

            // That's a hack that we need to make (which is taken from compose.php)
            // Otherwise, sending an email message from our custom form doesn't work
            $_SESSION['compose_data_' . $compose_id] = [
                'id' => $compose_id
            ];

            // Send our "compose_form" template to the UI
            $rcmail->output->send('roundcube_structured_email.compose_form');
        }
    }

    /**
     * A utility function to render the subject field of our compose form
     *
     * @return string The HTML string, representing the subject field
     */
    function subjectfield()
    {
        return html::tag(
            'input',
            [
                'name' => '_subject',
                'id' => '_subject',
                'type' => 'text'
            ]
        );
    }

    /**
     * A utility function to render compose headers in our compose form
     *
     * @param array $attrib The attributes, passed for the
     *                      creation of the HTML elements
     *
     * @return string The HTML string of a compose header
     */
    function composeheaders($attrib)
    {
        $part = strtolower($attrib['part']);
        $id = strtolower($attrib['id']);
        return html::tag(
            'input',
            [
                'name' => '_' . $part,
                'type' => 'text',
                'id' => $id
            ]
        );
    }

    /**
     * A utility function to render a request select field in our compose form
     *
     * @param array $attrib The attributes, passed for the
     *                      creation of the HTML element
     *
     * @return string The HTML string of the select field
     */
    function composerequest($attrib)
    {
        $request_list = [
            'GeoLocation' => json_encode(
                (object)[
                    'Latitude' => 'text',
                    'Longitude' => 'text',
                    'Name' => 'text'
                ]
            ),
            'MusicAlbum' => json_encode(
                (object)[
                    'AlbumName' => 'text'
                ]
            )
        ];

        $selector = new html_select($attrib);
        $selector->add(array_keys($request_list), array_values($request_list));

        $out = $selector->show();

        return $out;
    }

    /**
     * A utility function to render a div for the compose form
     *
     * @return string The HTML string of the div
     */
    function structuredform()
    {
        return html::div(
            [
                'id' => 'structuredcomposeform',
                'class' => 'col-10',
                'style' => 'display:flex;flex-direction:column;margin-bottom:2%;'
                    . 'border:1px solid black;padding:10px;'
            ]
        );
    }
    
    /**
     * A utility function to the submit button of the compose form
     *
     * @return string The HTML string of the submit button
     */
    function sendbtn()
    {
        return html::tag(
            'input',
            [
                'type' => 'submit',
                'value' => 'Submit',
                'onclick' => "return rcmail.command"
                    . "('sendstructuredform','',this,event)"
            ]
        );
    }
}
