<?php

// capture access log info from "logfeed" to catch 500 (and other) errors getting served from cds boxes

// available fields: puburl srcurl referer time duration hier res bytes addr
//                   useragent method range contentlen contentrange forwarded_for
//                   progress_times

$logfile = 'logfeed-capture.log';

$ts = date('M d Y H:i:s');

$log = fopen($logfile, 'a');


if (isset($_GET)) {
   foreach ($_GET as $a=>$b) {
      $msg = "[$ts] GET: KEY: $a VAL: $b\n";
      fwrite($log,$msg);
   }
}
if (isset($_POST)) {
   foreach ($_POST as $a=>$b) {
      $msg = "[$ts] POST: KEY: $a VAL: $b\n";
      fwrite($log,$msg);
   }
}

fclose($fh);
?>
