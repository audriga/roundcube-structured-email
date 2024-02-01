<?php

class RoundcubeStructuredEmail extends PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
        include_once __DIR__ . '/../roundcube_structured_email.php';
        include_once __DIR__ . '/../lib/structured_data_util.php';
        include_once __DIR__ . '/../vendor/autoload.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube = rcube::get_instance();
        $plugin = new roundcube_structured_email($rcube->plugins);

        $this->assertInstanceOf('roundcube_structured_email', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }

    function test_html2jsonld_single_script_tag()
    {
        $html = '
            <html>
            <head>
            <script type="application/ld+json">{"@context":"http://schema.googleapis.com/","@type":"MusicGroup","@id":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","url":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","name":"Semantics"}</script>
            </head>
            <body>
            </body>
            </html>';
        
        $jsonLdShould = '{"@context":"http://schema.googleapis.com/","@type":"MusicGroup","@id":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","url":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","name":"Semantics"}';

        $jsonLd = structured_data_util::html2jsonld($html);
        $this->assertEquals($jsonLd, $jsonLdShould);
    }

    function test_html2jsonld_multiple_script_tags()
    {
        $html = '
            <html>
            <head>
            <script id="config" data-testid="config" type="application/json">{"appName":"web_player_prototype","market":"DE","locale":{"locale":"en","rtl":false,"textDirection":"ltr"},"isPremium":false,"correlationId":"da018be9c79c02292e8a3d9b5871e492","isAnonymous":true,"gtmId":"GTM-PZHN3VD","optimizeId":"GTM-W53X654","retargetingPixels":null,"recaptchaWebPlayerFraudSiteKey":"6LfCVLAUAAAAALFwwRnnCJ12DalriUGbj8FW_J39"}</script><script id="session" data-testid="session" type="application/json">{"accessToken":"BQCsuv00GXz_WJmYM7nOOxjFXNPW97r-FM_S8TFiqmuomQjOXeDi0qv6-nlxMsiD8aHlWK1ZrTKCkQs-UyWgyygHoOwq6ZWXSic2VsKfG-ussNXBJss","accessTokenExpirationTimestampMs":1687254768204,"isAnonymous":true,"clientId":"d8a5ed958d274c2e8ee717e6a4b0971d"}</script><script id="features" type="application/json">{"enableShows":true,"isTracingEnabled":false,"upgradeButton":"control","mwp":false,"isMWPErrorCodeEnabled":false,"isMwpRadioEntity":true,"isMWPAndPlaybackCapable":false,"preauthRecaptcha":false,"isEqualizerABEnabled":false,"isPodcastEnabled":true,"isPodcastSeoEnabled":false,"enableI18nLocales":true,"isI18nAdditionalPagesEnabled":false,"isAudiobooksOnMWPEnabled":false,"isPathfinderBrowseCardsEnabled":false,"isReinventFreeEnabled":false,"isRTPTrackCreditsEnabled":false,"isBlendPartyEnabled":false,"isBlendPartyV2Enabled":false,"isEntityReportEnabled":true,"isAlbumReportEnabled":false,"isTrackReportEnabled":false,"isPodcastShowReportEnabled":false,"isPodcastEpisodeReportEnabled":false}</script><script id="seo" type="application/json">{"artist":{}}</script>
            <script type="application/ld+json">{"@context":"http://schema.googleapis.com/","@type":"MusicGroup","@id":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","url":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","name":"Semantics"}</script>
            <script id="features" type="application/json">{"enableShows":true,"isTracingEnabled":false,"upgradeButton":"control","mwp":false,"isMWPErrorCodeEnabled":false,"isMwpRadioEntity":true,"isMWPAndPlaybackCapable":false,"preauthRecaptcha":false,"isEqualizerABEnabled":false,"isPodcastEnabled":true,"isPodcastSeoEnabled":false,"enableI18nLocales":true,"isI18nAdditionalPagesEnabled":false,"isAudiobooksOnMWPEnabled":false,"isPathfinderBrowseCardsEnabled":false,"isReinventFreeEnabled":false,"isRTPTrackCreditsEnabled":false,"isBlendPartyEnabled":false,"isBlendPartyV2Enabled":false,"isEntityReportEnabled":true,"isAlbumReportEnabled":false,"isTrackReportEnabled":false,"isPodcastShowReportEnabled":false,"isPodcastEpisodeReportEnabled":false}</script>
            </head>
            <body>
            </body>
            </html>';
        
        $jsonLdShould = '{"@context":"http://schema.googleapis.com/","@type":"MusicGroup","@id":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","url":"https://open.spotify.com/artist/3ulcHuTU6eBcORZBJDhG6m","name":"Semantics"}';

        $jsonLd = structured_data_util::html2jsonld($html);
        $this->assertEquals($jsonLd, $jsonLdShould);
    }

    function test_create_http_link()
    {
        $link = structured_data_util::create_link('http://localhost:8080', 'Test Http');
        $expectedLink = '<a href="http://localhost:8080" target="_blank">Test Http</a>';
        $this->assertEquals($expectedLink, $link);
    }

    function test_create_https_link()
    {
        $link = structured_data_util::create_link('https://localhost:8080', 'Test Https');
        $expectedLink = '<a href="https://localhost:8080" target="_blank">Test Https</a>';
        $this->assertEquals($expectedLink, $link);
    }

    function test_create_mailto_link()
    {
        $link = structured_data_util::create_link('mailto:test@test.org', 'Test Mailto');
        $expectedLink = '<a href="mailto:test@test.org" target="_blank" onclick="return rcmail.command(\'compose\', \'test@test.org\', this)">Test Mailto</a>';
        $this->assertEquals($expectedLink, $link);
    }

    function test_create_action_buttons()
    {
        $msgUid = '12345';
        $actionButtons = structured_data_util::create_action_buttons($msgUid);
        $expectedActionButtons = '<button style="margin-left:10px;float:right;" title="Approve Expense" id="actionButtonconfirm12345" class="btn btn-primary actionButton" onclick="call_action(\'12345\', \'https://myexpenses.com/approve?expenseId=abc123\', \'\', \'confirm\')">confirm</button><button style="margin-left:10px;float:right;" title="Watch Movie" id="actionButtonview12345" class="btn btn-primary actionButton" onclick="window.location = \'https://watch-movies.com/?id=abc123\'">view</button>';
        $this->assertEquals($expectedActionButtons, $actionButtons);
    }

    function test_compose_structured_data_message_empty_param()
    {
        $body = structured_data_util::compose_structured_data_message([]);
        $expectedBody = '';
        $this->assertEquals($expectedBody, $body);
    }
}