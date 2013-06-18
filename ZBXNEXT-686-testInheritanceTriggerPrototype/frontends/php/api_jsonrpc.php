<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/include/func.inc.php';
require_once dirname(__FILE__).'/include/classes/class.chttp_request.php';

$allowed_content = array(
	'application/json-rpc' => 'json-rpc',
	'application/json' => 'json-rpc',
	'application/jsonrequest' => 'json-rpc',
);
$http_request = new CHTTP_request();
$content_type = $http_request->header('Content-Type');
$content_type = explode(';', $content_type);
$content_type = $content_type[0];

if (!isset($allowed_content[$content_type])) {
	header('HTTP/1.0 412 Precondition Failed');
	exit();
}


require_once dirname(__FILE__).'/include/classes/core/Z.php';

header('Content-Type: application/json');
$data = $http_request->body();

try {
	Z::getInstance()->run(ZBase::EXEC_MODE_API);

	$jsonRpc = new CJSONrpc($data);
	echo $jsonRpc->execute();
}
catch (Exception $e) {
	// decode input json request to get request's id
	$jsonData = CJs::decodeJson($data);

	$response = array(
		'jsonrpc' => '2.0',
		'error' => array(
			'code' => 1,
			'message' => $e->getMessage()
		),
		'id' => (isset($jsonData['id']) ? $jsonData['id'] : null)
	);

	echo CJs::encodeJson($response);
}

