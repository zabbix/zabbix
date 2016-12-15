<?php
$d = __DIR__ . '/';
include($d.'config.php');

// THIS PAGE IS LIVE!!!

$master=1;

$json = json_decode(file_get_contents("php://input"), true);  // true makes this fn return associative array, instead of object.

if (json_last_error() != 0) {
   sendErrorResponse('123','Invalid JSON', 'data received was not formatted properly');
   exit;
}

// TODO: convert this to an actual zbx user authentication using the Zabbix API login() and userGroup() functions
if (($json['key'] != 'sjE4i') && (strlen($json['key']) != 32)) {
   exit;
}

$trans_id = (isset($json['id'])) ? $json['id'] : '';


// TODO: at some point we should use this as the central landing point for all llnw custom api requests
// an example below shows how just checking the method and doing file includes would make this easy to maintain.
// you could use this to consolidate authentications, responses, error handling, etc.
// check for the master variable above so only the included scripts will run if $master is defined and equal to 1.


if ($json['method'] == 'add.squelch' || $json['method'] == 'get.squelch') {
   include($base_dir.'squelch.php');
}
elseif ($json['method'] == 'get.ack') {
   include($base_dir.'ack.php');
}
elseif ($json['method'] == 'hostlist.get' || $json['method'] == 'hostlist.pull') {
   include($base_dir.'hostlist.php');
}
elseif ($json['method'] == 'proxymap.get') {
   include($base_dir.'proxy.php');
}
elseif ($json['method'] == 'alertqueue.create'
   || $json['method'] == 'alertqueue.size'
   || $json['method'] == 'alertqueue.old') {
   include($base_dir.'alert-queue.php');
}
elseif ($json['method'] == 'proxy.status' || $json['method'] == 'proxy.reassign') {
   include($base_dir.'proxy-assign.php');
}
elseif ($json['method'] == 'dm.info') {
   include($base_dir.'dm-info.php');
}
elseif ($json['method'] == 'queue.info') {
   include($base_dir.'queue-info.php');
}
elseif ($json['method'] == 'circuit.get') {
   include($base_dir.'circuit-cache.php');
}
else {
   sendErrorResponse('235', "Invalid method", "Invalid method");
}
?>
