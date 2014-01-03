<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LLNW\Zabbix\Config;
use LLNW\Zabbix\RPC;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

Config::includeLLNWZabbix();

// TODO: at some point we should use this as the central landing point for all llnw custom api requests
// an example below shows how just checking the method and doing file includes would make this easy to maintain.
// you could use this to consolidate authentications, responses, error handling, etc.
// check for the master variable above so only the included scripts will run if $master is defined and equal to 1.

$app = new Application();

// TODO: convert this to an actual zbx user authentication using the Zabbix API login() and userGroup() functions
// if (($json['key'] != 'sjE4i') && (strlen($json['key']) != 32)) { exit; }
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $jsonrpc = new RPC($request->getContent());
        $jsonrpc->receive(); // Immediately process JSONRPC request
    }
});

// Process some regular browser requests (like info about how to use this API...)
$app->get('/', function() use($app) {
    return 'LLNW/Zabbix';
});

$app->run();