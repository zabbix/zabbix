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

$page['title'] = _('Configuration of host prototypes');
$page['file'] = 'host_prototypes.php';
$page['scripts'] = array('effects.js', 'class.cviewswitcher.js');

//???
$page['hist_arg'] = array('parent_discoveryid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hostid' =>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'parent_hostid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'parent_discoveryid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null),
	'host' =>		        array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Host name')),
	'name' =>	            array(T_ZBX_STR, O_OPT, null,		null,		'isset({save})'),
	'status' =>		        array(T_ZBX_INT, O_OPT, null,		        IN(array(HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED)), 'isset({save})'),
	'templates' =>		    array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null),
	'group_hostid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

// permissions
if (get_request('parent_discoveryid')) {
	$discoveryRule = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule) {
		access_deny();
	}

	$hostPrototype = API::HostPrototype()->get(array(
		'hostids' => get_request('hostid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	$hostPrototype = reset($hostPrototype);
	if (!$hostPrototype) {
		access_deny();
	}
}
else {
	access_deny();
}

/*
 * Actions
 */
if (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {

	$result = API::HostPrototype()->delete(get_request('hostid'));

	show_messages($result, _('Host prototype deleted'), _('Cannot delete host prototypes'));

	unset($_REQUEST['hostid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$newHostPrototype = array(
		'host' => get_request('host'),
		'name' => get_request('name'),
		'status' => get_request('status'),
		'templates' => get_request('templates'),
		'status' => get_request('status')
	);

	if (get_request('hostid')) {
		$newHostPrototype['hostid'] = get_request('hostid');
		$newHostPrototype = CArrayHelper::unsetEqualValues($newHostPrototype, $hostPrototype, array('hostid'));
		$result = API::HostPrototype()->update($newHostPrototype);

		show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
	}
	else {
		$newHostPrototype['ruleid'] = get_request('parent_discoveryid');
		$result = API::HostPrototype()->create($newHostPrototype);

		show_messages($result, _('Host prototype added'), _('Cannot add host prototype'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
	}
}
// GO
elseif (($_REQUEST['go'] == 'activate' || $_REQUEST['go'] == 'disable') && isset($_REQUEST['group_hostid'])) {
	$update = array();
	foreach ((array) get_request('group_hostid') as $hostPrototypeId) {
		$update[] = array(
			'hostid' => $hostPrototypeId,
			'status' => (get_request('go') == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED
		);
	}

	$go_result = API::HostPrototype()->update($update);

	show_messages($go_result, ($_REQUEST['go'] == 'activate') ? _('Host prototypes activated') : _('Host prototypes disabled'), null);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_hostid'])) {
	$go_result = API::HostPrototype()->delete($_REQUEST['group_hostid']);
	show_messages($go_result, _('Host prototypes deleted'), _('Cannot delete host prototypes'));
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
	$data = array(
		'parent_hostid' => get_request('parent_hostid', null),
		'discovery_rule' => $discoveryRule,
		'host_prototype' => array(
			'hostid' => get_request('hostid'),
			'host' => get_request('host'),
			'name' => get_request('name'),
			'status' => get_request('status', HOST_STATUS_MONITORED),
			'templates' => get_request('templates', array())
		)
	);

	if (get_request('hostid')) {
		$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);
	}

	// render view
	$itemView = new CView('configuration.host.prototype.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$data = array(
		'form' => get_request('form', null),
		'parent_discoveryid' => get_request('parent_discoveryid', null),
		'parent_hostid' => get_request('parent_hostid', null),
		'discovery_rule' => $discoveryRule
	);

	// get items
	$sortfield = getPageSortField('name');
	$data['hostPrototypes'] = API::HostPrototype()->get(array(
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	if ($data['hostPrototypes']) {
		order_result($data['hostPrototypes'], $sortfield, getPageSortOrder());
	}
	$data['paging'] = getPagingLine($data['hostPrototypes']);

	// render view
	$itemView = new CView('configuration.host.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
?>
