<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['hist_arg'] = array();
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'druleid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'proxy_hostid' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({save})'),
	'iprange' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'delay' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, SEC_PER_WEEK), 'isset({save})'),
	'status' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'uniqueness_criteria' => array(T_ZBX_STR, O_OPT, null, null,	'isset({save})', _('Device uniqueness criteria')),
	'g_druleid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'dchecks' =>		array(null, O_OPT, null,		null,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'output' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'ajaxaction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'ajaxdata' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP, array('name'));

$_REQUEST['status'] = isset($_REQUEST['status']) ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
$_REQUEST['dchecks'] = getRequest('dchecks', array());

/*
 * Permissions
 */
if (isset($_REQUEST['druleid'])) {
	$dbDRule = API::DRule()->get(array(
		'druleids' => getRequest('druleid'),
		'output' => array('name', 'proxy_hostid', 'iprange', 'delay', 'status'),
		'selectDChecks' => array(
			'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
			'snmpv3_contextname'
		),
		'editable' => true
	));
	if (empty($dbDRule)) {
		access_deny();
	}
}

$_REQUEST['go'] = getRequest('go', 'none');

// ajax
if (isset($_REQUEST['output']) && $_REQUEST['output'] == 'ajax') {
	$ajaxResponse = new AjaxResponse;

	if (isset($_REQUEST['ajaxaction']) && $_REQUEST['ajaxaction'] == 'validate') {
		$ajaxData = getRequest('ajaxdata', array());

		foreach ($ajaxData as $check) {
			switch ($check['field']) {
				case 'port':
					if (!validate_port_list($check['value'])) {
						$ajaxResponse->error(_('Incorrect port range.'));
					}
					break;
				case 'itemKey':
					$itemKey = new CItemKey($check['value']);

					if (!$itemKey->isValid()) {
						$ajaxResponse->error(_s('Invalid key "%1$s": %2$s.', $check['value'], $itemKey->getError()));
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
if (isset($_REQUEST['save'])) {
	$dChecks = getRequest('dchecks', array());
	$uniq = getRequest('uniqueness_criteria', 0);

	foreach ($dChecks as $dcnum => $check) {
		if (substr($check['dcheckid'], 0, 3) === 'new') {
			unset($dChecks[$dcnum]['dcheckid']);
		}

		$dChecks[$dcnum]['uniq'] = ($uniq == $dcnum) ? 1 : 0;
	}

	$discoveryRule = array(
		'name' => getRequest('name'),
		'proxy_hostid' => getRequest('proxy_hostid'),
		'iprange' => getRequest('iprange'),
		'delay' => getRequest('delay'),
		'status' => getRequest('status'),
		'dchecks' => $dChecks
	);

	DBStart();

	if (isset($_REQUEST['druleid'])) {
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
	$result = API::DRule()->delete(array($_REQUEST['druleid']));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['druleid']);
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable')) && hasRequest('g_druleid')) {
	$result = true;
	$enable = (getRequest('go') == 'activate');
	$status = $enable ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$updated = 0;

	DBStart();

	foreach (getRequest('g_druleid') as $druleId) {
		$result &= DBexecute('UPDATE drules SET status='.$status.' WHERE druleid='.zbx_dbstr($druleId));

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
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_druleid'])) {
	$result = API::DRule()->delete($_REQUEST['g_druleid']);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'druleid' => getRequest('druleid'),
		'drule' => array(),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0)
	);

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
		$data['drule']['dchecks'] = getRequest('dchecks', array());
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
	$data['proxies'] = API::Proxy()->get(array(
		'output' => API_OUTPUT_EXTEND
	));
	order_result($data['proxies'], 'host');

	// render view
	$discoveryView = new CView('configuration.discovery.edit', $data);
	$discoveryView->render();
	$discoveryView->show();
}
else {
	$data = array();

	// get drules
	$data['drules'] = API::DRule()->get(array(
		'output' => array('proxy_hostid', 'name', 'status', 'iprange', 'delay'),
		'selectDChecks' => array('type'),
		'editable' => true
	));

	if ($data['drules']) {
		foreach ($data['drules'] as $key => $drule) {
			// checks
			$checks = array();

			foreach ($drule['dchecks'] as $check) {
				$checks[$check['type']] = discovery_check_type2str($check['type']);
			}

			order_result($checks);

			$data['drules'][$key]['checks'] = $checks;

			// description
			$data['drules'][$key]['description'] = array();

			if ($drule['proxy_hostid']) {
				$proxy = get_host_by_hostid($drule['proxy_hostid']);

				array_push($data['drules'][$key]['description'], $proxy['host'].NAME_DELIMITER);
			}
		}

		order_result($data['drules'], getPageSortField('name'), getPageSortOrder());
	}

	// get paging
	$data['paging'] = getPagingLine($data['drules']);

	// render view
	$discoveryView = new CView('configuration.discovery.list', $data);
	$discoveryView->render();
	$discoveryView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
