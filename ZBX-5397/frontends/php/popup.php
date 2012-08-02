<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/nodes.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';

$srctbl = get_request('srctbl', ''); // source table name

// set page title
switch ($srctbl) {
	case 'host_templates':
	case 'templates':
		$page['title'] = _('Templates');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'hosts_and_templates':
		$page['title'] = _('Hosts and templates');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'hosts':
		$page['title'] = _('Hosts');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'proxies':
		$page['title'] = _('Proxies');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'applications':
		$page['title'] = _('Applications');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'host_group':
		$page['title'] = _('Host groups');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'triggers':
		$page['title'] = _('Triggers');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'usrgrp':
		$page['title'] = _('User groups');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'users':
		$page['title'] = _('Users');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'items':
		$page['title'] = _('Items');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'prototypes':
		$page['title'] = _('Prototypes');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'help_items':
		$page['title'] = _('Standard items');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'screens':
		$page['title'] = _('Screens');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'slides':
		$page['title'] = _('Slide shows');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'graphs':
		$page['title'] = _('Graphs');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'simple_graph':
		$page['title'] = _('Simple graph');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'sysmaps':
		$page['title'] = _('Maps');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'plain_text':
		$page['title'] = _('Plain text');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'screens2':
		$page['title'] = _('Screens');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'overview':
		$page['title'] = _('Overview');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'host_group_scr':
		$page['title'] = _('Host groups');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'nodes':
		if (ZBX_DISTRIBUTED) {
			$page['title'] = _('Nodes');
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		}
	case 'drules':
		$page['title'] = _('Discovery rules');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'dchecks':
		$page['title'] = _('Discovery checks');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'scripts':
		$page['title'] = _('Global scripts');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	default:
		$page['title'] = _('Error');
		$error = true;
		break;
}
$page['file'] = 'popup.php';
$page['scripts'] = array();

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

global $USER_DETAILS;
if ($min_user_type > $USER_DETAILS['type']) {
	access_deny();
}
if (isset($error)) {
	invalid_url();
}

/*
 * Fields
 */
// allowed 'srcfld*' parameter values for each 'srctbl' value
$allowedSrcFields = array(
	'users'					=> '"usergrpid", "alias", "userid"',
	'triggers'				=> '"description", "triggerid"',
	'items'					=> '"itemid", "name"',
	'prototypes'			=> '"itemid", "name", "flags"',
	'graphs'				=> '"graphid", "name"',
	'sysmaps'				=> '"sysmapid", "name"',
	'slides'				=> '"slideshowid"',
	'host_group'			=> '"groupid", "name"',
	'hosts_and_templates'	=> '"name", "hostid", "group"',
	'help_items'			=> '"key_"',
	'simple_graph'			=> '"itemid", "name"',
	'plain_text'			=> '"itemid", "name"',
	'hosts'					=> '"name", "hostid"',
	'overview'				=> '"groupid", "name"',
	'screens'				=> '"screenid"',
	'screens2'				=> '"screenid", "name"',
	'host_group_scr'		=> '"groupid", "name"',
	'host_templates'		=> '"templateid", "name"',
	'nodes'					=> '"nodeid", "name"',
	'drules'				=> '"druleid", "name"',
	'dchecks'				=> '"dcheckid", "name"',
	'proxies'				=> '"hostid", "host"',
	'usrgrp'				=> '"usrgrpid", "name"',
	'templates'				=> '"hostid", "host"',
	'applications'			=> '"name"',
	'scripts'				=> '"scriptid", "name"'
);

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>				array(T_ZBX_STR, O_OPT, P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
	'dstfld1' =>			array(T_ZBX_STR, O_OPT, P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
	'srctbl' =>				array(T_ZBX_STR, O_MAND, P_SYS,	NOT_EMPTY,	null),
	'srcfld1' =>			array(T_ZBX_STR, O_MAND, P_SYS,	IN($allowedSrcFields[$_REQUEST['srctbl']]), null),
	'nodeid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'groupid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'group' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'host' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'parent_discoveryid' => array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'screenid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'templates' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'host_templates' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'existed_templates' =>	array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'multiselect' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'submit' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'excludeids' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'only_hostid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'monitored_hosts' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'templated_hosts' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'real_hosts' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'normal_only' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'simpleName' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_applications' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_graphs' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_items' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_simple_graph_items' => array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'with_triggers' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'itemtype' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	'value_types' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 15), null),
	'reference' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'writeonly' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'noempty' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'select' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'submitParent' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null)
);

// unset disabled item types
$allowed_item_types = array(ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_SNMPTRAP);
if (isset($_REQUEST['itemtype']) && !str_in_array($_REQUEST['itemtype'], $allowed_item_types)) {
	unset($_REQUEST['itemtype']);
}

// set destination/source fields
$dstfldCount = countRequest('dstfld');
for ($i = 2; $i <= $dstfldCount; $i++) {
	$fields['dstfld'.$i] = array(T_ZBX_STR, O_OPT, P_SYS, null, null);
}
$srcfldCount = countRequest('srcfld');
for ($i = 2; $i <= $srcfldCount; $i++) {
	$fields['srcfld'.$i] = array(T_ZBX_STR, O_OPT, P_SYS, IN($allowedSrcFields[$_REQUEST['srctbl']]), null);
}

check_fields($fields);

$dstfrm = get_request('dstfrm', ''); // destination form
$dstfld1 = get_request('dstfld1', ''); // output field on destination form
$dstfld2 = get_request('dstfld2', ''); // second output field on destination form
$srcfld1 = get_request('srcfld1', ''); // source table field [can be different from fields of source table]
$srcfld2 = get_request('srcfld2', null); // second source table field [can be different from fields of source table]
$multiselect = get_request('multiselect', 0); // if create popup with checkboxes
$dstact = get_request('dstact', '');
$writeonly = get_request('writeonly');
$simpleName = get_request('simpleName');
$with_applications = get_request('with_applications', 0);
$with_graphs = get_request('with_graphs', 0);
$with_items = get_request('with_items', 0);
$noempty = get_request('noempty'); // display/hide "Empty" button
$existed_templates = get_request('existed_templates', null);
$excludeids = get_request('excludeids', null);
$reference = get_request('reference', get_request('srcfld1', 'unknown'));
$real_hosts = get_request('real_hosts', 0);
$monitored_hosts = get_request('monitored_hosts', 0);
$templated_hosts = get_request('templated_hosts', 0);
$with_simple_graph_items = get_request('with_simple_graph_items', 0);
$with_triggers = get_request('with_triggers', 0);
$value_types = get_request('value_types', null);
$submitParent = get_request('submitParent', 0);
$normal_only = get_request('normal_only');
$nodeid = get_request('nodeid', get_current_nodeid(false));
$group = get_request('group', '');
$host = get_request('host', '');
$only_hostid = get_request('only_hostid', null);
if (isset($only_hostid)) {
	$_REQUEST['hostid'] = $only_hostid;
	unset($_REQUEST['groupid'], $_REQUEST['nodeid']);
}


$url = new CUrl();
$path = $url->getPath();
insert_js('cookie.eraseArray(\''.$path.'\')');

function get_window_opener($frame, $field, $value) {
	if (empty($field)) {
		return '';
	}
	return '
		try {'.
			"window.opener.document.getElementById('".addslashes($field)."').value='".addslashes($value)."'; ".
		'} catch(e) {'.
			'throw("Error: Target not found")'.
		'}'."\n";
}

/*
 * Page filter
 */
if (!empty($group)) {
	$dbGroup = DBfetch(DBselect('SELECT g.groupid FROM groups g WHERE g.name='.zbx_dbstr($group)));
	if (!empty($dbGroup) && !empty($dbGroup['groupid'])) {
		$_REQUEST['groupid'] = $dbGroup['groupid'];
	}
	unset($dbGroup);
}
if (!empty($host)) {
	$dbHost = DBfetch(DBselect('SELECT h.hostid FROM hosts h WHERE h.name='.zbx_dbstr($host)));
	if (!empty($dbHost) && !empty($dbHost['hostid'])) {
		$_REQUEST['hostid'] = $dbHost['hostid'];
	}
	unset($dbHost);
}

$options = array(
	'config' => array('select_latest' => true, 'deny_all' => true, 'popupDD' => true),
	'groups' => array('nodeids' => $nodeid),
	'hosts' => array('nodeids' => $nodeid),
	'groupid' => get_request('groupid', null),
	'hostid' => get_request('hostid', null)
);

if (!is_null($writeonly)) {
	$options['groups']['editable'] = true;
	$options['hosts']['editable'] = true;
}

$host_status = null;
$templated = null;

if ($monitored_hosts) {
	$options['groups']['monitored_hosts'] = true;
	$options['hosts']['monitored_hosts'] = true;
	$host_status = 'monitored_hosts';
}
elseif ($real_hosts) {
	$options['groups']['real_hosts'] = true;
	$templated = 0;
}
elseif ($templated_hosts) {
	$options['hosts']['templated_hosts'] = true;
	$options['groups']['templated_hosts'] = true;
	$templated = 1;
	$host_status = 'templated_hosts';
}
else {
	$options['groups']['with_hosts_and_templates'] = true;
	$options['hosts']['templated_hosts'] = true; // for hosts templated_hosts comes with monitored and not monitored hosts
}

if ($with_applications) {
	$options['groups']['with_applications'] = true;
	$options['hosts']['with_applications'] = true;
}
elseif ($with_graphs) {
	$options['groups']['with_graphs'] = true;
	$options['hosts']['with_graphs'] = true;
}
elseif ($with_simple_graph_items) {
	$options['groups']['with_simple_graph_items'] = true;
	$options['hosts']['with_simple_graph_items'] = true;
}
elseif ($with_triggers) {
	$options['groups']['with_triggers'] = true;
	$options['hosts']['with_triggers'] = true;
}
$pageFilter = new CPageFilter($options);

// get groupid
$groupid = null;
if ($pageFilter->groupsSelected) {
	if ($pageFilter->groupid > 0) {
		$groupid = $pageFilter->groupid;
	}
}
else {
	$groupid = 0;
}

// get hostid
$hostid = null;
if ($pageFilter->hostsSelected) {
	if ($pageFilter->hostid > 0) {
		$hostid = $pageFilter->hostid;
	}
}
else {
	$hostid = 0;
}
if (isset($only_hostid)) {
	$hostid = $only_hostid;
}

/*
 * Display table header
 */
$frmTitle = new CForm();
if ($monitored_hosts) {
	$frmTitle->addVar('monitored_hosts', 1);
}
if ($real_hosts) {
	$frmTitle->addVar('real_hosts', 1);
}
if ($templated_hosts) {
	$frmTitle->addVar('templated_hosts', 1);
}
if ($with_applications) {
	$frmTitle->addVar('with_applications', 1);
}
if ($with_graphs) {
	$frmTitle->addVar('with_graphs', 1);
}
if ($with_items) {
	$frmTitle->addVar('with_items', 1);
}
if ($with_simple_graph_items) {
	$frmTitle->addVar('with_simple_graph_items', 1);
}
if ($with_triggers) {
	$frmTitle->addVar('with_triggers', 1);
}
if ($value_types) {
	$frmTitle->addVar('value_types', $value_types);
}
if ($normal_only) {
	$frmTitle->addVar('normal_only', $normal_only);
}
if (!is_null($existed_templates)) {
	$frmTitle->addVar('existed_templates', $existed_templates);
}
if (!is_null($excludeids)) {
	$frmTitle->addVar('excludeids', $excludeids);
}
if (isset($only_hostid)) {
	$frmTitle->addVar('only_hostid', $only_hostid);
}
if (get_request('screenid')) {
	$frmTitle->addVar('screenid', get_request('screenid'));
}

// adding param to a form, so that it would remain when page is refreshed
$frmTitle->addVar('dstfrm', $dstfrm);
$frmTitle->addVar('dstact', $dstact);
$frmTitle->addVar('srctbl', $srctbl);
$frmTitle->addVar('multiselect', $multiselect);
$frmTitle->addVar('writeonly', $writeonly);
$frmTitle->addVar('reference', $reference);
$frmTitle->addVar('submitParent', $submitParent);
$frmTitle->addVar('noempty', $noempty);

for ($i = 1; $i <= $dstfldCount; $i++) {
	$frmTitle->addVar('dstfld'.$i, get_request('dstfld'.$i));
}
for ($i = 1; $i <= $srcfldCount; $i++) {
	$frmTitle->addVar('srcfld'.$i, get_request('srcfld'.$i));
}

if (isset($only_hostid)) {
	$only_hosts = API::Host()->get(array(
		'hostids' => $hostid,
		'templated_hosts' => true,
		'output' => array('hostid', 'host'),
		'limit' => 1
	));
	$host = reset($only_hosts);

	if (empty($host)) {
		access_deny();
	}

	$cmbHosts = new CComboBox('hostid', $hostid);
	$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$host['host']);
	$cmbHosts->setEnabled('disabled');
	$cmbHosts->setAttribute('title', _('You can not switch hosts for current selection.'));
	$frmTitle->addItem(array(SPACE, _('Host'), SPACE, $cmbHosts));
}
else {
	if (str_in_array($srctbl, array('hosts', 'host_group', 'triggers', 'items', 'simple_graph', 'applications',
			'screens', 'slides', 'graphs', 'sysmaps', 'plain_text', 'screens2', 'overview', 'host_group_scr'))) {
		if (ZBX_DISTRIBUTED) {
			$cmbNode = new CComboBox('nodeid', $nodeid, 'submit()');

			$db_nodes = DBselect('SELECT n.* FROM nodes n WHERE '.DBcondition('n.nodeid', get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST)));
			while ($node_data = DBfetch($db_nodes)) {
				$cmbNode->addItem($node_data['nodeid'], $node_data['name']);
				if (bccomp($nodeid , $node_data['nodeid']) == 0) {
					$ok = true;
				}
			}
			$frmTitle->addItem(array(SPACE, _('Node'), SPACE, $cmbNode, SPACE));
		}
	}

	if (!isset($ok)) {
		$nodeid = get_current_nodeid();
	}
	unset($ok);

	if (str_in_array($srctbl, array('hosts_and_templates', 'hosts', 'templates', 'triggers', 'items', 'applications', 'host_templates', 'graphs', 'simple_graph', 'plain_text'))) {
		$frmTitle->addItem(array(_('Group'), SPACE, $pageFilter->getGroupsCB(true)));
	}
	if (str_in_array($srctbl, array('help_items'))) {
		$itemtype = get_request('itemtype', CProfile::get('web.popup.itemtype', 0));
		$cmbTypes = new CComboBox('itemtype', $itemtype, 'javascript: submit();');

		foreach ($allowed_item_types as $type) {
			$cmbTypes->addItem($type, item_type2str($type));
		}
		$frmTitle->addItem(array(_('Type'), SPACE, $cmbTypes));
	}
	if (str_in_array($srctbl, array('triggers', 'items', 'applications', 'graphs', 'simple_graph', 'plain_text'))) {
		$frmTitle->addItem(array(SPACE, _('Host'), SPACE, $pageFilter->getHostsCB(true)));
	}
	if (str_in_array($srctbl, array('triggers', 'hosts', 'host_group', 'hosts_and_templates'))) {
		if (zbx_empty($noempty)) {
			$value1 = isset($_REQUEST['dstfld1']) && zbx_strpos($_REQUEST['dstfld1'], 'id') !== false ? 0 : '';
			$value2 = isset($_REQUEST['dstfld2']) && zbx_strpos($_REQUEST['dstfld2'], 'id') !== false ? 0 : '';

			$epmtyScript = get_window_opener($dstfrm, $dstfld1, $value1);
			$epmtyScript .= get_window_opener($dstfrm, $dstfld2, $value2);
			$epmtyScript .= ' close_window(); return false;';

			$frmTitle->addItem(array(SPACE, new CButton('empty', _('Empty'), $epmtyScript)));
		}
	}
}

show_table_header($page['title'], $frmTitle);

insert_js_function('addSelectedValues');
insert_js_function('addValues');
insert_js_function('addValue');

/*
 * Hosts
 */
if ($srctbl == 'hosts') {
	$table = new CTableInfo(_('No hosts defined.'));
	$table->setHeader(array(_('Name'), _('DNS'), _('IP'), _('Port'), _('Status'), _('Availability')));

	$options = array(
		'nodeids' => $nodeid,
		'groupids' => $groupid,
		'output' => array('hostid', 'name', 'status', 'available'),
		'selectInterfaces' => array('dns', 'ip', 'useip', 'port')
	);
	if (!is_null($writeonly)) {
		$options['editable'] = 1;
	}
	if (!is_null($host_status)) {
		$options[$host_status] = 1;
	}

	$hosts = API::Host()->get($options);
	order_result($hosts, 'name');

	foreach ($hosts as $host) {
		$name = new CSpan($host['name'], 'link');
		$action = get_window_opener($dstfrm, $dstfld1, $host[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]) : '');
		$name->attr('onclick', $action.' close_window();');
		$name->attr('id', 'spanid'.$host['hostid']);

		if ($host['status'] == HOST_STATUS_MONITORED) {
			$status = new CSpan(_('Monitored'), 'off');
		}
		elseif ($host['status'] == HOST_STATUS_NOT_MONITORED) {
			$status = new CSpan(_('Not monitored'), 'on');
		}
		else {
			$status = _('Unknown');
		}

		$interface = reset($host['interfaces']);

		$dns = $interface['dns'];
		$ip = $interface['ip'];

		$tmp = $interface['useip'] == 1 ? 'ip' : 'dns';
		$tmp = bold($tmp);

		if ($host['available'] == HOST_AVAILABLE_TRUE) {
			$available = new CSpan(_('Available'), 'off');
		}
		elseif ($host['available'] == HOST_AVAILABLE_FALSE) {
			$available = new CSpan(_('Not available'), 'on');
		}
		elseif ($host['available'] == HOST_AVAILABLE_UNKNOWN) {
			$available = new CSpan(_('Unknown'), 'unknown');
		}

		$table->addRow(array(
			$name,
			$dns,
			$ip,
			$interface['port'],
			$status,
			$available
		));
		unset($host);
	}
	$table->show();
}
/*
 * Templates
 */
elseif ($srctbl == 'templates') {
	$existed_templates = get_request('existed_templates', array());
	$excludeids = get_request('excludeids', array());
	$templates = get_request('templates', array());
	$templates = $templates + $existed_templates;
	if (!validate_templates(array_keys($templates))) {
		show_error_message(_('Conflict between selected templates.'));
	}
	elseif (isset($_REQUEST['select'])) {
		$new_templates = array_diff($templates, $existed_templates);
		$script = '';
		if (count($new_templates) > 0) {
			foreach ($new_templates as $id => $name) {
				$script .= 'add_variable(null, "templates['.$id.']", '.zbx_jsvalue($name).', '.zbx_jsvalue($dstfrm).', window.opener.document);'."\n";
			}
		}
		$script .= 'var form = window.opener.document.forms['.zbx_jsvalue($dstfrm).']; if (form) { form.submit(); } close_window();';
		insert_js($script);
		unset($new_templates);
	}

	$table = new CTableInfo(_('No templates defined.'));
	$table->setHeader(array(_('Name')));

	$options = array(
		'nodeids' => $nodeid,
		'groupids' => $groupid,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'name'
	);
	if (!is_null($writeonly)) {
		$options['editable'] = 1;
	}

	$dbTemplates = API::Template()->get($options);
	foreach ($dbTemplates as $host) {
		$chk = new CCheckBox('templates['.$host['hostid'].']', isset($templates[$host['hostid']]), null, $host['name']);
		$chk->setEnabled(!isset($existed_templates[$host['hostid']]) && !isset($excludeids[$host['hostid']]));
		$table->addRow(array(array($chk, $host['name'])));
	}

	$table->setFooter(new CSubmit('select', _('Select')));
	$form = new CForm();
	$form->addVar('existed_templates', $existed_templates);

	if ($monitored_hosts) {
		$form->addVar('monitored_hosts', 1);
	}
	if ($real_hosts) {
		$form->addVar('real_hosts', 1);
	}
	$form->addVar('dstfrm', $dstfrm);
	$form->addVar('dstfld1', $dstfld1);
	$form->addVar('srctbl', $srctbl);
	$form->addVar('srcfld1', $srcfld1);
	$form->addVar('srcfld2', $srcfld2);
	$form->addItem($table);
	$form->show();
}
/*
 * Host group
 */
elseif ($srctbl == 'host_group') {
	$form = new CForm();
	$form->setName('groupform');
	$form->setAttribute('id', 'groups');

	$table = new CTableInfo(_('No host groups defined.'));
	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_groups', null, "javascript: checkAll('".$form->getName()."', 'all_groups', 'groups');") : null,
		_('Name')
	));

	$options = array(
		'nodeids' => $nodeid,
		'output' => array('groupid', 'name'),
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$hostgroups = API::HostGroup()->get($options);
	order_result($hostgroups, 'name');

	foreach ($hostgroups as $gnum => $group) {
		$nodeName = get_node_name_by_elid($group['groupid'], true);
		$group['node_name'] = isset($nodeName) ? '('.$nodeName.') ' : '';
		$hostgroups[$gnum]['node_name'] = $group['node_name'];

		$name = new CSpan(get_node_name_by_elid($group['groupid'], null, ': ').$group['name'], 'link');
		$name->attr('id', 'spanid'.$group['groupid']);

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($group['groupid']).');';
		}
		else {
			$values = array($dstfld1 => $group[$srcfld1]);
			if (isset($srcfld2)) {
				$values[$dstfld2] = $group[$srcfld2];
			}
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		$table->addRow(array(
			$multiselect ? new CCheckBox('groups['.zbx_jsValue($group[$srcfld1]).']', null, null, $group['groupid']) : null,
			$name
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('groups', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));
		insert_js('var popupReference = '.zbx_jsvalue($hostgroups, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "groups";');

	$form->addItem($table);
	$form->show();
}
/*
 * Host templates
 */
elseif ($srctbl == 'host_templates') {
	$form = new CForm();
	$form->setName('tplform');
	$form->setAttribute('id', 'templates');

	$table = new CTableInfo(_('No templates defined.'));
	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_templates', null, "javascript: checkAll('".$form->getName()."', 'all_templates', 'templates');") : null,
		_('Name')
	));

	$options = array(
		'nodeids' => $nodeid,
		'groupids' => $groupid,
		'output' => array('templateid', 'name'),
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$templates = API::Template()->get($options);
	order_result($templates, 'name');

	foreach ($templates as $template) {
		$name = new CSpan(get_node_name_by_elid($template['templateid'], null, ': ').$template['name'], 'link');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($template['templateid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $template[$srcfld1],
				$dstfld2 => $template[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		$table->addRow(array(
			$multiselect ? new CCheckBox('templates['.zbx_jsValue($template[$srcfld1]).']', null, null, $template['templateid']) : null,
			$name
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('templates', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($templates, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "templates";');

	$form->addItem($table);
	$form->show();
}
/*
 * Hosts and templates
 */
elseif ($srctbl == 'hosts_and_templates') {
	$table = new CTableInfo(_('No templates defined.'));
	$table->setHeader(array(_('Name')));

	$options = array(
		'nodeids' => $nodeid,
		'groupids' => $groupid,
		'output' => array('hostid', 'name')
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}

	// get templates
	$templates = API::Template()->get($options);
	foreach ($templates as $number => $template) {
		$templates[$number]['hostid'] = $template['templateid'];
	}
	order_result($templates, 'name');

	// get hosts
	$hosts = API::Host()->get($options);
	order_result($hosts, 'name');

	$hostsAndTemplates = array_merge($templates, $hosts);
	foreach ($hostsAndTemplates as $row) {
		$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		if ($submitParent) {
			$action .= ' var form = window.opener.document.forms['.zbx_jsvalue($dstfrm).']; if (form) { form.submit(); }';
		}
		$name = new CSpan($row['name'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * User group
 */
elseif ($srctbl == 'usrgrp') {
	$form = new CForm();
	$form->setName('usrgrpform');
	$form->setAttribute('id', 'usrgrps');

	$table = new CTableInfo(_('No user groups defined.'));
	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_usrgrps', null, "javascript: checkAll('".$form->getName()."', 'all_usrgrps', 'usrgrps');") : null,
		_('Name')
	));

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$userGroups = API::UserGroup()->get($options);
	order_result($userGroups, 'name');

	foreach ($userGroups as $userGroup) {
		$name = new CSpan(get_node_name_by_elid($userGroup['usrgrpid'], null, ': ').$userGroup['name'], 'link');
		$name->attr('id', 'spanid'.$userGroup['usrgrpid']);

		if ($multiselect) {
			$js_action = "javascript: addValue(".zbx_jsvalue($reference).', '.zbx_jsvalue($userGroup['usrgrpid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $userGroup[$srcfld1],
				$dstfld2 => $userGroup[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		$table->addRow(array(
			$multiselect ? new CCheckBox('usrgrps['.$userGroup['usrgrpid'].']', null, null, $userGroup['usrgrpid']) : null,
			$name,
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('usrgrps', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($userGroups, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "usrgrps";');

	$form->addItem($table);
	$form->show();
}
/*
 * Users
 */
elseif ($srctbl == 'users') {
	$form = new CForm();
	$form->setName('userform');
	$form->setAttribute('id', 'users');

	$table = new CTableInfo(_('No users defined.'));
	$table->setHeader(array(
		($multiselect ? new CCheckBox('all_users', null, "javascript: checkAll('".$form->getName()."', 'all_users', 'users');") : null),
		_('Alias'),
		_('Name'),
		_('Surname')
	));

	$options = array(
		'nodeids' => $nodeid,
		'output' => array('alias', 'name', 'surname', 'type', 'theme', 'lang'),
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$users = API::User()->get($options);
	order_result($users, 'alias');

	foreach ($users as $unum => $user) {
		$alias = new CSpan(get_node_name_by_elid($user['userid'], null, ': ').$user['alias'], 'link');
		$alias->attr('id', 'spanid'.$user['userid']);

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($user['userid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $user[$srcfld1],
				$dstfld2 => isset($srcfld2) ? $user[$srcfld2] : null,
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$alias->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		$table->addRow(array(
			$multiselect ? new CCheckBox('users['.zbx_jsValue($user[$srcfld1]).']', null, null, $user['userid']) : null,
			$alias,
			$user['name'],
			$user['surname']
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('users', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($users, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "users";');

	$form->addItem($table);
	$form->show();
}
/*
 * Help items
 */
elseif ($srctbl == 'help_items') {
	$table = new CTableInfo(_('No items defined.'));
	$table->setHeader(array(_('Key'), _('Name')));

	$result = DBselect('SELECT hi.* FROM help_items hi WHERE hi.itemtype='.$itemtype.' ORDER BY hi.key_');
	while ($row = DBfetch($result)) {
		$action = get_window_opener($dstfrm, $dstfld1, html_entity_decode($row[$srcfld1])).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name = new CSpan($row['key_'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow(array($name, $row['description']));
	}
	$table->show();
}
/*
 * Triggers
 */
elseif ($srctbl == 'triggers') {
	$form = new CForm();
	$form->setName('triggerform');
	$form->setAttribute('id', 'triggers');

	$table = new CTableInfo(_('No triggers defined.'));

	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_triggers', null, "checkAll('".$form->getName()."', 'all_triggers', 'triggers');") : null,
		_('Name'),
		_('Severity'),
		_('Status')
	));

	$options = array(
		'nodeids' => $nodeid,
		'hostids' => $hostid,
		'output' => array('triggerid', 'description', 'expression', 'priority', 'status'),
		'selectHosts' => array('hostid', 'name'),
		'selectDependencies' => API_OUTPUT_EXTEND,
		'expandDescription' => true
	);
	if (is_null($hostid)) {
		$options['groupids'] = $groupid;
	}
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	if (!is_null($templated)) {
		$options['templated'] = $templated;
	}
	$triggers = API::Trigger()->get($options);
	order_result($triggers, 'description');

	if ($multiselect) {
		$jsTriggers = array();
	}

	foreach ($triggers as $trigger) {
		$host = reset($trigger['hosts']);
		$trigger['hostname'] = $host['name'];

		$description = new CSpan($trigger['description'], 'link');
		$trigger['description'] = $trigger['hostname'].': '.$trigger['description'];

		if ($multiselect) {
			$js_action = 'addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($trigger['triggerid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $trigger[$srcfld1],
				$dstfld2 => $trigger[$srcfld2]
			);
			$js_action = 'addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if (count($trigger['dependencies']) > 0) {
			$description = array(
				$description,
				BR(),
				bold(_('Depends on')),
				BR()
			);

			foreach ($trigger['dependencies'] as $val) {
				$description[] = array(CTriggerHelper::expandDescription($val), BR());
			}
		}

		switch ($trigger['status']) {
			case TRIGGER_STATUS_DISABLED:
				$status = new CSpan(_('Disabled'), 'disabled');
				break;
			case TRIGGER_STATUS_ENABLED:
				$status = new CSpan(_('Enabled'), 'enabled');
				break;
		}
		$table->addRow(array(
			$multiselect ? new CCheckBox('triggers['.zbx_jsValue($trigger[$srcfld1]).']', null, null, $trigger['triggerid']) : null,
			$description,
			getSeverityCell($trigger['priority']),
			$status
		));

		// made to save memmory usage
		if ($multiselect) {
			$jsTriggers[$trigger['triggerid']] = array(
				'triggerid' => $trigger['triggerid'],
				'description' => $trigger['description'],
				'expression' => $trigger['expression'],
				'priority' => $trigger['priority'],
				'status' => $trigger['status'],
				'host' => $trigger['hostname']
			);
		}
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "addSelectedValues('triggers', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsValue($jsTriggers, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "triggers";');

	$form->addItem($table);
	$form->show();
}
/*
 * Items
 */
elseif ($srctbl == 'items') {
	$form = new CForm();
	$form->setName('itemform');
	$form->setAttribute('id', 'items');

	$table = new CTableInfo(_('No items defined.'));

	$header = array(
		$pageFilter->hostsAll ? _('Host') : null,
		$multiselect ? new CCheckBox('all_items', null, "javascript: checkAll('".$form->getName()."', 'all_items', 'items');") : null,
		_('Name'),
		_('Key'),
		_('Type'),
		_('Type of information'),
		_('Status')
	);
	$table->setHeader($header);

	$options = array(
		'nodeids' => $nodeid,
		'hostids' => $hostid,
		'webitems' => true,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name'),
		'preservekeys' => true
	);
	if (!is_null($normal_only)) {
		$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
	}
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	if (!is_null($templated) && $templated == 1) {
		$options['templated'] = $templated;
	}
	if (!is_null($value_types)) {
		$options['filter']['value_type'] = $value_types;
	}
	$items = API::Item()->get($options);
	order_result($items, 'name', ZBX_SORT_UP);

	if ($multiselect) {
		$jsItems = array();
	}

	foreach ($items as $item) {
		$host = reset($item['hosts']);
		$item['hostname'] = $host['name'];

		$item['name'] = itemName($item);
		$description = new CLink($item['name'], '#');
		$item['name'] = $item['hostname'].': '.$item['name'];

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($item['itemid']).');';
		}
		else {
			$values = array();
			for ($i = 1; $i <= $dstfldCount; $i++) {
				$dstfld = get_request('dstfld'.$i);
				$srcfld = get_request('srcfld'.$i);

				if (!empty($dstfld) && !empty($item[$srcfld])) {
					$values[$dstfld] = $item[$srcfld];
				}
			}

			// if we need to submit parent window
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).', '.($submitParent ? 'true' : 'false').'); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		$table->addRow(array(
			$hostid > 0 ? null : $item['hostname'],
			$multiselect ? new CCheckBox('items['.zbx_jsValue($item[$srcfld1]).']', null, null, $item['itemid']) : null,
			$description,
			$item['key_'],
			item_type2str($item['type']),
			item_value_type2str($item['value_type']),
			new CSpan(item_status2str($item['status']), item_status2style($item['status']))
		));

		// made to save memory usage
		if ($multiselect) {
			$jsItems[$item['itemid']] = array(
				'itemid' => $item['itemid'],
				'name' => $item['name'],
				'key_' => $item['key_'],
				'type' => $item['type'],
				'value_type' => $item['value_type'],
				'host' => $item['hostname']
			);
		}
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('items', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($jsItems, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "items";');

	$form->addItem($table);
	$form->show();
}
/*
 * Prototypes
 */
elseif ($srctbl == 'prototypes') {
	$form = new CForm();
	$form->setName('itemform');
	$form->setAttribute('id', 'items');

	$table = new CTableInfo(_('No item prototypes defined.'));

	if ($multiselect) {
		$header = array(
			array(new CCheckBox('all_items', null, "javascript: checkAll('".$form->getName()."', 'all_items', 'items');"), _('Name')),
			_('Key'),
			_('Type'),
			_('Type of information'),
			_('Status')
		);
	}
	else {
		$header = array(
			_('Name'),
			_('Key'),
			_('Type'),
			_('Type of information'),
			_('Status')
		);
	}
	$table->setHeader($header);

	$items = API::Item()->get(array(
		'nodeids' => $nodeid,
		'selectHosts' => array('name'),
		'discoveryids' => get_request('parent_discoveryid'),
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD),
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	order_result($items, 'name');

	foreach ($items as &$item) {
		$host = reset($item['hosts']);

		$description = new CSpan(itemName($item), 'link');
		$item['name'] = $host['name'].': '.$item['name'];

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($item['itemid']).');';
		}
		else {
			$values = array();
			for ($i = 1; $i <= $dstfldCount; $i++) {
				$dstfld = get_request('dstfld'.$i);
				$srcfld = get_request('srcfld'.$i);

				if (!empty($dstfld) && !empty($item[$srcfld])) {
					$values[$dstfld] = $item[$srcfld];
				}
			}

			// if we need to submit parent window
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).', '.($submitParent ? 'true' : 'false').'); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$description = new CCol(array(new CCheckBox('items['.zbx_jsValue($item[$srcfld1]).']', null, null, $item['itemid']), $description));
		}

		$table->addRow(array(
			$description,
			$item['key_'],
			item_type2str($item['type']),
			item_value_type2str($item['value_type']),
			new CSpan(item_status2str($item['status']), item_status2style($item['status']))
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('items', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($items, true).';');
	}
	unset($items);

	zbx_add_post_js('chkbxRange.pageGoName = "items";');

	$form->addItem($table);
	$form->show();
}
/*
 * Applications
 */
elseif ($srctbl == 'applications') {
	$table = new CTableInfo(_('No applications defined.'));
	$table->setHeader(array(
		$hostid > 0 ? null : _('Host'),
		_('Name')
	));

	$options = array(
		'nodeids' => $nodeid,
		'hostids' => $hostid,
		'output' => API_OUTPUT_EXTEND,
		'expandData' => true
	);
	if (is_null($hostid)) {
		$options['groupids'] = $groupid;
	}
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	if (!is_null($templated)) {
		$options['templated'] = $templated;
	}
	$apps = API::Application()->get($options);
	CArrayHelper::sort($apps, array('host', 'name'));

	foreach ($apps as $app) {
		$action = get_window_opener($dstfrm, $dstfld1, $app[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $app[$srcfld2]) : '');
		$name = new CSpan($app['name'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');

		$table->addRow(array($hostid > 0 ? null : $app['host'], $name));
	}
	$table->show();
}
/*
 * Nodes
 */
elseif ($srctbl == 'nodes') {
	$table = new CTableInfo(_('No nodes defined.'));
	$table->setHeader(_('Name'));

	$result = DBselect('SELECT DISTINCT n.* FROM nodes n WHERE '.DBcondition('n.nodeid', get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST)));
	while ($row = DBfetch($result)) {
		$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name = new CSpan($row['name'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Graphs
 */
elseif ($srctbl == 'graphs') {
	$form = new CForm();
	$form->setName('graphform');
	$form->setAttribute('id', 'graphs');

	$table = new CTableInfo(_('No graphs defined.'));

	if ($multiselect) {
		$header = array(
			array(new CCheckBox('all_graphs', null, "javascript: checkAll('".$form->getName()."', 'all_graphs', 'graphs');"), _('Description')),
			_('Graph type')
		);
	}
	else {
		$header = array(
			_('Name'),
			_('Graph type')
		);
	}

	$table->setHeader($header);

	if ($pageFilter->hostsSelected) {
		if ($pageFilter->hostsAll) {
			$hostid = array_keys($pageFilter->hosts);
		}
		else {
			$hostid = $pageFilter->hostid;
		}
		$options = array(
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => $nodeid,
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);

		if (!is_null($writeonly)) {
			$options['editable'] = true;
		}
		if (!is_null($templated)) {
			$options['templated'] = $templated;
		}
		$graphs = API::Graph()->get($options);
		order_result($graphs, 'name');
	}
	else {
		$graphs = array();
	}

	foreach ($graphs as $graph) {
		$host = reset($graph['hosts']);
		$graph['hostname'] = $host['name'];
		$graph['node_name'] = get_node_name_by_elid($graph['graphid'], null, ': ');

		if (!$simpleName) {
			$graph['name'] = $graph['node_name'].$graph['hostname'].': '.$graph['name'];
		}
		$description = new CSpan($graph['name'], 'link');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($graph['graphid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $graph[$srcfld1],
				$dstfld2 => $graph[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$description = new CCol(array(new CCheckBox('graphs['.zbx_jsValue($graph[$srcfld1]).']', null, null, $graph['graphid']), $description));
		}

		switch ($graph['graphtype']) {
			case GRAPH_TYPE_STACKED:
				$graphtype = _('Stacked');
				break;
			case GRAPH_TYPE_PIE:
				$graphtype = _('Pie');
				break;
			case GRAPH_TYPE_EXPLODED:
				$graphtype = _('Exploded');
				break;
			default:
				$graphtype = _('Normal');
				break;
		}
		$table->addRow(array(
			$description,
			$graphtype
		));
		unset($description);
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('graphs', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($graphs, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "graphs";');

	$form->addItem($table);
	$form->show();
}
/*
 * Simple graph
 */
elseif ($srctbl == 'simple_graph') {
	$form = new CForm();
	$form->setName('itemform');
	$form->setAttribute('id', 'items');

	$table = new CTableInfo(_('No items defined.'));

	if ($pageFilter->hostsSelected) {
		if ($pageFilter->hostsAll) {
			$hostid = array_keys($pageFilter->hosts);
		}
		else {
			$hostid = $pageFilter->hostid;
		}

		$options = array(
			'nodeids' => $nodeid,
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'webitems' => true,
			'templated' => false,
			'filter' => array(
				'value_type' => array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64),
				'status' => ITEM_STATUS_ACTIVE,
				'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)
			),
			'preservekeys' => true
		);
		if (!is_null($writeonly)) {
			$options['editable'] = true;
		}
		if (!is_null($templated)) {
			$options['templated'] = $templated;
		}
		$items = API::Item()->get($options);
		order_result($items, 'name');
	}
	else {
		$items = array();
	}

	if ($multiselect) {
		$header = array(
			is_array($hostid) ? _('Host') : null,
			array(new CCheckBox('all_items', null, "javascript: checkAll('".$form->getName()."', 'all_items', 'items');"), _('Name')),
			_('Type'),
			_('Type of information')
		);
	}
	else {
		$header = array(
			is_array($hostid) ? _('Host') : null,
			_('Name'),
			_('Type'),
			_('Type of information')
		);
	}
	$table->setHeader($header);

	foreach ($items as $item) {
		$host = reset($item['hosts']);
		$item['hostname'] = $host['name'];
		$item['name'] = itemName($item);
		$description = new CLink($item['name'], '#');

		if (!$simpleName) {
			$item['name'] = $item['hostname'].': '.$item['name'];
		}

		if ($multiselect) {
			$js_action ='javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($item['itemid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $item[$srcfld1],
				$dstfld2 => $item[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$description = new CCol(array(new CCheckBox('items['.zbx_jsValue($item[$srcfld1]).']', null, null, $item['itemid']), $description));
		}

		$table->addRow(array(
			$hostid > 0 ? null : $item['hostname'],
			$description,
			item_type2str($item['type']),
			item_value_type2str($item['value_type'])
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('items', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($items, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "items";');

	$form->addItem($table);
	$form->show();
}
/*
 * Sysmaps
 */
elseif ($srctbl == 'sysmaps') {
	$form = new CForm();
	$form->setName('sysmapform');
	$form->setAttribute('id', 'sysmaps');

	$table = new CTableInfo(_('No maps defined.'));

	if ($multiselect) {
		$header = array(array(new CCheckBox('all_sysmaps', null, "javascript: checkAll('".$form->getName()."', 'all_sysmaps', 'sysmaps');"), _('Name')));
	}
	else {
		$header = array(_('Name'));
	}

	$table->setHeader($header);

	$excludeids = get_request('excludeids', array());
	$excludeids = zbx_toHash($excludeids);

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$sysmaps = API::Map()->get($options);
	order_result($sysmaps, 'name');

	foreach ($sysmaps as $sysmap) {
		if (isset($excludeids[$sysmap['sysmapid']])) {
			continue;
		}
		$sysmap['node_name'] = isset($sysmap['node_name']) ? '('.$sysmap['node_name'].') ' : '';
		$name = $sysmap['node_name'].$sysmap['name'];
		$description = new CSpan($sysmap['name'], 'link');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($sysmap['sysmapid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $sysmap[$srcfld1],
				$dstfld2 => $sysmap[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$description = new CCol(array(new CCheckBox('sysmaps['.zbx_jsValue($sysmap[$srcfld1]).']', null, null, $sysmap['sysmapid']), $description));
		}
		$table->addRow($description);
		unset($description);
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('sysmaps', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($sysmaps, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "sysmaps";');

	$form->addItem($table);
	$form->show();
}
/*
 * Plain text
 */
elseif ($srctbl == 'plain_text') {
	$table = new CTableInfo(_('No items defined.'));
	$table->setHeader(array(
		$hostid > 0 ? null : _('Host'),
		_('Name'),
		_('Key'),
		_('Type'),
		_('Type of information'),
		_('Status')
	));

	$options = array(
		'nodeids' => $nodeid,
		'hostids' => $hostid,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'templated' => 0,
		'filter' => array(
			'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED),
			'status' => ITEM_STATUS_ACTIVE
		),
		'sortfield' => 'name'
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	if (!is_null($templated)) {
		$options['templated'] = $templated;
	}
	$items = API::Item()->get($options);

	foreach ($items as $item) {
		$host = reset($item['hosts']);
		$item['host'] = $host['name'];
		$item['name'] = itemName($item);
		$description = new CSpan($item['name'], 'link');
		$item['name'] = $item['host'].': '.$item['name'];

		$action = get_window_opener($dstfrm, $dstfld1, $item[$srcfld1]).get_window_opener($dstfrm, $dstfld2, $item[$srcfld2]);
		$description->setAttribute('onclick', $action.' close_window(); return false;');

		$table->addRow(array(
			$hostid > 0 ? null : $item['host'],
			$description,
			$item['key_'],
			item_type2str($item['type']),
			item_value_type2str($item['value_type']),
			new CSpan(item_status2str($item['status']), item_status2style($item['status']))
		));
	}
	$table->show();
}
/*
 * Slides
 */
elseif ($srctbl == 'slides') {
	require_once dirname(__FILE__).'/include/screens.inc.php';

	$form = new CForm();
	$form->setName('slideform');
	$form->setAttribute('id', 'slides');

	$table = new CTableInfo(_('No slides defined.'));

	if ($multiselect) {
		$header = array(array(new CCheckBox('all_slides', null, "javascript: checkAll('".$form->getName()."', 'all_slides', 'slides');"), _('Name')),);
	}
	else {
		$header = array(_('Name'));
	}

	$table->setHeader($header);

	$slideshows = array();
	$result = DBselect('SELECT s.slideshowid,s.name FROM slideshows s WHERE '.DBin_node('s.slideshowid', $nodeid).' ORDER BY s.name');
	while ($row = DBfetch($result)) {
		if (!slideshow_accessible($row['slideshowid'], PERM_READ_ONLY)) {
			continue;
		}
		$slideshows[$row['slideshowid']] = $row;

		$name = new CLink($row['name'], '#');
		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row['slideshowid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $row[$srcfld1],
				$dstfld2 => $row[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$name = new CCol(array(new CCheckBox('slides['.zbx_jsValue($row[$srcfld1]).']', null, null, $row['slideshowid']), $name));
		}
		$table->addRow($name);
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('slides', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($slideshows, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "slides";');

	$form->addItem($table);
	$form->show();
}
/*
 * Screens
 */
elseif ($srctbl == 'screens') {
	require_once dirname(__FILE__).'/include/screens.inc.php';

	$form = new CForm();
	$form->setName('screenform');
	$form->setAttribute('id', 'screens');

	$table = new CTableInfo(_('No screens defined.'));

	if ($multiselect) {
		$header = array(
			array(new CCheckBox('all_screens', null, "javascript: checkAll('".$form->getName()."', 'all_screens', 'screens');"), _('Name')),
		);
	}
	else {
		$header = array(_('Name'));
	}
	$table->setHeader($header);

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	$screens = API::Screen()->get($options);
	order_result($screens, 'name');

	foreach ($screens as $row) {
		$name = new CSpan($row['name'], 'link');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row['screenid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $row[$srcfld1],
				$dstfld2 => $row[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$name = new CCol(array(new CCheckBox('screens['.zbx_jsValue($row[$srcfld1]).']', null, null, $row['screenid']), $name));
		}
		$table->addRow($name);
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('screens', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));

		insert_js('var popupReference = '.zbx_jsvalue($screens, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "screens";');

	$form->addItem($table);
	$form->show();
}
/*
 * Screens 2
 */
elseif ($srctbl == 'screens2') {
	require_once dirname(__FILE__).'/include/screens.inc.php';

	$table = new CTableInfo(_('No screens defined.'));
	$table->setHeader(_('Name'));

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND
	);
	$screens = API::Screen()->get($options);
	order_result($screens, 'name');

	foreach ($screens as $row) {
		$row['node_name'] = get_node_name_by_elid($row['screenid'], true);

		if (check_screen_recursion($_REQUEST['screenid'], $row['screenid'])) {
			continue;
		}
		$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

		$name = new CLink($row['name'], '#');
		$row['name'] = $row['node_name'].$row['name'];

		$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Overview
 */
elseif ($srctbl == 'overview') {
	$table = new CTableInfo(_('No host groups defined.'));
	$table->setHeader(_('Name'));

	$options = array(
		'nodeids' => $nodeid,
		'monitored_hosts' => true,
		'output' => API_OUTPUT_EXTEND
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$hostGroups = API::HostGroup()->get($options);
	order_result($hostGroups, 'name');

	foreach ($hostGroups as $hostGroup) {
		$hostGroup['node_name'] = get_node_name_by_elid($hostGroup['groupid']);
		$name = new CSpan($hostGroup['name'], 'link');

		$hostGroup['node_name'] = isset($hostGroup['node_name']) ? '('.$hostGroup['node_name'].') ' : '';
		$hostGroup['name'] = $hostGroup['node_name'].$hostGroup['name'];

		$action = get_window_opener($dstfrm, $dstfld1, $hostGroup[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $hostGroup[$srcfld2]) : '');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Host group screen
 */
elseif ($srctbl == 'host_group_scr') {
	$table = new CTableInfo(_('No host groups defined.'));
	$table->setHeader(array(_('Name')));

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$hostGroups = API::HostGroup()->get($options);
	order_result($hostGroups, 'name');

	$all = false;
	foreach ($hostGroups as $hostGroup) {
		$hostGroup['node_name'] = get_node_name_by_elid($hostGroup['groupid']);

		if (!$all) {
			$action = get_window_opener($dstfrm, $dstfld1, create_id_by_nodeid(0, $nodeid)).get_window_opener($dstfrm, $dstfld2, $hostGroup['node_name']._('- all groups -'));
			$name = new CLink(bold(_('- all groups -')), '#');
			$name->setAttribute('onclick', $action.' close_window(); return false;');
			$table->addRow($name);
			$all = true;
		}
		$name = new CLink($hostGroup['name'], '#');
		$hostGroup['name'] = $hostGroup['node_name'].$hostGroup['name'];

		$name->setAttribute('onclick',
			get_window_opener($dstfrm, $dstfld1, $hostGroup[$srcfld1]).
			get_window_opener($dstfrm, $dstfld2, $hostGroup[$srcfld2]).
			' return close_window();'
		);
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Discovery rules
 */
elseif ($srctbl == 'drules') {
	$table = new CTableInfo(_('No discovery rules defined.'));
	$table->setHeader(_('Name'));

	$result = DBselect('SELECT DISTINCT d.* FROM drules d WHERE '.DBin_node('d.druleid', $nodeid));
	while ($row = DBfetch($result)) {
		$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name = new CSpan($row['name'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Discovery checks
 */
elseif ($srctbl == 'dchecks') {
	$table = new CTableInfo(_('No discovery checks defined.'));
	$table->setHeader(_('Name'));

	$dRules = API::DRule()->get(array(
		'selectDChecks' => array('dcheckid', 'type', 'key_', 'ports'),
		'output' => API_OUTPUT_EXTEND
	));
	foreach ($dRules as $dRule) {
		foreach ($dRule['dchecks'] as $dCheck) {
			$name = $dRule['name'].':'.discovery_check2str($dCheck['type'], $dCheck['key_'], $dCheck['ports']);
			$action = get_window_opener($dstfrm, $dstfld1, $dCheck[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $name) : '');
			$name = new CSpan($name, 'link');
			$name->setAttribute('onclick', $action.' close_window(); return false;');
			$table->addRow($name);
		}
	}
	$table->show();
}
/*
 * Proxies
 */
elseif ($srctbl == 'proxies') {
	$table = new CTableInfo(_('No proxies defined.'));
	$table->setHeader(_('Name'));

	$result = DBselect(
		'SELECT DISTINCT h.hostid,h.host'.
		' FROM hosts h'.
		' WHERE '.DBin_node('h.hostid', $nodeid).
			' AND h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
		' ORDER BY h.host,h.hostid'
	);
	while ($row = DBfetch($result)) {
		$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name = new CSpan($row['host'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Scripts
 */
elseif ($srctbl == 'scripts') {
	$form = new CForm();
	$form->setName('scriptform');
	$form->attr('id', 'scripts');

	$table = new CTableInfo(_('No scripts defined.'));

	if ($multiselect) {
		$header = array(
			array(new CCheckBox('all_scripts', null, "javascript: checkAll('".$form->getName()."', 'all_scripts', 'scripts');"), _('Name')),
			_('Execute on'),
			_('Commands')
		);
	}
	else {
		$header = array(
			_('Name'),
			_('Execute on'),
			_('Commands')
		);
	}
	$table->setHeader($header);

	$options = array(
		'nodeids' => $nodeid,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	if (is_null($hostid)) {
		$options['groupids'] = $groupid;
	}
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$scripts = API::Script()->get($options);
	order_result($scripts, 'name');

	foreach ($scripts as $script) {
		$description = new CLink($script['name'], '#');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($script['scriptid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $script[$srcfld1],
				$dstfld2 => $script[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$description->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$description = new CCol(array(new CCheckBox('scripts['.zbx_jsValue($script[$srcfld1]).']', null, null, $script['scriptid']), $description));
		}

		if ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
			switch ($script['execute_on']) {
				case ZBX_SCRIPT_EXECUTE_ON_AGENT:
					$scriptExecuteOn = _('Agent');
					break;
				case ZBX_SCRIPT_EXECUTE_ON_SERVER:
					$scriptExecuteOn = _('Server');
					break;
			}
		}
		else {
			$scriptExecuteOn = '';
		}
		$table->addRow(array(
			$description,
			$scriptExecuteOn,
			zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
		));
	}

	if ($multiselect) {
		$button = new CButton('select', _('Select'), "javascript: addSelectedValues('scripts', ".zbx_jsvalue($reference).');');
		$table->setFooter(new CCol($button, 'right'));
		insert_js('var popupReference = '.zbx_jsvalue($scripts, true).';');
	}
	zbx_add_post_js('chkbxRange.pageGoName = "scripts";');

	$form->addItem($table);
	$form->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
