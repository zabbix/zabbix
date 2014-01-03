<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LLNW\Zabbix\Config;
use LLNW\Zabbix\RPC;

Config::includeLLNWZabbix();

// TODO: Refactor to silex before processor: http://silex.sensiolabs.org/doc/cookbook/json_request_body.html
$json = json_decode(file_get_contents("php://input"), true);  // true makes this fn return associative array, instead of object.

// NEWHOTNESS!! --> SETUP
$jsonrpc = new RPC($json);


// TODO: convert this to an actual zbx user authentication using the Zabbix API login() and userGroup() functions
if (($json['key'] != 'sjE4i') && (strlen($json['key']) != 32)) {
   exit;
}

// TODO: at some point we should use this as the central landing point for all llnw custom api requests
// an example below shows how just checking the method and doing file includes would make this easy to maintain.
// you could use this to consolidate authentications, responses, error handling, etc.
// check for the master variable above so only the included scripts will run if $master is defined and equal to 1.


// TODO: this would be the default callback for application type jsonrpc
return $jsonrpc->receive();