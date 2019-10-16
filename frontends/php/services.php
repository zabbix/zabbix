<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

$page['title'] = _('Configuration of services');
$page['file'] = 'services.php';
$page['scripts'] = ['class.calendar.js'];

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'serviceid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'name' => 					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'algorithm' =>				[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({add}) || isset({update})'],
	'showsla' =>				[T_ZBX_INT, O_OPT, null,	IN([SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON]),	null],
	'goodsla' => 				[T_ZBX_DBL, O_OPT, null,	BETWEEN(0, 100), null,
									_('Calculate SLA, acceptable SLA (in %)')
								],
	'sortorder' => 				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 999), null, _('Sort order (0->999)')],
	'times' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'triggerid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'trigger' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'new_service_time' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'new_service_time_from' =>	[T_ZBX_ABS_TIME, O_OPT, null, 	NOT_EMPTY,	null, _('From')],
	'new_service_time_till' =>	[T_ZBX_ABS_TIME, O_OPT, null, 	NOT_EMPTY,	null, _('Till')],
	'children' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'parentid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'parentname' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_service_time' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null],
	// others
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,		null]
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

	if (isset($_REQUEST['delete'])) {
		$options['output'] = ['serviceid', 'name'];
	}
	else {
		$options['selectParent'] = ['serviceid', 'name'];
		$options['selectDependencies'] = API_OUTPUT_EXTEND;
		$options['selectTimes'] = API_OUTPUT_EXTEND;
	}

	$service = API::Service()->get($options);
	$service = reset($service);
	if (!$service && hasRequest('delete')) {
		show_error_message(_('No permissions to referred object or it does not exist!'));
	}
	elseif (!$service) {
		access_deny();
	}
}

/*
 * Actions
 */

// delete
if (hasRequest('delete') && $service) {
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

$service_times = [];

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
// Validate and get service times.
elseif (hasRequest('add_service_time') && hasRequest('new_service_time')) {
	$new_service_time = getRequest('new_service_time');
	$_REQUEST['times'] = getRequest('times', []);
	$result = true;

	if ($new_service_time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse(getRequest('new_service_time_from'));
		$new_service_time_from = $absolute_time_parser->getDateTime(true);

		if (!validateDateInterval($new_service_time_from->format('Y'), $new_service_time_from->format('m'),
				$new_service_time_from->format('d'))) {
			$result = false;
			error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('From')));
		}

		$absolute_time_parser->parse(getRequest('new_service_time_till'));
		$new_service_time_till = $absolute_time_parser->getDateTime(true);

		if (!validateDateInterval($new_service_time_till->format('Y'), $new_service_time_till->format('m'),
				$new_service_time_till->format('d'))) {
			$result = false;
			error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Till')));
		}

		if ($result) {
			$new_service_time['ts_from'] = $new_service_time_from->getTimestamp();
			$new_service_time['ts_to'] = $new_service_time_till->getTimestamp();
		}
	}
	else {
		$new_service_time['ts_from'] = dowHrMinToSec($new_service_time['from_week'], $new_service_time['from_hour'],
			$new_service_time['from_minute']
		);
		$new_service_time['ts_to'] = dowHrMinToSec($new_service_time['to_week'], $new_service_time['to_hour'],
			$new_service_time['to_minute']
		);
	}

	if ($result) {
		try {
			checkServiceTime($new_service_time);

			// If this time is not already there, adding it for inserting.
			unset($new_service_time['from_week']);
			unset($new_service_time['to_week']);
			unset($new_service_time['from_hour']);
			unset($new_service_time['to_hour']);
			unset($new_service_time['from_minute']);
			unset($new_service_time['to_minute']);

			if (!str_in_array($new_service_time, $_REQUEST['times'])) {
				$_REQUEST['times'][] = $new_service_time;
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

if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'service' => (getRequest('serviceid', 0) != 0) ? $service : null,
		'times' => getRequest('times', []),
		'new_service_time' => getRequest('new_service_time', ['type' => SERVICE_TIME_TYPE_UPTIME])
	];

	// Populate the form from the object from the database.
	if (getRequest('serviceid', 0) != 0 && !hasRequest('form_refresh')) {
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
			$child_services = API::Service()->get([
				'serviceids' => zbx_objectValues($service['dependencies'], 'servicedownid'),
				'selectTrigger' => ['description'],
				'output' => ['name', 'triggerid'],
				'preservekeys' => true,
			]);

			foreach ($service['dependencies'] as $dependency) {
				$child_service = $child_services[$dependency['servicedownid']];
				$data['children'][] = [
					'name' => $child_service['name'],
					'triggerid' => $child_service['triggerid'],
					'trigger' => ($child_service['triggerid'] == 0) ? '' : $child_service['trigger']['description'],
					'serviceid' => $dependency['servicedownid'],
					'soft' => $dependency['soft'],
				];
			}

			CArrayHelper::sort($data['children'], ['name', 'serviceid']);
		}
	}
	// Populate the form from a submitted request.
	else {
		$new_service_time = getRequest('new_service_time');

		if ($new_service_time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
			$data['new_service_time_from'] = hasRequest('new_service_time_from')
				? getRequest('new_service_time_from')
				: (new DateTime('today'))->format(ZBX_DATE_TIME);

			$data['new_service_time_till'] = hasRequest('new_service_time_till')
				? getRequest('new_service_time_till')
				: (new DateTime('tomorrow'))->format(ZBX_DATE_TIME);
		}

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
		'output' => ['name', 'serviceid', 'algorithm', 'sortorder'],
		'selectParent' => ['serviceid'],
		'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
		'selectTrigger' => ['description'],
		'preservekeys' => true
	]);

	sortServices($services);

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
