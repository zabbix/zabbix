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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of discovery rules');
$page['file'] = 'host_discovery.php';
$page['scripts'] = array('class.cviewswitcher.js');
$page['hist_arg'] = array('hostid');

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(get_request('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'!isset({form})'),
	'itemid' =>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'interfaceid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null, _('Interface')),
	'name' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Name')),
	'description' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'filter_macro' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'filter_value' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'key' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Key')),
	'delay' =>				array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_DAY),
		'isset({save})&&(isset({type})&&({type}!='.ITEM_TYPE_TRAPPER.'&&{type}!='.ITEM_TYPE_SNMPTRAP.'))',
		_('Update interval (in sec)')),
	'delay_flex' =>			array(T_ZBX_STR, O_OPT, null,	'',			null),
	'add_delay_flex' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'new_delay_flex' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add_delay_flex})&&(isset({type})&&({type}!=2))',
		_('New flexible interval')),
	'status' =>					array(T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null),
	'type' =>				array(T_ZBX_INT, O_OPT, null,
		IN(array(-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
			ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX)), 'isset({save})'),
	'authtype' =>			array(T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'isset({save})&&isset({type})&&({type}=='.ITEM_TYPE_SSH.')'),
	'username' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'), _('User name')),
	'password' =>			array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')),
	'publickey' =>			array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	'privatekey' =>			array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	$paramsFieldName =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(get_request('type', 0))),
	'snmp_community' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C,'type'),
		_('SNMP community')),
	'snmp_oid' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'),
		_('SNMP OID')),
	'port' =>				array(T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'),
		_('Port')),
	'snmpv3_contextname' => array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_securitylevel' => array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_securityname' => array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_authprotocol' => array(T_ZBX_INT, O_OPT, null, IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'),
	'snmpv3_authpassphrase' => array(T_ZBX_STR, O_OPT, null, null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'),
	'snmpv3_privprotocol' => array(T_ZBX_INT, O_OPT, null, IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'),
	'snmpv3_privpassphrase' => array(T_ZBX_STR, O_OPT, null, null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'),
	'ipmi_sensor' =>		array(T_ZBX_STR, O_OPT, NO_TRIM,	NOT_EMPTY,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', _('IPMI sensor')),
	'trapper_hosts' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&({type}==2)'),
	'lifetime' => 			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'g_hostdruleid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>				array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>			array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['params'] = get_request($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

/*
 * Permissions
 */
if (get_request('itemid', false)) {
	$item = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['itemid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('status', 'flags'),
		'editable' => true
	));
	$item = reset($item);
	if (!$item) {
		access_deny();
	}
	$_REQUEST['hostid'] = $item['hostid'];
	$host = reset($item['hosts']);
}
else {
	$hosts = API::Host()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'output' => array('status', 'flags'),
		'templated_hosts' => true,
		'editable' => true
	));
	$host = reset($hosts);
	if (!$host) {
		access_deny();
	}
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.host_discovery.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Actions
 */
if (isset($_REQUEST['add_delay_flex']) && isset($_REQUEST['new_delay_flex'])) {
	$timePeriodValidator = new CTimePeriodValidator(array('allowMultiple' => false));
	$_REQUEST['delay_flex'] = get_request('delay_flex', array());

	if ($timePeriodValidator->validate($_REQUEST['new_delay_flex']['period'])) {
		array_push($_REQUEST['delay_flex'], $_REQUEST['new_delay_flex']);
		unset($_REQUEST['new_delay_flex']);
	}
	else {
		error($timePeriodValidator->getError());
		show_messages(false, null, _('Invalid time period'));
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {
	$result = API::DiscoveryRule()->delete($_REQUEST['itemid']);

	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));
	unset($_REQUEST['itemid'], $_REQUEST['form']);
	clearCookies($result, $_REQUEST['hostid']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$delay_flex = get_request('delay_flex', array());

	$db_delay_flex = '';
	foreach ($delay_flex as $val) {
		$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
	}
	$db_delay_flex = trim($db_delay_flex, ';');

	$ifm = get_request('filter_macro');
	$ifv = get_request('filter_value');
	$filter = isset($ifm, $ifv) ? $ifm.':'.$ifv : '';

	$item = array(
		'interfaceid' => get_request('interfaceid'),
		'name' => get_request('name'),
		'description' => get_request('description'),
		'key_' => get_request('key'),
		'hostid' => get_request('hostid'),
		'delay' => get_request('delay'),
		'status' => get_request('status', ITEM_STATUS_DISABLED),
		'type' => get_request('type'),
		'snmp_community' => get_request('snmp_community'),
		'snmp_oid' => get_request('snmp_oid'),
		'trapper_hosts' => get_request('trapper_hosts'),
		'port' => get_request('port'),
		'snmpv3_contextname' => get_request('snmpv3_contextname'),
		'snmpv3_securityname' => get_request('snmpv3_securityname'),
		'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
		'snmpv3_authprotocol' => get_request('snmpv3_authprotocol'),
		'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
		'snmpv3_privprotocol' => get_request('snmpv3_privprotocol'),
		'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase'),
		'delay_flex' => $db_delay_flex,
		'authtype' => get_request('authtype'),
		'username' => get_request('username'),
		'password' => get_request('password'),
		'publickey' => get_request('publickey'),
		'privatekey' => get_request('privatekey'),
		'params' => get_request('params'),
		'ipmi_sensor' => get_request('ipmi_sensor'),
		'lifetime' => get_request('lifetime'),
		'filter' => $filter
	);

	if (hasRequest('itemid')) {
		$itemId = getRequest('itemid');

		DBstart();

		$dbItem = get_item_by_itemid_limited($itemId);

		// unset snmpv3 fields
		if ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
			$item['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
			$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
		}
		elseif ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
			$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
		}

		$item = CArrayHelper::unsetEqualValues($item, $dbItem);
		$item['itemid'] = $itemId;

		$result = API::DiscoveryRule()->update($item);
		$result = DBend($result);
		show_messages($result, _('Discovery rule updated'), _('Cannot update discovery rule'));
	}
	else {
		$result = API::DiscoveryRule()->create(array($item));
		show_messages($result, _('Discovery rule created'), _('Cannot add discovery rule'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		clearCookies($result, $_REQUEST['hostid']);
	}
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable')) && hasRequest('g_hostdruleid')) {
	$groupHostDiscoveryRuleId = getRequest('g_hostdruleid');
	$enable = (getRequest('go') == 'activate');

	DBstart();
	$result = $enable ? activate_item($groupHostDiscoveryRuleId) : disable_item($groupHostDiscoveryRuleId);
	$result = DBend($result);

	$updated = count($groupHostDiscoveryRuleId);

	$messageSuccess = $enable
		? _n('Discovery rule enabled', 'Discovery rules enabled', $updated)
		: _n('Discovery rule disabled', 'Discovery rules disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable discovery rules', 'Cannot enable discovery rules', $updated)
		: _n('Cannot disable discovery rules', 'Cannot disable discovery rules', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, getRequest('hostid'));
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_hostdruleid'])) {
	$goResult = API::DiscoveryRule()->delete($_REQUEST['g_hostdruleid']);

	show_messages($goResult, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
	clearCookies($goResult, $_REQUEST['hostid']);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = getItemFormData(array('is_discovery_rule' => true));
	$data['page_header'] = _('CONFIGURATION OF DISCOVERY RULES');

	// render view
	$itemView = new CView('configuration.item.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$data = array(
		'hostid' => get_request('hostid', 0),
		'host' => $host,
		'showErrorColumn' => ($host['status'] != HOST_STATUS_TEMPLATE)
	);

	$sortfield = getPageSortField('name');

	// discoveries
	$data['discoveries'] = API::DiscoveryRule()->get(array(
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectItems' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectHostPrototypes' => API_OUTPUT_COUNT,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	$data['discoveries'] = CMacrosResolverHelper::resolveItemNames($data['discoveries']);

	if ($sortfield === 'status') {
		orderItemsByStatus($data['discoveries'], getPageSortOrder());
	}
	else {
		order_result($data['discoveries'], $sortfield, getPageSortOrder());
	}

	// paging
	$data['paging'] = getPagingLine($data['discoveries'], array('itemid'), array('hostid' => get_request('hostid')));

	// render view
	$discoveryView = new CView('configuration.host.discovery.list', $data);
	$discoveryView->render();
	$discoveryView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
