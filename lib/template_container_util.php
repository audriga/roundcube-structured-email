<?php

/**
 * A utility class providing functionality, used for handling
 * the "template_container" hook
 *
 * @category Plugin for Roundcube Webmail
 * @author   Stanimir Bozhilov <stanimir@audriga.com>
 * @license  TODO
 */
class template_container_util
{
    /**
     * A handler function for the "template_container" hook.
     *
     * This function is called by the main plugin class
     *
     * @param array $args The arguments, passed to the "template_container" hook
     *
     * @return array The hook's arguments
     */
    public static function on_template_container_hook($args)
    {
        $rcmail = rcmail::get_instance();
        $name = $args['name'];
        $content = $args['content'];

        // Render a button in the 'compose' action which enables the user
        // to insert structured data in the message's body
        if (strcmp($rcmail->action, 'compose') === 0
            && strcmp($name, 'toolbar') === 0
            && strcmp($args['id'], 'compose-toolbar') === 0
        ) {
            $content .= html::a(
                [
                    'class' => 'attach active',
                    'href' => '#',
                    'title' => 'Add Structured Data',
                    'data-popup' => 'structured-templates-list'
                ],
                'Add Structured Data'
            );

            $templatesList = [];
            $ul_attr = [
                'role' => 'menu',
                'class' => 'menu listing'
            ];

            $templatesList = new html_table(
                [
                    'id' => 'templates-list',
                    'tagname' => 'ul',
                    'cols' => '1',
                    'itemclass' => ''
                ]
            );

            $item = html::a(
                [
                    'href' => '#',
                    'unselectable' => 'on',
                    'tabindex' => '0',
                    'onclick' => sprintf(
                        "return %s.command('insert-structured-data',"
                        . " '%s', this, event)",
                        rcmail_output::JS_OBJECT_NAME,
                        'geoLocation'
                    ),
                ],
                'Geo Location'
            );

            $item2 = html::a(
                [
                    'href' => '#',
                    'unselectable' => 'on',
                    'tabindex' => '0',
                    'onclick' => sprintf(
                        "return %s.command('insert-structured-data',"
                        . " '%s', this, event)",
                        rcmail_output::JS_OBJECT_NAME,
                        'musicAlbum'
                    ),
                ],
                'Music Album'
            );

            $templatesList->add([], $item);
            $templatesList->add([], $item2);


            $rcmail->output->add_footer(
                html::div(
                    [
                        'id' => 'structured-templates-list',
                        'class' => 'popupmenu'
                    ],
                    html::tag('h2', ['class' => 'voice'], 'Templates Test')
                    . html::tag('ul', $ul_attr, $templatesList->show())
                )
                . html::div(
                    [
                        'id' => 'structured-templates-dialog-geo-location',
                        'class' => 'popupmenu'
                    ],
                    html::tag('label', [], 'Latitude')
                    . html::br()
                    . html::tag(
                        'input',
                        [
                            'type' => 'text',
                            'id' => 'geo-location-lat-input'
                        ]
                    )
                    . html::tag('label', [], 'Longitude')
                    . html::br()
                    . html::tag(
                        'input',
                        [
                            'type' => 'text',
                            'id' => 'geo-location-lon-input'
                        ]
                    )
                    . html::tag('label', [], 'Name')
                    . html::br()
                    . html::tag(
                        'input',
                        [
                            'type' => 'text',
                            'id' => 'geo-location-name-input'
                        ]
                    )
                )
                . html::div(
                    [
                        'id' => 'structured-templates-dialog-music-album',
                        'class' => 'popupmenu'
                    ],
                    html::tag('label', [], 'Album Name')
                    . html::br()
                    . html::tag(
                        'input',
                        [
                            'type' => 'text',
                            'id' => 'music-album-name-input'
                        ]
                    )
                )
            );
        }

        $args['content'] = $content;

        return $args;
    }
}
