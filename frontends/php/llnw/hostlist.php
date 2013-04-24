<?php
include_once('config.php');



if ($json['method'] == 'hostlist.get' || $json['method'] == 'hostlist.pull') {

   // this section returns the current list of hosts in the given hostlist
   // below is where we determine if we have to truncate the file or not.
   
   $list_name = (isset($json['params']['name']) && $json['params']['name'] != '') ? addslashes($json['params']['name']) : '';
   $output    = (isset($json['params']['output']) && $json['params']['output'] == 'raw') ? 'raw' : 'json';

   if ($list_name == '') {
      error_log("Error: no hostlist name supplied in request");
      sendErrorResponse('235','Invalid params','no hostlist name provided');
   }

   $list_dir = '/tmp/';

   if (!is_readable($list_dir.$list_name)) {
      error_log("Error: hostlist file $list_dir$list_name does not exist");
      sendErrorResponse('151','Internal error','hostlist file does not exist');
   }

   $hostlist = file($list_dir.$list_name);

   // removes the newlines from each item in array
   $hostlist = array_map('trim', $hostlist);

   $resp['result'] = 'success';
   $resp['hosts'] = $hostlist;

   // method 'pull' truncates the list.
   if ($json['method'] == 'hostlist.pull') {
      if (!is_writable($list_dir.$list_name)) {
         error_log("Error: hostlist file $list_dir$list_name cannot be truncated");
      }
      else {
         $fh = fopen($list_dir.$list_name, 'w');
         fclose($fh);
      }
   }

   if ($output == 'json') {
      sendResponse($resp);
   }
   else {
      foreach ($hostlist as $host) {
         print $host."\n";
      }
      exit;
   }
}

?>
