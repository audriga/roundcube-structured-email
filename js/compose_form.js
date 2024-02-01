// NOTE: This file contains JS functions, used for our custom compose form in the "compose_form" task

/**
 * This function generates form fields together with their corresponding labels and them
 * to our custom compose form, rendered in the "compose_form" task.
 * The generated fields depend on the option, selected in the select field of the compose form
 */
rcmail.generate_compose_form_fields = function() {
    $('input#_to').on('input', function(e) {
        $('select#_request').prop('selectedIndex', 0);
    });
    
    var reqval = $('select#_request').find(":selected").val();
    var reqvalJson = JSON.parse(reqval);
    $('div#structuredcomposeform').empty();

    for (var key in reqvalJson) {
        var label = $(`<label>${key}</label>`);
        var field = $(`<input type="${reqvalJson[key]}" name="${key}" id="_${key}"/>`);

        $('div#structuredcomposeform').append(label);
        $('div#structuredcomposeform').append(field);
    }
    
    $('select#_request').on('change', function() {
        var json = JSON.parse(this.value);
        $('div#structuredcomposeform').empty();
        for (var key in json) {
            var label = $(`<label>${key}</label>`);
            var field = $(`<input type="${json[key]}" name="${key}" id="_${key}"/>`);

            $('div#structuredcomposeform').append(label);
            $('div#structuredcomposeform').append(field);
        }
    });
}

/**
 * This function generates a JSON-LD with structured data, based on the data from our custom composer form
 *
 * @param {string} requestType The type of selected option from the compose form
 * @param {Array} args The individual data properties, saved in the JSON-LD (depends on the value in requestType)
 *
 * @returns A JSON string if the value of requestType is a known one, otherwise returns the empty string
 */
rcmail.generate_json_from_request_type = function(requestType, args) {
    switch (requestType) {
        case 'GeoLocation':
            return JSON.stringify(
                {
                    '@context': 'https://schema.org',
                    '@type': 'Place',
                    geo: {
                        '@type': 'GeoCoordinates',
                        latitude: args['Latitude'],
                        longitude: args['Longitude']
                    },
                    name: args['Name']
                });
    
        case 'MusicAlbum':
            return JSON.stringify(
                {
                    '@context': 'http://schema.googleapis.com/',
                    '@type': 'MusicAlbum',
                    'name': args['AlbumName']
                });

        default:
            return '';
    }
}

/**
 * This function is called whenever the RC command 'sendstructuredform' is invoked.
 *
 * Its purpose is to submit our custom compose form, rendered in the "compose_form" task
 */
function send_structured_form(props) {
    var form = $('form#structuredform');
    form.attr('action', `?_task=mail&_unlock=loading${rcmail.env.compose_id}&_framed=1`);

    var requestType = $('select#_request :selected').text();
    var structuredFormInputs = $('div#structuredcomposeform :input');
    var structuredFormInputsValues = {};

    for (key in Object.keys(structuredFormInputs)) {
        var input = structuredFormInputs[key];
        structuredFormInputsValues[$(input).prop('name')] = $(input).val();
    }

    var jsonLd = rcmail.generate_json_from_request_type(requestType, structuredFormInputsValues);

    form.on('submit', function() {
        var jsonLdDiv = '<div id="jsonDivBeforeSend" style="display:none;">' + jsonLd + '</div>';

        $('<input />').attr('type', 'hidden')
            .attr('name', '_message')
            .attr('value', jsonLdDiv)
            .appendTo(form);

        $('<input />').attr('type', 'hidden')
            .attr('name', '_is_html')
            .attr('value', 1)
            .appendTo(form);

        $('<input />').attr('type', 'hidden')
            .attr('name', '_id')
            .attr('value', rcmail.env.compose_id)
            .appendTo(form);

        return true;
    });

    form.submit();
}