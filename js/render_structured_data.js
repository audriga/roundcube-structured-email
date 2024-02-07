// NOTE: This file contains functions, related to the rendering of structured data when viewing an individual message

/**
 * This function is called whenever the user is viewing a single message in RC's UI
 *
 * Its purpose is to render structured HTML, based on the structured data, contained in the message (if any).
 * The structured HTML is rendered between the message's subject and body 
 */
rcmail.render_structured_data = function() {
    // Logic for rendering structured data for a message
    var jsonLdContainer = $('div#message-objects div#jsonDiv p#jsonDivP');
    var actionButtons = null;
    if (jsonLdContainer !== null) {
        jsonLdString = jsonLdContainer.text();
        if (jsonLdString !== null && jsonLdString !== '') {
            jsonLdActions = JSON.parse(jsonLdString)['potentialAction'];
            
            var jsonLdMsgUid = $('div#message-objects div#jsonDiv p#jsonDivMsgUid');
            var msgUid = jsonLdMsgUid !== null ? jsonLdMsgUid.text() : '';

            var jsonLdUsername = $('div#message-objects div#jsonDiv p#jsonDivUsername');
            var username = jsonLdUsername !== null ? jsonLdUsername.text() : '';

            actionButtons = $('<div id="individualMessageActionButtons" style="background-color:#f1f1f1;float:right;align-self:flex-end;"></div>');
            if (jsonLdActions !== undefined && jsonLdActions !== null && jsonLdActions.length > 0 && msgUid !== '' && username !== '') {
                jsonLdActions.forEach(jsonLdAction => {
                    var actionButtonText;
                    if (jsonLdAction['@type'] === 'ConfirmAction') {
                        actionButtonText = 'Confirm';
                    } else if (jsonLdAction['@type'] === 'CancelAction') {
                        actionButtonText = 'Cancel';
                    } else if (jsonLdAction['@type'] === 'ViewAction') {
                        actionButtonText = 'View';
                    }

                    var actionButton = $(
                        `<button
                            style="margin-left:10px;float:right;"
                            title="${jsonLdAction['description'] ?? ''}"
                            id="actionButton${jsonLdAction['@type'] + msgUid}"
                            class="btn btn-primary actionButton"
                        >${actionButtonText}</button>`
                    );

                    if (jsonLdAction['@type'] === 'ViewAction') {
                        actionButton.click(function () {
                            window.location = encodeURIComponent(jsonLdAction['url']);
                        });
                    } else {
                        actionButton.click(function () {
                            rcmail.call_action(msgUid, encodeURIComponent(jsonLdAction['url']), username, jsonLdAction['@type']);
                        });
                    }

                    actionButtons.append(actionButton);
                });
            }
        }
    }

    // Logic for rendering action buttons, placed right below the structured data
    var mustacheTemplate = $('div#message-objects div#mustacheTemplateDiv');
    var mustacheTemplateString = '';
    if (mustacheTemplate !== null) {
        mustacheTemplateString = mustacheTemplate.html();
    }
    
    if (jsonLdContainer !== null) {
        jsonLdString = jsonLdContainer.text();
        if (jsonLdString !== null && jsonLdString !== '') {
            jsonLd = JSON.parse(jsonLdString);

            /** 
             * Sometimes the JSON-LD string is too stringified
             * and we need to call JSON.parse() once more
             * Cf.: https://stackoverflow.com/a/51955729
            */
            if (typeof jsonLd === 'string') {
                jsonLd = JSON.parse(jsonLd);
            }
            
            // If the parsed JSON-LD is an array, take the first object in it
            if (Array.isArray(jsonLd)) {
                jsonLd = jsonLd[0];
            }

            var structuredHtml = '';
            var shouldRender = true;
            var didRender = false;
            if (jsonLd !== null ) {
                if (jsonLd['@type'] === 'EmailSignature' && mustacheTemplateString !== '') {
                    // Make the signature, contained in the JSON-LD available for later via an env var
                    rcmail.env.emailSignatureFromJsonLd = jsonLd.signature;
                    let temp_card = Mustache.render(mustacheTemplateString, jsonLd);
                    structuredHtml = $('<div id="structured-data-content">').html(temp_card);
                    didRender = true;
                }
                else if (jsonLd['@type'] === 'FlightReservation' && mustacheTemplateString !== ''){
                    // TODO Currently FlightReservation is not supported in ld2h
                    let temp_card = Mustache.render(mustacheTemplateString, jsonLd);
                    structuredHtml = $('<div id="structured-data-content">').html(temp_card);
                    didRender = true;
                } 
                else if (jsonLd['@type'] === 'OutOfOffice' && mustacheTemplateString !== '') {
                    //TODO check if this code is still needed, functionality splitted in structured_vacation_notice
                    // Reformat the "start" and "end" dates of "OutOfOffice" JSON-LDs
                    var startDate = new Date(jsonLd['start']);
                    var endDate = new Date(jsonLd['end']);
                    jsonLd['start'] = `${startDate.getDate()}.${startDate.getMonth() + 1}.${startDate.getFullYear()}`;
                    jsonLd['end'] = `${endDate.getDate()}.${endDate.getMonth() + 1}.${endDate.getFullYear()}`;
                    var currentDate = new Date();
                    // Only render OOF structured data if the OOF is currently happening
                    shouldRender = currentDate > startDate && currentDate < endDate;

                }
              
                // If we had no special cases yet, we use ld2h
                if(didRender === false){
                    const card = Jsonld2html.render(jsonLd);
                    structuredHtml = $('<div id="structured-data-content">').html(card);
                }
            }
            
            if (shouldRender) {
                messageObjects = $('div#message-objects');
                var intervalId;
                if (messageObjects !== null && structuredHtml !== null && structuredHtml !== '') {
                    var structuredDataContainer = $('<div class="info structured-data-container" style="background-color:#f1f1f1;width:100%;display:flex;flex-direction:column;">');

                    // Add button and toggle switch for "Live Location" if necessary
                    if (jsonLd['@type'] === 'Place' && 'liveUrl' in jsonLd) {
                        var refreshLocationButton = $(
                        `<button
                            style="margin-right:10px;"
                            title="Refresh Location"
                            id="refreshLocationBtn"
                            class="btn btn-primary actionButton">`).text('Refresh').click(function() {
                            rcmail.get_latest_location(jsonLd.liveUrl, mustacheTemplateString, structuredDataContainer);
                        });

                        var autoRefreshLocationToggle = $('<input id="toggleswitch" type="checkbox" style="margin-left:10px;">').change(function() {
                            if ($(this).is(":checked")) {
                                $('#refreshLocationBtn').prop('disabled');
                                intervalId = setInterval(function() {
                                    rcmail.get_latest_location(jsonLd.liveUrl, mustacheTemplateString, structuredDataContainer);
                                }, 1000);
                            } else {
                                $('#refreshLocationBtn').prop('enabled');
                                clearInterval(intervalId);
                            }
                        });

                        var checkboxLabel = $('<label class="toggle">Automatically Refresh Location</label>');
                        checkboxLabel.append(autoRefreshLocationToggle);
                        checkboxLabel.append($('span.roundbutton'));

                        var refreshLocationDiv = $('<div class="info refresh-location-div" style="background-color:#f1f1f1;width:100%;display:flex;flex-direction:row;padding:10px;margin-bottom:10px;">');
                        refreshLocationDiv.append(refreshLocationButton);
                        refreshLocationDiv.append(checkboxLabel);
                        
                        messageObjects.append(refreshLocationDiv);
                    }

                    structuredDataContainer.append(structuredHtml);
                    messageObjects.append(structuredDataContainer);

                    var curr_mailbox = rcmail.env.mailbox;

                    /**
                     * If we have any action buttons, then append them after the structured data
                     * Note: don't render action buttons:
                     *  - If we're rendering HTML for an unknown markup (the second conjunct)
                     *  - OR If we're in a special folder ('sent', 'junk', 'trash', 'drafts') or a subfolder thereof
                     */
                    if (actionButtons !== null && $('p#unknown-markup').length === 0) {
                        var actionButtonsElements = actionButtons.children();
                        if (actionButtonsElements.length > 0) {
                            if (rcmail.is_folder_special_or_child_of_special(curr_mailbox)) {
                                actionButtonsElements.each(function() {
                                    $(this).prop("disabled", true);
                                });
                            }
                        }
                        
                        structuredDataContainer.append(actionButtons);
                    }
                }
            }
        }
    }
}

/**
 * This function is called whenver a button, displayed as part of the structured HTML for "Live Location" is clicked.
 *
 * It makes an AJAX request to a URL which returns back the current location of the sender of the message with the "Live Location"
 * structured HTML and updates this same structured HTML with the newly fetched location data
 *
 * @param {string} liveUrl The URL to send a request against for obtaining the current location of the user
 * @param {string} mustacheTemplateString A Mustache template string which is passed the new location data and is rendered to
 * a structured HTML for "Live Location"
 */
rcmail.get_latest_location = function(liveUrl, mustacheTemplateString) {
    $.ajax({
        url: liveUrl,
        type: 'GET',
        crossOrigin: true,
        dataType: 'json',
        success: function(res) {
            var updatedStructuredHtml = Mustache.render(mustacheTemplateString, res);
            $('div#structured-data-content').html(updatedStructuredHtml);
        }
    });
}
