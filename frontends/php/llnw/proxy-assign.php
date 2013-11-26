<?php
include_once('config.php');

// this has the $static_proxies array in it
include_once($base_dir.'static-proxy.list');

// FOR TESTING
/*
$json['method'] = 'proxy.status';
$json['params']['proxynames'] = array('PHX01');
$json['params']['selectAll'] = 1;
$json['params']['output'] = 'json';

$json = array();
$json['method'] = 'proxy.reassign';
$json['params']['from_proxy'] = 'BAH01';
$json['params']['to_proxy'] = 'BAH02';
$json['params']['force_move'] = 1;
*/


if ($json['method'] == 'proxy.status') {

   $proxynames = (is_array($json['params']['proxynames']) && count($json['params']['proxynames']) > 0) ? $json['params']['proxynames'] : '';
   $all        = (isset($json['params']['selectAll'])) ? 1 : '';
   $output     = (isset($json['params']['output']) && $json['params']['output'] == 'csv') ? 'csv' : 'json';

   if ($proxynames == '' && $all == '') {
      dbug("Error: no proxynames or selectAll param supplied in request");
      sendErrorResponse('235','Invalid params','no proxyname or selectAll param provided');
   }

   if ($all == 1) {
      $proxy_hash = getProxiesNew('',1);
   }
   else {
      $proxyids = array();
      foreach ($proxynames as $proxy) {
         $hostid = getProxyId($proxy);
         array_push($proxyids, $hostid[0]['proxyid']);
      }
      $proxy_hash = getProxiesNew($proxyids,1);
   }

   $resp = array();

   $c=0;
   foreach ($proxy_hash as $a=>$b) {
      $host       = $proxy_hash[$a]['host'];
      $lastaccess = $proxy_hash[$a]['lastaccess'];
      $proxyid    = $proxy_hash[$a]['proxyid'];

      $last_response = strtotime('now') - $lastaccess;

      $host_count = count($proxy_hash[$a]['hosts']);

      $resp['result'][$c]['host'] = $host;
      $resp['result'][$c]['lastaccess'] = $last_response;
      $resp['result'][$c]['proxyid'] = $proxyid;
      $resp['result'][$c]['host_count'] = $host_count;
      ++$c;
   }

   if ($output == 'json') {
      sendResponse($resp);
   }
   else {
      foreach ($resp['result'] as $c) {
         print $c['host'].','.$c['proxyid'].','.$c['lastaccess'].','.$c['host_count']."\n";
      }
      exit;
   }
}
elseif ($json['method'] == 'proxy.reassign') {
// Automatically moves all hosts from a given proxy to the closest alternate.
// A force move is possible by specifyin a second proxy and a force parameter.
// proxy.reassign <proxy shortname 1> [<proxy shortname 2> <force true/false>]
// ex: proxy.reassign PRX01 PRX02 1
   $proxy_data = file_get_contents('proxymap.json');
   $proxymap = json_decode($proxy_data,true);

   $from_proxy = (isset($json['params']['from_proxy'])) ? $json['params']['from_proxy'] : '';
   $to_proxy   = (isset($json['params']['to_proxy'])) ? $json['params']['to_proxy'] : '';
   $force_move = (isset($json['params']['force_move'])) ? $json['params']['force_move'] : '';

   if ($from_proxy == '') {
      dbug("Error: required from_proxy param was not set");
      sendErrorResponse('235','Invalid params','no from_proxy param provided');
   }

   $proxy_hash = getProxiesNew();

   $proxies_lastaccess = array();
   $proxyids = array();

   foreach ($proxy_hash as $a=>$b) {
      $proxy      = $proxy_hash[$a]['host'];
      $lastaccess = $proxy_hash[$a]['lastaccess'];
      $proxyid    = $proxy_hash[$a]['proxyid'];

      $proxies_lastaccess[$proxy] = $lastaccess;
      $proxyids[$proxy] = $proxyid;

      if ($proxy == $from_proxy) {
         $the_proxy = $proxy;
      }
   }

   if (!isset($the_proxy)) {
      dbug("Error: no matching proxy found by the name: $from_proxy");
      sendErrorResponse('285','Invalid params','proxy name not found');
   }

   if (!isset($proxymap[$the_proxy])) {
      dbug("Error: no matching proxy found in sitemap: $from_proxy");
      sendErrorResponse('285','Invalid params','proxy name not found in sitemap');
   }

   foreach ($proxymap[$the_proxy] as $alternates) {
      if (isset($proxies_lastaccess[$alternates])) {
         $resptime = strtotime('now') - $proxies_lastaccess[$alternates];
         if ($to_proxy == '') { 
            if ($resptime < 60) {
               $new_proxy = $alternates;
               break;
            }
         }
         else {
            if ($to_proxy == $alternates) {
               if ($force_move != '') {
                  $new_proxy = $alternates;
                  break;
               }
               elseif ($resptime < 60) {
                  $new_proxy = $alternates;
                  break;
               }
            }
         }
      }
   }
   if (isset($new_proxy)) {
      $resp = getProxiesNew($proxyids[$the_proxy], 1);
      $host_count_prev = count($resp[0]['hosts']);

      $hostids = array();
      $hostids_obj = array();

      $c=0;
      foreach ($resp[0]['hosts'] as $w) {
         array_push($hostids, $w['hostid']);
         $hostids_obj[$c]['hostid'] = $w['hostid'];
         ++$c;
      }

      $resp = getProxiesNew($proxyids[$new_proxy], 1);
      $host_count_new = count($resp[0]['hosts']);

      dbug("Attempting to move Hosts from: $the_proxy (current host assignment count: $host_count_prev) "
          ."to: $new_proxy (current host assignment count: $host_count_new) [resp time: $resptime secs]. "
          ."force option set to: $force_move");

      $resp = assignHostsToProxy($proxyids[$new_proxy],$hostids_obj);

      if (is_array($resp)) {
         $resp['moved_from']['name'] = $the_proxy;
         $resp['moved_from']['host_count'] = $host_count_prev;
         $resp['moved_to']['name'] = $new_proxy;
         $resp['moved_to']['host_count'] = $host_count_new;
         sendResponse($resp);
         exit;
      }
      else {
         dbug('proxy host reassignment api query failed');
         sendErrorResponse('246','Warning','proxy host assignment failed');
      }
   }
   else {
      dbug("Error: unable to find a suitable proxy to migrate to: $from_proxy");
      sendErrorResponse('225','Warning','unable to find a suitable proxy to migration to');
   }
   exit;
}

/**
 * proxy.get Zabbix API helper
 *
 * @param string,array $proxyids    Proxy or proxies
 * @param array        $selectHost  Specific hosts
 */
function getProxiesNew($proxyids='',$selectHosts='') {

   $t['method'] = 'proxy.get';
   $t['params']['output'] = 'extend';
   $t['params']['sortfield'] = 'host';
   if ($selectHosts != '') {
      $t['params']['selectHosts'] = array('host');
   }
   if (is_array($proxyids)) {
      $t['params']['proxyids'] = $proxyids;
   }
   elseif ($proxyids != '') {
      $t['params']['proxyids'] = array($proxyids);
   }

   $resp = apiQuery($t);

   if (isset($resp['result'])) {
      return $resp['result'];
   }
   else {
      return 0;
   }
}

/**
 * Get a proxy id from a hostname
 * @param $name hostname (long or short)
 */
function getProxyId($name) {

   $t['method'] = 'proxy.get';
   $t['params']['output'] = 'hostid';
   $t['params']['filter']['host'] = $name;

   $resp = apiQuery($t);

   if (isset($resp['result'])) {
      return $resp['result'];
   }
   else {
      return 0;
   }
}

/**
 * Assign hosts to a proxy (host.massupdate API helper)
 * @param $proxyid
 * @param array $hostids
 */
function assignHostsToProxy($proxyid, $hostids) {

   $t['method'] = 'host.massupdate';
   $t['params']['hosts'] = $hostids;
   $t['params']['proxy_hostid'] = $proxyid;

   //print_r($t);

   $resp = apiQuery($t);

   if (isset($resp['result'])) {
      return $resp['result'];
   }
   else {
      return 0;
   }
}

?>
