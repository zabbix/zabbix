<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

require_once('include/config.inc.php');

$page['title'] = "RPC";
$page['file'] = 'jsrpc.php';
$page['hist_arg'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_JSON);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array();
	check_fields($fields);

// ACTION /////////////////////////////////////////////////////////////////////////////
	$http_request = new CHTTP_request();
	$data = $http_request->body();

	$json = new CJSON();
	$data = $json->decode($data, true);

	if(!is_array($data)) fatal_error('Wrong RPC call to JS RPC');
	if(!isset($data['method']) || !isset($data['params'])) fatal_error('Wrong RPC call to JS RPC');
	if(($data['method'] != 'host.get') || !is_array($data['params'])) fatal_error('Wrong RPC call to JS RPC');

	$pattern = $data['params']['pattern'];

	$options = array(
		"startPattern" => 1,
		"pattern" => $pattern,
		"output" => array("hostid", "host"),
		"sortfield" => "host",
		"limit" => 15
	);

	$hosts = CHost::get($options);

	$rpcResp = array(
		'jsonrpc' => '2.0',
		'result' => $hosts,
		'id' => $data['id']
	);

	print($json->encode($rpcResp));
?>
<?php

include_once('include/page_footer.php');

?>