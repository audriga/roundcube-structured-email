<?php

/**
 * A utility class for working with structured data
 *
 * @author Stanimir Bozhilov <stanimir@audriga.com>
 */
class structured_data_util
{
    /**
     * A function for creating <a> tags (links) in RC
     *
     * @param string $url The URL which we're creating a link to
     * @param string $content The text content of the link
     *
     * @return string The HTML string, representing the link
     */
    public static function create_link($url, $content)
    {
        $prefix = '';
        $target = '';

        if (preg_match('/^(mailto|http|https)(:\/\/|:)(.*)$/', $url, $matches)) {
            $prefix = $matches[1];
            $target = $matches[3];
        }

        if (strcmp($prefix, 'mailto') === 0) {
            $onclick = "return rcmail.command('compose', '$target', this)";
        } else {
            $onclick = "";
        }

        $link = html::a(
            array(
                'href' => $url,
                'target' => '_blank',
                'onclick' => $onclick
            ),
            $content
        );

        return $link;
    }

    public static function get_mustache_template($contextType)
    {
        switch ($contextType) {
            case 'flight':
                return file_get_contents(__DIR__ . '/../mustache_templates/reservation_flight.html');
            
            case 'album':
                return file_get_contents(__DIR__ . '/../mustache_templates/music_album.html');

            case 'geo':
                return file_get_contents(__DIR__ . '/../mustache_templates/place_geo.html');

            case 'signature':
                return file_get_contents(__DIR__ . '/../mustache_templates/signature.html');

            case 'lodging':

            default:
                return file_get_contents(__DIR__ . '/../mustache_templates/default_placeholder.html');
        }
    }

    /**
     * Extract a JSON-LD from a raw message
     *
     * @param string $uid The UID of the message
     *                    we want to extract JSON-LD from
     *
     * @return string The extracted JSON-LD
     */
    public static function extract_json_ld($uid)
    {
        $rcmail = rcmail::get_instance();

        // We need to obtain the raw message body and pass it to kitinerary
        $rawMessage = $rcmail->storage->get_raw_body($uid);

        $descriptors = [
            0 => ["pipe", "r"], // STDIN
            1 => ["pipe", "w"], // STDOUT
        ];

        $proc = proc_open(
            __DIR__ . '/bin/kitinerary-extractor',
            $descriptors,
            $pipes
        );

        if (!is_resource($proc)) {
            return '';
        }

        // Give the raw message as input to kitinerary
        fwrite($pipes[0], $rawMessage);
        fclose($pipes[0]);

        // Read the JSON-LD as output, produced by kitinerary
        $extractedJsonLd = stream_get_contents($pipes[1]);

        fclose($pipes[1]);
        $ret = proc_close($proc);

        return $extractedJsonLd;
    }

    /**
     * Generate action buttons
     *
     * @param string $msgUid The UID of the message we want to generate buttons for
     * @param string $jsonLd The JSON-LD containing potential actions
     *
     * @return string The action buttons' HTML
     */
    public static function create_action_buttons($msgUid, $jsonLd)
    {
        $rcube = rcube::get_instance();

        $jsonLd = json_decode($jsonLd, true);
        $messageActions = $jsonLd['potentialAction'];
        if (isset($messageActions) && !empty($messageActions)) {
            // Create action buttons that we'll put below the structured data
            $actionButtons = '';
            foreach ($messageActions as $action) {
                if ($action['@type'] === 'ConfirmAction') {
                    $actionButtons .= html::tag(
                        'button',
                        [
                            'style' => 'margin-left:10px;float:right;',
                            'title' => $action['name'],
                            'id' => 'actionButton' . $action['@type'] . $msgUid,
                            'class' => 'btn btn-primary actionButton',
                            'onclick' => 'call_action(\'' . $msgUid . '\', \''. $action['target'] . '\', \'' . $rcube->user->get_username() . '\', \'' . $action['@type'] . '\')'
                        ],
                        'Confirm'
                    );
                } else if ($action['@type'] === 'CancelAction') {
                    $actionButtons .= html::tag(
                        'button',
                        [
                            'style' => 'margin-left:10px;float:right;',
                            'title' => $action['name'],
                            'id' => 'actionButton' . $action['@type'] . $msgUid,
                            'class' => 'btn btn-primary actionButton',
                            'onclick' => 'call_action(\'' . $msgUid . '\', \''. $action['target'] . '\', \'' . $rcube->user->get_username() . '\', \'' . $action['@type'] . '\')'
                        ],
                        'Cancel'
                    );
                } else if ($action['@type'] === 'ViewAction') {
                    $actionButtons .= html::tag(
                        'button',
                        [
                            'style' => 'margin-left:10px;float:right;',
                            'title' => $action['name'],
                            'id' => 'actionButton' . $action['@type'] . $msgUid,
                            'class' => 'btn btn-primary actionButton',
                            'onclick' => 'window.location = \'' . $action['target'] . '\''
                        ],
                        'View'
                    );
                }
            }

            return $actionButtons;
        }

        return '';
    }

    /**
     * Add HTML with structured data to a message's HTML body
     *
     * @param array  $content     The HTML body of the message
     * @param string $msgUid      The UID of the message
     * @param string $contextType The type of the JSON-LD context
     * @param string $jsonLd      The JSON-LD to pass to jsonld2html_dart
     *
     * @return void
     */
    public static function add_structured_data(
        &$content,
        $msgUid,
        $contextType,
        $jsonLd
    ) {
        $descriptors = [
            0 => ["pipe", "r"], // STDIN
            1 => ["pipe", "w"], // STDOUT
        ];

        $output = '';
        $rcube = rcube::get_instance();

        // If config says to not use a binary for converting the JSON-LD to HTML,
        // then we use a hardcoded HTML
        if (!$rcube->config->get('use_binary_for_json_ld', false)) {
            switch ($contextType) {
                case 'album':
                    $output = file_get_contents(__DIR__ . '/html/music_album.html');
                    break;
                
                case 'geo':
                    $output = file_get_contents(__DIR__ . '/html/place_geo.html');
                    break;

                case 'flight':
                    $output = file_get_contents(
                        __DIR__ . '/html/reservation_flight.html'
                    );
                    break;

                case 'lodging':
                    $output = file_get_contents(__DIR__ . '/html/lodging.html');
                    break;

                default:
                    $output = file_get_contents(__DIR__ . '/html/default_placeholder.html');
                    break;
            }
        } else {
            $proc = proc_open(
                __DIR__ . "/bin/jsonld2html_dart",
                $descriptors,
                $pipes
            );
    
            // If proc_open didn't work properly, then we just return from the hook
            // directly without modifying the message body
            if (!is_resource($proc)) {
                return;
            }
    
            // Write the JSON-LD that we extracted from the email
            // to STDIN (which is used as input by jsonld2html_dart)
            fwrite($pipes[0], $jsonLd);
            
            // Close STDIN right after we've written the JSON-LD to it
            fclose($pipes[0]);
    
            // Read the output from the jsonld2html_dart CLI
            // via STDOUT (jsonld2html_dart writes to STDOUT)
            $output = stream_get_contents($pipes[1]);
            
            // Close STDOUT after we've read from it
            fclose($pipes[1]);
    
            // If reading the output didn't work out, we close the proc
            // and exit without modifying the message body
            if ($output === false) {
                proc_close($proc);
                return;
            }
    
            $ret = proc_close($proc);
    
            // If the return code from the Dart CLI was not zero,
            // something probably went wrong, hence we don't modify the message body
            // and directly return
            if ($ret !== 0) {
                return;
            }
        }

        $actionButtons = self::create_action_buttons(
            $msgUid,
            $jsonLd
        );

        $actionButtonsSpan = html::div(
            [
                'id' => 'individualMessageActionButtons',
                'style' => 'background-color:#f1f1f1;float:right;align-self:flex-end;'
            ],
            $actionButtons
        );

        // If we found the HTML, we put into our div container
        // and append it to the contents of the message body
        $html = '<div class="info structured-data-container" style="background-color:#f1f1f1;width:100%;display:flex;flex-direction:column;">';
        $html .= $output;
        $html .= $actionButtonsSpan;
        $html .= '</div>';
        array_push($content, $html);
    }

    public static function compose_structured_data_message($param)
    {
        $rcube = rcube::get_instance();

        $body = '';
        if (isset($param['lat']) && isset($param['lon'])) {
            $jsonLdPlace = [
                '@context' => 'https://schema.org',
                '@type' => 'Place',
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $param['lat'],
                    'longitude' => $param['lon']
                ],
                'name' => 'Location'
            ];

            $jsonLdDiv = html::div(
                ['id' => 'jsonDivBeforeSend', 'style' => 'display:none;'],
                json_encode($jsonLdPlace)
            );

            $body .= $jsonLdDiv;

            $m = new Mustache_Engine(
                array(
                    'entity_flags' => ENT_QUOTES,
                    'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/../mustache_templates')
                )
            );
            $template = $m->loadTemplate('compose_geo');
            $txt = $template->render(
                array(
                    'latitude' => $param['lat'],
                    'longitude' => $param['lon']
                )
            );
            
            $body .= $txt;
        }

        /**
         * If the 'body' parameter is a URL,
         * then try to obtain a JSON-LD from the HTML behind this URL
        */
        if (isset($param['body'])
            && !empty($param['body'])
            && filter_var($param['body'], FILTER_VALIDATE_URL)
        ) {
            // If the config option allows,
            // try to fetch the HTML from the remote URL in 'body'
            // and extract JSON-LD (if any) from this HTML
            if ($rcube->config->get('allowJsonLdExtractionFromRemoteUrl', true)) {
                $html = self::loadHtmlFromUrl($param['body']);
                $jsonLd = self::html2jsonld($html);
                if (isset($jsonLd) && !empty($jsonLd)) {
                    $jsonLdDiv = html::div(
                        ['id' => 'jsonDiv', 'style' => 'display:none;'],
                        json_encode($jsonLd)
                    );

                    $body .= $jsonLdDiv;
                }

                /**
                 * Add the URL from the 'body' parameter as
                 * an HTML link to the message body
                */
                $urlLink = html::a(
                    [
                        'id' => 'urlFromBodyParam',
                        'href' => $param['body']
                    ],
                    "URL from body parameter"
                );

                $body .= $urlLink;
            }
        }

        return $body;
    }

    /**
     * Extract JSON-LD (if any) from an HTML source
     *
     * @param string $html The HTML that we want to extract JSON-LD from
     *
     * @return string|null The JSON-LD as a string (if there's any). Otherwise null
     */
    public static function html2jsonld($html)
    {
        $jsonLd = null;
        $startScriptTagPos = strpos($html, '<script type="application/ld+json">');

        if ($startScriptTagPos !== false) {
            /**
             * Remove anything before '<script type="application/ld+json">'
             * from the HTML
             *
             * This ensures that we don't extract anything based on other
             * script tags, placed before the one, containing the JSON-LD
            */
            $htmlMod = substr($html, $startScriptTagPos);

            // Calculate the start and end positions of the script tag
            $startScriptTagPos = strpos(
                $htmlMod,
                '<script type="application/ld+json">'
            );
            $endScriptTagPos = strpos($htmlMod, '</script>');

            // Extract the JSON-LD by searching for it in the HTML string
            $jsonLd = substr(
                $htmlMod,
                $startScriptTagPos + strlen('<script type="application/ld+json">'),
                $endScriptTagPos - ($startScriptTagPos + strlen(
                    '<script type="application/ld+json">'
                ))
            );

            // If we coulnd't extract anything, then set $jsonLd to null
            if (!isset($jsonLd) || empty($jsonLd)) {
                $jsonLd = null;
            }
        }
        return $jsonLd;
    }

    /**
     * A utility function to extract the type of "context"
     * that a JSON-LD with structured data has
     *
     * @param string $jsonLd The JSON-LD as a string
     *
     * @return string The context type
     */
    public static function get_context_type($jsonLd)
    {
        // Return an empty context type if the JSON-LD string is not set or empty
        if (!isset($jsonLd) || empty($jsonLd)) {
            return '';
        }

        // Decode the JSON-LD
        $jsonLd = json_decode($jsonLd, true);
    
        // Get the JSON-LD's @type property
        $jsonLdType = $jsonLd['@type'];

        // Return an empty context type if the JSON-LD's @type is not set or empty
        if (!isset($jsonLdType) || empty($jsonLdType)) {
            return '';
        }

        // Return the appropriate context type based on @type's value
        switch ($jsonLdType) {
            case 'MusicAlbum':
                return 'album';
        
            case 'Place':
                return 'geo';

            case 'FlightReservation':
                return 'flight';

            case 'EmailSignature':
                return 'signature';
            
            case 'OutOfOffice':
                return 'outOfOffice';
            
            case 'LodgingReservation':
                return 'lodging';
            
            default:
                return '';
        }
    }

    /**
     * Load HTML source from a remote URL
     *
     * @param string $url The remote URL to fetch the HTML from
     *
     * @return string|null The fetched HTML string. Null if no HTML could be fetched
     */
    private static function loadHtmlFromUrl($url)
    {
        // We need to set a User-Agent header. Otherwise we might end up with a 403
        $context = stream_context_create(
            array(
                'http' => array(
                    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
                )
            )
        );

        $html = file_get_contents($url, false, $context);

        if ($html === false || !isset($html) && empty($html)) {
            return null;
        }

        return $html;
    }
}