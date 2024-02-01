// NOTE: This file contains functions, related to action buttons, rendered for messages with structured data

/**
 * A function which is called every time a row is rendered in the message list in RC's UI
 *
 * It adds action buttons to each row in the message list in case that the message,
 * contained in the row, has associated actions with it.
 *
 * @param {Event} evt The event object which contains data for the current row being rendered in the message list
 */
rcmail.add_action_buttons = function(evt) {
    var curr_mailbox = rcmail.env.mailbox;

    // If we have message actions and we're not in a special folder (or a subfolder thereof), then we can add action buttons to the message list
    if (
        typeof(rcmail.env.messageActions[evt.uid]) !== 'undefined'
        && typeof(rcmail.env.messageActions[evt.uid]['potentialAction']) !== 'undefined'
        && !rcmail.is_folder_special_or_child_of_special(curr_mailbox)
    ) {
        // Create a container for the action buttons of each message in the message list
        buttonsToInsert = $(`<span id="action${evt.uid}"></span>`);

        var potentialAction = rcmail.env.messageActions[evt.uid]['potentialAction'];

        // If "potentialAction" is an object instead of array, turn it into array
        if (typeof(potentialAction) === 'object' && !Array.isArray(potentialAction)) {
            potentialAction = Object.keys(potentialAction).map((key) => potentialAction[key]);
        }

        // For each action, associated with a given message, create the appropriate action buttons
        potentialAction.forEach(action => {
            var url = encodeURIComponent(action["url"]);
            var mbox = $('span.username').text();
            var button;

            // If we have a "ConfirmAction" or a "CancelAction" button, we add a function to its
            // "onclick" event which calls an action, that is registered in the backend of the plugin
            if (action["@type"] === 'ConfirmAction') {
                button = $('<button style="margin-left:10px;float:right;">Confirm</button>')
                    .click(function () {
                        rcmail.call_action(evt.uid, url, mbox, action['@type']);
                    })
                    .addClass('btn btn-primary actionButton').attr('id', 'actionButton' + action['@type'] + evt.uid);
            } else if (action["@type"] === 'CancelAction') {
                button = $('<button style="margin-left:10px;float:right;">Cancel</button>')
                    .click(function () {
                        rcmail.call_action(evt.uid, url, mbox, action['@type']);
                    })
                    .addClass('btn btn-primary actionButton').attr('id', 'actionButton' + action['@type'] + evt.uid);
            // If we have a "ViewAction" button, we just make it an ordinary link
            } else if (action["@type"] === "ViewAction") {
                button = $('<button style="margin-left:10px;float:right;">View</button>')
                    .click(function () {window.location = action["target"];})
                    .addClass('btn btn-primary actionButton').attr('id', 'actionButton' + action['@type'] + evt.uid);
            }

            // Add a tooltip text to each button
            if (action["description"]) {
                button.prop('title', action["description"]);
            }
            
            // If the action button is a "ConfirmAction" one and if its action has been
            // succesffully called (indicated by the "actionStatus" cookie), then we disable the button
            // and slightly change its CSS styling
            if (rcmail.get_cookie('actionStatus') === 'success' && (action["@type"] === 'ConfirmAction' || action["@type"] === 'ConfirmAction')) {
                button.addClass('btn btn-primary actionButton actionButtonClicked').text(action["@type"] + ' \u2713').prop("disabled", true);
            }
            
            // Add the button to the container which is meant to hold action buttons
            $(buttonsToInsert).append(button);
        });

        // Hide action buttons of messages that don't stem from trusted senders
        if (
            typeof(rcmail.env.messagesFromTrustedSenders[evt.uid]) !== 'undefined'
            && rcmail.env.messagesFromTrustedSenders[evt.uid] === false
        ) {
            $(buttonsToInsert).hide();
        }

        // Place the container with the action buttons after the link which opens
        // the message detail view for each message in the message list
        $('a[href*="_uid=' + evt.uid + '&_action=show"]').after(buttonsToInsert);
    }
}

/**
 * This function calls an action on the plugin's backend which is responsible for handling requests, sent
 * from clicking action buttons
 *
 * @param {string} messageId The ID of the message which the action button is associated with
 * @param {string} url The URL, associated with the action of the action button (e.g., a URL to confirm a booking reservation)
 * @param {string} mbox The username of the currently logged-in user
 * @param {string} type The type of the action that the action buttons is associated with
 */
rcmail.call_action = function(messageId, url, mbox, type) {
    rcmail.http_post('plugin.action_button_handler', {_uid: messageId, _url: url, _mbox: mbox, _type: type});
}

/**
 * A callback which is called by the plugin's backend when an action, associated with an action button has been executed
 *
 * @param {Object} response The response, containing the result of the action that was executed
 */
function action_callback(response) {
    if (response.actionStatus) {
        document.cookie = 'actionStatus=success;';
        if (response.type === 'confirm') {
            var button = $('#actionButton' + response.type + response.uid);
            var buttonOldText = button.text();
            button.addClass('btn btn-primary actionButton actionButtonClicked').text(buttonOldText + ' \u2713').prop("disabled", true);
        }
    } else {
        console.log("ACTION STATUS IS FAIL");
    }
}

/**
 * This function is used to unhide action buttons for a message with structured data
 *
 * @param {string} uid The UID of the message whose action buttons should be unhidden
 */
rcmail.unhide_message_list_buttons = function (uid) {
    $(`span#action${uid}`, parent.document).show();
}