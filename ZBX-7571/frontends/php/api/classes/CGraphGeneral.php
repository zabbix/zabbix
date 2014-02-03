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


/**
 * Class containing methods for operations with graphs.
 *
 * @package API
 */
abstract class CGraphGeneral extends CZBXAPI {

	const ERROR_TEMPLATE_HOST_MIX = 'templateHostMix';
	const ERROR_MISSING_GRAPH_NAME = 'missingGraphName';
	const ERROR_MISSING_GRAPH_ITEMS = 'missingGraphItems';
	const ERROR_MISSING_REQUIRED_VALUE = 'missingRequiredValue';
	const ERROR_TEMPLATED_ID = 'templatedId';
	const ERROR_GRAPH_SUM = 'graphSum';

	/**
	 * Update existing graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function update($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphIds = zbx_objectValues($graphs, 'graphid');

		$graphs = $this->extendObjects($this->tableName(), $graphs,
			array('name', 'graphtype', 'ymin_type', 'ymin_itemid', 'ymax_type', 'ymax_itemid', 'yaxismin', 'yaxismax')
		);

		$dbGraphs = $this->get(array(
			'graphids' => $graphIds,
			'editable' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND
		));

		$updateDiscoveredValidator = new CUpdateDiscoveredValidator(array(
			'messageAllowed' => _('Cannot update a discovered graph.')
		));
		foreach ($graphs as &$graph) {
			// check permissions
			if (!isset($dbGraphs[$graph['graphid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// cannot update discovered graphs
			$this->checkPartialValidator($graph, $updateDiscoveredValidator, $dbGraphs[$graph['graphid']]);

			// validate items on set or pass existing items from DB
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as $gitems) {
					if (isset($gitems['gitemid']) && !$gitems['gitemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "gitemid" field for item.'));
					}

					if (isset($gitems['gitemid']) && $gitems['gitemid']) {
						$validGitemIds = array();
						foreach ($dbGraphs[$graph['graphid']]['gitems'] as $dbGitem) {
							$validGitemIds[$dbGitem['gitemid']] = $dbGitem['gitemid'];
						}

						if (!in_array($gitems['gitemid'], $validGitemIds)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No permissions to referred object or it does not exist!')
							);
						}
					}
				}
			}
			else {
				$graph['gitems'] = $dbGraphs[$graph['graphid']]['gitems'];
			}
		}
		unset($graph);

		$this->validateUpdate($graphs, $dbGraphs);

		foreach ($graphs as $graph) {
			unset($graph['templateid']);

			$graph['gitems'] = isset($graph['gitems']) ? $graph['gitems'] : $dbGraphs[$graph['graphid']]['gitems'];

			$this->updateReal($graph, $dbGraphs[$graph['graphid']]);

			$this->inherit($graph);
		}

		return array('graphids' => $graphIds);
	}

	/**
	 * Create new graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function create($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		// set default parameters
		foreach ($graphs as &$graph) {
			if (!isset($graph['graphtype'])) {
				$graph['graphtype'] = GRAPH_TYPE_NORMAL;
			}
			if (!isset($graph['ymin_type'])) {
				$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
			}
			if (!isset($graph['ymax_type'])) {
				$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
			}
		}
		unset($graph);

		$this->validateCreate($graphs);

		foreach ($graphs as $graph) {
			$graph['graphid'] = $this->createReal($graph);

			$this->inherit($graph);

			$graphids[] = $graph['graphid'];
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

		// delete remaining items only if new items or items that require update are set
		if ($graph['gitems']) {
			$insertGitems = array();
			$deleteGitemIds = $dbGitemIds;

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
	 * Get graphid by graph name.
	 *
	 * params: hostids, name
	 *
	 * @param array $graphData
	 *
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

	/**
	 * Check values for Y axis items and values.
	 *
	 * @param array $graph
	 * @param bool $tpl
	.*.
	 * @return void
	 */
	protected function checkAxisItems(array $graph, $tpl = false) {
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

		// more than one sum type item for pie graph
		if ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED) {
			$sumItems = 0;
			foreach ($graph['gitems'] as $gitem) {
				if ($gitem['type'] == GRAPH_ITEM_SUM) {
					$sumItems++;
				}
			}
			if ($sumItems > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_GRAPH_SUM), $graph['name'])
				);
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

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding GraphItems
		if ($options['selectGraphItems'] !== null && $options['selectGraphItems'] !== API_OUTPUT_COUNT) {
			$gitems = API::GraphItem()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $this->outputExtend($options['selectGraphItems'], array('graphid', 'gitemid')),
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

	/**
	 * Validate graph name and graph items including Y axis item ID's and graph item fields on Create method
	 * and return valid item ID's on success or trow an error on failure.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function validateItemsCreate(array $graphs) {
		$itemIds = array();

		foreach ($graphs as $graph) {
			// validate graph name
			$fields = array('name' => null);
			if (!check_db_fields($fields, $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _($this->getErrorMsg(self::ERROR_MISSING_GRAPH_NAME)));
			}

			// graph items are mandatory
			if (!isset($graph['gitems']) || !is_array($graph['gitems']) || !$graph['gitems']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_MISSING_GRAPH_ITEMS), $graph['name'])
				);
			}

			// validate item fields
			if (isset($graph['gitems'])) {
				$fields = array('itemid' => null);
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required
					if (!check_db_fields($fields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y axis item IDs for persmission validation
			if (isset($graph['ymin_type']) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!isset($graph['ymin_itemid']) || zbx_empty($graph['ymin_itemid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'ymin_itemid')
					);
				}
				else {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}
			if (isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!isset($graph['ymax_itemid']) || zbx_empty($graph['ymax_itemid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'ymax_itemid')
					);
				}
				else {
					$itemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
				}
			}
		}

		return $itemIds;
	}

	/**
	 * Validate graph gerenal data on Create method.
	 * Check if new items are from same templated host, validate Y axis items and values and hosts and templates.
	 *
	 * @param array $graphs
	 *
	 * @return void
	 */
	protected function validateCreate(array $graphs) {
		$colorValidator = new CColorValidator();

		foreach ($graphs as $graph) {
			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $graph)) {
				$error = _s($this->getErrorMsg(self::ERROR_TEMPLATED_ID), $graph['name']);
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$templatedGraph = false;
			if (isset($graph['gitems'])) {
				// check if new items are from same templated host
				$graphHosts = API::Host()->get(array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => array('hostid', 'status'),
					'editable' => true,
					'templated_hosts' => true
				));

				// check - items from one template. at least one item belongs to template
				foreach ($graphHosts as $host) {
					if (HOST_STATUS_TEMPLATE == $host['status']) {
						$templatedGraph = $host['hostid'];
						break;
					}
				}

				if ($templatedGraph && count($graphHosts) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name'])
					);
				}

				// check color
				foreach ($graph['gitems'] as $gitem) {
					if (!isset($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'color')
						);
					}

					if (!$colorValidator->validate($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
					}
				}
			}

			// check graph type and ymin/ymax items
			$this->checkAxisItems($graph, $templatedGraph);
		}

		$this->validateHostsAndTemplates($graphs);
	}

	/**
	 * Validate graph items including valid Y axis item ID's on Update method
	 * and return valid item ID's on success or trow an error on failure.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function validateItemsUpdate(array $graphs) {
		foreach ($graphs as $graph) {
			// graph items are optional
			if (isset($graph['gitems']) && (!is_array($graph['gitems']) || !$graph['gitems'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_MISSING_GRAPH_ITEMS), $graph['name'])
				);
			}

			// validate item fields
			if (isset($graph['gitems'])) {
				$fields = array('itemid' => null);
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required only if no "gitemid" is set
					if (!isset($gitem['gitemid']) && !check_db_fields($fields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y axis item IDs for persmission validation
			if (isset($graph['ymin_type']) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!isset($graph['ymin_itemid']) || zbx_empty($graph['ymin_itemid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'ymin_itemid')
					);
				}
				else {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}
			if (isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!isset($graph['ymax_itemid']) || zbx_empty($graph['ymax_itemid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'ymax_itemid')
					);
				}
				else {
					$itemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
				}
			}
		}

		return $itemIds;
	}

	/**
	 * Validate graph general data on Update method.
	 * When updating graph check to what host graph belongs to and trow an error if new items added from other hosts.
	 * Includes Y axis validation and if graph already exists somewhere in DB.
	 *
	 * @param array $graphs
	 * @param array $dbGraphs
	 *
	 * @return void
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		$colorValidator = new CColorValidator();

		foreach ($graphs as $graph) {
			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $graph)) {
				$error = _s($this->getErrorMsg(self::ERROR_TEMPLATED_ID), $graph['name']);
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$templatedGraph = false;
			if (isset($graph['gitems'])) {
				// first item determines to which host graph belongs to
				$gitem = array_shift($dbGraphs[$graph['graphid']]['gitems']);
				$graphHosts = API::Host()->get(array(
					'itemids' => $gitem['itemid'],
					'output' => array('hostid', 'status'),
					'editable' => true,
					'templated_hosts' => true
				));
				$host = array_shift($graphHosts);

				// if the current graph is templated and new items to be added
				if (HOST_STATUS_TEMPLATE == $host['status']) {
					$templatedGraph = $host['hostid'];
					foreach ($graph['gitems'] as $gitem) {
						if (!isset($gitem['gitemid']) && isset($gitem['itemid'])) {
							$itemIds[] = $gitem['itemid'];
						}
					}

					if (isset($itemIds) && $itemIds) {
						$itemHosts = API::Host()->get(array(
							'itemids' => $itemIds,
							'output' => array('hostid'),
							'editable' => true,
							'templated_hosts' => true
						));

						// only one host is allowed and it has to be the current. other templated hosts are allowed
						$itemHosts = array_unique(zbx_objectValues($itemHosts, 'hostid'));
						if (count($itemHosts) > 1 || !in_array($host['hostid'], $itemHosts)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name'])
							);
						}
					}
				}

				// items fields
				foreach ($graph['gitems'] as $gitem) {
					// check color
					if (isset($gitem['color']) && !$colorValidator->validate($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
					}
				}
			}

			// check ymin, ymax items
			$this->checkAxisItems($graph, $templatedGraph);
		}

		$this->validateHostsAndTemplates($graphs);
	}

	/**
	 * Check if graph already exists somewhere in DB.
	 *
	 * @param array $graphs
	 *
	 * @return void
	 */
	protected function validateHostsAndTemplates(array $graphs) {
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

			$dbGraphs = API::Graph()->get(array(
				'hostids' => $hostAndTemplateIds,
				'output' => array('graphid'),
				'filter' => array('name' => $graph['name'], 'flags' => null), // 'flags' => null overrides default behaviour
				'nopermissions' => true
			));

			if ($dbGraphs) {
				$duplicateGraphsFound = false;

				if (isset($graph['graphid'])) {
					foreach ($dbGraphs as $dbGraph) {
						if (bccomp($dbGraph['graphid'], $graph['graphid']) != 0) {
							$duplicateGraphsFound = true;
							break;
						}
					}
				}
				else {
					$duplicateGraphsFound = true;
				}

				if ($duplicateGraphsFound) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph with name "%1$s" already exists in graphs or graph prototypes.', $graph['name'])
					);
				}
			}

			// cheks that there is no two graphs with the same name within host
			foreach ($hostAndTemplateIds as $id) {
				if (!isset($graphNames[$graph['name']])) {
					$graphNames[$graph['name']] = array();
				}
				if (isset($graphNames[$graph['name']][$id])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('More than one graph with name "%1$s" within host.', $graph['name'])
					);
				}
				else {
					$graphNames[$graph['name']][$id] = true;
				}
			}
		}
	}
}
