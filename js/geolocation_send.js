// NOTE: This file contains functions, related to the sending of messages with GeoLocation structured data

/**
 * This function is called whenever the user wants to send an email
 * with a Geolocation structured data that contains the user's current position.
 *
 * This function makes use of the Browser Geolocation API
 * @param {string} username The username of the currently logged-in user in RC
 */
rcmail.send_geolocation_structured_email = function (username) {
    usernameVar = username;
    if (navigator.geolocation) {
        // If the user provided us with their current location, then we use the location data
        // to navigate them to the compose form, pre-filled with their location data,
        // where they can send their message containing Geolocation structured data
        navigator.geolocation.getCurrentPosition(rcmail.navigate_to_compose_step);
    } else {
        prompt('Cannot access geolocation');
    }
}

/**
 * This function is called whenever the user has allowed us to obtain their current location.
 *
 * It redirects them to RC's compose form, which is pre-filled with structured Geolocation data,
 * containing their current location.
 *
 * @param {Object} position The object, containing the user's current position (in particular, their latitude and longitude)
 */
rcmail.navigate_to_compose_step = function(position) {
    rcmail.open_compose_step(`_to=${usernameVar}&lat=${position.coords.latitude}&lon=${position.coords.longitude}&_html=1&_subject=My%20Current%20Location`);
}