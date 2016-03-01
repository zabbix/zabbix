<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


function update_node_profile($nodeids) {
	DBstart();
	DBexecute('DELETE FROM profiles WHERE userid='.CWebUser::$data['userid'].' AND idx='.zbx_dbstr('web.nodes.selected'));

	foreach ($nodeids as $nodeid) {
		DBexecute('INSERT INTO profiles (profileid,userid,idx,value_id,type)'.
					' VALUES ('.get_dbid('profiles', 'profileid').','.CWebUser::$data['userid'].','
						.zbx_dbstr('web.nodes.selected').','.$nodeid.',4)');
	}
	DBend();
}

function get_node_profile($default = null) {
	$result = array();

	$db_profiles = DBselect(
		'SELECT p.value_id'.
		' FROM profiles p'.
		' WHERE p.userid='.CWebUser::$data['userid'].
			' AND p.idx='.zbx_dbstr('web.nodes.selected')
	);
	while ($profile = DBfetch($db_profiles)) {
		$result[] = $profile['value_id'];
	}
	return (empty($result) ? $default : $result);
}

function init_nodes() {
	// init current node id
	if (defined('ZBX_NODES_INITIALIZED')) {
		return null;
	}

	global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID, $ZBX_CURRENT_NODEID, $ZBX_CURMASTERID, $ZBX_NODES, $ZBX_NODES_IDS,
			$ZBX_AVAILABLE_NODES, $ZBX_VIEWED_NODES, $ZBX_WITH_ALL_NODES;

	$ZBX_AVAILABLE_NODES = array();
	$ZBX_NODES_IDS = array();
	$ZBX_NODES = array();
	$ZBX_CURRENT_NODEID = $ZBX_LOCALNODEID;
	$ZBX_WITH_ALL_NODES = !defined('ZBX_NOT_ALLOW_ALL_NODES');

	if (!defined('ZBX_PAGE_NO_AUTHORIZATION') && ZBX_DISTRIBUTED) {
		if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
			$sql = 'SELECT DISTINCT n.nodeid,n.name,n.masterid FROM nodes n';
		}
		else {
			$sql = 'SELECT DISTINCT n.nodeid,n.name,n.masterid'.
					' FROM nodes n,groups hg,rights r,users_groups g'.
					' WHERE r.id=hg.groupid'.
						' AND r.groupid=g.usrgrpid'.
						' AND g.userid='.CWebUser::$data['userid'].
						' AND n.nodeid='.DBid2nodeid('hg.groupid');
		}
		$db_nodes = DBselect($sql);
		while ($node = DBfetch($db_nodes)) {
			$ZBX_NODES[$node['nodeid']] = $node;
			$ZBX_NODES_IDS[$node['nodeid']] = $node['nodeid'];
		}

		$ZBX_AVAILABLE_NODES = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ_LIST, PERM_RES_IDS_ARRAY, $ZBX_NODES_IDS);
		$ZBX_VIEWED_NODES = get_viewed_nodes();
		$ZBX_CURRENT_NODEID = $ZBX_VIEWED_NODES['selected'];

		if ($node_data = DBfetch(DBselect('SELECT n.masterid FROM nodes n WHERE n.nodeid='.$ZBX_CURRENT_NODEID))) {
			$ZBX_CURMASTERID = $node_data['masterid'];
		}

		if (!isset($ZBX_NODES[$ZBX_CURRENT_NODEID])) {
			$ZBX_CURRENT_NODEID = $ZBX_LOCALNODEID;
			$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
		}

		if (isset($_REQUEST['select_nodes'])) {
			update_node_profile($ZBX_VIEWED_NODES['nodeids']);
		}

		if (isset($_REQUEST['switch_node'])) {
			CProfile::update('web.nodes.switch_node', $ZBX_VIEWED_NODES['selected'], PROFILE_TYPE_ID);
		}
	}
	else {
		$ZBX_CURRENT_NODEID = $ZBX_LOCALNODEID;
		$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
	}

	define('ZBX_NODES_INITIALIZED', 1);

	// reset profiles if node is different than local
	if ($ZBX_CURRENT_NODEID != $ZBX_LOCALNODEID) {
		CProfile::init();
	}
}

function get_current_nodeid($force_all_nodes = null, $perm = null) {
	global $ZBX_CURRENT_NODEID, $ZBX_AVAILABLE_NODES, $ZBX_VIEWED_NODES;

	if (!isset($ZBX_CURRENT_NODEID)) {
		init_nodes();
	}

	if (!is_null($perm)) {
		return get_accessible_nodes_by_user(CWebUser::$data, $perm, PERM_RES_IDS_ARRAY, $ZBX_AVAILABLE_NODES);
	}
	elseif (is_null($force_all_nodes)) {
		if ($ZBX_VIEWED_NODES['selected'] == 0) {
			$result = $ZBX_VIEWED_NODES['nodeids'];
		}
		else {
			$result = $ZBX_VIEWED_NODES['selected'];
		}

		if (empty($result)) {
			$result = CWebUser::$data['node']['nodeid'];
		}
		if (empty($result)) {
			$result = $ZBX_CURRENT_NODEID;
		}
	}
	elseif ($force_all_nodes) {
		$result = $ZBX_AVAILABLE_NODES;
	}
	else {
		$result = $ZBX_CURRENT_NODEID;
	}
	return $result;
}

function get_viewed_nodes() {
	global $ZBX_LOCALNODEID;

	$result = array('selected' => 0, 'nodes' => array(), 'nodeids' => array());

	if (!defined('ZBX_NOT_ALLOW_ALL_NODES')) {
		$result['nodes'][0] = array('nodeid' => 0, 'name' => _('All'));
	}
	$available_nodes = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ_LIST, PERM_RES_DATA_ARRAY);
	$available_nodes = get_tree_by_parentid($ZBX_LOCALNODEID, $available_nodes, 'masterid'); // remove parent nodes
	$selected_nodeids = get_request('selected_nodes', get_node_profile(array(CWebUser::$data['node']['nodeid'])));

	// +++ Fill $result['NODEIDS'], $result['NODES'] +++
	$nodeids = array();
	foreach ($selected_nodeids as $num => $nodeid) {
		if (isset($available_nodes[$nodeid])) {
			$result['nodes'][$nodeid] = array(
				'nodeid' => $available_nodes[$nodeid]['nodeid'],
				'name' => $available_nodes[$nodeid]['name'],
				'masterid' => $available_nodes[$nodeid]['masterid']
			);
			$nodeids[$nodeid] = $nodeid;
		}
	}

	$switch_node = get_request('switch_node', CProfile::get('web.nodes.switch_node', -1));

	if (!isset($available_nodes[$switch_node]) || !uint_in_array($switch_node, $selected_nodeids)) { // check switch_node
		$switch_node = 0;
	}

	$result['nodeids'] = $nodeids;
	if (!defined('ZBX_NOT_ALLOW_ALL_NODES')) {
		$result['selected'] = $switch_node;
	}
	elseif (!empty($nodeids)) {
		$result['selected'] = ($switch_node > 0) ? $switch_node : array_shift($nodeids);
	}
	return $result;
}

function get_node_name_by_elid($id_val, $force_with_all_nodes = null, $delimiter = '') {
	global $ZBX_NODES, $ZBX_VIEWED_NODES;

	if ($force_with_all_nodes === false || (is_null($force_with_all_nodes) && $ZBX_VIEWED_NODES['selected'] != 0)) {
		return null;
	}

	$nodeid = id2nodeid($id_val);

	if (!isset($ZBX_NODES[$nodeid])) {
		return null;
	}
	return $ZBX_NODES[$nodeid]['name'].$delimiter;
}

function getNodeIdByNodeName($nodeName) {
	global $ZBX_NODES;

	foreach ($ZBX_NODES as $nodeid => $node) {
		if ($node['name'] == $nodeName) {
			return $nodeid;
		}
	}
	return 0;
}

function is_show_all_nodes() {
	global $ZBX_VIEWED_NODES;

	return ZBX_DISTRIBUTED && $ZBX_VIEWED_NODES['selected'] == 0;
}

function detect_node_type($nodeid, $masterid) {
	global $ZBX_CURMASTERID, $ZBX_LOCALNODEID;

	if (bccomp($nodeid, $ZBX_LOCALNODEID) == 0) {
		$nodetype = ZBX_NODE_LOCAL;
	}
	elseif (bccomp($nodeid, get_current_nodeid(false)) == 0) {
		$nodetype = ZBX_NODE_LOCAL;
	}
	elseif (bccomp($nodeid, $ZBX_CURMASTERID) == 0) {
		$nodetype = ZBX_NODE_MASTER;
	}
	elseif (bccomp($masterid, get_current_nodeid(false)) == 0) {
		$nodetype = ZBX_NODE_CHILD;
	}
	else {
		$nodetype = -1;
	}

	return $nodetype;
}

function node_type2str($nodetype) {
	switch ($nodetype) {
		case ZBX_NODE_CHILD:
			$result = _('Child');
			break;
		case ZBX_NODE_MASTER:
			$result = _('Master');
			break;
		case ZBX_NODE_LOCAL:
			$result = _('Local');
			break;
		default:
			$result = _('Unknown');
			break;
	}
	return $result;
}

function add_node($nodeid, $name, $ip, $port, $nodetype, $masterid) {
	global $ZBX_LOCMASTERID, $ZBX_LOCALNODEID;

	if (!preg_match('/^'.ZBX_PREG_NODE_FORMAT.'$/i', $name)) {
		error(_('Incorrect characters used for Node name.'));
		return false;
	}

	switch ($nodetype) {
		case ZBX_NODE_CHILD:
			break;
		case ZBX_NODE_MASTER:
			if (!empty($masterid)) {
				error(_('Master node "ID" must be empty.'));
				return false;
			}

			if ($ZBX_LOCMASTERID) {
				error(_('Master node already exists.'));
				return false;
			}
			break;
		default:
			error(_('Incorrect node type.'));
			return false;
	}

	if (DBfetch(DBselect('SELECT n.nodeid FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeid)))) {
		error(_('Node with same ID already exists.'));
		return false;
	}

	$result = DBexecute('INSERT INTO nodes (nodeid,name,ip,port,nodetype,masterid)'.
		' VALUES ('.$nodeid.','.zbx_dbstr($name).','.zbx_dbstr($ip).','.zbx_dbstr($port).','.zbx_dbstr($nodetype).','.($masterid ? zbx_dbstr($masterid) : 'NULL').')');

	if ($result && $nodetype == ZBX_NODE_MASTER) {
		DBexecute('UPDATE nodes SET masterid='.zbx_dbstr($nodeid).' WHERE nodeid='.$ZBX_LOCALNODEID);
		$ZBX_CURMASTERID = $nodeid; // apply master node for this script
	}

	return $result ? $nodeid : $result;
}

function update_node($nodeid, $name, $ip, $port) {
	if (!preg_match('/^'.ZBX_PREG_NODE_FORMAT.'$/i', $name)) {
		error(_('Incorrect characters used for Node name.'));
		return false;
	}
	return DBexecute('UPDATE nodes SET name='.zbx_dbstr($name).',ip='.zbx_dbstr($ip).',port='.zbx_dbstr($port).' WHERE nodeid='.zbx_dbstr($nodeid));
}

function delete_node($nodeid) {
	$result = false;
	$node = DBfetch(DBselect('SELECT n.nodeid,n.masterid FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeid)));
	$nodetype = detect_node_type($node['nodeid'], $node['masterid']);

	if ($nodetype == ZBX_NODE_LOCAL) {
		error(_('Unable to remove local node.'));
	}
	else {
		$result = (
			DBexecute('UPDATE nodes SET masterid=NULL WHERE masterid='.zbx_dbstr($nodeid)) &&
			DBexecute('DELETE FROM nodes WHERE nodeid='.zbx_dbstr($nodeid))
		);
		if ($nodetype != ZBX_NODE_MASTER) {
			error(_('Please be aware that database still contains data related to the deleted node.'));
		}
	}
	return $result;
}

function get_node_by_nodeid($nodeid) {
	return DBfetch(DBselect('SELECT n.* FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeid)));
}

function get_node_path($nodeid, $result = '') {
	global $ZBX_NODES;

	$node_data = isset($ZBX_NODES[$nodeid]) ? $ZBX_NODES[$nodeid] : false;
	if ($node_data) {
		if ($node_data['masterid']) {
			$result = get_node_path($node_data['masterid'], $result);
		}
		$result .= $node_data['name'].' &rArr; ';
	}

	return $result;
}
