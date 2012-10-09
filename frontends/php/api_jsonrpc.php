<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

require_once dirname(__FILE__).'/include/debug.inc.php';
require_once dirname(__FILE__).'/include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/include/defines.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';
require_once dirname(__FILE__).'/include/perm.inc.php';
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/validate.inc.php';
require_once dirname(__FILE__).'/include/profiles.inc.php';

// abc sorting
require_once dirname(__FILE__).'/include/acknow.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';
require_once dirname(__FILE__).'/include/images.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/maintenances.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';
require_once dirname(__FILE__).'/include/sounds.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/valuemap.inc.php';
require_once dirname(__FILE__).'/include/nodes.inc.php';


header('Content-Type: application/json');
$data = $http_request->body();

try {
	Z::getInstance()->run();

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

