<?php
include('config.php');

if ($_GET['key'] != 'sjE4i') {
   exit;
}

$sql_where='';
if (isset($_GET['eventid'])) {
   $sql_where = 'WHERE eventid='.$_GET['eventid'];
}

$table_name = 'acknowledges';

$res = $db->get_results("SELECT * FROM $table_name $sql_where ORDER BY clock DESC LIMIT 1");

header('Content-type: application/json');
print json_encode($res);

?>
