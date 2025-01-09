<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	$user_type = CUser::$userData === null ? USER_TYPE_ZABBIX_USER : CUser::$userData['type'];

	if ($e instanceof DBException && $user_type != USER_TYPE_SUPER_ADMIN) {
		$exception = new APIException(ZBX_API_ERROR_INTERNAL,
			_('System error occurred. Please contact Zabbix administrator.')
		);
	}
	else {
		$exception = $e instanceof APIException ? $e : new APIException(ZBX_API_ERROR_INTERNAL, $e->getMessage());
	}

	$response->setException($exception);
}

$response->send();
