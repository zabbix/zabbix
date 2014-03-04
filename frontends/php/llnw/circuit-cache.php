<?php
include_once('config.php');

if ($json['method'] == 'circuit.get') {
    $host = (isset($json['params']['host'])) ? $json['params']['host'] : '';
    $regx = (isset($json['params']['regx'])) ? $json['params']['regx'] : '';
}
else {
    exit;
}

if ($host == '' || $regx == '') {
    print "ERROR: missing required fields.\n";
    exit;
}

$host = addslashes($host);
$regx = addslashes($regx);

$q = "SELECT * FROM interface_cache
      WHERE ifAdminStatus = 1
      AND device = '$host'
      AND ifDescr LIKE '%Ethernet%'
      AND ifAlias REGEXP '$regx'";

$res = $cdb->get_results($q);

$data = array();

$c=0;
foreach ($res as $a) {
    $data['data'][$c]['{#HOST}'] = $host;
    $data['data'][$c]['{#IFDESCR}'] = $a->ifDescr;
    $data['data'][$c]['{#IFALIAS}'] = $a->ifAlias;
    $data['data'][$c]['{#IFINDEX}'] = $a->ifIndex;
    ++$c;
}

$json = json_encode($data);

print $json;

?>
