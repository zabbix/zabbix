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
require_once dirname(__FILE__).'/include/nodes.inc.php';

$page['title'] = _('Configuration of nodes');
$page['file'] = 'nodes.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'nodeid' =>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
	'new_nodeid' =>		array(T_ZBX_STR, O_OPT, null,	DB_ID.NOT_ZERO,	'isset({save})', _('ID')),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({save})'),
	'ip' =>				array(T_ZBX_IP,	 O_OPT, null,	null,			'isset({save})'),
	'nodetype' =>		array(T_ZBX_INT, O_OPT, null,	IN(ZBX_NODE_CHILD.','.ZBX_NODE_MASTER.','.ZBX_NODE_LOCAL),
		'isset({save})&&!isset({nodeid})'),
	'masterid' => 		array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'port' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 65535), 'isset({save})', _('Port')),
	// actions
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,	null)
);
check_fields($fields);

/*
 * Permissions
 */
$available_nodes = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ);
if (count($available_nodes) == 0) {
	access_deny();
}
$node = null;
if (get_request('nodeid')) {
	$node = get_node_by_nodeid($_REQUEST['nodeid']);
	if (!$node) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	DBstart();

	if (hasRequest('nodeid')) {
		$nodeid = getRequest('nodeid');

		$result = update_node($nodeid, get_request('name'), get_request('ip'), get_request('port'));

		$messageSuccess = _('Node updated');
		$messageFailed = _('Cannot update node');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$nodeid = add_node(get_request('new_nodeid'), get_request('name'), get_request('ip'), get_request('port'), get_request('nodetype'), get_request('masterid'));

		$messageSuccess = _('Node added');
		$messageFailed = _('Cannot add node');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_NODE, 'Node ['.$_REQUEST['name'].'] id ['.$nodeid.']');
		unset($_REQUEST['form']);
	}

	$result = DBend($result);
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete'])) {
	DBstart();

	$result = delete_node($_REQUEST['nodeid']);

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_NODE, 'Node ['.$node['name'].'] id ['.$node['nodeid'].']');
		unset($_REQUEST['form'], $node);
	}

	$result = DBend($result);
	show_messages($result, _('Node deleted'), _('Cannot delete node'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'nodeid' => get_request('nodeid'),
		'masterNode' => DBfetch(DBselect('SELECT n.name FROM nodes n WHERE n.masterid IS NULL AND n.nodetype='.ZBX_NODE_MASTER))
	);
	if (get_request('nodeid') && !isset($_REQUEST['form_refresh'])) {
		$data['new_nodeid'] = $node['nodeid'];
		$data['name'] = $node['name'];
		$data['ip'] = $node['ip'];
		$data['port'] = $node['port'];
		$data['masterid'] = $node['masterid'];
		$data['nodetype'] = $node['nodetype'];
	}
	else {
		$data['new_nodeid'] = get_request('new_nodeid');
		$data['name'] = get_request('name', '');
		$data['ip'] = get_request('ip', '127.0.0.1');
		$data['port'] = get_request('port', 10051);
		$data['nodetype'] = get_request('nodetype', ZBX_NODE_CHILD);
		$data['masterid'] = get_request('masterid', get_current_nodeid(false));
	}

	$nodeView = new CView('administration.node.edit', $data);
}
else {
	validate_sort_and_sortorder();

	$data = array();
	if (ZBX_DISTRIBUTED) {
		$data['nodes'] = DBselect('SELECT n.* FROM nodes n '.order_by('n.nodeid,n.name,n.ip', 'n.masterid'));
	}

	$nodeView = new CView('administration.node.list', $data);
}

$nodeView->render();
$nodeView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
