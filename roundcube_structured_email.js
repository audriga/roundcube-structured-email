/**
 * This is an event listener which is triggered as soon as RC is initialized
 * (i.e., the 'init' event is fired)
 *
 * It is responsible for setting up and registering custom event listeners and
 * commands, used throughout the plugin
 */
window.rcmail && rcmail.addEventListener('init', function(evt) {
    // Add an action button on each message list row insertion event
    if (rcmail.gui_objects.messagelist) {
        rcmail.addEventListener('insertrow', rcmail.add_action_buttons);
    }

    // Register an action callback that is called by the backend whenever it returns an AJAX response to us
    rcmail.addEventListener('plugin.action_callback', action_callback);

    // Register our custom command for sending messages via our structured message form (in compose_form task)
    rcmail.register_command('sendstructuredform', send_structured_form, true);

    // Register our custom command for inserting structured in a message which is being composed
    rcmail.register_command('insert-structured-data', insert_structured_data, true);

    if (rcmail.task === 'mail') {
        // Render the structured data for a message when displaying it
        rcmail.render_structured_data();
    }

    if (rcmail.task === 'compose_form') {
        // Generate the compose form input fields, based on the selected option in the select field
        rcmail.generate_compose_form_fields();
    }

    // If we're sending a message that was previously saved as draft, we need to fix its HTML which was sanitized by RC
    rcmail.fix_structured_data_on_draft_send();
});

// A global variable which is meant to hold the username of the current user, logged into RC
var usernameVar = '';
