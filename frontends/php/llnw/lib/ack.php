<?php
include('config.php');

if ($_POST['key'] != 'sjE4i') {
   exit;
}

$_POST['method'] = urldecode($_POST['method']);

if ($_POST['method'] == 'get.ack' || $_POST['method'] == 'ack.get') {
   $sql_where = (isset($_POST['eventids']) && $_POST['eventids'] != '') ? 'WHERE eventid IN ('.$_POST['eventids'].')' : '';

   $q = "SELECT * FROM acknowledges $sql_where ORDER BY clock ASC";

   error_log("get.ack query: $q");
   $resp = $db->get_results($q);

}
elseif ($_POST['method'] == 'add.ack' || $_POST['method'] == 'ack.add') {
   $userid  = $_POST['userid'];
   $triggerid = $_POST['triggerid']; // this is the objectid in the events table
                                     // have to use triggerid because checkboxes are trigger ids.

   $clock   = strtotime('now'); //$_POST['clock'];
   $message = urldecode($_POST['message']);
   $message = addslashes($message);

   error_log("received triggerid: $triggerid");

   $q = "SELECT eventid FROM events ".
        "WHERE objectid=$triggerid AND value=1 ".
        "ORDER BY clock DESC LIMIT 1";

   $eventid = $db->get_var($q);
   error_log("got eventid from events table: $eventid");

   $resp['result'] = 'error';
   if (isset($eventid) && $eventid > 0) {

      $ackid = $db->get_var("SELECT nextid FROM ids WHERE table_name='acknowledges' LIMIT 1");
      error_log("got nextid: $ackid");
      if (isset($ackid) && $ackid > 0) {
         $next_id = $ackid + 1;
         error_log("adding $next_id to ids table");
         $db->get_var("UPDATE ids SET nextid=$next_id WHERE table_name='acknowledges'");

         $q = "UPDATE events SET acknowledged=1 WHERE eventid=$eventid";
         $db->query($q);
         error_log($q);

         $q = "INSERT INTO acknowledges SET acknowledgeid='$ackid', userid='$userid', eventid='$eventid', clock='$clock', message='$message'";
         $db->query($q);
         error_log($q);

         $resp['result'] = 'success';
      }
   }
}

header('Content-type: application/json');
$json = json_encode($resp);
//error_log("response sent: ".$json);
print $json;
?>
