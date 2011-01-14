<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	require_once(dirname(__FILE__).'/../../include/func.inc.php');

	global  $URL;
	global  $ID;
	global	$auth;

	$ID = 1;
	if(strstr(PHPUNIT_URL,'http://'))
	{
		$URL=PHPUNIT_URL.'api_jsonrpc.php';
	} else {
		$URL='http://hudson/~hudson/'.PHPUNIT_URL.'/frontends/php/api_jsonrpc.php';
	}

function do_post_request($data,&$raw){
	global $URL;
	global $ID;

	$data = json_encode($data);

//	print("Request:\n".$data."\n");

	$raw="----DATA FLOW-------\nRequest:\n$data\n\n";

	$params = array(
			'http' => array(
				'method' => 'post',
				'content' => $data
			));


	$params['http']['header'] = "Content-type: application/json-rpc\r\n".
		"Content-Length: ".strlen($data)."\r\n".
		"\r\n";

	$ctx = stream_context_create($params);

	$fp = fopen($URL, 'rb', false, $ctx);
	if(!$fp) {
		throw new Exception("Problem with $URL, $php_errormsg");
	}

	$response = @stream_get_contents($fp);

	fclose($fp);

	if($response === false) {
		throw new Exception("Problem reading data from $URL, $php_errormsg");
	}

	$ID++;
//	print("Response:\n".$response."\n\n");
	$raw=$raw."Response:\n$response\n--------------------\n\n";

	return $response;
}

function call_api($method, $params, &$raw){
	global $ID;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'id'=> $ID
			);

	$response = do_post_request($data,$raw);
//	print("Request:\n".$raw."\n");
	$decoded = json_decode($response, true);

	return $decoded;
}

function api_auth($user, $password){
	global $ID;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'user.authenticate',
			'params' => array('user'=>'Admin', 'password'=>'zabbix'),
			'id'=> $ID
			);

	$response = do_post_request($data,$raw);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded['result'];
}

function api_host_delete($hostids) {
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'host.delete',
//			'params' => array(array('hostid'=>$hostid),array('hostid'=>2333)),
			'params' => zbx_toObject($hostids,'hostid'),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded;
}

function api_host_get($hostid) {
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'host.get',
//			'params' => array('hostids'=>$hostids,'extendoutput'=>0,'filter'=>array('host'=>'nz_1537')),
			'params' => array('extendoutput'=>1,'filter'=>array('host'=>'nz_1537')),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded;
}

function api_host_update($hostid) {
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'host.update',
			'params' => array('hostid'=>$hostid,'status'=>0),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded;
}

function api_host_massUpdate($hostids) {
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'host.massUpdate',
			'params' => array('hosts'=>zbx_toObject($hostids,'hostid'),'status'=>0,'use_ip'=>1),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded;
}


function api_host_add($host, $ip, $groups, $templates){
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'host.create',
			'params' => array('host'=>$host, 'ip'=>$ip, 'port'=>10050, 'useip'=>1, 'groups'=>$groups,'templates'=>$templates),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded['result']['hostids'][0];
}

function api_hostgroup_add($name){
	global $ID;
	global $auth;

	$data = array(
			'jsonrpc' => '2.0',
			'method' => 'hostgroup.create',
			'params' => array('name'=>$name),
			'auth' => $auth,
			'id'=> $ID
			);

//	print_r($data);

	$response = do_post_request($data);
	$decoded = json_decode($response, true);

//	print_r($decoded);

	return $decoded['result']['groupids'][0];
}




$auth = api_auth('Admin','zabbix');
/*

print	"Athenticated: $auth\n";
exit;

$IP='192.168.3.2';
$TEMPLATEID=10001;
$HOSTS_PER_GROUP=10;

$hostgroups = array(
	'Brazil'	=>'br',
	'Australia'	=>'au',
	'Canada'	=>'ca',
	'Estonia'	=>'ee',
	'Finland'	=>'fi',
	'France'	=>'fr',
	'Germany'	=>'de',
	'Japan'		=>'jp',
	'Lithuania'	=>'lt',
	'Latvia'	=>'lv',
	'Netherlands'	=>'nl',
	'Poland'	=>'pl',
	'Russia'	=>'ru',
	'Spain'		=>'sp',
	'Sweden'	=>'se',
	'United Kingdom'=>'uk',
	'USA'		=>'us',
	'Ireland'	=>'ie',
	'Togo'		=>'tg',
	'Armenia'	=>'am',
	'Cina'		=>'cn',
	'South Africa'	=>'sa',
	'Norway'	=>'no',
	'Belorussia'	=>'by',
	'Hungary'	=>'hu',
	'Georgia'	=>'ge',
	'Portuguese'	=>'pt',
	'Chili'		=>'ci'
);

#$result = api_host_update(69665);
#$result = api_host_massUpdate(array(69665,69666));
#$result = api_hostgroup_add("New group2");

foreach($hostgroups as $hostgroup => $shortname)
{
	print "Group: $hostgroup Short name: $shortname\n";
	$hostgroupid = api_hostgroup_add($hostgroup);
	print "Hostgroup ID: $hostgroupid\n";
	for($i=1;$i<=$HOSTS_PER_GROUP;$i++)
	{
		$hostname=sprintf("%s_%03d",$shortname,$i);
		$hostid = api_host_add($hostname,$IP,array(array('groupid'=>$hostgroupid)),array(array('templateid'=>$TEMPLATEID)));
		print	"$host: New host ID: $hostid\n";
	}
}

exit;

#$result = api_host_delete($hostid);

//print	"Deleted: $hostid\n";
*/
