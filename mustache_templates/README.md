Current use:

compose_geo.mustache:

Used to prefill "compose" dialog if lat/lon URL parameter is set
(https://bitbucket.org/audriga/roundcube-structured-email/src/3cd2528acfd64885bf8cfb3ceaea2d048ae877f0/lib/structured_data_util.php?at=master#structured_data_util.php-323 :: compose_structured_data_message)
(via roundcube_structured_email.php::on_message_compose_hook)


All other .html files:

Will be used in get_mustache_template in https://bitbucket.org/audriga/roundcube-structured-email/src/2f80d1a706292fbe2e869a6ebb7ba82e0b8c40a3/lib/structured_data_util.php?at=master#lines-46
...to set "mustacheTemplateDiv" which is sent to the client. There, Mustache.js will render cards from JSON-LD.

