<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Dashboard configuration');
$page['file'] = 'dashconf.php';
$page['scripts'] = ['multiselect.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR						 TYPE		 OPTIONAL FLAGS	VALIDATION		EXCEPTION
$fields = [
	'filterEnable' =>	[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'grpswitch' =>		[T_ZBX_INT, O_OPT, P_SYS,			BETWEEN(0, 1),	null],
	'groupids' =>		[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'hidegroupids' =>	[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'trgSeverity' =>	[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'trigger_name' =>	[T_ZBX_STR, O_OPT, P_SYS,			null,			null],
	'maintenance' =>	[T_ZBX_INT, O_OPT, P_SYS,			BETWEEN(0, 1),	null],
	'extAck' =>			[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, P_SYS,			null,			null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,			null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS,			null,			null]
];
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	// filter
	$filterEnable = getRequest('filterEnable', 0);
	CProfile::update('web.dashconf.filter.enable', $filterEnable, PROFILE_TYPE_INT);

	if ($filterEnable == 1) {
		// groups
		CProfile::update('web.dashconf.groups.grpswitch', $_REQUEST['grpswitch'], PROFILE_TYPE_INT);

		if ($_REQUEST['grpswitch'] == 1) {
			// show groups
			$groupIds = getRequest('groupids', []);

			$result = true;

			DBstart();

			$result &= CFavorite::remove('web.dashconf.groups.groupids');
			foreach ($groupIds as $groupId) {
				$result &= CFavorite::add('web.dashconf.groups.groupids', $groupId);
			}

			// hide groups
			$hideGroupIds = getRequest('hidegroupids', []);

			$result &= CFavorite::remove('web.dashconf.groups.hide.groupids');
			foreach ($hideGroupIds as $hideGroupId) {
				$result &= CFavorite::add('web.dashconf.groups.hide.groupids', $hideGroupId);
			}

			DBend($result);
		}

		// hosts
		CProfile::update('web.dashconf.hosts.maintenance', getRequest('maintenance', 0), PROFILE_TYPE_INT);

		// triggers
		CProfile::update('web.dashconf.triggers.severity',
			implode(';', array_keys(getRequest('trgSeverity', []))), PROFILE_TYPE_STR
		);
		CProfile::update('web.dashconf.triggers.name', getRequest('trigger_name', ''), PROFILE_TYPE_STR);

		// events
		$config = select_config();
		if ($config['event_ack_enable']) {
			CProfile::update('web.dashconf.events.extAck', getRequest('extAck', 0), PROFILE_TYPE_INT);
		}
	}

	jSredirect(ZBX_DEFAULT_URL);
}
elseif (hasRequest('cancel')) {
	ob_end_clean();
	redirect(ZBX_DEFAULT_URL);
}

ob_end_flush();

/*
 * Display
 */
$data = [
	'config' => select_config()
];

if (hasRequest('form_refresh')) {
	$data['isFilterEnable'] = getRequest('filterEnable', 0);
	$data['maintenance'] = getRequest('maintenance', 0);
	$data['extAck'] = getRequest('extAck', 0);

	$data['severity'] = getRequest('trgSeverity', []);
	$data['severity'] = array_keys($data['severity']);
	$data['trigger_name'] = getRequest('trigger_name', '');

	// groups
	$data['grpswitch'] = getRequest('grpswitch', 0);
	$data['groupIds'] = getRequest('groupids', []);
	$data['groupIds'] = zbx_toHash($data['groupIds']);
	$data['hideGroupIds'] = getRequest('hidegroupids', []);
	$data['hideGroupIds'] = zbx_toHash($data['hideGroupIds']);
}
else {
	$data['isFilterEnable'] = CProfile::get('web.dashconf.filter.enable', 0);
	$data['maintenance'] = CProfile::get('web.dashconf.hosts.maintenance', 1);
	$data['extAck'] = CProfile::get('web.dashconf.events.extAck', 0);

	$data['severity'] = CProfile::get('web.dashconf.triggers.severity', '0;1;2;3;4;5');
	$data['severity'] = zbx_empty($data['severity']) ? [] : explode(';', $data['severity']);
	$data['trigger_name'] = CProfile::get('web.dashconf.triggers.name', '');

	// groups
	$data['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);
	$data['groupIds'] = CFavorite::get('web.dashconf.groups.groupids');
	$data['groupIds'] = zbx_objectValues($data['groupIds'], 'value');
	$data['groupIds'] = zbx_toHash($data['groupIds']);
	$data['hideGroupIds'] = CFavorite::get('web.dashconf.groups.hide.groupids');
	$data['hideGroupIds'] = zbx_objectValues($data['hideGroupIds'], 'value');
	$data['hideGroupIds'] = zbx_toHash($data['hideGroupIds']);
}

$data['severity'] = zbx_toHash($data['severity']);
$data['severities'] = [
	TRIGGER_SEVERITY_NOT_CLASSIFIED,
	TRIGGER_SEVERITY_INFORMATION,
	TRIGGER_SEVERITY_WARNING,
	TRIGGER_SEVERITY_AVERAGE,
	TRIGGER_SEVERITY_HIGH,
	TRIGGER_SEVERITY_DISASTER
];

if ($data['grpswitch']) {
	// show groups
	$data['groups'] = API::HostGroup()->get([
		'groupids' => $data['groupIds'],
		'output' => ['groupid', 'name']
	]);

	CArrayHelper::sort($data['groups'], [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	foreach ($data['groups'] as &$group) {
		$group['id'] = $group['groupid'];

		unset($group['groupid']);
	}
	unset($group);

	// hide groups
	$data['hideGroups'] = API::HostGroup()->get([
		'groupids' => $data['hideGroupIds'],
		'output' => ['groupid', 'name']
	]);

	CArrayHelper::sort($data['hideGroups'], [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	foreach ($data['hideGroups'] as &$group) {
		$group['id'] = $group['groupid'];

		unset($group['groupid']);
	}
	unset($group);
}

// render view
$dashconfView = new CView('monitoring.dashconf', $data);
$dashconfView->render();
$dashconfView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
