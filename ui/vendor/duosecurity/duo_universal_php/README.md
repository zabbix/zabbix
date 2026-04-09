# Duo Universal PHP library

[![Build Status](https://github.com/duosecurity/duo_universal_php/workflows/PHP%20CI/badge.svg)](https://github.com/duosecurity/duo_universal_php/actions)

This SDK allows a web developer to quickly add Duo's interactive, self-service, two-factor authentication to any PHP web login form.


What's included:
* `src` - The PHP Duo SDK for interacting with the Duo Universal Prompt
* `example` - An example PHP application with Duo integrated
* `tests` - Test cases

## Getting started
This library requires PHP 7.4 or later

To use SDK in your existing developing environment, install it from Packagist
```
composer require duosecurity/duo_universal_php
```
Once it's installed, see our developer documentation at https://duo.com/docs/duoweb and `sample/index.php` in this repo for guidance on integrating Duo 2FA into your web application.

### TLS 1.2 and 1.3 Support

Duo_universal_php uses PHP's cURL extension and OpenSSL for TLS operations.  TLS support will depend on the versions of multiple libraries:

TLS 1.2 support requires PHP 5.5 or higher, curl 7.34.0 or higher, and OpenSSL 1.0.1 or higher.

TLS 1.3 support requires PHP 7.3 or higher, curl 7.61.0 or higher, and OpenSSL 1.1.1 or higher.


## Contribute
To contribute, fork this repo and make a pull request with your changes when they are ready.

Install the SDK from source:
```
composer install
```

Run interactive mode
```
php -a -d auto_prepend_file=vendor/autoload.php

Interactive shell

php > $client = new Duo\DuoUniversal\Client("IntegrationKey", "SecretKey", "api-XXXXXXXX.duosecurity.com", "https://example.com");
php > $state = $client->generateState();
php > $username = "example";
string(700) "https://api-XXXXXXXX.duosecurity.com/oauth/v1/authorize?response_type=code&client_id=DIXXXXXXXXXXXXXXXXXX&scope=openid&redirect_uri=https%3A%2F%2Fexample.com&request=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzY29wZSI6Im9wZW5pZCIsInJlZGlyZWN0X3VyaSI6Imh0dHBzOlwvXC9leGFtcGxlLmNvbSIsImNsaWVudF9pZCI6IkRJWFhYWFhYWFhYWFhYWFhYWFhYIiwiaXNzIjoiRElYWFhYWFhYWFhYWFhYWFhYWFgiLCJhdWQiOiJodHRwczpcL1wvYXBpLVhYWFhYWFhYLmR1b3NlY3VyaXR5LmNvbSIsImV4cCI6MTYxMjI5OTA3Nywic3RhdGUiOiJtYjlWalFGeDNzMEswRVpidVBJMmlCVWE4N29qbWFMTUl2VksiLCJyZXNwb25zZV90eXBlIjoiY29kZSIsImR1b191bmFtZSI6ImV4YW1wbGUiLCJ1c2VfZHVvX2NvZGVfYXR0cmlidXRlIjp0cnVlfQ.8Pr02LJd0pi6rsiAf5mvzGbf51piHysHyP5PlmnMiwNIkQ0HsYED0wECilXxsIyISz--oU528Cy7Sfebj0copg"
```

## Tests
```
./vendor/bin/phpunit --process-isolation tests
```

## Lint
To run linter
```
./vendor/bin/phpcs --standard=.duo_linting.xml -n src/* tests
```
