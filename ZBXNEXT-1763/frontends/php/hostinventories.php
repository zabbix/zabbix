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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventories');
$page['file'] = 'hostinventories.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
	'hostid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),
	'filter_field'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
	'filter_field_value'=>	array(T_ZBX_STR, O_OPT,  null,	null,	null),
	'filter_exact'=>        array(T_ZBX_INT, O_OPT,  null,	'IN(0,1)',	null),
	//ajax
	'favobj'=>			array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
	'favref'=>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable(array(getRequest('groupid')))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable(array(getRequest('hostid')))) {
	access_deny();
}

validate_sort_and_sortorder('name', ZBX_SORT_UP);

if (hasRequest('favobj')) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.hostinventories.filter.state', getRequest('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$hostid = getRequest('hostid', 0);
$data = array();

/*
 * Display
 */
if ($hostid > 0) {
	// host scripts
	$data['hostScripts'] = API::Script()->getScriptsByHosts($hostid);

	// inventory info
	$data['tableTitles'] = getHostInventories();
	$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
	$sqlFields = implode(', ', array_keys($data['tableTitles']));

	$sql = 'SELECT '.$sqlFields.' FROM host_inventory WHERE hostid='.$hostid;
	$result = DBselect($sql);

	$data['tableValues'] = DBfetch($result);

	// overview tab
	$host = API::Host()->get(array(
		'hostids' => $hostid,
		'output' => array('hostid', 'host', 'name', 'maintenance_status'),
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => array('hostid'),
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'preservekeys' => true
	));

	$data['overview']['host'] = reset($host);
	$data['overview']['host']['status'] = null;
	if ($data['overview']['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
		$data['overview']['host']['status'] = new CLink(_('In maintenance'), null, 'orange');
	}

	// get permissions
	$userType = CWebUser::getType();
	if ($userType == USER_TYPE_SUPER_ADMIN) {
		$data['rwHost'] = true;
	}
	else if ($userType == USER_TYPE_ZABBIX_ADMIN) {
		$data['rwHost'] = API::Host()->get(array(
			'hostids' => $hostid,
			'editable' => true
		));
		$data['rwHost'] = zbx_toHash($data['rwHost'], 'hostid');
		$data['rwHost'] = isset($data['rwHost'][$hostid]) ? true : false;
	}
	else {
		$data['rwHost'] = false;
	}

	$data['overview']['host']['ip'] = array();
	$dnsInterfaces = array();
	$ipInterfaces = array();

	$i = 0;
	foreach ($data['overview']['host']['interfaces'] as $interface) {
		$defaultInterface[$i] = $interface['main'];

		switch ($interface['type']) {
			case INTERFACE_TYPE_AGENT:
				if ($interface['ip']) {
					$ipInterfaces[$i] = _('Agent').NAME_DELIMITER.$interface['ip'];
				}
				if ($interface['dns']) {
					$dnsInterfaces[$i] = _('Agent').NAME_DELIMITER.$interface['dns'];
				}
				break;

			case INTERFACE_TYPE_SNMP:
				if ($interface['ip']) {
					$ipInterfaces[$i] = _('SNMP').NAME_DELIMITER.$interface['ip'];
				}
				if ($interface['dns']) {
					$dnsInterfaces[$i] = _('SNMP').NAME_DELIMITER.$interface['dns'];
				}
				break;

			case INTERFACE_TYPE_IPMI:
				if ($interface['ip']) {
					$ipInterfaces[$i] = _('IPMI').NAME_DELIMITER.$interface['ip'];
				}
				if ($interface['dns']) {
					$dnsInterfaces[$i] = _('IPMI').NAME_DELIMITER.$interface['dns'];
				}
				break;

			case INTERFACE_TYPE_JMX:
				if ($interface['ip']) {
					$ipInterfaces[$i] = _('JMX').NAME_DELIMITER.$interface['ip'];
				}
				if ($interface['dns']) {
					$dnsInterfaces[$i] = _('JMX').NAME_DELIMITER.$interface['dns'];
				}
				break;
		}

		$i++;
	}
	natsort($ipInterfaces);
	natsort($dnsInterfaces);

	$data['overview']['host']['ip'] = null;
	foreach ($ipInterfaces as $key => $ip) {
		$spanClass = $defaultInterface[$key] ? 'default_interface' : null;
		if (!$data['overview']['host']['ip']) {
			$data['overview']['host']['ip'][] = new CSpan($ip, $spanClass);
		}
		else {
			$data['overview']['host']['ip'][] = ','.SPACE;
			$data['overview']['host']['ip'][] = new CSpan($ip, $spanClass);
		}
	}

	$data['overview']['host']['dns'] = null;
	foreach ($dnsInterfaces as $key => $dns) {
		$spanClass = $defaultInterface[$key] ? 'default_interface' : null;
		if (!$data['overview']['host']['dns']) {
			$data['overview']['host']['dns'][] = new CSpan($dns, $spanClass);
		}
		else {
			$data['overview']['host']['dns'][] = ','.SPACE;
			$data['overview']['host']['dns'][] = new CSpan($dns, $spanClass);
		}
	}

	// js templates
	require_once dirname(__FILE__).'/include/views/js/general.script.confirm.js.php';

	// view generation
	$hostinventoriesView = new CView('inventory.host.view', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}
else{
	$data['config'] = $config;
	$options = array(
		'groups' => array(
			'real_hosts' => 1,
		),
		'groupid' => getRequest('groupid', null),
	);
	$data['pageFilter'] = new CPageFilter($options);

	$hostinventoriesView = new CView('inventory.host.list', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
