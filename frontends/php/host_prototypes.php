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
	'parent_hostid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'parent_discoveryid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null),
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
if (get_request('parent_discoveryid', false)) {
	$discoveryRule = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule) {
		access_deny();
	}
//	$_REQUEST['hostid'] = $discovery_rule['hostid'];

//	if (isset($_REQUEST['itemid'])) {
//		$itemPrototype = API::ItemPrototype()->get(array(
//			'itemids' => $_REQUEST['itemid'],
//			'output' => array('itemid'),
//			'editable' => true,
//			'preservekeys' => true
//		));
//		if (empty($itemPrototype)) {
//			access_deny();
//		}
//	}
}
else {
	access_deny();
}

/*
 * Actions
 */
if (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {


	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {

	if (isset($_REQUEST['hostid'])) {

		$item['itemid'] = $_REQUEST['itemid'];

		$result = API::Itemprototype()->update($item);

		show_messages($result, _('Item updated'), _('Cannot update item'));
	}
	else {
		$result = API::Itemprototype()->create($item);
		show_messages($result, _('Item added'), _('Cannot add item'));
	}

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
	$data = array(
		'parent_hostid' => get_request('parent_hostid', null),
		'discovery_rule' => $discoveryRule,
		'host_prototype' => array(
			'hostid' => null,
			'host' => '',
			'name' => '',
			'status' => HOST_STATUS_MONITORED,
			'templates' => array()
		)
	);

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
	$data['items'] = array();
//	$sortfield = getPageSortField('name');
//	$data['items'] = API::ItemPrototype()->get(array(
//		'discoveryids' => $data['parent_discoveryid'],
//		'output' => API_OUTPUT_EXTEND,
//		'editable' => true,
//		'selectApplications' => API_OUTPUT_EXTEND,
//		'sortfield' => $sortfield,
//		'limit' => $config['search_limit'] + 1
//	));
//
//	if (!empty($data['items'])) {
//		order_result($data['items'], $sortfield, getPageSortOrder());
//	}
	$data['paging'] = getPagingLine($data['items']);

	// render view
	$itemView = new CView('configuration.host.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
?>
