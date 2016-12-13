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
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';

$page['title'] = _('Configuration of discovery rules');
$page['file'] = 'discoveryconf.php';
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'druleid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})'],
	'proxy_hostid' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({add}) || isset({update})'],
	'iprange' =>		[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'delay' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(1, SEC_PER_WEEK), 'isset({add}) || isset({update})'],
	'status' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'uniqueness_criteria' => [T_ZBX_STR, O_OPT, null, null,	'isset({add}) || isset({update})', _('Device uniqueness criteria')],
	'g_druleid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'dchecks' =>		[null, O_OPT, null,		null,		null],
	// actions
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"drule.massdelete","drule.massdisable","drule.massenable"'),
							null
						],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'output' =>			[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'ajaxaction' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'ajaxdata' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	// sort and sortorder
	'sort' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>		[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['status'] = isset($_REQUEST['status']) ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
$_REQUEST['dchecks'] = getRequest('dchecks', []);

/*
 * Permissions
 */
if (isset($_REQUEST['druleid'])) {
	$dbDRule = API::DRule()->get([
		'druleids' => getRequest('druleid'),
		'output' => ['name', 'proxy_hostid', 'iprange', 'delay', 'status'],
		'selectDChecks' => [
			'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
			'snmpv3_contextname'
		],
		'editable' => true
	]);
	if (empty($dbDRule)) {
		access_deny();
	}
}

// ajax
if (isset($_REQUEST['output']) && $_REQUEST['output'] == 'ajax') {
	$ajaxResponse = new CAjaxResponse;

	if (isset($_REQUEST['ajaxaction']) && $_REQUEST['ajaxaction'] == 'validate') {
		$ajaxData = getRequest('ajaxdata', []);
		$item_key_parser = new CItemKey();

		foreach ($ajaxData as $check) {
			switch ($check['field']) {
				case 'port':
					if (!validate_port_list($check['value'])) {
						$ajaxResponse->error(_('Incorrect port range.'));
					}
					break;
				case 'itemKey':
					if ($item_key_parser->parse($check['value']) != CParser::PARSE_SUCCESS) {
						$ajaxResponse->error(
							_s('Invalid key "%1$s": %2$s.', $check['value'], $item_key_parser->getError())
						);
					}
					break;
			}
		}
	}

	$ajaxResponse->send();

	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Action
 */
if (hasRequest('add') || hasRequest('update')) {
	$dChecks = getRequest('dchecks', []);
	$uniq = getRequest('uniqueness_criteria', 0);

	foreach ($dChecks as $dcnum => $check) {
		if (substr($check['dcheckid'], 0, 3) === 'new') {
			unset($dChecks[$dcnum]['dcheckid']);
		}

		$dChecks[$dcnum]['uniq'] = ($uniq == $dcnum) ? 1 : 0;
	}

	$discoveryRule = [
		'name' => getRequest('name'),
		'proxy_hostid' => getRequest('proxy_hostid'),
		'iprange' => getRequest('iprange'),
		'delay' => getRequest('delay'),
		'status' => getRequest('status'),
		'dchecks' => $dChecks
	];

	DBStart();

	if (hasRequest('update')) {
		$discoveryRule['druleid'] = getRequest('druleid');
		$result = API::DRule()->update($discoveryRule);

		$messageSuccess = _('Discovery rule updated');
		$messageFailed = _('Cannot update discovery rule');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = API::DRule()->create($discoveryRule);

		$messageSuccess = _('Discovery rule created');
		$messageFailed = _('Cannot create discovery rule');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		$druleid = reset($result['druleids']);
		add_audit($auditAction, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$discoveryRule['name']);
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['druleid'])) {
	$result = API::DRule()->delete([$_REQUEST['druleid']]);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['druleid']);
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['drule.massenable', 'drule.massdisable']) && hasRequest('g_druleid')) {
	$result = true;
	$enable = (getRequest('action') == 'drule.massenable');
	$status = $enable ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$updated = 0;

	DBStart();

	foreach (getRequest('g_druleid') as $druleId) {
		$result &= DBexecute('UPDATE drules SET status='.zbx_dbstr($status).' WHERE druleid='.zbx_dbstr($druleId));

		if ($result) {
			$druleData = get_discovery_rule_by_druleid($druleId);
			add_audit($auditAction, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleId.'] '.$druleData['name']);
		}

		$updated++;
	}

	$messageSuccess = $enable
		? _n('Discovery rule enabled', 'Discovery rules enabled', $updated)
		: _n('Discovery rule disabled', 'Discovery rules disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable discovery rule', 'Cannot enable discovery rules', $updated)
		: _n('Cannot disable discovery rule', 'Cannot disable discovery rules', $updated);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'drule.massdelete' && hasRequest('g_druleid')) {
	$result = API::DRule()->delete(getRequest('g_druleid'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'druleid' => getRequest('druleid'),
		'drule' => [],
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0)
	];

	// get drule
	if (isset($data['druleid']) && !isset($_REQUEST['form_refresh'])) {
		$data['drule'] = reset($dbDRule);
		$data['drule']['uniqueness_criteria'] = -1;

		if (!empty($data['drule']['dchecks'])) {
			foreach ($data['drule']['dchecks'] as $id => $dcheck) {
				if ($dcheck['uniq']) {
					$data['drule']['uniqueness_criteria'] = $dcheck['dcheckid'];
				}
			}
		}
	}
	else {
		$data['drule']['proxy_hostid'] = getRequest('proxy_hostid', 0);
		$data['drule']['name'] = getRequest('name', '');
		$data['drule']['iprange'] = getRequest('iprange', '192.168.0.1-254');
		$data['drule']['delay'] = getRequest('delay', SEC_PER_HOUR);
		$data['drule']['status'] = getRequest('status', DRULE_STATUS_ACTIVE);
		$data['drule']['dchecks'] = getRequest('dchecks', []);
		$data['drule']['nextcheck'] = getRequest('nextcheck', 0);
		$data['drule']['uniqueness_criteria'] = getRequest('uniqueness_criteria', -1);
	}

	if (!empty($data['drule']['dchecks'])) {
		foreach ($data['drule']['dchecks'] as $id => $dcheck) {
			$data['drule']['dchecks'][$id]['name'] = discovery_check2str(
				$dcheck['type'],
				isset($dcheck['key_']) ? $dcheck['key_'] : '',
				isset($dcheck['ports']) ? $dcheck['ports'] : ''
			);
		}

		order_result($data['drule']['dchecks'], 'name');
	}

	// get proxies
	$data['proxies'] = API::Proxy()->get([
		'output' => API_OUTPUT_EXTEND
	]);
	order_result($data['proxies'], 'host');

	// render view
	$discoveryView = new CView('configuration.discovery.edit', $data);
	$discoveryView->render();
	$discoveryView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// get drules
	$data['drules'] = API::DRule()->get([
		'output' => ['proxy_hostid', 'name', 'status', 'iprange', 'delay'],
		'selectDChecks' => ['type'],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	if ($data['drules']) {
		foreach ($data['drules'] as $key => $drule) {
			// checks
			$checks = [];

			foreach ($drule['dchecks'] as $check) {
				$checks[$check['type']] = discovery_check_type2str($check['type']);
			}

			order_result($checks);

			$data['drules'][$key]['checks'] = $checks;

			// description
			$data['drules'][$key]['description'] = [];

			if ($drule['proxy_hostid']) {
				$proxy = get_host_by_hostid($drule['proxy_hostid']);

				array_push($data['drules'][$key]['description'], $proxy['host'].NAME_DELIMITER);
			}
		}

		order_result($data['drules'], $sortField, $sortOrder);
	}

	// get paging
	$data['paging'] = getPagingLine($data['drules'], $sortOrder, new CUrl('discoveryconf.php'));

	// render view
	$discoveryView = new CView('configuration.discovery.list', $data);
	$discoveryView->render();
	$discoveryView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
