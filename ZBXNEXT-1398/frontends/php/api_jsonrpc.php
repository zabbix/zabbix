<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


define('ZBX_RPC_REQUEST', 1);

require_once dirname(__FILE__).'/include/config.inc.php';

$allowedContent = array(
	'application/json-rpc'		=> 'json-rpc',
	'application/json'			=> 'json-rpc',
	'application/jsonrequest'	=> 'json-rpc'
);

$httpRequest = new CHTTP_request();

$contentType = $httpRequest->header('Content-Type');
$contentType = explode(';', $contentType);
$contentType = $contentType[0];

if (empty($allowedContent[$contentType])) {
	header('HTTP/1.0 412 Precondition Failed');
	exit();
}
elseif ($allowedContent[$contentType] == 'json-rpc') {
	header('Content-Type: application/json');

	$jsonRpc = new CJSONrpc($httpRequest->body());

	echo $jsonRpc->execute();
}
