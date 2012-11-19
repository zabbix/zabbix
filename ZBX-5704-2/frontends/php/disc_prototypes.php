<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of item prototypes');
$page['file'] = 'disc_prototypes.php';
$page['scripts'] = array('effects.js', 'class.cviewswitcher.js');
$page['hist_arg'] = array('parent_discoveryid');

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(get_request('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'parent_discoveryid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'itemid' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'groupid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'interfaceid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')),
	'name' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})', _('Name')),
	'description' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'key' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})', _('Key')),
	'delay' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_DAY),
		'isset({save})&&(isset({type})&&({type}!='.ITEM_TYPE_TRAPPER.'&&{type}!='.ITEM_TYPE_SNMPTRAP.'))',
		_('Update interval (in sec)')),
	'new_delay_flex' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({add_delay_flex})&&(isset({type})&&({type}!=2))',
		_('New flexible interval')),
	'delay_flex' =>				array(T_ZBX_STR, O_OPT, null,	'',			null),
	'status' =>					array(T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null),
	'type' =>					array(T_ZBX_INT, O_OPT, null,
		IN(array(-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED,
			ITEM_TYPE_SNMPTRAP)),'isset({save})'),
	'value_type' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({save})'),
	'data_type' =>				array(T_ZBX_INT, O_OPT, null,	IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.
		ITEM_DATA_TYPE_HEXADECIMAL.','.ITEM_DATA_TYPE_BOOLEAN), 'isset({save})&&(isset({value_type})&&({value_type}=='.
		ITEM_VALUE_TYPE_UINT64.'))'),
	'valuemapid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({save})&&isset({value_type})&&'.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')),
	'authtype' =>				array(T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'isset({save})&&isset({type})&&({type}=='.ITEM_TYPE_SSH.')'),
	'username' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&'.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')),
	'password' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&'.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')),
	'publickey' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&({type})=='.
		ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	'privatekey' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&({type})=='.
		ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	$paramsFieldName =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.IN(
		ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED,'type'), getParamFieldLabelByType(get_request('type', 0))),
	'snmp_community' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C,'type'), _('SNMP community')),
	'snmp_oid' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'),
		_('SNMP OID')),
	'port' => array(T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'),
		_('Port')),
	'snmpv3_securitylevel' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_securityname' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_authpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'),
	'snmpv3_privpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'),
	'ipmi_sensor' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', _('IPMI sensor')),
	'trapper_hosts' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&({type}==2)'),
	'units' =>					array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({value_type})&&'.IN('0,3','value_type').'(isset({data_type})&&({data_type}!='.ITEM_DATA_TYPE_BOOLEAN.'))'),
	'multiplier' =>				array(T_ZBX_INT, O_OPT, null,	null,		null),
	'delta' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'isset({save})&&isset({value_type})&&'.IN('0,3','value_type').'(isset({data_type})&&({data_type}!='.ITEM_DATA_TYPE_BOOLEAN.'))'),
	'formula' =>				array(T_ZBX_DBL, O_OPT, null,	NOT_ZERO,
		'isset({save})&&isset({multiplier})&&({multiplier}==1)&&'.IN('0,3','value_type'), _('Custom multiplier')),
	'logtimefmt' =>				array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({value_type})&&({value_type}==2))'),
	'group_itemid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'new_application' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'applications' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'history' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535), 'isset({save})', _('Keep history (in days)')),
	'trends' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535),
		'isset({save})&&isset({value_type})&&'.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
		_('Keep trends (in days)')),
	'add_delay_flex' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// actions
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	// ajax
	'favobj' =>					array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>					array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>				array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})'),
	'item_filter' => 			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['params'] = get_request($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

// permissions
if (get_request('parent_discoveryid', false)) {
	$discovery_rule = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	$discovery_rule = reset($discovery_rule);
	if (!$discovery_rule) {
		access_deny();
	}
	$_REQUEST['hostid'] = $discovery_rule['hostid'];

	if (isset($_REQUEST['itemid'])) {
		$itemPrototype = API::ItemPrototype()->get(array(
			'triggerids' => $_REQUEST['itemid'],
			'output' => array('itemid'),
			'editable' => true,
			'preservekeys' => true
		));
		if (empty($itemPrototype)) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

// ajax
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.host_discovery.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}
if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Actions
 */
if (isset($_REQUEST['add_delay_flex']) && isset($_REQUEST['new_delay_flex'])) {
	$_REQUEST['delay_flex'] = get_request('delay_flex', array());
	array_push($_REQUEST['delay_flex'], $_REQUEST['new_delay_flex']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {
	DBstart();
	$result = API::Itemprototype()->delete($_REQUEST['itemid']);
	$result = DBend($result);
	show_messages($result, _('Item deleted'), _('Cannot delete item'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$delay_flex = get_request('delay_flex', array());
	$db_delay_flex = '';
	foreach ($delay_flex as $value) {
		$db_delay_flex .= $value['delay'].'/'.$value['period'].';';
	}
	$db_delay_flex = trim($db_delay_flex, ';');

	DBstart();
	$applications = get_request('applications', array());
	$application = reset($applications);
	if ($application == 0) {
		array_shift($applications);
	}

	if (!zbx_empty($_REQUEST['new_application'])) {
		$new_appid = API::Application()->create(array(
			'name' => $_REQUEST['new_application'],
			'hostid' => $_REQUEST['hostid']
		));
		if ($new_appid) {
			$new_appid = reset($new_appid['applicationids']);
			$applications[$new_appid] = $new_appid;
		}
	}

	$item = array(
		'name'			=> get_request('name'),
		'description'	=> get_request('description'),
		'key_'			=> get_request('key'),
		'hostid'		=> get_request('hostid'),
		'interfaceid'	=> get_request('interfaceid'),
		'delay'			=> get_request('delay'),
		'status'		=> get_request('status', ITEM_STATUS_DISABLED),
		'type'			=> get_request('type'),
		'snmp_community' => get_request('snmp_community'),
		'snmp_oid'		=> get_request('snmp_oid'),
		'value_type'	=> get_request('value_type'),
		'trapper_hosts'	=> get_request('trapper_hosts'),
		'port'			=> get_request('port'),
		'history'		=> get_request('history'),
		'trends'		=> get_request('trends'),
		'units'			=> get_request('units'),
		'multiplier'	=> get_request('multiplier', 0),
		'delta'			=> get_request('delta'),
		'snmpv3_securityname' => get_request('snmpv3_securityname'),
		'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
		'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
		'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase'),
		'formula'		=> get_request('formula'),
		'logtimefmt'	=> get_request('logtimefmt'),
		'valuemapid'	=> get_request('valuemapid'),
		'authtype'		=> get_request('authtype'),
		'username'		=> get_request('username'),
		'password'		=> get_request('password'),
		'publickey'		=> get_request('publickey'),
		'privatekey'	=> get_request('privatekey'),
		'params'		=> get_request('params'),
		'ipmi_sensor'	=> get_request('ipmi_sensor'),
		'data_type'		=> get_request('data_type'),
		'ruleid'		=> get_request('parent_discoveryid'),
		'delay_flex'	=> $db_delay_flex,
		'applications'	=> $applications
	);

	if (isset($_REQUEST['itemid'])) {
		$db_item = get_item_by_itemid_limited($_REQUEST['itemid']);
		$db_item['applications'] = get_applications_by_itemid($_REQUEST['itemid']);

		foreach ($item as $field => $value) {
			if (isset($db_item[$field]) && ($item[$field] == $db_item[$field])) {
				unset($item[$field]);
			}
		}
		$item['itemid'] = $_REQUEST['itemid'];
		$result = API::Itemprototype()->update($item);

		show_messages($result, _('Item updated'), _('Cannot update item'));
	}
	else {
		$result = API::Itemprototype()->create($item);
		show_messages($result, _('Item added'), _('Cannot add item'));
	}

	$result = DBend($result);
	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
	}
}
// GO
elseif (($_REQUEST['go'] == 'activate' || $_REQUEST['go'] == 'disable') && isset($_REQUEST['group_itemid'])) {
	$group_itemid = $_REQUEST['group_itemid'];

	DBstart();
	$go_result = ($_REQUEST['go'] == 'activate') ? activate_item($group_itemid) : disable_item($group_itemid);
	$go_result = DBend($go_result);
	show_messages($go_result, ($_REQUEST['go'] == 'activate') ? _('Items activated') : _('Items disabled'), null);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_itemid'])) {
	$group_itemid = $_REQUEST['group_itemid'];
	DBstart();
	$go_result = API::Itemprototype()->delete($group_itemid);
	$go_result = DBend($go_result);
	show_messages($go_result, _('Items deleted'), _('Cannot delete items'));
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = getItemFormData();
	$data['page_header'] = _('CONFIGURATION OF ITEM PROTOTYPES');
	$data['is_item_prototype'] = true;

	// render view
	$itemView = new CView('configuration.item.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$data = array(
		'form' => get_request('form', null),
		'parent_discoveryid' => get_request('parent_discoveryid', null),
		'hostid' => get_request('hostid', null),
		'discovery_rule' => $discovery_rule
	);

	// get items
	$sortfield = getPageSortField('name');
	$data['items'] = API::ItemPrototype()->get(array(
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectApplications' => API_OUTPUT_EXTEND,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	if (!empty($data['items'])) {
		order_result($data['items'], $sortfield, getPageSortOrder());
	}
	$data['paging'] = getPagingLine($data['items']);

	// render view
	$itemView = new CView('configuration.item.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
?>
