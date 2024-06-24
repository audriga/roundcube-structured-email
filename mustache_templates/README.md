Current use:

compose_geo.mustache:

Used to prefill "compose" dialog if lat/lon URL parameter is set
* at: structured_data_util.php::compose_structured_data_message
* via:  roundcube_structured_email.php::on_message_compose_hook

All other remaining .html files:
* are for speical cases which ld2h doesnt cover
* Will be used in structured_data_util.php::get_mustache_template ...to set "mustacheTemplateDiv" which is sent to the client. There, Mustache.js will render cards from JSON-LD.