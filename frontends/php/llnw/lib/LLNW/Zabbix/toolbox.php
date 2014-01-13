<?php

function apiAuth()
{
    global $zabbix_user, $zabbix_password;

    $auth['method']  = 'user.login';
    $auth['params']['user']      = $zabbix_user;
    $auth['params']['password'] = $zabbix_password;
    $auth['id'] = 1;

    if ($token = zbxToken()) {
        dbug("Using auth token: $token from static file\n");

        return $token;
    } else {
        $resp = apiQuery($auth, 'auth'); // the 'auth' prevents infinit loop in apiQuery fn
        $auth_resp = print_r($resp, true);
        dbug($auth_resp);
        dbug("auth token stale or not avail in static file. authenticated again and got token: ".$resp['result']);
        zbxToken($resp['result']);

        return $resp['result'];
    }
}

function zbxToken($token = '')
{
    global $zabbix_token_file;

    if ($token == '') {
        // get token from file and return it

        if (file_exists($zabbix_token_file)) {
            // see if token file is stale, if it is lets re-auth and get a fresh token.
            $now_ts = strtotime('now');
            $last_mod = filemtime($zabbix_token_file);
            $time_diff = $now_ts - $last_mod;
            if ($time_diff > 12800) {
                return false;
            } else {
                $token = file($zabbix_token_file, FILE_IGNORE_NEW_LINES);
                if ($token[0] == '') {
                    return false;
                } else {
                    return $token[0];
                }
            }
        } else {
            return false;
        }
    } else {
        // save token to file
        $fh = fopen($zabbix_token_file, 'w');
        fwrite($fh, $token);
        fclose($fh);
    }
}

function apiQuery($content, $type = '')
{
    global $apiurl, $apiver, $token, $api_reconnect_counter, $api_dynamic_timeout;

    if (!isset($api_reconnect_counter)) {
        $api_reconnect_counter = 1;
    }
    if (!isset($api_dynamic_timeout)) {
        $api_dynamic_timeout = 10;
    }

    $orig_content = $content;
    $orig_type     = $type;

    if ($type != 'auth'
        && (!isset($token) || $token == '')
    ) { // if we're trying to auth then we certainly dont have a token either.
        $token = apiAuth();
    }


    // $content comes in as an array, we tack on the token and then encode the json
    if (is_array($content)) {
        $method = ($content['method'] != '') ? $content['method'] : '';
        if (isset($token) && $token != '') {
            $content['auth'] = $token;
        }
        if (!isset($content['id'])) {
            $content['id'] = rand(1, 99);
        }
        $content['jsonrpc'] = $apiver;
        $content = json_encode($content);
    } else {
        dbug("Error: content passed to apiQuery is not an array!\n");
    }

    $qs = ($method != '') ? '?'.$method : '';

    $url = $apiurl.$qs;

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($curl, CURLOPT_TIMEOUT, $api_dynamic_timeout); // 2min timeout
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status != 200) {
        dbug(
            "Error: (attempt # $api_reconnect_counter) call to URL $url "
            . "failed with status $status, response $json_response, curl_error "
            . curl_error($curl) . ", curl_errno " . curl_errno($curl)."\n"
        );

        if ($api_reconnect_counter <= 5) {
            ++$api_reconnect_counter;
            $api_dynamic_timeout = $api_dynamic_timeout + 5;
            $response = apiQuery($orig_content, $orig_type);

            return $response;
        } else {
            die("Error: call to URL $url failed with status $status, response $json_response, curl_error "
            . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }
    }

    curl_close($curl);

    $response = json_decode($json_response, true);

    return $response;

}



function sendResponse($hash)
{
    global $trans_id;

    header('Content-type: application/json');

    $hash['jsonrpc'] = '2.0';
    $hash['id'] = $trans_id;
    $json = json_encode($hash);
    //dbug("response sent: ".$json);
    print $json;
    exit;
}

function sendErrorResponse($code, $msg, $data)
{
    $d['error']['code'] = $code;
    $d['error']['message'] = $msg;
    $d['error']['data'] = $data;
    sendResponse($d);
}


function getHostGrpIds($type = '', $field = '', $group_names = '')
{
    $t['method'] = 'hostgroup.get';
    $t['params']['output'] = 'extend';
    if ($type == 'search') {
        $t['params']['search'][$field] = $group_names;
    } else {
        $t['params']['filter']['name'] = $group_names;
    }

    $resp = apiQuery($t);

    //print_r($resp);

    $grps = array();
    foreach ($resp['result'] as $a => $b) {
         $id  = $resp['result'][$a]['groupid'];
         $name = $resp['result'][$a]['name'];
         $grps[$id] = $name;
    }

    return $grps;

}

function getHostsInHostGrp($grpids)
{
    $t['method'] = 'hostgroup.get';
    $t['params']['output'] = 'extend';
    $t['params']['groupids'] = $grpids;
    $t['params']['selectHosts'] = 'refer';

    $resp = apiQuery($t);
    print_r($resp);

    // This does not distinguish between host groups if more than one hostgroup is being searched on
    // it only returns an array of hostids at this time.

    $hostids = array();
    if (isset($resp['result'])) {
        foreach ($resp['result'] as $a => $b) {
            $id = $resp['result'][$a]['groupid'];
            if (isset($resp['result'][$a]['hosts'])) {
                foreach ($resp['result'][$a]['hosts'] as $d => $e) {
                    $hostid = $resp['result'][$a]['hosts'][$d]['hostid'];
                    array_push($hostids, $hostid);
                }
            }
        }
    }

    return $hostids;

}

function getProxies()
{
    $t['method'] = 'proxy.get';
    $t['params']['output'] = 'extend';

    $resp = apiQuery($t);

    if (isset($resp['result'])) {
        return $resp['result'];
    } else {
        return 0;
    }
}


function getHosts($hids = '')
{
    $t['method'] = 'host.get';
    if (is_array($hids)) {
        $t['params']['hostids'] = $hids;
    }
    $t['params']['output'] = 'extend';

    $resp = apiQuery($t);

    if (isset($resp['result'])) {
        return $resp['result'];
    } else {
        return 0;
    }
}


function getHostId($hostname)
{
    $t['method'] = 'host.get';
    $t['params']['search']['host'] = $hostname;
    $t['params']['startSearch'] = 1;
    $t['params']['output'] = 'extend';

    $resp = apiQuery($t);

    //print_r($resp);

    if (isset($resp['result'][0]['hostid'])) {
        foreach ($resp['result'] as $a=>$b) {
            $host = $resp['result'][$a]['host'];

            // this makes an attempt to match on shorten names like cds200.lax when the fqdn is not passed to this fn.
            // if the pattern is not matched, the first entry in the list is returned which should be a fqdn match by the api.
            $pattern = "/^$hostname\./";
            if (preg_match($pattern, $host)) {
                //print "returning matched: $host with $pattern\n";
                return $resp['result'][$a]['hostid'];
            }
        }
        //print "returning first search result entry for hostname: $hostname\n";
        return $resp['result'][0]['hostid'];
    } else {
        //print "no matches found for: $hostname\n";
        return 0;
    }

}

function hostGroupMassUpdate($hostgroup_id, $hosts_array)
{
    //print "In mass update fn\n";

    $t['method'] = 'hostgroup.massUpdate';
    $t['params']['groups'][0]['groupid'] = $hostgroup_id;
    $c=0;
    foreach ($hosts_array as $a=>$b) {
        $t['params']['hosts'][$c]['hostid'] = $b;
        ++$c;
    }

    //print_r($t);
    $resp = apiQuery($t);

    //print_r($resp);

}

function hostGroupMassRemove($hostgroup_id, $hosts_array)
{
    $t['method'] = 'hostgroup.massRemove';
    $t['params']['groupids'][0] = $hostgroup_id;
    $c=0;
    foreach ($hosts_array as $a=>$b) {
        $t['params']['hostids'][$c] = $b;
        ++$c;
    }
    print_r($t);
    $resp = apiQuery($t);

    print_r($resp);

}

function dbug($msg)
{
    global $debug, $log;

    if (is_array($msg)) {
        $msg = print_r($msg, true);
    }

    $msg = (!preg_match('/\n/', $msg)) ? $msg."\n" : $msg;
    if ($debug == 1 || $debug == 3) {
        if (isset($log)) {
            fwrite($log, $msg);
        }
    }
    if ($debug == 2 || $debug == 3) {
        print $msg;
    }
}
