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


/**
 * Class containing methods for operations with graphs.
 *
 * @package API
 */
abstract class CGraphGeneral extends CZBXAPI {

	const ERROR_TEMPLATE_HOST_MIX = 'templateHostMix';

	/**
	 * Check $graphs:
	 *	whether graphs have name field
	 *	whether not set  templateid
	 *	whether graphs has at least one item
	 *	whether all graph items has ids
	 *	whether Pie and Exploded graphs has at most one sum item
	 *	whether all graph items are editable by user
	 *	whether graph has at least one prototype
	 *	whether not creating graphs with the same name
	 *
	 * @param array $graphs
	 * @param boolean $update
	 * @param boolean $prototype
	 * @return true
	 */
	protected function checkInput($graphs, $update = false) {
		$colorValidator = new CColorValidator();
		if ($update){
			$graphs = $this->extendObjects($this->tableName(), $graphs, array('name'));
		}
		foreach ($graphs as $gnum => $graph) {
			// graph fields
			$fields = array('name' => null);
			if (!$update && !check_db_fields($fields, $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "name" field for graph.'));
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $graph)) {
				if ($update) {
					$error = _s('Cannot update "templateid" for graph "%1$s".', $graph['name']);
				}
				else {
					$error = _s('Cannot set "templateid" for graph "%1$s".', $graph['name']);
				}
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			// items fields
			foreach ($graph['gitems'] as $gitem) {
				// check color
				if (!$colorValidator->validate($gitem['color'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
				}
			}

			// more than one sum type item for pie graph
			if ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED) {
				$sumItems = 0;
				foreach ($graph['gitems'] as $gitem) {
					if ($gitem['type'] == GRAPH_ITEM_SUM) {
						$sumItems++;
					}
				}
				if ($sumItems > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot add more than one item with type "Graph sum" on graph "%1$s".', $graph['name']));
				}
			}

			// Y axis MIN value < Y axis MAX value
			if (($graph['graphtype'] == GRAPH_TYPE_NORMAL || $graph['graphtype'] == GRAPH_TYPE_STACKED)
					&& $graph['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED
					&& $graph['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED
					&& $graph['yaxismin'] >= $graph['yaxismax']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Y axis MAX value must be greater than Y axis MIN value.'));
			}
		}

		$graphNames = array();
		foreach ($graphs as $graph) {
			// check if the host has any graphs in DB with the same name within host
			$hostsAndTemplates = API::Host()->get(array(
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => array('hostid'),
				'nopermissions' => true,
				'preservekeys' => true,
				'templated_hosts' => true
			));

			$hostAndTemplateIds = array_keys($hostsAndTemplates);
			$graphsExists = API::Graph()->get(array(
				'hostids' => $hostAndTemplateIds,
				'output' => array('graphid'),
				'filter' => array('name' => $graph['name'], 'flags' => null), // 'flags' => null overrides default behaviour
				'nopermissions' => true,
				'preservekeys' => true, // faster
				'limit' => 1 // one match enough for check
			));
			// if graph exists with given name and it is create action or update action with ids not matching, rise exception
			foreach ($graphsExists as $graphExists) {
				if (!$update || (bccomp($graphExists['graphid'], $graph['graphid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph with name "%1$s" already exists in graphs or graph prototypes.', $graph['name']));
				}
			}
			// cheks that there is no two graphs with the same name within host
			foreach ($hostAndTemplateIds as $id) {
				if (!isset($graphNames[$graph['name']])) {
					$graphNames[$graph['name']] = array();
				}
				if (isset($graphNames[$graph['name']][$id])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('More than one graph with name "%1$s" within host.', $graph['name']));
				}
				else {
					$graphNames[$graph['name']][$id] = true;
				}
			}
		}

		return true;
	}

	/**
	 * Update existing graphs
	 *
	 * @param array $graphs
	 * @return array
	 */
	public function update($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = zbx_objectValues($graphs, 'graphid');

		$updateGraphs = $this->get(array(
			'graphids' => $graphids,
			'editable' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND
		));

		foreach ($graphs as $graph) {
			// if missing in $updateGraphs then no permissions
			if (!isset($updateGraphs[$graph['graphid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkInput($graphs, true);

		foreach ($graphs as $graph) {
			unset($graph['templateid']);

			$graphHosts = API::Host()->get(array(
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'templated_hosts' => true
			));

			// mass templated items
			$templatedGraph = false;
			foreach ($graphHosts as $host) {
				if (HOST_STATUS_TEMPLATE == $host['status']) {
					$templatedGraph = $host['hostid'];
					if (count($graphHosts) > 1) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name']));
					}
					break;
				}
			}


			// check ymin, ymax items
			$this->checkAxisItems($graph, $templatedGraph);

			$this->updateReal($graph, $updateGraphs[$graph['graphid']]);

			// inheritance
			if ($templatedGraph) {
				$this->inherit($graph);
			}
		}

		return array('graphids' => $graphids);
	}

	/**
	 * Create new graphs
	 *
	 * @param array $graphs
	 * @return array
	 */
	public function create($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		$this->checkInput($graphs, false);

		foreach ($graphs as $graph) {
			$graphHosts = API::Host()->get(array(
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'templated_hosts' => true
			));

			// check - items from one template
			$templatedGraph = false;
			foreach ($graphHosts as $host) {
				if (HOST_STATUS_TEMPLATE == $host['status']) {
					$templatedGraph = $host['hostid'];
					break;
				}
			}
			if ($templatedGraph && count($graphHosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name']));
			}

			// check ymin, ymax items
			$this->checkAxisItems($graph, $templatedGraph);

			$graphid = $this->createReal($graph);

			if ($templatedGraph) {
				$graph['graphid'] = $graphid;
				$this->inherit($graph);
			}

			$graphids[] = $graphid;
		}

		return array('graphids' => $graphids);
	}

	/**
	 * Creates a new graph and returns it's ID.
	 *
	 * @param $graph
	 *
	 * @return mixed
	 */
	protected function createReal($graph) {
		$graphids = DB::insert('graphs', array($graph));
		$graphid = reset($graphids);

		foreach ($graph['gitems'] as &$gitem) {
			$gitem['graphid'] = $graphid;
		}
		unset($gitem);

		DB::insert('graphs_items', $graph['gitems']);

		return $graphid;
	}

	/**
	 * Updates the graph if $graph differs from $dbGraph.
	 *
	 * @param $graph
	 * @param $dbGraph
	 *
	 * @return string
	 */
	protected function updateReal($graph, $dbGraph) {
		$dbGitems = zbx_toHash($dbGraph['gitems'], 'gitemid');
		$dbGitemIds = zbx_toHash(zbx_objectValues($dbGitems, 'gitemid'));

		// update the graph if it's modified
		if (DB::recordModified('graphs', $dbGraph, $graph)) {
			DB::updateByPk($this->tableName(), $graph['graphid'], $graph);
		}

		// update graph items
		$insertGitems = array();
		$deleteGitemIds = array_combine($dbGitemIds, $dbGitemIds);

		foreach ($graph['gitems'] as $gitem) {
			// updating an existing item
			if (!empty($gitem['gitemid']) && isset($dbGitemIds[$gitem['gitemid']])) {
				if (DB::recordModified('graphs_items', $dbGitems[$gitem['gitemid']], $gitem)) {
					DB::updateByPk('graphs_items', $gitem['gitemid'], $gitem);
				}

				// remove this graph item from the collection so it won't get deleted
				unset($deleteGitemIds[$gitem['gitemid']]);
			}
			// adding a new item
			else {
				$gitem['graphid'] = $graph['graphid'];
				$insertGitems[] = $gitem;
			}
		}

		if ($deleteGitemIds) {
			DB::delete('graphs_items', array('gitemid' => $deleteGitemIds));
		}
		if ($insertGitems) {
			DB::insert('graphs_items', $insertGitems);
		}

		return $graph['graphid'];
	}

	/**
	 * @param array $object
	 * @return bool
	 */
	public function exists($object) {
		$options = array(
			'filter' => array('flags' => null),
			'output' => array('graphid'),
			'nopermissions' => true,
			'limit' => 1
		);
		if (isset($object['name'])) {
			$options['filter']['name'] = $object['name'];
		}
		if (isset($object['host'])) {
			$options['filter']['host'] = $object['host'];
		}
		if (isset($object['hostids'])) {
			$options['hostids'] = zbx_toArray($object['hostids']);
		}

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$objs = $this->get($options);

		return !empty($objs);
	}

	/**
	 * Get graphid by graph name
	 *
	 * params: hostids, name
	 *
	 * @param array $graphData
	 * @return string|boolean
	 */
	public function getObjects($graphData) {
		$options = array(
			'filter' => $graphData,
			'output' => API_OUTPUT_EXTEND
		);
		if (isset($graphData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($graphData['node']);
		}
		elseif (isset($graphData['nodeids'])) {
			$options['nodeids'] = $graphData['nodeids'];
		}
		return $this->get($options);
	}

	protected function checkAxisItems($graph, $tpl = false) {
		$axisItems = array();
		if (isset($graph['ymin_type']) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$axisItems[$graph['ymin_itemid']] = $graph['ymin_itemid'];
		}
		if (isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$axisItems[$graph['ymax_itemid']] = $graph['ymax_itemid'];
		}

		if (!empty($axisItems)) {
			$options = array(
				'itemids' => $axisItems,
				'output' => array('itemid'),
				'countOutput' => true,
				'webitems' => true,
				'filter' => array('flags' => null, 'value_type' => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
			);
			if ($tpl) {
				$options['hostids'] = $tpl;
			}
			else {
				$options['templated'] = false;
			}

			$cntExist = API::Item()->get($options);

			if ($cntExist != count($axisItems)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect item for axis value.'));
			}
		}

		return true;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding GraphItems
		if ($options['selectGraphItems'] !== null && $options['selectGraphItems'] !== API_OUTPUT_COUNT) {
			$gitems = API::GraphItem()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $this->outputExtend('graphs_items', array('graphid', 'gitemid'), $options['selectGraphItems']),
				'graphids' => $graphids,
				'nopermissions' => true,
				'preservekeys' => true
			));
			$relationMap = $this->createRelationMap($gitems, 'graphid', 'gitemid');

			$gitems = $this->unsetExtraFields($gitems, array('graphid', 'gitemid'), $options['selectGraphItems']);
			$result = $relationMap->mapMany($result, $gitems, 'gitems');
		}

		// adding HostGroups
		if ($options['selectGroups'] !== null && $options['selectGroups'] !== API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,hg.groupid'.
					' FROM graphs_items gi,items i,hosts_groups hg'.
					' WHERE '.dbConditionInt('gi.graphid', $graphids).
					' AND gi.itemid=i.itemid'.
					' AND i.hostid=hg.hostid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['groupid']);
			}

			$groups = API::HostGroup()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// adding Hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,i.hostid'.
					' FROM graphs_items gi,items i'.
					' WHERE '.dbConditionInt('gi.graphid', $graphids).
					' AND gi.itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['hostid']);
			}

			$hosts = API::Host()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding Templates
		if ($options['selectTemplates'] !== null && $options['selectTemplates'] !== API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,i.hostid'.
					' FROM graphs_items gi,items i'.
					' WHERE '.dbConditionInt('gi.graphid', $graphids).
					' AND gi.itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['hostid']);
			}

			$templates = API::Template()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectTemplates'],
				'templateids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $templates, 'templates');
		}

		return $result;
	}
}
