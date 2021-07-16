<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */
abstract class CGraphGeneral extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	const ERROR_TEMPLATE_HOST_MIX = 'templateHostMix';
	const ERROR_MISSING_GRAPH_NAME = 'missingGraphName';
	const ERROR_MISSING_GRAPH_ITEMS = 'missingGraphItems';
	const ERROR_MISSING_REQUIRED_VALUE = 'missingRequiredValue';
	const ERROR_GRAPH_SUM = 'graphSum';

	/**
	 * Update graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function update(array $graphs) {
		$graphs = zbx_toArray($graphs);
		$graphIds = zbx_objectValues($graphs, 'graphid');

		$graphs = $this->extendObjects($this->tableName(), $graphs,
			['name', 'graphtype', 'ymin_type', 'ymin_itemid', 'ymax_type', 'ymax_itemid', 'yaxismin', 'yaxismax']
		);

		$dbGraphs = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'graphids' => $graphIds,
			'editable' => true,
			'preservekeys' => true,
			'inherited' => false
		]);

		$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
			'messageAllowed' => _('Cannot update a discovered graph.')
		]);

		foreach ($graphs as &$graph) {
			// check permissions
			if (!isset($dbGraphs[$graph['graphid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// cannot update discovered graphs
			$this->checkPartialValidator($graph, $updateDiscoveredValidator, $dbGraphs[$graph['graphid']]);

			// validate items on set or pass existing items from DB
			if (array_key_exists('gitems', $graph)) {
				foreach ($graph['gitems'] as $graph_item) {
					if (array_key_exists('gitemid', $graph_item) && !$graph_item['gitemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "gitemid" field for item.'));
					}

					if (array_key_exists('gitemid', $graph_item) && $graph_item['gitemid']) {
						$validGraphItemIds = [];

						foreach ($dbGraphs[$graph['graphid']]['gitems'] as $db_graph_item) {
							$validGraphItemIds[$db_graph_item['gitemid']] = $db_graph_item['gitemid'];
						}

						if (!in_array($graph_item['gitemid'], $validGraphItemIds)) {
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

		foreach ($graphs as &$graph) {
			unset($graph['templateid']);

			$graph['gitems'] = isset($graph['gitems']) ? $graph['gitems'] : $dbGraphs[$graph['graphid']]['gitems'];

			// Y axis min clean unused fields
			if (isset($graph['ymin_type'])) {
				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['yaxismin'] = null;
				}
				else {
					$graph['ymin_itemid'] = null;
				}
			}

			// Y axis max clean unused fields
			if (isset($graph['ymax_type'])) {
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['yaxismax'] = null;
				}
				else {
					$graph['ymax_itemid'] = null;
				}
			}
		}
		unset($graph);

		$this->updateReal($graphs);
		$this->inherit($graphs);

		return ['graphids' => $graphIds];
	}

	/**
	 * Create graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function create(array $graphs) {
		$graphs = zbx_toArray($graphs);

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

		$this->createReal($graphs);
		$this->inherit($graphs);

		return ['graphids' => array_column($graphs, 'graphid')];
	}

	/**
	 * Creates new graphs.
	 *
	 * @param array $graphs
	 */
	protected function createReal(array &$graphs) {
		$graphids = DB::insert('graphs', $graphs);
		$graph_items = [];

		// Collect graph_items to insert.
		foreach ($graphs as $key => $graph) {
			$sort_order = 0;

			foreach ($graph['gitems'] as $graph_item) {
				$graph_item['graphid'] = $graphids[$key];

				if (!array_key_exists('sortorder', $graph_item)) {
					$graph_item['sortorder'] = $sort_order;
				}

				$graph_items[] = $graph_item;

				$sort_order++;
			}
		}

		$graphs_itemsids = DB::insert('graphs_items', $graph_items);

		// Set id for graphs and graph items.
		$i = 0;
		foreach ($graphs as $key => &$graph) {
			$graph['graphid'] = $graphids[$key];

			foreach ($graph['gitems'] as &$graph_item) {
				$graph_item['gitemid'] = $graphs_itemsids[$i++];
			}
			unset($graph_item);
		}
		unset($graph);
	}

	/**
	 * Updates the graphs.
	 *
	 * @param array $graphs
	 *
	 * @return string
	 */
	protected function updateReal(array $graphs) {
		$data = [];
		foreach ($graphs as $graph) {
			unset($graph['gitems']);

			$data[] = ['values' => $graph, 'where' => ['graphid' => $graph['graphid']]];
		}
		DB::update('graphs', $data);

		$db_graph_items = API::GraphItem()->get([
			'output' => ['gitemid'],
			'graphids' => array_column($graphs, 'graphid'),
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$ins_graph_items = [];
		$upd_graph_items = [];
		$del_graph_items = $db_graph_items;
		foreach ($graphs as $graph) {
			$sort_order = 0;

			foreach ($graph['gitems'] as $graph_item) {
				// Update an existing item.
				if (array_key_exists('gitemid', $graph_item)
						&& array_key_exists($graph_item['gitemid'], $db_graph_items)) {
					unset($graph_item['graphid']);
					$upd_graph_items[] = ['values' => $graph_item, 'where' => ['gitemid' => $graph_item['gitemid']]];

					unset($del_graph_items[$graph_item['gitemid']]);
				}
				// Adding a new item.
				else {
					$graph_item['graphid'] = $graph['graphid'];

					if (!array_key_exists('sortorder', $graph_item)) {
						$graph_item['sortorder'] = $sort_order;
					}

					$ins_graph_items[] = $graph_item;

					$sort_order++;
				}
			}
		}

		if ($ins_graph_items) {
			DB::insert('graphs_items', $ins_graph_items);
		}

		if ($upd_graph_items) {
			DB::update('graphs_items', $upd_graph_items);
		}

		if ($del_graph_items) {
			DB::delete('graphs_items', ['gitemid' => array_keys($del_graph_items)]);
		}
	}

	/**
	 * Check values for Y axis items and values.
	 *
	 * @param array $graph
	 * @param bool  $tpl
	 */
	protected function checkAxisItems(array $graph, $tpl = false) {
		$axisItems = [];
		if (isset($graph['ymin_type']) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$axisItems[$graph['ymin_itemid']] = $graph['ymin_itemid'];
		}
		if (isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$axisItems[$graph['ymax_itemid']] = $graph['ymax_itemid'];
		}

		if (!empty($axisItems)) {
			$options = [
				'itemids' => $axisItems,
				'output' => ['itemid'],
				'countOutput' => true,
				'webitems' => true,
				'filter' => ['flags' => null, 'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]
			];
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
				if (array_key_exists('type', $gitem) && $gitem['type'] == GRAPH_ITEM_SUM) {
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
			$gitems = API::GraphItem()->get([
				'output' => $this->outputExtend($options['selectGraphItems'], ['graphid', 'gitemid']),
				'graphids' => $graphids,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($gitems, 'graphid', 'gitemid');

			$gitems = $this->unsetExtraFields($gitems, ['graphid', 'gitemid'], $options['selectGraphItems']);
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

			$groups = API::HostGroup()->get([
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// adding Hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {
			$hosts = [];
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

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = API::Host()->get([
					'output' => $options['selectHosts'],
					'hostids' => $related_ids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding Templates
		if ($options['selectTemplates'] !== null && $options['selectTemplates'] !== API_OUTPUT_COUNT) {
			$templates = [];
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

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$templates = API::Template()->get([
					'output' => $options['selectTemplates'],
					'templateids' => $related_ids,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

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
		$itemIds = [];
		$itemid_rules = ['type' => API_ID, 'flags' => API_NOT_EMPTY];

		foreach ($graphs as $graph) {
			// validate graph name
			$fields = ['name' => null];
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
				$fields = ['itemid' => null];
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required
					if (!check_db_fields($fields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y min axis item ID for permission validation
			if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymin_itemid'], 'ymin_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				else {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}

			// add Y max axis item ID for permission validation
			if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymax_itemid'], 'ymax_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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
	 * @throws APIException
	 */
	protected function validateCreate(array &$graphs) {
		$colorValidator = new CColorValidator();

		switch (get_class($this)) {
			case 'CGraph':
				$error_cannot_set = _('Cannot set "%1$s" for graph "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'uniq' => [['uuid']], 'fields' => [
					'uuid' =>		['type' => API_UUID]
				]];
				break;

			case 'CGraphPrototype':
				$error_cannot_set = _('Cannot set "%1$s" for graph prototype "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'uniq' => [['uuid']], 'fields' => [
					'uuid' =>		['type' => API_UUID],
					'discover' => 	['type' => API_INT32, 'in' => implode(',', [GRAPH_DISCOVER, GRAPH_NO_DISCOVER])]
				]];
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$read_only_fields = ['templateid', 'flags'];
		$templated_graph_indexes = [];

		foreach ($graphs as $key => &$graph) {
			$this->checkNoParameters($graph, $read_only_fields, $error_cannot_set, $graph['name']);

			$data = array_intersect_key($graph, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $data, '/'.($key + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$templatedGraph = false;

			if (isset($graph['gitems'])) {
				// check if new items are from same templated host
				$graphHosts = API::Host()->get([
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => ['hostid', 'status'],
					'editable' => true,
					'templated_hosts' => true
				]);

				// check - items from one template. at least one item belongs to template
				foreach ($graphHosts as $host) {
					if ($host['status'] == HOST_STATUS_TEMPLATE) {
						$templatedGraph = $host['hostid'];
						$templated_graph_indexes[$key] = true;
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
		unset($graph);

		$this->validateHostsAndTemplates($graphs);
		$this->checkAndAddUuid($graphs, $templated_graph_indexes);
	}

	/**
	 * Check that only graphs on templates have UUID. Add UUID to all graphs on templates, if it does not exists.
	 *
	 * @param array $graphs_to_create
	 * @param array $templated_graph_indexes
	 *
	 * @throws APIException
	 */
	protected function checkAndAddUuid(array &$graphs_to_create, array $templated_graph_indexes): void {
		foreach ($graphs_to_create as $index => &$graph) {
			if (!array_key_exists($index, $templated_graph_indexes) && array_key_exists('uuid', $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($index + 1),
						_s('unexpected parameter "%1$s"', 'uuid')
					)
				);
			}

			if (array_key_exists($index, $templated_graph_indexes) && !array_key_exists('uuid', $graph)) {
				$graph['uuid'] = generateUuidV4();
			}
		}
		unset($graph);

		$db_uuid = DB::select('graphs', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($graphs_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
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
		$dbFields = ['itemid' => null];
		$itemid_rules = ['type' => API_ID, 'flags' => API_NOT_EMPTY];

		foreach ($graphs as $graph) {
			// graph items are optional
			if (isset($graph['gitems']) && (!is_array($graph['gitems']) || !$graph['gitems'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_MISSING_GRAPH_ITEMS), $graph['name'])
				);
			}

			// validate item fields
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required only if no "gitemid" is set
					if (!isset($gitem['gitemid']) && !check_db_fields($dbFields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y min axis item ID for permission validation
			if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymin_itemid'], 'ymin_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				else {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}

			// add Y max axis item ID for permission validation
			if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymax_itemid'], 'ymax_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		$colorValidator = new CColorValidator();

		switch (get_class($this)) {
			case 'CGraph':
				$error_cannot_update = _('Cannot update "%1$s" for graph "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => []];
				break;

			case 'CGraphPrototype':
				$error_cannot_update = _('Cannot update "%1$s" for graph prototype "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'discover' => ['type' => API_INT32, 'in' => implode(',', [GRAPH_DISCOVER, GRAPH_NO_DISCOVER])]
				]];
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$read_only_fields = ['templateid', 'flags'];

		foreach ($graphs as $key => $graph) {
			$this->checkNoParameters($graph, $read_only_fields, $error_cannot_update, $graph['name']);

			$data = array_intersect_key($graph, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $data, '/'.($key + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if (array_key_exists('uuid', $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/' . ($key + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			$templatedGraph = false;

			if (isset($graph['gitems'])) {
				// first item determines to which host graph belongs to
				$gitem = array_shift($dbGraphs[$graph['graphid']]['gitems']);

				$graphHosts = API::Host()->get([
					'itemids' => $gitem['itemid'],
					'output' => ['hostid', 'status'],
					'editable' => true,
					'templated_hosts' => true
				]);

				$host = array_shift($graphHosts);

				// if the current graph is templated and new items to be added
				if (HOST_STATUS_TEMPLATE == $host['status']) {
					$templatedGraph = $host['hostid'];

					$itemIds = [];

					foreach ($graph['gitems'] as $gitem) {
						if (!isset($gitem['gitemid']) && isset($gitem['itemid'])) {
							$itemIds[] = $gitem['itemid'];
						}
					}

					if ($itemIds) {
						$itemHosts = API::Host()->get([
							'itemids' => $itemIds,
							'output' => ['hostid'],
							'editable' => true,
							'templated_hosts' => true
						]);

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
	 */
	protected function validateHostsAndTemplates(array $graphs) {
		$graphNames = [];

		foreach ($graphs as $graph) {
			// check if the host has any graphs in DB with the same name within host
			$hostsAndTemplates = API::Host()->get([
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => ['hostid'],
				'nopermissions' => true,
				'preservekeys' => true,
				'templated_hosts' => true
			]);

			$hostAndTemplateIds = array_keys($hostsAndTemplates);

			$dbGraphs = API::Graph()->get([
				'hostids' => $hostAndTemplateIds,
				'output' => ['graphid'],
				'filter' => ['name' => $graph['name'], 'flags' => null], // 'flags' => null overrides default behaviour
				'nopermissions' => true
			]);

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

			// checks that there are no two graphs with the same name within host
			foreach ($hostAndTemplateIds as $id) {
				if (!isset($graphNames[$graph['name']])) {
					$graphNames[$graph['name']] = [];
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

	/**
	 * Returns visible host name. Can be used for error reporting.
	 *
	 * @static
	 *
	 * @param string|int $hostid
	 *
	 * @return string
	 */
	private static function getHostName($hostid): string {
		return DB::select('hosts', [
			'output' => ['name'],
			'hostids' => $hostid
		])[0]['name'];
	}

	/**
	 * Updates the children of the graph on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $graphs   An array of graphs to inherit. Each graph must contain all graph properties including
	 *                             "gitems" property.
	 * @param array|null $hostids  An array of hosts to inherit to; if set to null, the graphs will be inherited to all
	 *                             linked hosts or templates.
	 * @throws APIException
	 */
	protected function inherit(array $graphs, array $hostids = null): void {
		$graphs = array_column($graphs, null, 'graphid');

		if ($hostids === null) {
			/*
			 * From the passed graphs we are able to inherit only those, which are template graphs and templates of
			 * which are linked at least to one host. There we try to find the graphs which meet these conditions.
			 */
			$graphids = DBfetchColumn(DBselect(
				'SELECT DISTINCT gi.graphid'.
				' FROM graphs_items gi,items i,hosts h,hosts_templates ht'.
				' WHERE gi.itemid=i.itemid'.
					' AND i.hostid=h.hostid'.
					' AND h.hostid=ht.templateid'.
					' AND '.dbConditionId('gi.graphid', array_keys($graphs)).
					' AND h.status='.HOST_STATUS_TEMPLATE
			), 'graphid');

			// Based on the found graphs, we leave only graphs that is possible to inherit.
			$graphs = array_intersect_key($graphs, array_flip($graphids));

			if (!$graphs) {
				return;
			}
		}

		$same_name_graphs = [];
		$itemids = [];

		foreach ($graphs as $graphid => $graph) {
			$same_name_graphs[$graph['name']][] = $graphid;

			if ($graph['ymin_itemid'] > 0) {
				$itemids[$graph['ymin_itemid']] = true;
			}

			if ($graph['ymax_itemid'] > 0) {
				$itemids[$graph['ymax_itemid']] = true;
			}

			foreach ($graph['gitems'] as $gitem) {
				$itemids[$gitem['itemid']] = true;
			}
		}

		$itemids = array_keys($itemids);

		$itemids_templateids = [];
		$templateids = [];

		$db_items = DBselect('SELECT i.itemid,i.hostid FROM items i WHERE '.dbConditionId('i.itemid', $itemids));

		while ($db_item = DBfetch($db_items)) {
			$itemids_templateids[$db_item['itemid']] = $db_item['hostid'];
			$templateids[$db_item['hostid']] = true;
		}

		$templateids = array_keys($templateids);

		$templateids_hosts = [];
		$hostids_condition = ($hostids === null) ? '' : ' AND '.dbConditionId('ht.hostid', $hostids);
		$hostids = [];

		$db_host_templates = DBselect(
			'SELECT ht.templateid,ht.hostid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionId('ht.templateid', $templateids).
				$hostids_condition
		);

		while ($db_host_template = DBfetch($db_host_templates)) {
			$templateids_hosts[$db_host_template['templateid']][$db_host_template['hostid']] = true;
			$hostids[$db_host_template['hostid']] = true;
		}

		$hostids = array_keys($hostids);

		foreach ($same_name_graphs as $name => $graphids) {
			if (count($graphids) > 1) {
				$_templateids = [];

				foreach ($graphids as $graphid) {
					$itemid = reset($graphs[$graphid]['gitems'])['itemid'];
					$_templateids[] = $itemids_templateids[$itemid];
				}

				$_templateids_count = count($_templateids);

				for ($i = 0; $i < $_templateids_count - 1; $i++) {
					for ($j = $i + 1; $j < $_templateids_count; $j++) {
						$same_hosts = array_intersect_key($templateids_hosts[$_templateids[$i]],
							$templateids_hosts[$_templateids[$j]]
						);

						if ($same_hosts) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Graph "%1$s" already exists on "%2$s".', $name, self::getHostName(key($same_hosts)))
							);
						}
					}
				}
			}
		}

		/*
		 * In case when all equivalent items to graphs templates items exists on all hosts, to which they are linked,
		 * there will be collected relations between template items and these equivalents on hosts.
		 */
		$item_links = [];

		$db_items = DBselect(
			'SELECT src.itemid AS src_itemid,src.hostid AS src_hostid,'.
				'dest.itemid AS dest_itemid,dest.hostid AS dest_hostid'.
			' FROM items src,items dest'.
			' WHERE src.itemid=dest.templateid'.
				' AND '.dbConditionId('src.itemid', $itemids).
				' AND '.dbConditionId('dest.hostid', $hostids)
		);

		while ($db_item = DBfetch($db_items)) {
			if (array_key_exists($db_item['dest_hostid'], $templateids_hosts[$db_item['src_hostid']])) {
				$item_links[$db_item['src_itemid']][$db_item['dest_hostid']] = $db_item['dest_itemid'];
			}
		}

		$child_graphs_to_add = [];
		$child_graphs_to_update = [];

		/*
		 * Since the inherit() method called either when graphs are only created (called from create() or
		 * syncTemplates() methods) or when only updated (called from update() method), the child graphs will be only
		 * found when graph was updated.
		 */
		$child_graphs = $this->get([
			'output' => ['graphid', 'name', 'templateid'],
			'selectItems' => ['hostid'],
			'hostids' => $hostids,
			'filter' => ['templateid' => array_keys($graphs)],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		// Graphs data collection to inherit in case when inherit() method was called from update() API method.
		if ($child_graphs) {
			$graphs_child_graphs = [];
			$child_graphs_hostids = [];

			foreach ($child_graphs as $child_graphid => $child_graph) {
				$graphs_child_graphs[$child_graph['templateid']][$child_graphid] = $child_graph;

				/*
				 * Since graph on template can have only items of this template, the hostid also will be the same for
				 * all child graph items.
				 */
				$child_graphs_hostids[$child_graphid] = reset($child_graph['items'])['hostid'];
			}

			$graphs_changed_names_child_graphids = [];

			foreach ($graphs as $graphid => $graph) {
				foreach ($graphs_child_graphs[$graphid] as $child_graphid => $child_graph) {
					/*
					 * If template graph name was changed, we collect all that names to check whether graphs with the
					 * same name already exists on child hosts/templates.
					 */
					if ($graph['name'] !== $child_graph['name']) {
						$graphs_changed_names_child_graphids[$graph['name']][] = $child_graphid;
					}

					$child_graph_hostid = reset($child_graph['items'])['hostid'];
					$child_graph_to_update = ['graphid' => $child_graphid, 'templateid' => $graphid] + $graph;

					if ($graph['ymin_itemid'] != 0) {
						$child_graph_to_update['ymin_itemid'] = $item_links[$graph['ymin_itemid']][$child_graph_hostid];
					}

					if ($graph['ymax_itemid'] != 0) {
						$child_graph_to_update['ymax_itemid'] = $item_links[$graph['ymax_itemid']][$child_graph_hostid];
					}

					foreach ($child_graph_to_update['gitems'] as &$gitem) {
						$gitem['itemid'] = $item_links[$gitem['itemid']][$child_graph_hostid];
					}
					unset($gitem);

					$child_graphs_to_update[$child_graphid] = $child_graph_to_update;
				}
			}

			if ($graphs_changed_names_child_graphids) {
				$possible_duplicate_name_hostids = [];
				$db_possible_duplicate_name_graphs = DBselect(
					'SELECT g.name,i.hostid'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid AND gi.itemid=i.itemid'.
						' AND '.dbConditionString('g.name', array_keys($graphs_changed_names_child_graphids)).
						' AND '.dbConditionId('i.hostid', $hostids).
					' GROUP BY g.name,i.hostid'
				);

				while ($row = DBfetch($db_possible_duplicate_name_graphs)) {
					$possible_duplicate_name_hostids[$row['name']][] = $row['hostid'];
				}

				if ($possible_duplicate_name_hostids) {
					foreach ($graphs_changed_names_child_graphids as $name => $child_graphids) {
						if (array_key_exists($name, $possible_duplicate_name_hostids)) {
							foreach ($child_graphids as $child_graphid) {
								$duplicate_name_hostid = array_intersect($possible_duplicate_name_hostids[$name],
									[$child_graphs_hostids[$child_graphid]]
								);
								$duplicate_name_hostid = reset($duplicate_name_hostid);

								if ($duplicate_name_hostid) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Graph "%1$s" already exists on "%2$s".', $name,
											self::getHostName($duplicate_name_hostid)
										)
									);
								}
							}
						}
					}
				}
			}
		}

		/*
		 * Graphs data collection to inherit in case when inherit() method was called from create() or syncTemplates()
		 * API method.
		 */
		else {
			$graphids_names = [];
			$graphs_names_required_hosts = [];
			$hostids_graph_names_parent_graphids = [];
			$hostids_graphs = [];

			foreach ($graphs as $graphid => $graph) {
				$graphids_names[$graphid] = $graph['name'];

				$itemid = reset($graph['gitems'])['itemid'];
				$templateid = $itemids_templateids[$itemid];

				if (!array_key_exists($graph['name'], $graphs_names_required_hosts)) {
					$graphs_names_required_hosts[$graph['name']] = [];
				}

				$graphs_names_required_hosts[$graph['name']] += $templateids_hosts[$templateid];

				foreach (array_keys($templateids_hosts[$templateid]) as $hostid) {
					$hostids_graph_names_parent_graphids[$hostid][$graph['name']] = $graphid;
					$hostids_graphs[$hostid][$graphid] = true;
				}
			}

			$possible_same_name_hosts_graphs = $this->get([
				'output' => ['graphid', 'name', 'templateid', 'flags'],
				'selectGraphItems' => ['gitemid', 'graphid', 'itemid'],
				'selectItems' => ['hostid'],
				'hostids' => $hostids,
				'filter' => ['name' => $graphids_names],
				'nopermissions' => true,
				'preservekeys' => true
			]);

			$parent_graphids_updated_hosts = [];

			foreach ($possible_same_name_hosts_graphs as $graphid => $graph) {
				$graph_hostids = array_unique(array_column($graph['items'], 'hostid'));
				$required_hostids = array_intersect($graph_hostids,
					array_keys($graphs_names_required_hosts[$graph['name']])
				);

				if (!$required_hostids) {
					continue;
				}

				if (count($graph_hostids) > 1) {
					$hostid = reset($required_hostids);
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $graph['name'],
							self::getHostName(reset($required_hostids))
						)
					);
				}

				$hostid = reset($graph_hostids);

				$parent_graphid = $hostids_graph_names_parent_graphids[$hostid][$graph['name']];
				$parent_graph = $graphs[$parent_graphid];

				if ($graph['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Graph "%1$s" already exists on "%2$s" (inherited from another template).', $graph['name'],
						self::getHostName($hostid)
					));
				}
				elseif  ($graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Graph with same name but other type exist.')
					);
				}

				if (count($graph['gitems']) === count($parent_graph['gitems'])) {
					$gitems_itemids = array_column($graph['gitems'], 'itemid');
					$child_graph_to_update = [];

					foreach ($parent_graph['gitems'] as $parent_gitem) {
						$index = array_search($item_links[$parent_gitem['itemid']][$hostid], $gitems_itemids);

						if ($index === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Graph "%1$s" already exists on "%2$s" (items are not identical).',
									$parent_graph['name'], self::getHostName($hostid)
								)
							);
						}

						unset($gitems_itemids[$index]);

						$parent_gitem['itemid'] = $item_links[$parent_gitem['itemid']][$hostid];
						$child_graph_to_update['gitems'][] = $parent_gitem;
					}

					$child_graph_to_update = ['graphid' => $graphid, 'templateid' => $parent_graphid,
						'gitems' => $child_graph_to_update['gitems']
					] + $parent_graph;

					if ($parent_graph['ymin_itemid'] != 0) {
						$child_graph_to_update['ymin_itemid'] = $item_links[$parent_graph['ymin_itemid']][$hostid];
					}

					if ($parent_graph['ymax_itemid'] != 0) {
						$child_graph_to_update['ymax_itemid'] = $item_links[$parent_graph['ymax_itemid']][$hostid];
					}

					$child_graphs_to_update[] = $child_graph_to_update;

					$parent_graphids_updated_hosts[$parent_graphid][$hostid] = true;
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $parent_graph['name'],
							self::getHostName($hostid)
						)
					);
				}
			}

			foreach ($graphs as $graphid => $graph) {
				$itemid = reset($graph['gitems'])['itemid'];
				$templateid = $itemids_templateids[$itemid];

				$hosts_to_create_graph = array_diff_key($templateids_hosts[$templateid],
					array_key_exists($graphid, $parent_graphids_updated_hosts)
						? $parent_graphids_updated_hosts[$graphid]
						: []
				);

				foreach (array_keys($hosts_to_create_graph) as $hostid) {
					$child_graph_to_add = ['templateid' => $graphid] + array_diff_key($graph, ['graphid' => true]);

					if ($graph['ymin_itemid'] != 0) {
						$child_graph_to_add['ymin_itemid'] = $item_links[$graph['ymin_itemid']][$hostid];
					}

					if ($graph['ymax_itemid'] != 0) {
						$child_graph_to_add['ymax_itemid'] = $item_links[$graph['ymax_itemid']][$hostid];
					}

					foreach ($child_graph_to_add['gitems'] as &$gitem) {
						$gitem['itemid'] = $item_links[$gitem['itemid']][$hostid];
					}
					unset($gitem);

					$child_graphs_to_add[] = $child_graph_to_add;
				}
			}
		}

		if ($child_graphs_to_add) {
			$this->createReal($child_graphs_to_add);
			$child_graphs_to_add = zbx_toHash($child_graphs_to_add, 'graphid');
		}

		if ($child_graphs_to_update) {
			$this->updateReal($child_graphs_to_update);
		}

		$this->inherit($child_graphs_to_add + $child_graphs_to_update);
	}

	/**
	 * Inherit template graphs from template to host.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data): void {
		$output = ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'templateid', 'show_work_period',
			'show_triggers', 'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right', 'ymin_type',
			'ymax_type', 'ymin_itemid', 'ymax_itemid'
		];

		if ($this instanceof CGraphPrototype) {
			$output[] = 'discover';
		}

		$graphs = $this->get([
			'output' => $output,
			'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'hostids' => $data['templateids'],
			'preservekeys' => true
		]);

		if ($graphs) {
			$this->inherit($graphs, $data['hostids']);
		}
	}
}
