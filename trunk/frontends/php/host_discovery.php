<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of discovery rules');
$page['file'] = 'host_discovery.php';
$page['scripts'] = ['class.cviewswitcher.js', 'items.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// supported eval types
$evalTypes = [
	CONDITION_EVAL_TYPE_AND_OR,
	CONDITION_EVAL_TYPE_AND,
	CONDITION_EVAL_TYPE_OR,
	CONDITION_EVAL_TYPE_EXPRESSION
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'!isset({form})'],
	'itemid' =>				[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'interfaceid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null, _('Interface')],
	'name' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'description' =>		[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Key')],
	'delay' =>				[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP,
		_('Update interval')
	],
	'delay_flex' =>			[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null],
	'type' =>				[T_ZBX_INT, O_OPT, null,
		IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
			ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX]), 'isset({add}) || isset({update})'],
	'authtype' =>			[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH],
	'username' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'), _('User name')],
	'password' =>			[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')],
	'publickey' =>			[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY],
	'privatekey' =>			[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY],
	$paramsFieldName =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add}) || isset({update})) && isset({type}) && '.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(getRequest('type', 0))],
	'snmp_community' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'),
		_('SNMP community')],
	'snmp_oid' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('SNMP OID')],
	'port' =>				[T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('Port')],
	'snmpv3_contextname' => [T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_securitylevel' => [T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_securityname' => [T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_authprotocol' => [T_ZBX_INT, O_OPT, null, IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && ({snmpv3_securitylevel} == '.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.' || {snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'],
	'snmpv3_authpassphrase' => [T_ZBX_STR, O_OPT, null, null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && ({snmpv3_securitylevel} == '.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.' || {snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'],
	'snmpv3_privprotocol' => [T_ZBX_INT, O_OPT, null, IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV],
	'snmpv3_privpassphrase' => [T_ZBX_STR, O_OPT, null, null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV],
	'ipmi_sensor' =>		[T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_IPMI, _('IPMI sensor')],
	'trapper_hosts' =>		[T_ZBX_STR, O_OPT, null,	null,		'(isset({add}) || isset({update})) && isset({type}) && {type} == 2'],
	'lifetime' => 			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'evaltype' =>	 		[T_ZBX_INT, O_OPT, null, 	IN($evalTypes), 'isset({add}) || isset({update})'],
	'formula' => 			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'conditions' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'jmx_endpoint' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
	],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"discoveryrule.massdelete","discoveryrule.massdisable","discoveryrule.massenable"'),
								null
							],
	'g_hostdruleid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"delay","key_","name","status","type"'),	null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

/*
 * Permissions
 */
if (getRequest('itemid', false)) {
	$item = API::DiscoveryRule()->get([
		'itemids' => $_REQUEST['itemid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['status', 'flags'],
		'selectFilter' => ['formula', 'evaltype', 'conditions'],
		'editable' => true
	]);
	$item = reset($item);
	if (!$item) {
		access_deny();
	}
	$_REQUEST['hostid'] = $item['hostid'];
	$host = reset($item['hosts']);
}
else {
	$hosts = API::Host()->get([
		'hostids' => $_REQUEST['hostid'],
		'output' => ['status', 'flags'],
		'templated_hosts' => true,
		'editable' => true
	]);
	$host = reset($hosts);
	if (!$host) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {
	$result = API::DiscoveryRule()->delete([getRequest('itemid')]);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (hasRequest('add') || hasRequest('update')) {
	$result = true;

	$delay = getRequest('delay', DB::getDefault('items', 'delay'));
	$type = getRequest('type', ITEM_TYPE_ZABBIX);

	/*
	 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
	 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
	 */
	if (!in_array($type, [ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP]) && hasRequest('delay_flex')) {
		$intervals = [];

		foreach (getRequest('delay_flex') as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
				if ($interval['delay'] === '' && $interval['period'] === '') {
					continue;
				}

				if (strpos($interval['delay'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}
				elseif (strpos($interval['period'], ';')  !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['period']));
					break;
				}

				$intervals[] = $interval['delay'].'/'.$interval['period'];
			}
			else {
				if ($interval['schedule'] === '') {
					continue;
				}

				if (strpos($interval['schedule'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['schedule']));
					break;
				}

				$intervals[] = $interval['schedule'];
			}
		}

		if ($intervals) {
			$delay .= ';'.implode(';', $intervals);
		}
	}

	if ($result) {
		$newItem = [
			'itemid' => getRequest('itemid'),
			'interfaceid' => getRequest('interfaceid'),
			'name' => getRequest('name'),
			'description' => getRequest('description'),
			'key_' => getRequest('key'),
			'hostid' => getRequest('hostid'),
			'delay' => $delay,
			'status' => getRequest('status', ITEM_STATUS_DISABLED),
			'type' => getRequest('type'),
			'snmp_community' => getRequest('snmp_community'),
			'snmp_oid' => getRequest('snmp_oid'),
			'trapper_hosts' => getRequest('trapper_hosts'),
			'port' => getRequest('port'),
			'snmpv3_contextname' => getRequest('snmpv3_contextname'),
			'snmpv3_securityname' => getRequest('snmpv3_securityname'),
			'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel'),
			'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol'),
			'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase'),
			'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol'),
			'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase'),
			'authtype' => getRequest('authtype'),
			'username' => getRequest('username'),
			'password' => getRequest('password'),
			'publickey' => getRequest('publickey'),
			'privatekey' => getRequest('privatekey'),
			'params' => getRequest('params'),
			'ipmi_sensor' => getRequest('ipmi_sensor'),
			'lifetime' => getRequest('lifetime')
		];

		if ($newItem['type'] == ITEM_TYPE_JMX) {
			$newItem['jmx_endpoint'] = getRequest('jmx_endpoint', '');
		}

		// add macros; ignore empty new macros
		$filter = [
			'evaltype' => getRequest('evaltype'),
			'conditions' => []
		];
		$conditions = getRequest('conditions', []);
		ksort($conditions);
		$conditions = array_values($conditions);
		foreach ($conditions as $condition) {
			if (!zbx_empty($condition['macro'])) {
				$condition['macro'] = mb_strtoupper($condition['macro']);

				$filter['conditions'][] = $condition;
			}
		}
		if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			// if only one or no conditions are left, reset the evaltype to and/or and clear the formula
			if (count($filter['conditions']) <= 1) {
				$filter['formula'] = '';
				$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
			}
			else {
				$filter['formula'] = getRequest('formula');
			}
		}
		$newItem['filter'] = $filter;

		if (hasRequest('update')) {
			DBstart();

			// unset snmpv3 fields
			if ($newItem['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				$newItem['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
				$newItem['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}
			elseif ($newItem['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
				$newItem['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}

			// unset unchanged values
			$newItem = CArrayHelper::unsetEqualValues($newItem, $item, ['itemid']);

			// don't update the filter if it hasn't changed
			$conditionsChanged = false;
			if (count($newItem['filter']['conditions']) != count($item['filter']['conditions'])) {
				$conditionsChanged = true;
			}
			else {
				$conditions = $item['filter']['conditions'];
				foreach ($newItem['filter']['conditions'] as $i => $condition) {
					if (CArrayHelper::unsetEqualValues($condition, $conditions[$i])) {
						$conditionsChanged = true;
						break;
					}
				}
			}
			$filter = CArrayHelper::unsetEqualValues($newItem['filter'], $item['filter']);
			if (!isset($filter['evaltype']) && !isset($filter['formula']) && !$conditionsChanged) {
				unset($newItem['filter']);
			}

			$result = API::DiscoveryRule()->update($newItem);
			$result = DBend($result);
		}
		else {
			$result = API::DiscoveryRule()->create([$newItem]);
		}
	}

	if (hasRequest('add')) {
		show_messages($result, _('Discovery rule created'), _('Cannot add discovery rule'));
	}
	else {
		show_messages($result, _('Discovery rule updated'), _('Cannot update discovery rule'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows(getRequest('hostid'));
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['discoveryrule.massenable', 'discoveryrule.massdisable']) && hasRequest('g_hostdruleid')) {
	$itemids = getRequest('g_hostdruleid');
	$status = (getRequest('action') == 'discoveryrule.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$lld_rules = [];
	foreach ($itemids as $itemid) {
		$lld_rules[] = ['itemid' => $itemid, 'status' => $status];
	}

	$result = (bool) API::DiscoveryRule()->update($lld_rules);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}

	$updated = count($itemids);

	$messageSuccess = ($status == ITEM_STATUS_ACTIVE)
		? _n('Discovery rule enabled', 'Discovery rules enabled', $updated)
		: _n('Discovery rule disabled', 'Discovery rules disabled', $updated);
	$messageFailed = ($status == ITEM_STATUS_ACTIVE)
		? _n('Cannot enable discovery rule', 'Cannot enable discovery rules', $updated)
		: _n('Cannot disable discovery rule', 'Cannot disable discovery rules', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'discoveryrule.massdelete' && hasRequest('g_hostdruleid')) {
	$result = API::DiscoveryRule()->delete(getRequest('g_hostdruleid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$formItem = (hasRequest('itemid') && !hasRequest('clone')) ? $item : [];
	$data = getItemFormData($formItem, [
		'is_discovery_rule' => true
	]);
	$data['lifetime'] = getRequest('lifetime', DB::getDefault('items', 'lifetime'));
	$data['evaltype'] = getRequest('evaltype');
	$data['formula'] = getRequest('formula');
	$data['conditions'] = getRequest('conditions', []);

	// update form
	if (hasRequest('itemid') && !getRequest('form_refresh')) {
		$data['lifetime'] = $item['lifetime'];
		$data['evaltype'] = $item['filter']['evaltype'];
		$data['formula'] = $item['filter']['formula'];
		$data['conditions'] = $item['filter']['conditions'];
	}
	// clone form
	elseif (hasRequest('clone')) {
		unset($data['itemid']);
		$data['form'] = 'clone';
	}

	// Sort interfaces to be listed starting with one selected as 'main'.
	CArrayHelper::sort($data['interfaces'], [
		['field' => 'main', 'order' => ZBX_SORT_DOWN]
	]);

	if ($data['type'] != ITEM_TYPE_JMX) {
		$data['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
	}

	// render view
	$itemView = new CView('configuration.host.discovery.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'hostid' => getRequest('hostid', 0),
		'host' => $host,
		'showInfoColumn' => ($host['status'] != HOST_STATUS_TEMPLATE),
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// discoveries
	$data['discoveries'] = API::DiscoveryRule()->get([
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectItems' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectHostPrototypes' => API_OUTPUT_COUNT,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	$data['discoveries'] = CMacrosResolverHelper::resolveItemNames($data['discoveries']);

	switch ($sortField) {
		case 'delay':
			orderItemsByDelay($data['discoveries'], $sortOrder, ['usermacros' => true]);
			break;

		case 'status':
			orderItemsByStatus($data['discoveries'], $sortOrder);
			break;

		default:
			order_result($data['discoveries'], $sortField, $sortOrder);
	}

	// paging
	$url = (new CUrl('host_discovery.php'))
		->setArgument('hostid', $data['hostid']);

	$data['paging'] = getPagingLine($data['discoveries'], $sortOrder, $url);

	// Get real hosts and select writable templates IDs.
	$data['writable_templates'] = [];
	$discovery_hostids = [];

	foreach ($data['discoveries'] as &$discovery) {
		if ($discovery['templateid']) {
			$discovery['dbTemplate'] = get_realhost_by_itemid($discovery['templateid']);
			$discovery_hostids[] = $discovery['dbTemplate']['hostid'];
		}
	}
	unset($discovery);

	if ($discovery_hostids) {
		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys(array_flip($discovery_hostids)),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// render view
	$discoveryView = new CView('configuration.host.discovery.list', $data);
	$discoveryView->render();
	$discoveryView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
