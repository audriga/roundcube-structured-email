// NOTE: This file contains utility functions that are typically used as helpers within other (typically bigger) functions

/**
 * This function finds and returns an array of all special folders in RC's UI
 * (Note: Special folders are 'sent', 'drafts', 'trash', 'junk')
 *
 * @returns An array, containing the IDs of the special folders
 */
rcmail.get_special_folder_names = function() {
    if (rcmail) {
        // Obtain all mailbox folders in RC's UI
        var mailboxes = rcmail.env.mailboxes;

        // These are special CSS classes which are set as properties in the JSON representation of special folders ONLY
        // Cf.: https://github.com/roundcube/roundcubemail/blob/master/program/include/rcmail_action.php#L1322-L1325
        var specialFolderClasses= ['sent', 'drafts', 'trash', 'junk'];

        // An array to hold only special folders
        var specialFolders = [];

        // Collect all special folders in the array "specialFolders"
        for (mailboxKey of Object.keys(mailboxes)) {
            var mailbox = mailboxes[mailboxKey];
            if (mailbox.hasOwnProperty('class')) {
                if (specialFolderClasses.includes(mailbox["class"])) {
                    specialFolders.push(mailbox);
                }
            }
        }

        // Return an array, containing solely the IDs of all special folders
        return specialFolders.map(mailbox => mailbox.id);
    }
}

/**
 * This function finds and returns an array of all child folders of the special folders
 *
 * @returns An array, containing the IDs of the child folders of special folders
 */
rcmail.get_subfolders_of_special_folders = function() {
    if (rcmail) {
        // Get all mailbox folders in RC's UI
        var mailboxes = rcmail.env.mailboxes;

        // Get all special folder names in RC's UI
        var special_folder_names = rcmail.get_special_folder_names();

        // An array to hold the child folders of the special folders
        var children_of_special_folders = [];

        // Iterate through all folders, check if they have a special folder name as part of their ID and
        // add them to the "children_of_special_folders" array in case they're not a part of the array of special folders
        for (mailboxKey of Object.keys(mailboxes)) {
            for (special_folder_name of special_folder_names) {
                var mailbox = mailboxes[mailboxKey];
                if (mailbox.id.includes(special_folder_name) && !special_folder_names.includes(mailbox.id)) {
                    children_of_special_folders.push(mailbox);
                }
            }
        }

        // Return an array, containing solely the IDs of the child folders of special folders
        return children_of_special_folders.map(mailbox => mailbox.id);
    }
}

/**
 * This function checks if a given folder is a special folder or a child of a special folder
 *
 * @param {string} mailbox The name of the folder to be checked
 * @returns True if the folder is a special folder or a child of one, false otherwise
 */
rcmail.is_folder_special_or_child_of_special = function(mailbox) {
    // Get the array of all special folders
    var special_folders = rcmail.get_special_folder_names();

    // Get the array of all child folders of special folders
    var children_of_special_folders = rcmail.get_subfolders_of_special_folders();

    // Check if the given folder is either a special folder or a child of one
    return special_folders.includes(mailbox) || children_of_special_folders.includes(mailbox);
}

/**
 * A function which retrieves a cookie with a specific name
 *
 * @param {string} name The name of the cookie
 *
 * @returns The cookie which was found (if any)
 */
rcmail.get_cookie = function(name) {
    var value = `; ${document.cookie}`;
    var parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop().split(';').shift();
    } 
}

/**
 * This function is called whenever a composed message is being sent.
 * If the message contains structured data and was first saved as a draft before getting sent,
 * then this function inspects the structured data of the message and fixes it by pruning
 * the structured data's CSS ID.
 * 
 * This is necessary, since RC seems to alter certain CSS IDs of a message's HTML body contents
 * whenever the message is saved as a draft.
 */
rcmail.fix_structured_data_on_draft_send = function() {
    if (rcmail.task === 'mail' && rcmail.env.action === 'compose') {
        
        // Get the compose editor's contents (the message body)
        var composeEditorContent = rcmail.editor.get_content();
        if (composeEditorContent !== '') {
            // Turn it into a jQuery object
            var composeEditorContentHtml = $($.parseHTML(composeEditorContent));

            // If there's a div which contains structured data that might be with a changed id due to RC's sanitization,
            // then take this div and change its id to the original one that we need
            var jsonBeforeSendDiv = $(composeEditorContentHtml).filter("div[id*='jsonDivBeforeSend']");
            $(jsonBeforeSendDiv[0]).attr('id', 'jsonDivBeforeSend');

            // Update the message body after updating the div's id above
            var updatedMessageBody = $(composeEditorContentHtml).html($(composeEditorContentHtml).clone()).html();
            rcmail.editor.set_content(updatedMessageBody);
        }
    }
}