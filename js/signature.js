// NOTE: This file contains functions, related to structured data for email signatures

/**
 * This function calls the action on the backend for creating a new identity of the currently logged-in user
 *
 * The structured HTML which is rendered for structured data of type "EmailSignature" contains a button which,
 * when clicked by the user, calls this function and thus creates a new identity, containing the signature from
 * the message's "EmailSignature" structured data
 */
rcmail.create_identity_form = function () {
    rcmail.http_post('plugin.create_identity', {_signature: rcmail.env.emailSignatureFromJsonLd});
}