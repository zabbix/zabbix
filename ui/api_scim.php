<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require __DIR__ . '/vendor/autoload.php';
require_once __DIR__.'/include/func.inc.php';
require_once __DIR__.'/include/classes/core/CHttpRequest.php';
require_once __DIR__.'/include/classes/core/APP.php';

use SCIM\API as SCIM;
use SCIM\clients\ScimApiClient;
use SCIM\HttpResponse;
use SCIM\services\Group;
use SCIM\services\ServiceProviderConfig;
use SCIM\services\User;

$request = new CHttpRequest(['PATH_INFO', 'QUERY_STRING']);

try {
	APP::getInstance()->run(APP::EXEC_MODE_API);
	API::setWrapper();
	$client = new ScimApiClient();
	$client->setServiceFactory(new CRegistryFactory([
		'users'						=> User::class,
		'groups'					=> Group::class,
		'serviceproviderconfig'		=> ServiceProviderConfig::class
	]));
	$scim = new SCIM();

	$response = $scim->execute($client, $request);
}
catch (Throwable $e) {
	$response = new HttpResponse();
	$exception = $e instanceof APIException ? $e : new APIException(ZBX_API_ERROR_INTERNAL, $e->getMessage());

	$response->setException($exception);
}

$response->send();
