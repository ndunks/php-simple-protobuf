# php-simple-protobuf
PHP Implementation of Google Protocol Buffers
- Simple Protobuf implementation for PHP without install/compile PHP extension
- Support to compile proto 2 files without use any binary/executable
- Support group type field

## About Protobuf
Protocol Buffers (a.k.a., protobuf) are Google's language-neutral,
platform-neutral, extensible mechanism for serializing structured data. You
can find [protobuf's documentation on the Google Developers site](https://developers.google.com/protocol-buffers/).

## Getting started

### Requirements
* PHP 5.6 or above
* php-json extension (for json serializer)

### Installation
Install with composer.
````
# add ndunks/php-simple-protobuf entry to your composer.json
{
    "require": {
        "ndunks/php-simple-protobuf": "*"
    }
}

# install requirements composer
composer install
````
## Usage

## Compile proto 2 file to PHP Classes
Just execute php code on console in your project root dir
````
vendor\bin\compiler.php.bat --out=<out-dir> --file=<proto-file>
````
## Run the code
````
include 'vendor/autoload.php';
// Class Simple compiled from simple.proto (find on test/proto)
$obj = new Simple();
$obj->setName('user');
$obj->setAddress('Indonesia');
$obj->setAge(25);
$obj->toArray(); // as PHP Array
$obj->toJson(); // as JSON formated string
````

