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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';

$srctbl = get_request('srctbl', ''); // source table name

// set page title
switch ($srctbl) {
	case 'proxies':
		$page['title'] = _('Proxies');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
	case 'applications':
		$page['title'] = _('Applications');
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
	case 'sysmaps':
		$page['title'] = _('Maps');
		$min_user_type = USER_TYPE_ZABBIX_USER;
		break;
	case 'screens2':
		$page['title'] = _('Screens');
		$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
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

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

if (isset($error)) {
	invalid_url();
}
if ($min_user_type > CWebUser::$data['type']) {
	access_deny();
}

/*
 * Fields
 */
// allowed 'srcfld*' parameter values for each 'srctbl' value
$allowedSrcFields = array(
	'users'					=> '"usergrpid", "alias", "fullname", "userid"',
	'triggers'				=> '"description", "triggerid", "expression"',
	'items'					=> '"itemid", "name"',
	'prototypes'			=> '"itemid", "name", "flags"',
	'graphs'				=> '"graphid", "name"',
	'sysmaps'				=> '"sysmapid", "name"',
	'slides'				=> '"slideshowid"',
	'help_items'			=> '"key"',
	'screens'				=> '"screenid"',
	'screens2'				=> '"screenid", "name"',
	'drules'				=> '"druleid", "name"',
	'dchecks'				=> '"dcheckid", "name"',
	'proxies'				=> '"hostid", "host"',
	'usrgrp'				=> '"usrgrpid", "name"',
	'applications'			=> '"name"',
	'scripts'				=> '"scriptid", "name"'
);

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>						array(T_ZBX_STR, O_OPT, P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
	'dstfld1' =>					array(T_ZBX_STR, O_OPT, P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
	'srctbl' =>						array(T_ZBX_STR, O_MAND, P_SYS,	NOT_EMPTY,	null),
	'srcfld1' =>					array(T_ZBX_STR, O_MAND, P_SYS,	IN($allowedSrcFields[$_REQUEST['srctbl']]), null),
	'groupid' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'group' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	'hostid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'host' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	'parent_discoveryid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'screenid' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'templates' =>					array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'host_templates' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'existed_templates' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'multiselect' =>				array(T_ZBX_INT, O_OPT, null,	null,		null),
	'submit' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	'excludeids' =>					array(T_ZBX_STR, O_OPT, null,	null,		null),
	'only_hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'monitored_hosts' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'templated_hosts' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'real_hosts' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'normal_only' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_applications' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_graphs' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_items' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_simple_graph_items' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_triggers' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'with_monitored_triggers' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'itemtype' =>					array(T_ZBX_INT, O_OPT, null,	null,		null),
	'value_types' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 15), null),
	'numeric' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'reference' =>					array(T_ZBX_STR, O_OPT, null,	null,		null),
	'writeonly' =>					array(T_ZBX_STR, O_OPT, null,	null,		null),
	'noempty' =>					array(T_ZBX_STR, O_OPT, null,	null,		null),
	'select' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'submitParent' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null)
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

// validate permissions
if (get_request('only_hostid')) {
	if (!API::Host()->isReadable(array($_REQUEST['only_hostid']))) {
		access_deny();
	}
}
else {
	if (get_request('hostid') && !API::Host()->isReadable(array($_REQUEST['hostid'])) ||
			get_request('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
		access_deny();
	}
}
if (get_request('parent_discoveryid') && !API::DiscoveryRule()->isReadable(array($_REQUEST['parent_discoveryid']))) {
	access_deny();
}

$dstfrm = get_request('dstfrm', ''); // destination form
$dstfld1 = get_request('dstfld1', ''); // output field on destination form
$dstfld2 = get_request('dstfld2', ''); // second output field on destination form
$dstfld3 = get_request('dstfld3', ''); // third output field on destination form
$srcfld1 = get_request('srcfld1', ''); // source table field [can be different from fields of source table]
$srcfld2 = get_request('srcfld2', null); // second source table field [can be different from fields of source table]
$srcfld3 = get_request('srcfld3', null); //  source table field [can be different from fields of source table]
$multiselect = get_request('multiselect', 0); // if create popup with checkboxes
$dstact = get_request('dstact', '');
$writeonly = get_request('writeonly');
$withApplications = get_request('with_applications', 0);
$withGraphs = get_request('with_graphs', 0);
$withItems = get_request('with_items', 0);
$noempty = get_request('noempty'); // display/hide "Empty" button
$existedTemplates = get_request('existed_templates', null);
$excludeids = get_request('excludeids', null);
$reference = get_request('reference', get_request('srcfld1', 'unknown'));
$realHosts = get_request('real_hosts', 0);
$monitoredHosts = get_request('monitored_hosts', 0);
$templatedHosts = get_request('templated_hosts', 0);
$withSimpleGraphItems = get_request('with_simple_graph_items', 0);
$withTriggers = get_request('with_triggers', 0);
$withMonitoredTriggers = get_request('with_monitored_triggers', 0);
$submitParent = get_request('submitParent', 0);
$normalOnly = get_request('normal_only');
$group = get_request('group', '');
$host = get_request('host', '');
$onlyHostid = get_request('only_hostid', null);

if (isset($onlyHostid)) {
	$_REQUEST['hostid'] = $onlyHostid;

	unset($_REQUEST['groupid']);
}

// value types
$value_types = null;
if (get_request('value_types')) {
	$value_types = get_request('value_types');
}
elseif (get_request('numeric')) {
	$value_types = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
}

clearCookies(true);

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
	'groups' => array(),
	'hosts' => array(),
	'groupid' => get_request('groupid', null),
	'hostid' => get_request('hostid', null)
);

if (!is_null($writeonly)) {
	$options['groups']['editable'] = true;
	$options['hosts']['editable'] = true;
}

$host_status = null;
$templated = null;

if ($monitoredHosts) {
	$options['groups']['monitored_hosts'] = true;
	$options['hosts']['monitored_hosts'] = true;
	$host_status = 'monitored_hosts';
}
elseif ($realHosts) {
	$options['groups']['real_hosts'] = true;
	$templated = 0;
}
elseif ($templatedHosts) {
	$options['hosts']['templated_hosts'] = true;
	$options['groups']['templated_hosts'] = true;
	$templated = 1;
	$host_status = 'templated_hosts';
}
else {
	$options['groups']['with_hosts_and_templates'] = true;
	$options['hosts']['templated_hosts'] = true; // for hosts templated_hosts comes with monitored and not monitored hosts
}

if ($withApplications) {
	$options['groups']['with_applications'] = true;
	$options['hosts']['with_applications'] = true;
}
elseif ($withGraphs) {
	$options['groups']['with_graphs'] = true;
	$options['hosts']['with_graphs'] = true;
}
elseif ($withSimpleGraphItems) {
	$options['groups']['with_simple_graph_items'] = true;
	$options['hosts']['with_simple_graph_items'] = true;
}
elseif ($withTriggers) {
	$options['groups']['with_triggers'] = true;
	$options['hosts']['with_triggers'] = true;
}
elseif ($withMonitoredTriggers) {
	$options['groups']['with_monitored_triggers'] = true;
	$options['hosts']['with_monitored_triggers'] = true;
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
if (isset($onlyHostid)) {
	$hostid = $onlyHostid;
}

/*
 * Display table header
 */
$frmTitle = new CForm();
if ($monitoredHosts) {
	$frmTitle->addVar('monitored_hosts', 1);
}
if ($realHosts) {
	$frmTitle->addVar('real_hosts', 1);
}
if ($templatedHosts) {
	$frmTitle->addVar('templated_hosts', 1);
}
if ($withApplications) {
	$frmTitle->addVar('with_applications', 1);
}
if ($withGraphs) {
	$frmTitle->addVar('with_graphs', 1);
}
if ($withItems) {
	$frmTitle->addVar('with_items', 1);
}
if ($withSimpleGraphItems) {
	$frmTitle->addVar('with_simple_graph_items', 1);
}
if ($withTriggers) {
	$frmTitle->addVar('with_triggers', 1);
}
if ($withMonitoredTriggers) {
	$frmTitle->addVar('with_monitored_triggers', 1);
}
if ($value_types) {
	$frmTitle->addVar('value_types', $value_types);
}
if ($normalOnly) {
	$frmTitle->addVar('normal_only', $normalOnly);
}
if (!is_null($existedTemplates)) {
	$frmTitle->addVar('existed_templates', $existedTemplates);
}
if (!is_null($excludeids)) {
	$frmTitle->addVar('excludeids', $excludeids);
}
if (isset($onlyHostid)) {
	$frmTitle->addVar('only_hostid', $onlyHostid);
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

/*
 * Only host id
 */
if (isset($onlyHostid)) {
	$only_hosts = API::Host()->get(array(
		'hostids' => $hostid,
		'templated_hosts' => true,
		'output' => array('hostid', 'host'),
		'limit' => 1
	));
	$host = reset($only_hosts);

	$cmbHosts = new CComboBox('hostid', $hostid);
	$cmbHosts->addItem($hostid, $host['host']);
	$cmbHosts->setEnabled('disabled');
	$cmbHosts->setAttribute('title', _('You can not switch hosts for current selection.'));
	$frmTitle->addItem(array(SPACE, _('Host'), SPACE, $cmbHosts));
}
else {
	if (str_in_array($srctbl, array('triggers', 'items', 'applications', 'graphs'))) {
		$frmTitle->addItem(array(_('Group'), SPACE, $pageFilter->getGroupsCB()));
	}
	if (str_in_array($srctbl, array('help_items'))) {
		$itemtype = get_request('itemtype', 0);
		$cmbTypes = new CComboBox('itemtype', $itemtype, 'javascript: submit();');

		foreach ($allowed_item_types as $type) {
			$cmbTypes->addItem($type, item_type2str($type));
		}
		$frmTitle->addItem(array(_('Type'), SPACE, $cmbTypes));
	}
	if (str_in_array($srctbl, array('triggers', 'items', 'applications', 'graphs'))) {
		$frmTitle->addItem(array(SPACE, _('Host'), SPACE, $pageFilter->getHostsCB()));
	}
}

if (str_in_array($srctbl, array('applications', 'triggers'))) {
	if (zbx_empty($noempty)) {
		$value1 = isset($_REQUEST['dstfld1']) && strpos($_REQUEST['dstfld1'], 'id') !== false ? 0 : '';
		$value2 = isset($_REQUEST['dstfld2']) && strpos($_REQUEST['dstfld2'], 'id') !== false ? 0 : '';
		$value3 = isset($_REQUEST['dstfld3']) && strpos($_REQUEST['dstfld3'], 'id') !== false ? 0 : '';

		$epmtyScript = get_window_opener($dstfrm, $dstfld1, $value1);
		$epmtyScript .= get_window_opener($dstfrm, $dstfld2, $value2);
		$epmtyScript .= get_window_opener($dstfrm, $dstfld3, $value3);
		$epmtyScript .= ' close_window(); return false;';

		$frmTitle->addItem(array(SPACE, new CButton('empty', _('Empty'), $epmtyScript)));
	}
}

show_table_header($page['title'], $frmTitle);

insert_js_function('addSelectedValues');
insert_js_function('addValues');
insert_js_function('addValue');

/*
 * User group
 */
if ($srctbl == 'usrgrp') {
	$form = new CForm();
	$form->setName('usrgrpform');
	$form->setAttribute('id', 'usrgrps');

	$table = new CTableInfo(_('No user groups found.'));
	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_usrgrps', null, "javascript: checkAll('".$form->getName()."', 'all_usrgrps', 'usrgrps');") : null,
		_('Name')
	));

	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$userGroups = API::UserGroup()->get($options);
	order_result($userGroups, 'name');

	foreach ($userGroups as $userGroup) {
		$name = new CSpan($userGroup['name'], 'link');
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

	$table = new CTableInfo(_('No users found.'));
	$table->setHeader(array(
		($multiselect ? new CCheckBox('all_users', null, "javascript: checkAll('".$form->getName()."', 'all_users', 'users');") : null),
		_('Alias'),
		_('Name'),
		_('Surname')
	));

	$options = array(
		'output' => array('alias', 'name', 'surname', 'type', 'theme', 'lang'),
		'preservekeys' => true
	);
	if (!is_null($writeonly)) {
		$options['editable'] = true;
	}
	$users = API::User()->get($options);
	order_result($users, 'alias');

	foreach ($users as &$user) {
		$alias = new CSpan($user['alias'], 'link');
		$alias->attr('id', 'spanid'.$user['userid']);

		if (isset($srcfld2) && $srcfld2 == 'fullname') {
			$user[$srcfld2] = getUserFullname($user);
		}

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($user['userid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $user[$srcfld1]
			);
			if (isset($srcfld2)) {
				$values[$dstfld2] = $user[$srcfld2];
			}
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
	unset($user);

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
	$table = new CTableInfo(_('No item keys found.'));
	$table->setHeader(array(_('Key'), _('Name')));

	$helpItems = new CHelpItems();
	foreach ($helpItems->getByType($itemtype) as $helpItem) {
		$action = get_window_opener($dstfrm, $dstfld1, $helpItem[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
		$name = new CSpan($helpItem['key'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow(array($name, $helpItem['description']));
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

	$table = new CTableInfo(_('No triggers found.'));

	$table->setHeader(array(
		$multiselect ? new CCheckBox('all_triggers', null, "checkAll('".$form->getName()."', 'all_triggers', 'triggers');") : null,
		_('Name'),
		_('Severity'),
		_('Status')
	));

	$options = array(
		'hostids' => $hostid,
		'output' => array('triggerid', 'description', 'expression', 'priority', 'status', 'state'),
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
	if ($withMonitoredTriggers) {
		$options['monitored'] = true;
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
		$trigger['description'] = $trigger['hostname'].NAME_DELIMITER.$trigger['description'];

		if ($multiselect) {
			$js_action = 'addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($trigger['triggerid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $trigger[$srcfld1],
				$dstfld2 => $trigger[$srcfld2]
			);
			if (isset($srcfld3)) {
				$values[$dstfld3] = $trigger[$srcfld3];
			}
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

			foreach ($trigger['dependencies'] as $dependentTrigger) {
				$description[] = array(CMacrosResolverHelper::resolveTriggerName($dependentTrigger), BR());
			}
		}

		$table->addRow(array(
			$multiselect ? new CCheckBox('triggers['.zbx_jsValue($trigger[$srcfld1]).']', null, null, $trigger['triggerid']) : null,
			$description,
			getSeverityCell($trigger['priority']),
			new CSpan(
				triggerIndicator($trigger['status'], $trigger['state']),
				triggerIndicatorStyle($trigger['status'], $trigger['state'])
			)
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

	$table = new CTableInfo(_('No items found.'));

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
		'hostids' => $hostid,
		'webitems' => true,
		'output' => array('itemid', 'hostid', 'name', 'key_', 'type', 'value_type', 'status', 'state'),
		'selectHosts' => array('hostid', 'name')
	);
	if (!is_null($normalOnly)) {
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

	$items = CMacrosResolverHelper::resolveItemNames($items);

	order_result($items, 'name_expanded');

	if ($multiselect) {
		$jsItems = array();
	}

	foreach ($items as $item) {
		$host = reset($item['hosts']);
		$item['hostname'] = $host['name'];

		$description = new CLink($item['name_expanded'], '#');
		$item['name'] = $item['hostname'].NAME_DELIMITER.$item['name_expanded'];

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
			($hostid > 0) ? null : $item['hostname'],
			$multiselect ? new CCheckBox('items['.zbx_jsValue($item[$srcfld1]).']', null, null, $item['itemid']) : null,
			$description,
			$item['key_'],
			item_type2str($item['type']),
			itemValueTypeString($item['value_type']),
			new CSpan(itemIndicator($item['status'], $item['state']), itemIndicatorStyle($item['status'], $item['state']))
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

	$table = new CTableInfo(_('No item prototypes found.'));

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

	$options = array(
		'selectHosts' => array('name'),
		'discoveryids' => get_request('parent_discoveryid'),
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	);
	if (!is_null($value_types)) {
		$options['filter']['value_type'] = $value_types;
	}

	$items = API::ItemPrototype()->get($options);

	$items = CMacrosResolverHelper::resolveItemNames($items);

	order_result($items, 'name_expanded');

	foreach ($items as &$item) {
		$host = reset($item['hosts']);

		$description = new CSpan($item['name_expanded'], 'link');
		$item['name'] = $host['name'].NAME_DELIMITER.$item['name_expanded'];

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
			itemValueTypeString($item['value_type']),
			new CSpan(itemIndicator($item['status']), itemIndicatorStyle($item['status']))
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
	$table = new CTableInfo(_('No applications found.'));
	$table->setHeader(array(
		$hostid > 0 ? null : _('Host'),
		_('Name')
	));

	$options = array(
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
 * Graphs
 */
elseif ($srctbl == 'graphs') {
	$form = new CForm();
	$form->setName('graphform');
	$form->setAttribute('id', 'graphs');

	$table = new CTableInfo(_('No graphs found.'));

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
		$options = array(
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
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
		$description = new CSpan($graph['name'], 'link');
		$graph['name'] = $graph['hostname'].NAME_DELIMITER.$graph['name'];

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
 * Sysmaps
 */
elseif ($srctbl == 'sysmaps') {
	$form = new CForm();
	$form->setName('sysmapform');
	$form->setAttribute('id', 'sysmaps');

	$table = new CTableInfo(_('No maps found.'));

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
 * Slides
 */
elseif ($srctbl == 'slides') {
	require_once dirname(__FILE__).'/include/screens.inc.php';

	$form = new CForm();
	$form->setName('slideform');
	$form->setAttribute('id', 'slides');

	$table = new CTableInfo(_('No slides found.'));

	if ($multiselect) {
		$header = array(array(new CCheckBox('all_slides', null, "javascript: checkAll('".$form->getName()."', 'all_slides', 'slides');"), _('Name')),);
	}
	else {
		$header = array(_('Name'));
	}

	$table->setHeader($header);

	$slideshows = array();

	$dbSlideshows = DBfetchArray(DBselect('SELECT s.slideshowid,s.name FROM slideshows s'));

	order_result($dbSlideshows, 'name');

	foreach ($dbSlideshows as $dbSlideshow) {
		if (!slideshow_accessible($dbSlideshow['slideshowid'], PERM_READ)) {
			continue;
		}
		$slideshows[$dbSlideshow['slideshowid']] = $dbSlideshow;

		$name = new CLink($dbSlideshow['name'], '#');
		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($dbSlideshow['slideshowid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $dbSlideshow[$srcfld1],
				$dstfld2 => $dbSlideshow[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$name = new CCol(array(new CCheckBox('slides['.zbx_jsValue($dbSlideshow[$srcfld1]).']', null, null, $dbSlideshow['slideshowid']), $name));
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

	$table = new CTableInfo(_('No screens found.'));

	if ($multiselect) {
		$header = array(
			array(new CCheckBox('all_screens', null, "javascript: checkAll('".$form->getName()."', 'all_screens', 'screens');"), _('Name')),
		);
	}
	else {
		$header = array(_('Name'));
	}
	$table->setHeader($header);

	$screens = API::Screen()->get(array(
		'output' => array('screenid', 'name'),
		'preservekeys' => true,
		'editable' => ($writeonly === null) ? null: true
	));
	order_result($screens, 'name');

	foreach ($screens as $screen) {
		$name = new CSpan($screen['name'], 'link');

		if ($multiselect) {
			$js_action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($screen['screenid']).');';
		}
		else {
			$values = array(
				$dstfld1 => $screen[$srcfld1],
				$dstfld2 => $screen[$srcfld2]
			);
			$js_action = 'javascript: addValues('.zbx_jsvalue($dstfrm).', '.zbx_jsvalue($values).'); close_window(); return false;';
		}
		$name->setAttribute('onclick', $js_action.' jQuery(this).removeAttr("onclick");');

		if ($multiselect) {
			$name = new CCol(array(new CCheckBox('screens['.zbx_jsValue($screen[$srcfld1]).']', null, null, $screen['screenid']), $name));
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

	$table = new CTableInfo(_('No screens found.'));
	$table->setHeader(_('Name'));

	$screens = API::Screen()->get(array(
		'output' => array('screenid', 'name'),
		'editable' => ($writeonly === null) ? null: true
	));
	order_result($screens, 'name');

	foreach ($screens as $screen) {
		if (check_screen_recursion($_REQUEST['screenid'], $screen['screenid'])) {
			continue;
		}

		$name = new CLink($screen['name'], '#');

		$action = get_window_opener($dstfrm, $dstfld1, $screen[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $screen[$srcfld2]) : '');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Discovery rules
 */
elseif ($srctbl === 'drules') {
	$table = new CTableInfo(_('No discovery rules found.'));
	$table->setHeader(_('Name'));

	$dRules = API::DRule()->get(array(
		'output' => array('druleid', 'name')
	));

	order_result($dRules, 'name');

	foreach ($dRules as $dRule) {
		$action = get_window_opener($dstfrm, $dstfld1, $dRule[$srcfld1]).(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $dRule[$srcfld2]) : '');
		$name = new CSpan($dRule['name'], 'link');
		$name->setAttribute('onclick', $action.' close_window(); return false;');
		$table->addRow($name);
	}
	$table->show();
}
/*
 * Discovery checks
 */
elseif ($srctbl === 'dchecks') {
	$table = new CTableInfo(_('No discovery rules found.'));
	$table->setHeader(_('Name'));

	$dRules = API::DRule()->get(array(
		'selectDChecks' => array('dcheckid', 'type', 'key_', 'ports'),
		'output' => array('druleid', 'name')
	));

	order_result($dRules, 'name');

	foreach ($dRules as $dRule) {
		foreach ($dRule['dchecks'] as $dCheck) {
			$name = $dRule['name'].NAME_DELIMITER.discovery_check2str($dCheck['type'], $dCheck['key_'], $dCheck['ports']);
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
	$table = new CTableInfo(_('No proxies found.'));
	$table->setHeader(_('Name'));

	$result = DBselect(
		'SELECT h.hostid,h.host'.
		' FROM hosts h'.
		' WHERE h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
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

	$table = new CTableInfo(_('No scripts found.'));

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
