#!/usr/bin/php
<?php
$d='/var/www/zabbix-prod/llnw/';
include($d.'config.php');

// this script maintains the squelch db table
// its run from cron every minute. must supply the 'update' cmd line arg to execute.
// this prevents it from running via a web query.

if (!isset($argv[1]) || $argv[1] != 'update') {
   print "Error: exiting\n";
   exit;
}

$pidfile = '/var/run/squelch-mgr-cron-script.pid';
pidCk();


$token = apiAuth();


$group_name = array('llnw-hg_squelched-hosts');

$hostgroup_hash = getHostGrpIds('', '', $group_name);
$hostgroup_id   = array_search($group_name[0], $hostgroup_hash);
//print "HGID: $hostgroup_id\n";

$ldb->query("DELETE FROM squelch WHERE end < now()");

$squelch_hosts = $ldb->get_results("SELECT hostname FROM squelch WHERE start <= now() AND end >= now()");
$squelch_count = $ldb->get_var("SELECT count(*) AS cnt FROM squelch WHERE start <= now() AND end >= now()");

//print "C: $squelch_count\n";

$hostids = array();
if (isset($squelch_hosts)) {
   foreach ($squelch_hosts as $a) {
      $hid = getHostId($a->hostname);
      if ($hid > 0) {
         array_push($hostids, $hid);
      }
   }

   hostGroupMassUpdate($hostgroup_id, $hostids);
}
elseif (isset($squelch_count) && $squelch_count == 0) {
   $grpids = array($hostgroup_id);
   $hostids = getHostsInHostGrp($grpids);
   //print_r($hostids);
   hostGroupMassRemove($hostgroup_id, $hostids);
}
else {
   //print "nadda\n";
}

unlink($pidfile);
exit;

function pidCk() {

   global $pidfile;

   if (file_exists($pidfile)) {
       $pid = file_get_contents($pidfile);
       if (file_exists("/proc/$pid")) {
           error_log( "squelch-mgr-cron-script: found a running instance, exiting.");
           exit(1);
       }
       else {
           error_log( "squelch-mgr-cron-script: previous process exited without cleaning pidfile, removing" );
           unlink($pidfile);
       }
   }
   $h = fopen($pidfile,'w');
   if ($h) {
      fwrite($h, getmypid());
   }
   fclose($h);
}

?>
