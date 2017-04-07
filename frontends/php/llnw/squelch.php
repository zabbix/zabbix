<?php
include_once('config.php');


if ($json['method'] == 'add.squelch') {

   // accept a start and end timestamp. if start time is omitted, use now();
   // if end time is also omitted, use a default of 1 hour.
   // only accept hostnames. we'll convert hostnames to hostids in the cron script that processes squelches.
   // also accept a username, reason (short text), and comment (longer text).
   
   $start_ts = (isset($json['params']['start']) && $json['params']['start'] != '') ? addslashes($json['params']['start']) : strtotime("now");
   $end_ts   = (isset($json['params']['end']) && $json['params']['end'] != '') ? addslashes($json['params']['end']) : strtotime("+1 hour");

   $start = date('Y-m-d H:i:s', $start_ts);
   $end   = date('Y-m-d H:i:s', $end_ts);

   $username = addslashes($json['params']['username']);
   $reason   = addslashes($json['params']['reason']);
   $comment  = addslashes($json['params']['comment']);

   if ($username == '') {
      error_log("Error: no username set to apply squelch!");
      sendErrorResponse('235','Invalid params','no username provided');
   }

   $site = (isset($json['params']['site']) && $json['params']['site'] != '') ? addslashes($json['params']['site']) : '';

   if ($site != '') {

      $site = strtolower($site);
      $hg_string = array();
      $hg_string['net'] = 'llnw-hg_net-pop-'.$site;
      $hg_string['sys'] = 'llnw-hg_sys-pop-'.$site;

      $hgids = array();
      foreach ($hg_string as $key=>$val) {
         $hgs = getHostGrpIds('', '', $val);
         foreach ($hgs as $a=>$b) {
            array_push($hgids, $a);
         }
      }

      $hostids = getHostsInHostGrp($hgids);
      $resp = getHosts($hostids);

      $json['params']['hostname'] = array();
      foreach ($resp as $a=>$b) {
         array_push($json['params']['hostname'], $b['name']);
      }
   }


   // validate hostname entries
   foreach ($json['params']['hostname'] as $key=>$hostname) {
      $hostname = addslashes($hostname);

      error_log("received squelch for $hostname start: $start end: $end");

      if ($hostname == '') {
         error_log("Error: no hostname set to apply squelch!");
         sendErrorResponse('234','Invalid params','invalid hostname or hostname missing');
      }
   }

   // see if a squelch already exists for given host. 
   // if so, and existing clear time is less then new clear time then update it.
   // if not, insert a new squelch entry.

   // maintain 2 tables: squelch = just hostid, start and end times.
   // squelch_log = entries for each submittion - hostid,start,end plus - username,reason,comment,action (add or clear)


   foreach ($json['params']['hostname'] as $key=>$hostname) {
      $hostname = addslashes($hostname);

      $q = "SELECT * FROM squelch WHERE hostname = '$hostname'";
      $e = $ldb->get_row($q);

      if (isset($e) && $e->id > 0) {

         $id = $e->id;

         $existing_start_ts = strtotime($e->start);
         $existing_end_ts   = strtotime($e->end);

         $sql = "UPDATE squelch SET ";

         // if new start time is earlier than existing starttime:
         if ($start_ts < $existing_start_ts) {
            // need to udpate start timea
            $su=1;
            $sql .= "start = '$start'";
         }
         // if existing end time is earlier than the new end time:
         if ($existing_end_ts < $end_ts) {
            // need to update end time
            $eu=1;
            $sql .= (isset($su)) ? ", end = '$end'" : "end = '$end'"; // adds the comma if we're appending.
         }

         $sql .= ", updated_by = '$username', updated_date=now()";
         $sql .= " WHERE id = $id";

         if (isset($su) || isset($eu)) {
            error_log($sql);
            $ldb->query($sql);
         }
      }
      else {
         $sql = "INSERT INTO squelch SET ".
                "hostname = '$hostname', ".
                "start = '$start', ".
                "end = '$end', ".
                "created_by = '$username', ".
                "created_date = now(), ".
                "updated_date = '0000-00-00 00:00:00'";

         error_log($sql);
         $ldb->query($sql);

         $id = $ldb->insert_id;
      }

      // log the details of this squelch request

      $sql = "INSERT INTO squelch_log SET ".
             "squelch_id = $id, ".
             "hostname = '$hostname', ".
             "start = '$start', ".
             "end = '$end', ".
             "reason = '$reason', ".
             "comment = '$comment', ".
             "created_by = '$username', ".
             "created_date = now(), ".
             "updated_date = '0000-00-00 00:00:00'";

      error_log($sql);
      $ldb->query($sql);

      $resp['result']['hostnames'][$key] = $hostname;
   }
   sendResponse($resp);

}
elseif ($json['method'] == 'get.squelch') {
   
   $active = ($json['params']['active'] == 0) ? 0 : 1;

   if ($active == 1) {
      $sql = "SELECT MAX(l.id) AS id, l.hostname, l.start, l.end, l.reason, l.comment, l.created_by, l.created_date
              FROM squelch AS s 
              LEFT JOIN squelch_log AS l ON s.id = l.squelch_id 
              GROUP BY s.hostname 
              ORDER BY s.start ASC";
   }
   else {
      $sql = "SELECT id, hostname, start, end, reason, created_by, created_date, comment
              FROM squelch_log
              WHERE end >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
              ORDER BY start ASC";
   }

   $resp = array();
   $resp['result'] = $ldb->get_results($sql);

   sendResponse($resp);

}
elseif ($json['method'] == 'clear.squelch') {
   // run the possibility of clearing someone elses squelch that may have been submitted for a different reason.
   // but not going to worry about that now.
   // be sure to log these clears as a separate entry in the squlech_log so we can track potential issues.
   // this separate table may help with logic to avoid clearing other squelches?
   // FOR avoiding of clearing only authorized squelches, we need to send back some ID, and optionally require it for delete
   // could be optonal or predictible, for something shared we could use THE same ID, but for safety ones generates and send them back for add method.


    $hostname = $json['params']['hostname'];
    $username = $json['params']['username'];
    $comment = "deleted squelch";
    $reason = $json['params']['reason'];
    $start = date('Y-m-d H:i:s');

    if ($username == '' || $hostname == '') {
      error_log("Error: no username/hostname set to apply squelch!");
      sendErrorResponse('235','Invalid params','no username or hostname provided');
    }

    $sql = "SELECT * FROM squelch
            WHERE hostname='$hostname' AND created_by = '$username'";

    error_log($sql);
    $rows = $ldb->get_results($sql);
    $resp['result'] = $ldb->get_results($sql);
    $ids = array();

    if (isset($rows)) {

      foreach ($rows as $row) {
        $row = get_object_vars($row);
        array_push($ids, $row['id']);
      }

      foreach ($ids as $id) {
        $sql = "INSERT INTO squelch_log SET ".
            "squelch_id = '$id', ".
            "hostname = '$hostname', ".
            "start = '$start', ".
            "end = '$start', ".
            "reason = '$reason', ".
            "comment = '$comment', ".
            "created_by = '$username', ".
            "created_date = now(), ".
            "updated_date = '0000-00-00 00:00:00'";

        error_log($sql);
  
        $ldb->query($sql);
      }
      $sql = "DELETE FROM squelch
              WHERE hostname='$hostname' AND created_by = '$username'";
      error_log($sql);

      $ldb->query($sql);

      sendResponse($resp);
    }
    else {
      error_log("Error: Incorrect username/hostname provided");
      sendErrorResponse('235','Invalid params','not valid host or username');
    }

 }

?>