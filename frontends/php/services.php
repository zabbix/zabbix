<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Configuration of IT services');
$page['file'] = 'services.php';
$page['scripts'] = array('class.calendar.js');
$page['hist_arg'] = array();

if (isset($_REQUEST['pservices']) || isset($_REQUEST['cservices'])) {
	define('ZBX_PAGE_NO_MENU', 1);
}

include_once('include/page_header.php');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'serviceid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'group_serviceid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'name' => 							array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save_service})', _('Name')),
	'algorithm' =>						array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({save_service})'),
	'showsla' =>						array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'goodsla' => 						array(T_ZBX_DBL, O_OPT, null,	BETWEEN(0, 100), null, _('Calculate SLA, acceptable SLA (in %)')),
	'sortorder' => 						array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 999), null, _('Sort order (0->999)')),
	'times' =>							array(T_ZBX_STR, O_OPT, null,	null,		null),
	'triggerid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'trigger' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	'new_service_time' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'new_service_time_from_day' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_from_month' =>	array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_from_year' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_from_hour' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_from_minute' =>	array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_to_day' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_to_month' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_to_year' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_to_hour' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_service_time_to_minute' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'children' =>						array(T_ZBX_STR, O_OPT, P_SYS,	DB_ID,		null),
	'parentid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'parentname' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	// actions
	'save_service' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_service_time' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>							array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	// ajax
	'favobj' =>							array(T_ZBX_STR, O_OPT, P_ACT,	IN("'hat'"), null),
	'favref' =>							array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>						array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	// others
	'form' =>							array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>					array(T_ZBX_INT, O_OPT, null,	null,		null),
	'pservices' =>						array(T_ZBX_INT, O_OPT, null,	null,		null),
	'cservices' =>						array(T_ZBX_INT, O_OPT, null,	null,		null)
);
check_fields($fields);

/*
 * AJAX
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.services.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}
if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
	include_once('include/page_footer.php');
	exit();
}

// check permissions
if (!empty($_REQUEST['serviceid'])) {
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'serviceids' => $_REQUEST['serviceid']
	);

	if (isset($_REQUEST['delete']) || isset($_REQUEST['pservices']) || isset($_REQUEST['cservices'])) {
		$options['output'] = array('serviceid', 'name');
	}
	else {
		$options['selectParent'] = array('serviceid', 'name');
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
	$result = API::Service()->delete($service['serviceid']);
	show_messages($result, _('Service deleted'), _('Cannot delete service'));
	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_IT_SERVICE, 'Name ['.$service['name'].'] id ['.$service['serviceid'].']');
		unset($_REQUEST['form']);
	}
	unset($service);
}

if (isset($_REQUEST['form'])) {
	$_REQUEST['showsla'] = get_request('showsla', 0);
	$result = false;

	// save
	if (isset($_REQUEST['save_service'])) {
		DBstart();

		$children = get_request('children', array());
		$dependencies = array();
		foreach ($children as $child) {
			$dependencies[] = array(
				'dependsOnServiceid' => $child['serviceid'],
				'soft' => (isset($child['soft'])) ? $child['soft'] : 0
			);
		}

		$serviceRequest = array(
			'name' => get_request('name'),
			'triggerid' => get_request('triggerid'),
			'algorithm' => get_request('algorithm'),
			'showsla' => get_request('showsla', 0),
			'goodsla' => get_request('goodsla'),
			'sortorder' => get_request('sortorder'),
			'times' => get_request('times', array()),
			'parentid' => get_request('parentid'),
			'dependencies' => $dependencies
		);

		if (isset($service['serviceid'])) {
			$serviceRequest['serviceid'] = $service['serviceid'];
			$result = API::Service()->update($serviceRequest);

			show_messages($result, _('Service updated'), _('Cannot update service'));
			$audit_action = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::Service()->create($serviceRequest);

			show_messages($result, _('Service created'), _('Cannot add service'));
			$audit_action = AUDIT_ACTION_ADD;
		}

		if ($result) {
			$serviceid = (isset($service['serviceid'])) ? $service['serviceid'] : reset($result['serviceids']);
			add_audit($audit_action, AUDIT_RESOURCE_IT_SERVICE, 'Name ['.$_REQUEST['name'].'] id ['.$serviceid.']');
			unset($_REQUEST['form']);
		}

		DBend($result);
	}
	// validate and get service times
	elseif (isset($_REQUEST['add_service_time']) && isset($_REQUEST['new_service_time'])) {
		$_REQUEST['times'] = get_request('times', array());
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
	$parentServices = API::Service()->get(array(
		'output' => array('serviceid', 'name', 'algorithm'),
		'selectTrigger' => array('triggerid', 'description', 'expression'),
		'preservekeys' => true,
		'sortfield' => array('sortorder', 'name')
	));

	if (isset($service)) {
		// unset unavailable parents
		$childServicesIds = get_service_childs($service['serviceid']);
		$childServicesIds[] = $service['serviceid'];
		foreach ($childServicesIds as $childServiceId) {
			unset($parentServices[$childServiceId]);
		}

		$data = array('service' => $service);
	}
	else {
		$data = array();
	}

	// expand trigger descriptions
	$triggers = zbx_objectValues($parentServices, 'trigger');
	$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers);
	foreach ($parentServices as $key => $parentService) {
		$parentServices[$key]['trigger'] = !empty($parentService['trigger'])
			? $triggers[$parentService['trigger']['triggerid']]['description']
			: '-';
	}

	$data['db_pservices'] = $parentServices;

	// render view
	$servicesView = new CView('configuration.services.parent.list', $data);
	$servicesView->render();
	$servicesView->show();
	include_once('include/page_footer.php');
}

/*
 * Display child services list
 */
if (isset($_REQUEST['cservices'])) {
	$childServices = API::Service()->get(array(
		'output' => array('serviceid', 'name', 'algorithm'),
		'selectTrigger' => array('triggerid', 'description', 'expression'),
		'preservekeys' => true,
		'sortfield' => array('sortorder', 'name')
	));

	if (isset($service)) {
		// unset unavailable parents
		$childServicesIds = get_service_childs($service['serviceid']);
		$childServicesIds[] = $service['serviceid'];
		foreach ($childServicesIds as $childServiceId) {
			unset($childServices[$childServiceId]);
		}

		$data = array('service' => $service);
	}
	else {
		$data = array();
	}

	// expand trigger descriptions
	$triggers = zbx_objectValues($childServices, 'trigger');
	$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers);
	foreach ($childServices as $key => $childService) {
		$childServices[$key]['trigger'] = !empty($childService['trigger'])
			? $triggers[$childService['trigger']['triggerid']]['description']
			: '-';
	}

	$data['db_cservices'] = $childServices;

	// render view
	$servicesView = new CView('configuration.services.child.list', $data);
	$servicesView->render();
	$servicesView->show();
	include_once('include/page_footer.php');
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array();
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh', 0);
	$data['service'] = !empty($service) ? $service : null;

	$data['times'] = get_request('times', array());
	$data['new_service_time'] = get_request('new_service_time', array('type' => SERVICE_TIME_TYPE_UPTIME));

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
		$data['children'] = array();
		if ($service['dependencies']) {
			$childServices = API::Service()->get(array(
				'serviceids' => zbx_objectValues($service['dependencies'], 'servicedownid'),
				'selectTrigger' => array('triggerid', 'description', 'expression'),
				'output' => array('name', 'triggerid'),
				'preservekeys' => true,
			));

			// expand trigger descriptions
			$triggers = zbx_objectValues($childServices, 'trigger');
			$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers);
			foreach ($service['dependencies'] as $dependency) {
				$childService = $childServices[$dependency['servicedownid']];
				$data['children'][] = array(
					'name' => $childService['name'],
					'triggerid' => $childService['triggerid'],
					'trigger' => !empty($childService['triggerid'])
							? $triggers[$childService['trigger']['triggerid']]['description']
							: '-',
					'serviceid' => $dependency['servicedownid'],
					'soft' => $dependency['soft'],
				);
			}
		}
	}
	// populate the form from a submitted request
	else {
		$data['name'] = get_request('name', '');
		$data['algorithm'] = get_request('algorithm', SERVICE_ALGORITHM_MAX);
		$data['showsla'] = get_request('showsla', 0);
		$data['goodsla'] = get_request('goodsla', SERVICE_SLA);
		$data['sortorder'] = get_request('sortorder', 0);
		$data['triggerid'] = get_request('triggerid', 0);
		$data['parentid'] = get_request('parentid', 0);
		$data['parentname'] = get_request('parentname', '');
		$data['children'] = get_request('children', array());
	}

	// get trigger
	if ($data['triggerid'] > 0) {
		$trigger = API::Trigger()->get(array(
			'triggerids' => $data['triggerid'],
			'output' => array('description'),
			'selectHosts' => array('name'),
			'expandDescription' => true
		));
		$trigger = reset($trigger);
		$host = reset($trigger['hosts']);
		$data['trigger'] = $host['name'].':'.$trigger['description'];
	}
	else {
		$data['trigger'] = '';
	}

	// render view
	$servicesView = new CView('configuration.services.edit', $data);
	$servicesView->render();
	$servicesView->show();
}
// service list
else {

	// fetch services
	$services = API::Service()->get(array(
		'output' => array('name', 'serviceid', 'algorithm'),
		'selectParent' => array('serviceid'),
		'selectDependencies' => array('servicedownid', 'soft', 'linkid'),
		'selectTrigger' => array('description', 'triggerid', 'expression'),
		'preservekeys' => true,
		'sortfield' => 'sortorder',
		'sortorder' => ZBX_SORT_UP
	));
	// expand trigger descriptions
	$triggers = zbx_objectValues($services, 'trigger');

	$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers);

	foreach ($services as &$service) {
		if ($service['trigger']) {
			$service['trigger'] = $triggers[$service['trigger']['triggerid']];
		}
	}
	unset($service);

	$treeData = array();
	createServiceConfigurationTree($services, $treeData);
	$tree = new CServiceTree('service_conf_tree', $treeData, array(
		'caption' => _('Service'),
		'algorithm' => _('Status calculation'),
		'description' => _('Trigger')
	));
	if (empty($tree)) {
		error(_('Cannot format tree.'));
	}

	$data = array('tree' => $tree);

	// render view
	$servicesView = new CView('configuration.services.list', $data);
	$servicesView->render();
	$servicesView->show();
}

include_once('include/page_footer.php');
