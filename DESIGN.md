# Structured Email for Roundcube - Design document

This document describes the design and architecture of the *Structured Email* plugin for [Roundcube Webmail](https://roundcube.net/). In particular, it shows how to use existing Roundcube extension points for this purpose.

Whenever possible, it tries to abstract from Roundcube specifics, such that this document might prove helpful for developers of other Webmailers, when adding Structured Email functionality.

The main goal of the plugin is to let users receive and send so called *structured email*. As a side goal, it aims to provide a blueprint for the implementation of similar plugins for other Webmailers.

## Structured Email

While conventional emails are mostly for humans to read and act upon, structured email contain machine-readable information. Applications can thus process information on behalf of these user or offer a more efficient way of processing emails.

In a wider sense, various instances of structured email already exist today, *calendar invites* probably being the most widely used. While such kinds of structured email required explicit email client support, structured email in a narrower sense aims to provide a standard framework for machine-readable email messages. Work is carried out in the [IETF Structured Email (SML) Working Group](https://datatracker.ietf.org/group/sml/about/).

Similar to the fact that most "HTML emails" (i.e., emails with an HTML-formatted body) today still contain a plain text "alternative" version (in case the recipient's email client cannot deal with HTML), structured emails will and should typically contain a human-readable HTML and/or plain text body as well.

The relationship between the "structured data" part and the human-readable part can be twofold:
* Structured data can complement or partially represent the content of an email message. I.e., the user will still need to read the human-readable part (which might however ...)
* Structured data can be an alternative, full representation of the human readable content. In this case an email client could make fully sense of an incoming message on behalf of the user.

Further information can also be found at [www.structured.email](www.structured.email)

### Receiving structured email

Given the fact that lots of (transactional) email is nowadays sent to end users by tools, the receicing end of structured email is probably the most important one.

The IETF SML WG is maintaining a dedicted [use case document](https://datatracker.ietf.org/doc/draft-ietf-sml-structured-email-use-cases/).

While SML provides a framework for exchanging structured email, it does not prescribe user interaction. In fact, due to the flexibility of email, users may choose various means of working with structured email. Examples might be:

* Server-side actions (e.g., "Milters")
* Explicit (client-side) interaction with structured data

Due to the nature of Webmail as mostly client-side tooling, this document will primarily focus on the client-side interaction aspect.

Due to the "structured" nature of structured data, it allows for standardized user (inter-)actions. E.g. a `FlightReservation` might be *confirmed* or *added to a calendar*. Similarly, its deparature and arrival locations might be subject of interaction.

Actions might already be explicitly prescribed in structured data (e.g., [Schema.org potentialAction concept](https://schema.org/potentialAction)) or might be added in further, context-aware processing.


### Sending structured email 

While it may seem ambitous to make users create and send "structured emails", there exists actual various precedent:

* Pre-structured messages content (email signatures; content pasted into email)
* Pre-structured messages (vacation notice; canned replies)

Accordingly, it seems possible to allow users sending structured email. 

This will be further elaborated in future iterations of this document.


## Roundcube Webmail

Roundcube Webmail is a widely used web-based software to read and write email messages in a web browser. It consists of HTML/JavaScript-based client-side code and server-side code written in PHP which runs on a webserver.

Roundcube accesses emails directly from ther user's IMAP server while storing additional data (e.g, the address book) in a database. Roundcube offers a plugin API with extension points (also called *hooks*) that can be used by plugins to extend its core functionality.

Our preference was to add structured email within the boundaries of this plugin API, if this would be possible. This should also server as a reference point for other email cients in terms of archtiectural requirements for structured email.

### Receiving email
As a Webmail software, Roundcube code is mainly running in response to actual user interactions - i.e., the user openinng Roundcube Webmail in her web browser.

For reading email, there are two main kinds of typical interactions:

* Listing messages in a folder (the Webmail home screen will typically list the INBOX)
* Displaying a particular email message

#### Message listing
The first operation when opening Roundcube is typically listing the email INBOX or another folder. For this list view, Roundcube fetches message headers from the underlying email server, but for efficiency reasons not the complete messages.

While message headers are usually sufficient to render a message list, there are cases where information about the full message can be beneficial. One such example is the so-called "attachment" indicator, which shows if a message contains attached files. Since this information cannot be obtained efficiently by Roundcube for a folder listing, there exist conventions in which a downstream message processor assigns an IMAP flag ("hasAttachments").

#### Message display
Once a user selects a particular message in a folder for reading, Roundcube will fetch the complete message from the IMAP server. It will pre-process the email within its PHP-code an deliver a HTML representation to the user interface for display.

The message display will classically note the most important header fields (sender, recipient, date, subject), followed by the message body and potential attachments. 

In between the headers and the message body, there is a so called "banner" space. While often empty, it is used for the display of special purpose information such as information extracted from *calendar invites* or certain interaction options, as described in the following.

#### Trust

Despite of efforts in email signing and encryption, email is mostly not considered a highly trustworthy means of communication.

Hence, email clients only enable some features if a certain level of trust is given. For example, external images embedded into an email can be used for user tracking. Therefore, Roundcube will hide those external images by default and allow the user to opt-in. On option in this interaction is to add the email sender to a special addressbook folder with *trusted senders*.

### Sending email 
The message compose screen is typically accessed by:
* By clicking "reply" or "forward" on an existing message
* By clicking the "compose" icon

In adition, the URL scheme of Roundcube can be used to open the compose screen and optionally provide URL parameters for parametrization (e.g., recipient or body text). In its settings, Roundcube offers to register a handler for the "mailto" URI scheme within the browser, which maps to the described URL scheme.

For writing messages, Rouncube uses a typical form-based interface. By default, the Open Source editor TinyMCE is used for the mssage body. It can be switched to either writing HTML messages with formatting or plain text messages.

Upon pressing the send button, Roundcube JavaScript code will post the message to the server-side PHP code, which finally submits to an outbound SMTP server and stores the message in the "sent" folder of the IMAP store.

### Special concepts

This section describes a number of special-use features that can be found in many Webmail clients and that could play a role in implementing SML.

#### Canned replies
Canned replies are templates which the user has stored for later reuse.

#### Email signature
An email siganture is a small portion of text which is appended to outgoing messages. It is often used to state contact data or upcoming events. In Roundcube, users can configure different email signatures for each sending address or "identity" (of which Roundcube allows multiple). When the user starts to compose a message, it will be prefilled with the signature at the bottom. The user can modify or remove that signature text before sending.

#### Vacation notice
Vacation notices (also called "out-of-office reply" or "autoreply" are message which are automatically sent in recponse to incoming emails if a user stated to be absent. Such vacation notice is set automatically by the Sieve filter engine of the server (if available). Rounducbe offers a user interface to configure vacation notices (e.g., start end end date, message content).

## Implementation

Initial research indicated, that SML support may be added to Roundcube Webmail based on existing extension points.

Hence, we will be realizing SML by creating a dedicated [Roundcube Plugin](https://github.com/roundcube/roundcubemail/wiki/Plugin)

### Receiving structured email

We aimed to implement support for two means of reading emails:

* Dealing with `potentialActions` in message/folder listings
* Interacting with structured data while displaying a particular message

#### Message listing 

To detect structured emails with quick actions, we implement the [`messages_list` hook](https://github.com/roundcube/roundcubemail/wiki/Plugin-Hooks#messages_list).

On server-side, all messages to be listed will be checked for structured data with `potentialActions`. Data found will be put into a map with the message uid as its key. The map will be passed to the user interface as an environment variable.

Client-side JavaScript code will then add action buttons to the message preview.

Note that iterating listed messages is slightly inefficient, since it  fetches more data from the IMAP server than required for the basic message preview. Potential optimizations might require indicators such as message header fields, which are subject of ongoing standardization discussion.

#### Message display
Much of today's structured emails contain structured data within a SCRIPT HTML tag in the BODY section of the HTML email. However, for security reasons, many email clients sanitze HTML message bodies before rendering. This particularly applies to the `SCRIPT` tag.

Hence, our plugin requires means to bypass sanitizing. We therefore chose Roundcube's [`message_objects` hook](https://github.com/roundcube/roundcubemail/wiki/Plugin-Hooks#message_objects). The hook allows to inspect the complete message for structured data. 

Large parts of this extraction code might be reusable, which is why we moved it into a separate library (see below).

Extraction yields `JSON-LD` which needs to be converted into a human-readable form. We chose the "cards" UX metaphor to provide a generic approach of user interaction with structured data.

Code for converting `JSON-LD` to HTML has been turned into a set of templates using the [Mustache](https://mustache.github.io/) template language, which is available for various platforms. This allows us to either render HTML on the server- *or* client-side.

In the current implementation, we chose a server-side approach. The resulting HTML will be passed in a hidden HTML `div` container, which is further processed by client-side JavaScript code.
 
### Sending structured email
(TBD)

## Software libraries
The following software libraries have been (resp. are in the process of being) extracted from SML Roundcube, since functionality is not Roundcube-specific and might be re-used in other contexts

### Data extraction

* [html2jsonld-php](https://github.com/audriga/html2jsonld-php) is a PHP library for extracting structured data (`JSON-LD`, `Microdata`; potentially more) from HTML or documents or email messages
* html2jsonld-js is a similar implementation in JavaScript

### Visualization

* [jsonld2html-cards](https://github.com/audriga/jsonld2html-cards) consists of [Mustache](https://mustache.github.io/) templates for rendering `JSON-LD` types into HTML cards. The project also contains utility code in different languages, including data pre- and post-processing.

## Summary

This document describes the feasibility and approach for adding structured email functionality to Roundcube Webmail by means of a Roundcube Plugin.

While the current main focus is on receiving structured email, further iterations of this document will also discuss the sending of structured email in more detail.


