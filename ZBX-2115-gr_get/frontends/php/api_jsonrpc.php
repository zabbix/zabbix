<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
define('ZBX_PAGE_NO_AUTHORIZATION', 1);
require_once('include/config.inc.php');

$allowed_content = array(
				'application/json-rpc'		=> 'json-rpc',
				'application/json'			=> 'json-rpc',
				'application/jsonrequest'	=> 'json-rpc',
//				'application/xml-rpc'		=> 'xml-rpc',
//				'application/xml'			=> 'xml-rpc',
//				'application/xmlrequest'	=> 'xml-rpc'
				);
?>
<?php

$http_request = new CHTTP_request();
$content_type = $http_request->header('Content-Type');

if(!isset($allowed_content[$content_type])){
	header('HTTP/1.0 412 Precondition Failed');
	exit();
}

$data = $http_request->body();
//SDI($data);

if($allowed_content[$content_type] == 'json-rpc'){
	$json_rpc = new CJSONrpc();

	$json_rpc->process($data);
	$data = $json_rpc->result();

	echo $data;
}
else if($allowed_content[$content_type] == 'xml-rpc'){

}
?>
