<?php

/**
 * Roundcube Structured Email
 *
 * Provides support for structured email in Roundcube
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/action_button_util.php';
require __DIR__ . '/lib/compose_form_util.php';
require __DIR__ . '/lib/message_send_util.php';
require __DIR__ . '/lib/structured_data_util.php';
require __DIR__ . '/lib/template_container_util.php';

class roundcube_structured_email extends rcube_plugin
{
    /**
     * A regex-like variable which indicates that our plugin
     * should be active in all tasks excluding "login" and "logout"
     *
     * @var string $task
     */
    public $task = '?(?!login|logout).*';

    /**
     * Initialize the plugin
     *
     * @return void
     */
    function init()
    {
        // Load the configuration of the plugin
        $this->load_config();

        // Register our own "compose_form" task
        $this->register_task("compose_form");

        // Register the relevant hooks that we use in our plugin
        $this->add_hook('startup', [$this, 'on_startup_hook']);
        $this->add_hook('html_editor', [$this, 'on_html_editor_hook']);
        $this->add_hook('template_container', [$this, 'on_template_container_hook']);
        $this->add_hook('message_compose', [$this, 'on_message_compose_hook']);
        $this->add_hook('message_load', [$this, 'on_message_load_hook']);
        $this->add_hook('message_objects', [$this, 'on_message_objects_hook']);
        $this->add_hook('messages_list', [$this, 'on_messages_list_hook']);

        /**
         * Register an action handler for rendering
         * a custom screen in the "compose_form" task
         */
        $this->register_action('index', [$this, 'compose_form_action']);

        // Register an action handler for our custom AJAX action
        $this->register_action(
            'plugin.action_button_handler',
            [$this, 'action_button_handler_action']
        );

        // Register an action handler for creating an identity with a signature
        $this->register_action('create_identity', [$this, 'create_identity_action']);

        // Include our CSS file
        $this->include_stylesheet('roundcube_structured_email.css');
        
        // Include our JS files
        $this->include_script('mustache.min.js');
        $this->include_script('roundcube_structured_email.js');
        $this->include_script('js/action_buttons.js');
        $this->include_script('js/compose_form.js');
        $this->include_script('js/geolocation_send.js');
        $this->include_script('js/insert_structured_data.js');
        $this->include_script('js/render_structured_data.js');
        $this->include_script('js/signature.js');
        $this->include_script('js/util.js');
    }

    /**
     * A handler function for the "startup" hook
     *
     * @param array $args The arguments, passed to the "startup" hook
     *
     * @return array The hook's arguments (in our case, unmodified)
     */
    function on_startup_hook($args)
    {
        $rcmail = rcmail::get_instance();

        // Upon startup, add custom buttons in the taskbar for
        // the "compose_form" task and for structured mail composition
        if (!$rcmail->output->framed) {
            $this->add_button(
                array(
                    'command' => 'compose_form',
                    'class' => 'button-compose-form',
                    'classsel' => 'button-compose-form button-selected',
                    'innerclass' => 'button-inner',
                    'type' => 'link'
                ),
                'taskbar'
            );

            $username = $rcmail->user->get_username('mail');

            $this->add_button(
                array(
                    'command' => 'Geolocation',
                    'class' => 'button-geolocation',
                    'classsel' => 'button-geolocation button-selected',
                    'innerclass' => 'button-inner',
                    'type' => 'link',
                    'onclick' => "return rcmail.send_geolocation_structured_email('"
                        . $username
                        . "')"
                ),
                'taskbar'
            );
        }

        return $args;
    }

    /**
     * A handler function for the "html_editor" hook
     *
     * @param array $args The arguments, passed to the "html_editor" hook
     *
     * @return array The hook's arguments (in our case, unmodified)
     */
    function on_html_editor_hook($args)
    {
        // We enable the "noneditable" plugin of Roundcube's HTML editor (TinyMCE)
        $args['extra_plugins'][] = 'noneditable';
        return $args;
    }

    /**
     * A handler function for the "template_container" hook
     *
     * @param array $args The arguments, passed to the "template_container" hook
     *
     * @return array The hook's arguments
     */
    function on_template_container_hook($args)
    {
        return template_container_util::on_template_container_hook($args);
    }

    /**
     * A handler for the "message_before_send" hook
     * We use it to turn a hidden JSON-LD from a "div" into a "script" tag
     * (used when composing messages with structured data via our plugin)
     *
     * @param $args array The hook arguments that we receive
     *
     * @return array The (potentially) modified hook arguments
     */
    function on_message_before_send_hook($args)
    {
        return message_send_util::on_message_before_send_hook($args);
    }

    /**
     * A handler function for the "message_compose" hook
     *
     * @param array $args The arguments, passed to the "message_compose" hook
     *
     * @return array The hook's arguments
     */
    function on_message_compose_hook($args)
    {
        $param = $args['param'];
        $param['body'] .= structured_data_util::compose_structured_data_message(
            $param
        );

        $param['html'] = 1;
        return array('param' => $param);
    }
    
    /**
     * A callback for the 'message_objects' plugin hook
     * Used to modify the message body by rendering structured email HTML
     *
     * @param array $args The hook arguments, used in the hook
     *
     * @return array
     */
    public function on_message_objects_hook($args)
    {
        $rcmail = rcmail::get_instance();

        // A variable which holds an extracted JSON-LD with structured data
        $jsonLd = '';

        // A variable which holds the context type of a JSON-LD with structured data
        $contextType = '';

        // Obtain the arguments for the hook
        $content = $args['content'];
        $message = $args['message'];
        
        // Get the message's sender
        $messageSender = $message->sender['mailto'];

        // Check if we have a "text/html" part in the message
        foreach ($message->parts as $part) {
            if ($part->ctype_primary === 'text'
                && $part->ctype_secondary === 'html'
            ) {
                // Get the HTML part of the message's body
                $htmlBody = $message->get_part_body($part->mime_id, true);
                
                // Try to extract JSON-LD from the HTML part
                $jsonLd = structured_data_util::html2jsonld($htmlBody);

                // Get the message's "from" header
                $from = $message->get_header('from');

                /**
                 * Try to extract a JSON-LD once more if there's no JSON-LD so far
                 * and if the "from" header contains "@aohostels.com"
                 */
                if (empty($jsonLd) && strpos($from, '@aohostels.com') !== false) {
                    $jsonLd = structured_data_util::extract_json_ld(
                        $message->uid
                    );
                }

                // Set the context type appropriately
                $contextType = structured_data_util::get_context_type($jsonLd);
            }
        }

        // The type of a trusted sender, we'll need it below
        $trustedSenderType = rcube_addressbook::TYPE_TRUSTED_SENDER;

        /**
         * Check if we have email markup and if
         * the address of the message's sender is not empty
         */
        if (isset($jsonLd) && !empty($jsonLd) && !empty($messageSender)) {
            /**
             * If the sender doesn't exist as a trusted sender,
             * add a button to make them a trusted sender
             */
            if (!$rcmail->contact_exists($messageSender, $trustedSenderType)) {
                $msg = html::span(
                    null,
                    rcube::Q(
                        'This message contains structured email data.'
                    )
                );
                $button = html::a(
                    [
                        'href' => "#loadremotealways",
                        'onclick' =>
                            rcmail_output::JS_OBJECT_NAME
                            . ".add_contact('$messageSender', true, "
                            . "$trustedSenderType);"
                            
                            . rcmail_output::JS_OBJECT_NAME
                            . ".show_message(rcmail.env.uid, true,"
                            . " rcmail.env.action == 'preview');"

                            . "rcmail.unhide_message_list_buttons(rcmail.env.uid);",
                        'style' => "white-space:nowrap"
                    ],
                    rcube::Q(
                        "Allow structured email content"
                        . " and make sender a trusted sender"
                    )
                );
                $attrib['id'] = 'structured-data-message';
                $attrib['class'] = 'notice';
                $div = html::div(
                    $attrib,
                    $msg . '&nbsp;' . html::span('boxbuttons', $button)
                );

                array_push($content, $div);
            }
        }

        $showStructuredEmailForTrustedSenders = $rcmail->config->get(
            'showStructuredEmailForTrustedSenders',
            false
        );

        /**
         * Only show structured email data if:
         * * our config flag allows it
         * * and the sender is a trusted one
         */
        if ($showStructuredEmailForTrustedSenders
            && $rcmail->contact_exists($messageSender, $trustedSenderType)
        ) {
            $renderStructuredDataOnBackend = $rcmail->config->get(
                'renderStructuredDataOnBackend',
                false
            );
            if ($renderStructuredDataOnBackend) {
                structured_data_util::add_structured_data(
                    $content,
                    $message->uid,
                    $contextType,
                    $jsonLd
                );
            } else {
                // JSON-LD to send to UI for rendering structured data
                $jsonDiv = html::div(
                    ['id' => 'jsonDiv', 'style' => 'display:none;'],
                    html::p(['id' => 'jsonDivP'], $jsonLd)
                    . html::p(['id' => 'jsonDivMsgUid'], $message->uid)
                    . html::p(
                        ['id' => 'jsonDivUsername'],
                        $rcmail->user->get_username()
                    )
                );
                array_push($content, $jsonDiv);

                $mustacheTemplateDiv = html::div(
                    ['id' => 'mustacheTemplateDiv', 'style' => 'display:none;'],
                    structured_data_util::get_mustache_template($contextType)
                );
                array_push($content, $mustacheTemplateDiv);
            }
        }

        return array('content' => $content);
    }

    /**
     * A callback for the 'messages_list' plugin hook
     *
     * @param array $args The hook arguments
     *
     * @return void
     */
    public function on_messages_list_hook($args)
    {
        action_button_util::on_messages_list_hook($args);
    }

    /**
     * Handler function for our "index" action
     * Main task here is to render the "compose_form" template of our plugin
     *
     * @return void
     */
    function compose_form_action()
    {
        /**
         * We need a non-static method here, because it internally needs to
         * reference other methods of its class non-statically
         */
        (new compose_form_util())->render_compose_form();
    }

    /**
     * A handler function for our AJAX action for handling
     * when action buttons are clicked
     *
     * @return void
     */
    function action_button_handler_action()
    {
        action_button_util::action_button_handler_action();
    }

    /**
     * A handler function for our AJAX action for creating a new
     * identity with a signature for the currently logged-in user in Roundcube
     *
     * @return void
     */
    function create_identity_action()
    {
        signature_util::create_identity_action();
    }
}
