<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


function update_node_profile($nodeIds) {
	DBstart();

	DBexecute(
		'DELETE FROM profiles WHERE userid='.CWebUser::$data['userid'].' AND idx='.zbx_dbstr('web.nodes.selected')
	);

	foreach ($nodeIds as $nodeId) {
		DBexecute(
			'INSERT INTO profiles (profileid,userid,idx,value_id,type)'.
			' VALUES ('.get_dbid('profiles', 'profileid').','.CWebUser::$data['userid'].','.
				zbx_dbstr('web.nodes.selected').','.zbx_dbstr($nodeId).',4)');
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

	return $result ? $result : $default;
}

function init_nodes() {
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
		$dbNodes = DBselect($sql);
		while ($dbNode = DBfetch($dbNodes)) {
			$ZBX_NODES[$dbNode['nodeid']] = $dbNode;
			$ZBX_NODES_IDS[$dbNode['nodeid']] = $dbNode['nodeid'];
		}

		$ZBX_AVAILABLE_NODES = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ, PERM_RES_IDS_ARRAY, $ZBX_NODES_IDS);
		$ZBX_VIEWED_NODES = get_viewed_nodes();
		$ZBX_CURRENT_NODEID = $ZBX_VIEWED_NODES['selected'];

		if ($node = DBfetch(DBselect('SELECT n.masterid FROM nodes n WHERE n.nodeid='.$ZBX_CURRENT_NODEID))) {
			$ZBX_CURMASTERID = $node['masterid'];
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

/**
 * Returns the ID of the currently selected node(s).
 *
 * Supported $forceAllNodes values:
 * - null 	- return the currently visible nodes;
 * - true 	- return all nodes that the user has read permissions to, including the master node;
 * - false	- return the currently selected node or the local node if all nodes are selected.
 *
 * @param bool 	$forceAllNodes	which nodes to return
 * @param int 	$permission		required node permissions
 *
 * @return array|int
 */
function get_current_nodeid($forceAllNodes = null, $permission = null) {
	global $ZBX_CURRENT_NODEID, $ZBX_AVAILABLE_NODES, $ZBX_VIEWED_NODES;

	if (!ZBX_DISTRIBUTED) {
		return 0;
	}

	if (!isset($ZBX_CURRENT_NODEID)) {
		init_nodes();
	}

	if (!is_null($permission)) {
		return get_accessible_nodes_by_user(CWebUser::$data, $permission, PERM_RES_IDS_ARRAY, $ZBX_AVAILABLE_NODES);
	}
	elseif (is_null($forceAllNodes)) {
		$result = ($ZBX_VIEWED_NODES['selected'] == 0) ? $ZBX_VIEWED_NODES['nodeids'] : $ZBX_VIEWED_NODES['selected'];

		if (empty($result)) {
			$result = CWebUser::$data['node']['nodeid'];
		}
		if (empty($result)) {
			$result = $ZBX_CURRENT_NODEID;
		}
	}
	elseif ($forceAllNodes) {
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

	$availableNodes = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ, PERM_RES_DATA_ARRAY);
	$availableNodes = get_tree_by_parentid($ZBX_LOCALNODEID, $availableNodes, 'masterid');
	$selectedNodeIds = get_request('selected_nodes', get_node_profile(array(CWebUser::$data['node']['nodeid'])));

	$nodeIds = array();

	foreach ($selectedNodeIds as $nodeId) {
		if (isset($availableNodes[$nodeId])) {
			$nodeIds[$nodeId] = $nodeId;

			$result['nodes'][$nodeId] = array(
				'nodeid' => $availableNodes[$nodeId]['nodeid'],
				'name' => $availableNodes[$nodeId]['name'],
				'masterid' => $availableNodes[$nodeId]['masterid']
			);
		}
	}

	$switchNode = get_request('switch_node', CProfile::get('web.nodes.switch_node', -1));

	if (!isset($availableNodes[$switchNode]) || !uint_in_array($switchNode, $selectedNodeIds)) {
		$switchNode = 0;
	}

	$result['nodeids'] = $nodeIds;

	if (!defined('ZBX_NOT_ALLOW_ALL_NODES')) {
		$result['selected'] = $switchNode;
	}
	elseif ($nodeIds) {
		$result['selected'] = ($switchNode > 0) ? $switchNode : array_shift($nodeIds);
	}

	return $result;
}

/**
 * Get node name by given array of object IDs.
 *
 * @global array $ZBX_NODES				array of node names
 * @global array $ZBX_VIEWED_NODES		array of node view parameters
 * @param array  $objectIds				array of object IDs
 * @param bool   $forceWithAllNodes		force display nodes
 * @param string $delimiter				node name delimiter
 *
 * @return array						return node names of given objects if they have any
 */
function getNodeNamesByElids($objectIds, $forceWithAllNodes = null, $delimiter = '') {
	global $ZBX_NODES, $ZBX_VIEWED_NODES;

	$result = array();
	foreach ($objectIds as $objectId) {
		if ($forceWithAllNodes === false || ($forceWithAllNodes === null && $ZBX_VIEWED_NODES['selected'] != 0)) {
			$result[$objectId] = null;
		}
		else {
			$nodeId = id2nodeid($objectId);

			$result[$objectId] = isset($ZBX_NODES[$nodeId]) ? $ZBX_NODES[$nodeId]['name'].$delimiter : null;
		}
	}

	return $result;
}

function get_node_name_by_elid($objectId, $forceWithAllNodes = null, $delimiter = '') {
	global $ZBX_NODES, $ZBX_VIEWED_NODES;

	if ($forceWithAllNodes === false || (is_null($forceWithAllNodes) && $ZBX_VIEWED_NODES['selected'] != 0)) {
		return null;
	}

	$nodeId = id2nodeid($objectId);

	if (!isset($ZBX_NODES[$nodeId])) {
		return null;
	}

	return $ZBX_NODES[$nodeId]['name'].$delimiter;
}

function getNodeIdByNodeName($nodeName) {
	global $ZBX_NODES;

	foreach ($ZBX_NODES as $nodeId => $node) {
		if ($node['name'] == $nodeName) {
			return $nodeId;
		}
	}

	return 0;
}

function is_show_all_nodes() {
	global $ZBX_VIEWED_NODES;

	return (ZBX_DISTRIBUTED && $ZBX_VIEWED_NODES['selected'] == 0);
}

function detect_node_type($nodeId, $masterId) {
	global $ZBX_CURMASTERID, $ZBX_LOCALNODEID;

	if (bccomp($nodeId, $ZBX_LOCALNODEID) == 0) {
		$nodeType = ZBX_NODE_LOCAL;
	}
	elseif (bccomp($nodeId, get_current_nodeid(false)) == 0) {
		$nodeType = ZBX_NODE_LOCAL;
	}
	elseif (bccomp($nodeId, $ZBX_CURMASTERID) == 0) {
		$nodeType = ZBX_NODE_MASTER;
	}
	elseif (bccomp($masterId, get_current_nodeid(false)) == 0) {
		$nodeType = ZBX_NODE_CHILD;
	}
	else {
		$nodeType = -1;
	}

	return $nodeType;
}

function node_type2str($nodeType) {
	switch ($nodeType) {
		case ZBX_NODE_CHILD:
			return _('Child');
		case ZBX_NODE_MASTER:
			return _('Master');
		case ZBX_NODE_LOCAL:
			return _('Local');
		default:
			return _('Unknown');
	}
}

function add_node($nodeId, $name, $ip, $port, $nodeType, $masterId) {
	global $ZBX_LOCMASTERID, $ZBX_LOCALNODEID;

	if (!preg_match('/^'.ZBX_PREG_NODE_FORMAT.'$/i', $name)) {
		error(_('Incorrect characters used for Node name.'));
		return false;
	}

	switch ($nodeType) {
		case ZBX_NODE_CHILD:
			break;

		case ZBX_NODE_MASTER:
			if ($masterId) {
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

	if (DBfetch(DBselect('SELECT n.nodeid FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeId)))) {
		error(_('Node with same ID already exists.'));
		return false;
	}

	$result = DBexecute('INSERT INTO nodes (nodeid,name,ip,port,nodetype,masterid)'.
		' VALUES ('.zbx_dbstr($nodeId).','.zbx_dbstr($name).','.zbx_dbstr($ip).','.zbx_dbstr($port).','.zbx_dbstr($nodeType).','.($masterId ? zbx_dbstr($masterId) : 'NULL').')');

	if ($result && $nodeType == ZBX_NODE_MASTER) {
		DBexecute('UPDATE nodes SET masterid='.$nodeId.' WHERE nodeid='.$ZBX_LOCALNODEID);

		// apply master node for this script
		$ZBX_CURMASTERID = $nodeId;
	}

	return $result ? $nodeId : $result;
}

function update_node($nodeId, $name, $ip, $port) {
	if (!preg_match('/^'.ZBX_PREG_NODE_FORMAT.'$/i', $name)) {
		error(_('Incorrect characters used for Node name.'));
		return false;
	}

	return DBexecute(
		'UPDATE nodes SET name='.zbx_dbstr($name).',ip='.zbx_dbstr($ip).',port='.zbx_dbstr($port).' WHERE nodeid='.zbx_dbstr($nodeId)
	);
}

function delete_node($nodeId) {
	$result = false;

	$node = DBfetch(DBselect('SELECT n.nodeid,n.masterid FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeId)));
	$nodeType = detect_node_type($node['nodeid'], $node['masterid']);

	if ($nodeType == ZBX_NODE_LOCAL) {
		error(_('Unable to remove local node.'));
	}
	else {
		$result = (
			DBexecute('UPDATE nodes SET masterid=NULL WHERE masterid='.zbx_dbstr($nodeId)) &&
			DBexecute('DELETE FROM nodes WHERE nodeid='.zbx_dbstr($nodeId))
		);

		if ($nodeType != ZBX_NODE_MASTER) {
			error(_('Please be aware that database still contains data related to the deleted node.'));
		}
	}

	return $result;
}

function get_node_by_nodeid($nodeId) {
	return DBfetch(DBselect('SELECT n.* FROM nodes n WHERE n.nodeid='.zbx_dbstr($nodeId)));
}

function get_node_path($nodeId, $result = '') {
	global $ZBX_NODES;

	$node = isset($ZBX_NODES[$nodeId]) ? $ZBX_NODES[$nodeId] : false;

	if ($node) {
		if ($node['masterid']) {
			$result = get_node_path($node['masterid'], $result);
		}

		$result .= $node['name'].' &rArr; ';
	}

	return $result;
}
