<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LLNW\Zabbix;

LLNW\Zabbix\Config::includeLLNWZabbix();

// TODO: Refactor to silex before processor: http://silex.sensiolabs.org/doc/cookbook/json_request_body.html
$json = json_decode(file_get_contents("php://input"), true);  // true makes this fn return associative array, instead of object.

// TODO: Refactor to jsonrpc/jsonrpc error class
if (json_last_error() != 0) {
   sendErrorResponse('123','Invalid JSON', 'data received was not formatted properly');
   exit;
}

// TODO: convert this to an actual zbx user authentication using the Zabbix API login() and userGroup() functions
if (($json['key'] != 'sjE4i') && (strlen($json['key']) != 32)) {
   exit;
}

// TODO: at some point we should use this as the central landing point for all llnw custom api requests
// an example below shows how just checking the method and doing file includes would make this easy to maintain.
// you could use this to consolidate authentications, responses, error handling, etc.
// check for the master variable above so only the included scripts will run if $master is defined and equal to 1.

// TODO: add.squelch,clear.squelch :: Squelch.php
// TODO: get.ack,ack.get,add.ack,ack.add :: Ack.php
// TODO: hostlist.get,hostlist.pull :: Hostlist.php
// TODO: proxymap.get :: proxy.php --> Proxymap.php
// TODO: alertqueue.create :: alert-queue.php --> AlertQueue.php
// TODO: proxy.status, proxy.reassign :: proxy-assign.php --> Proxy.php
// TODO: dm.info :: dm-info.php --> DM.php
// TODO: queue.info :: queue-info.php --> Queue.php