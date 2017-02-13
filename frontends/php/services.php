<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Configuration of IT services');
$page['file'] = 'services.php';
$page['scripts'] = ['class.calendar.js'];

if (isset($_REQUEST['pservices']) || isset($_REQUEST['cservices'])) {
	define('ZBX_PAGE_NO_MENU', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'serviceid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'name' => 							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'algorithm' =>						[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({add}) || isset({update})'],
	'showsla' =>						[T_ZBX_INT, O_OPT, null,	IN([SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON]),	null],
	'goodsla' => 						[T_ZBX_DBL, O_OPT, null,	BETWEEN(0, 100), null, _('Calculate SLA, acceptable SLA (in %)')],
	'sortorder' => 						[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 999), null, _('Sort order (0->999)')],
	'times' =>							[T_ZBX_STR, O_OPT, null,	null,		null],
	'triggerid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'trigger' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'new_service_time' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'new_service_time_from_day' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_from_month' =>	[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_from_year' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_from_hour' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_from_minute' =>	[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_to_day' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_to_month' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_to_year' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_to_hour' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'new_service_time_to_minute' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
	'children' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'parentid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'parentname' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'add' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_service_time' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null],
	// others
	'form' =>							[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>					[T_ZBX_INT, O_OPT, null,	null,		null],
	'pservices' =>						[T_ZBX_INT, O_OPT, null,	null,		null],
	'cservices' =>						[T_ZBX_INT, O_OPT, null,	null,		null]
];
check_fields($fields);

/*
 * Permissions
 */
if (!empty($_REQUEST['serviceid'])) {
	$options = [
		'output' => API_OUTPUT_EXTEND,
		'serviceids' => $_REQUEST['serviceid']
	];

	if (isset($_REQUEST['delete']) || isset($_REQUEST['pservices']) || isset($_REQUEST['cservices'])) {
		$options['output'] = ['serviceid', 'name'];
	}
	else {
		$options['selectParent'] = ['serviceid', 'name'];
		$options['selectDependencies'] = API_OUTPUT_EXTEND;
		$options['selectTimes'] = API_OUTPUT_EXTEND;
	}

	$service = API::Service()->get($options);
	$service = reset($service);
	if (!$service) {
		access_deny();
	}
}

/*
 * Actions
 */

// delete
if (isset($_REQUEST['delete']) && isset($_REQUEST['serviceid'])) {
	DBstart();

	$result = API::Service()->delete([$service['serviceid']]);

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_IT_SERVICE, 'Name ['.$service['name'].'] id ['.$service['serviceid'].']');
		unset($_REQUEST['form']);
	}
	unset($service);

	$result = DBend($result);
	show_messages($result, _('Service deleted'), _('Cannot delete service'));
}

if (isset($_REQUEST['form'])) {
	$result = false;

	// save
	if (hasRequest('add') || hasRequest('update')) {
		DBstart();

		$children = getRequest('children', []);
		$dependencies = [];
		foreach ($children as $child) {
			$dependencies[] = [
				'dependsOnServiceid' => $child['serviceid'],
				'soft' => (isset($child['soft'])) ? $child['soft'] : 0
			];
		}

		$request = [
			'name' => getRequest('name'),
			'triggerid' => getRequest('triggerid'),
			'algorithm' => getRequest('algorithm'),
			'showsla' => getRequest('showsla', SERVICE_SHOW_SLA_OFF),
			'sortorder' => getRequest('sortorder'),
			'times' => getRequest('times', []),
			'parentid' => getRequest('parentid'),
			'dependencies' => $dependencies
		];
		if (hasRequest('goodsla')) {
			$request['goodsla'] = getRequest('goodsla');
		}

		if (isset($service['serviceid'])) {
			$request['serviceid'] = $service['serviceid'];

			$result = API::Service()->update($request);

			$messageSuccess = _('Service updated');
			$messageFailed = _('Cannot update service');
			$auditAction = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::Service()->create($request);

			$messageSuccess = _('Service created');
			$messageFailed = _('Cannot add service');
			$auditAction = AUDIT_ACTION_ADD;
		}

		if ($result) {
			$serviceid = (isset($service['serviceid'])) ? $service['serviceid'] : reset($result['serviceids']);
			add_audit($auditAction, AUDIT_RESOURCE_IT_SERVICE, 'Name ['.$_REQUEST['name'].'] id ['.$serviceid.']');
			unset($_REQUEST['form']);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
	// validate and get service times
	elseif (isset($_REQUEST['add_service_time']) && isset($_REQUEST['new_service_time'])) {
		$_REQUEST['times'] = getRequest('times', []);
		$new_service_time['type'] = $_REQUEST['new_service_time']['type'];
		$result = true;
		if ($_REQUEST['new_service_time']['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
			if (!validateDateTime($_REQUEST['new_service_time_from_year'],
					$_REQUEST['new_service_time_from_month'],
					$_REQUEST['new_service_time_from_day'],
					$_REQUEST['new_service_time_from_hour'],
					$_REQUEST['new_service_time_from_minute'])) {
				$result = false;
				error(_s('Invalid date "%s".', _('From')));
			}
			if (!validateDateInterval($_REQUEST['new_service_time_from_year'],
					$_REQUEST['new_service_time_from_month'],
					$_REQUEST['new_service_time_from_day'])) {
				$result = false;
				error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('From')));
			}
			if (!validateDateTime($_REQUEST['new_service_time_to_year'],
					$_REQUEST['new_service_time_to_month'],
					$_REQUEST['new_service_time_to_day'],
					$_REQUEST['new_service_time_to_hour'],
					$_REQUEST['new_service_time_to_minute'])) {
				$result = false;
				error(_s('Invalid date "%s".', _('Till')));
			}
			if (!validateDateInterval($_REQUEST['new_service_time_to_year'],
					$_REQUEST['new_service_time_to_month'],
					$_REQUEST['new_service_time_to_day'])) {
				$result = false;
				error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Till')));
			}
			if ($result) {
				$new_service_time['ts_from'] = mktime($_REQUEST['new_service_time_from_hour'],
						$_REQUEST['new_service_time_from_minute'],
						0,
						$_REQUEST['new_service_time_from_month'],
						$_REQUEST['new_service_time_from_day'],
						$_REQUEST['new_service_time_from_year']);

				$new_service_time['ts_to'] = mktime($_REQUEST['new_service_time_to_hour'],
						$_REQUEST['new_service_time_to_minute'],
						0,
						$_REQUEST['new_service_time_to_month'],
						$_REQUEST['new_service_time_to_day'],
						$_REQUEST['new_service_time_to_year']);

				$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
			}
		}
		else {
			$new_service_time['ts_from'] = dowHrMinToSec($_REQUEST['new_service_time']['from_week'], $_REQUEST['new_service_time']['from_hour'], $_REQUEST['new_service_time']['from_minute']);
			$new_service_time['ts_to'] = dowHrMinToSec($_REQUEST['new_service_time']['to_week'], $_REQUEST['new_service_time']['to_hour'], $_REQUEST['new_service_time']['to_minute']);
			$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
		}

		if ($result) {
			try {
				checkServiceTime($new_service_time);

				// if this time is not already there, adding it for inserting
				if (!str_in_array($_REQUEST['times'], $new_service_time)) {
					array_push($_REQUEST['times'], $new_service_time);

					unset($_REQUEST['new_service_time']['from_week']);
					unset($_REQUEST['new_service_time']['to_week']);
					unset($_REQUEST['new_service_time']['from_hour']);
					unset($_REQUEST['new_service_time']['to_hour']);
					unset($_REQUEST['new_service_time']['from_minute']);
					unset($_REQUEST['new_service_time']['to_minute']);
				}
			}
			catch (APIException $e) {
				error($e->getMessage());
			}
		}

		show_messages();
	}
	else {
		unset($_REQUEST['new_service_time']['from_week']);
		unset($_REQUEST['new_service_time']['to_week']);
		unset($_REQUEST['new_service_time']['from_hour']);
		unset($_REQUEST['new_service_time']['to_hour']);
		unset($_REQUEST['new_service_time']['from_minute']);
		unset($_REQUEST['new_service_time']['to_minute']);
	}
}

/*
 * Display parent services list
 */
if (isset($_REQUEST['pservices'])) {
	$parentServices = API::Service()->get([
		'output' => ['serviceid', 'name', 'algorithm'],
		'selectTrigger' => ['triggerid', 'description', 'expression'],
		'preservekeys' => true,
		'sortfield' => ['name']
	]);

	if (isset($service)) {
		// unset unavailable parents
		$childServicesIds = get_service_children($service['serviceid']);
		$childServicesIds[] = $service['serviceid'];
		foreach ($childServicesIds as $childServiceId) {
			unset($parentServices[$childServiceId]);
		}

		$data = ['service' => $service];
	}
	else {
		$data = [];
	}

	// expand trigger descriptions
	$triggers = zbx_objectValues(
		array_filter($parentServices, function($service) { return (bool) $service['trigger']; }), 'trigger'
	);
	$triggers = CMacrosResolverHelper::resolveTriggerNames(zbx_toHash($triggers, 'triggerid'));

	foreach ($parentServices as $key => $parentService) {
		$parentServices[$key]['trigger'] = !empty($parentService['trigger'])
			? $triggers[$parentService['trigger']['triggerid']]['description']
			: '';
	}

	$data['db_pservices'] = $parentServices;

	// render view
	$servicesView = new CView('configuration.services.parent.list', $data);
	$servicesView->render();
	$servicesView->show();
}
/*
 * Display child services list
 */
elseif (isset($_REQUEST['cservices'])) {
	$childServices = API::Service()->get([
		'output' => ['serviceid', 'name', 'algorithm'],
		'selectTrigger' => ['triggerid', 'description', 'expression'],
		'preservekeys' => true,
		'sortfield' => ['name']
	]);

	if (isset($service)) {
		// unset unavailable parents
		$childServicesIds = get_service_children($service['serviceid']);
		$childServicesIds[] = $service['serviceid'];
		foreach ($childServicesIds as $childServiceId) {
			unset($childServices[$childServiceId]);
		}

		$data = ['service' => $service];
	}
	else {
		$data = [];
	}

	// expand trigger descriptions
	$triggers = zbx_objectValues(
		array_filter($childServices, function($service) { return (bool) $service['trigger']; }), 'trigger'
	);
	$triggers = CMacrosResolverHelper::resolveTriggerNames(zbx_toHash($triggers, 'triggerid'));

	foreach ($childServices as $key => $childService) {
		$childServices[$key]['trigger'] = !empty($childService['trigger'])
			? $triggers[$childService['trigger']['triggerid']]['description']
			: '';
	}

	$data['db_cservices'] = $childServices;

	// render view
	$servicesView = new CView('configuration.services.child.list', $data);
	$servicesView->render();
	$servicesView->show();
}
/*
 * Display
 */
elseif (isset($_REQUEST['form'])) {
	$data = [];
	$data['form'] = getRequest('form');
	$data['form_refresh'] = getRequest('form_refresh', 0);
	$data['service'] = !empty($service) ? $service : null;

	$data['times'] = getRequest('times', []);
	$data['new_service_time'] = getRequest('new_service_time', ['type' => SERVICE_TIME_TYPE_UPTIME]);

	// populate the form from the object from the database
	if (isset($data['service']['serviceid']) && !isset($_REQUEST['form_refresh'])) {
		$data['name'] = $data['service']['name'];
		$data['algorithm'] = $data['service']['algorithm'];
		$data['showsla'] = $data['service']['showsla'];
		$data['goodsla'] = $data['service']['goodsla'];
		$data['sortorder'] = $data['service']['sortorder'];
		$data['triggerid'] = isset($data['service']['triggerid']) ? $data['service']['triggerid'] : 0;
		$data['times'] = $service['times'];

		// parent
		if ($parent = $service['parent']) {
			$data['parentid'] = $parent['serviceid'];
			$data['parentname'] = $parent['name'];
		}
		else {
			$data['parentid'] = 0;
			$data['parentname'] = 'root';
		}

		// get children
		$data['children'] = [];
		if ($service['dependencies']) {
			$childServices = API::Service()->get([
				'serviceids' => zbx_objectValues($service['dependencies'], 'servicedownid'),
				'selectTrigger' => ['triggerid', 'description', 'expression'],
				'output' => ['name', 'triggerid'],
				'preservekeys' => true,
			]);

			// expand trigger descriptions
			$triggers = zbx_objectValues(
				array_filter($childServices, function($service) { return (bool) $service['trigger']; }), 'trigger'
			);
			$triggers = CMacrosResolverHelper::resolveTriggerNames(zbx_toHash($triggers, 'triggerid'));

			foreach ($service['dependencies'] as $dependency) {
				$childService = $childServices[$dependency['servicedownid']];
				$data['children'][] = [
					'name' => $childService['name'],
					'triggerid' => $childService['triggerid'],
					'trigger' => !empty($childService['triggerid'])
							? $triggers[$childService['trigger']['triggerid']]['description']
							: '',
					'serviceid' => $dependency['servicedownid'],
					'soft' => $dependency['soft'],
				];
			}

			CArrayHelper::sort($data['children'], ['name', 'serviceid']);
		}
	}
	// populate the form from a submitted request
	else {
		$data['name'] = getRequest('name', '');
		$data['algorithm'] = getRequest('algorithm', SERVICE_ALGORITHM_MAX);
		$data['showsla'] = getRequest('showsla', SERVICE_SHOW_SLA_OFF);
		$data['goodsla'] = getRequest('goodsla', SERVICE_SLA);
		$data['sortorder'] = getRequest('sortorder', 0);
		$data['triggerid'] = getRequest('triggerid', 0);
		$data['parentid'] = getRequest('parentid', 0);
		$data['parentname'] = getRequest('parentname', '');
		$data['children'] = getRequest('children', []);
	}

	// get trigger
	if ($data['triggerid'] > 0) {
		$trigger = API::Trigger()->get([
			'triggerids' => $data['triggerid'],
			'output' => ['description'],
			'selectHosts' => ['name'],
			'expandDescription' => true
		]);
		$trigger = reset($trigger);
		$host = reset($trigger['hosts']);
		$data['trigger'] = $host['name'].NAME_DELIMITER.$trigger['description'];
	}
	else {
		$data['trigger'] = '';
	}

	// render view
	$servicesView = new CView('configuration.services.edit', $data);
	$servicesView->render();
	$servicesView->show();
}
else {
	// services
	$services = API::Service()->get([
		'output' => ['name', 'serviceid', 'algorithm'],
		'selectParent' => ['serviceid'],
		'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
		'selectTrigger' => ['description', 'triggerid', 'expression'],
		'preservekeys' => true,
		'sortfield' => 'sortorder',
		'sortorder' => ZBX_SORT_UP
	]);

	// triggers
	$triggers = zbx_objectValues(
		array_filter($services, function($service) { return (bool) $service['trigger']; }), 'trigger'
	);
	$triggers = CMacrosResolverHelper::resolveTriggerNames(zbx_toHash($triggers, 'triggerid'));

	foreach ($services as &$service) {
		if ($service['trigger']) {
			$service['trigger'] = $triggers[$service['trigger']['triggerid']];
		}
	}
	unset($service);

	$treeData = [];
	createServiceConfigurationTree($services, $treeData);
	$tree = new CServiceTree('service_conf_tree', $treeData, [
		'caption' => _('Service'),
		'action' => _('Action'),
		'algorithm' => _('Status calculation'),
		'description' => _('Trigger')
	]);
	if (empty($tree)) {
		error(_('Cannot format tree.'));
	}

	$data = ['tree' => $tree];

	// render view
	$servicesView = new CView('configuration.services.list', $data);
	$servicesView->render();
	$servicesView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
