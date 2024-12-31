# Roundcube Structured Email

Open Source reference implementation for Structured Email in Roundcube. See also [DESIGN.md](DESIGN.md)

Note: this plugin is still under development and not yet ready for general use. 

## Installation

### Prerequisites 
The plugin is tested with Roundcube version 1.6.0 and later.

### Plugin installation
Clone this repository into the `\plugins` folder of your Rouncube installation. Then add `'roundcube_structured_email'`  to the `$config['plugins']`  variable in your `\config\config.inc.php` file.

A Packagist/Composer package will be provided soon.

## Configuration

There are a number of experimental configuration options. See [config.inc.php.dist](config.inc.php.dist).

## Usage

### Receive structured email

The plugin will show a standardized "card-style" visualization of structured data found in an email. Depending on the structured data type, options for structured interaction might be available.

See also https://github.com/audriga/jakarta-structured-email for structured email test data.

### Send structured email

Sending structured email is still experimental.

## Development

tbd.

## License

Code is available under GPL-3.0 license.

## Dependencies

* [html2jsonld-php](https://github.com/audriga/html2jsonld-php) 
* [jsonld2html-cards](https://github.com/audriga/jsonld2html-cards)

## Acknowledgements

The project has received funding from the European Union's Horizon 2020 research and innovation program under grant agreement No 101092990.

See also https://nlnet.nl/project/StructuredEmail/
