<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		$graphids = array_column($graphs, 'graphid');

		$graphs = $this->extendObjects($this->tableName(), $graphs,
			['name', 'graphtype', 'ymin_type', 'ymin_itemid', 'ymax_type', 'ymax_itemid', 'yaxismin', 'yaxismax']
		);

		$db_graphs = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => ['gitemid', 'itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc',
				'type'
			],
			'graphids' => $graphids,
			'editable' => true,
			'preservekeys' => true,
			'inherited' => false
		]);

		$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
			'messageAllowed' => _('Cannot update a discovered graph.')
		]);

		foreach ($db_graphs as &$db_graph) {
			$db_graph['gitems'] = array_column($db_graph['gitems'], null, 'gitemid');
		}
		unset($db_graph);

		foreach ($graphs as &$graph) {
			// check permissions
			if (!array_key_exists($graph['graphid'], $db_graphs)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// cannot update discovered graphs
			$this->checkPartialValidator($graph, $updateDiscoveredValidator, $db_graphs[$graph['graphid']]);

			// validate items on set or pass existing items from DB
			if (array_key_exists('gitems', $graph)) {
				foreach ($graph['gitems'] as &$gitem) {
					if (array_key_exists('gitemid', $gitem)) {
						if (!array_key_exists($gitem['gitemid'], $db_graphs[$graph['graphid']]['gitems'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No permissions to referred object or it does not exist!')
							);
						}

						$gitem += $db_graphs[$graph['graphid']]['gitems'][$gitem['gitemid']];
					}
				}
				unset($gitem);
			}
			else {
				$graph['gitems'] = $db_graphs[$graph['graphid']]['gitems'];
			}
		}
		unset($graph);

		$this->validateUpdate($graphs, $db_graphs);

		foreach ($graphs as &$graph) {
			unset($graph['templateid']);

			$graph['gitems'] = isset($graph['gitems']) ? $graph['gitems'] : $db_graphs[$graph['graphid']]['gitems'];

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

		$resource = ($this instanceof CGraph) ? CAudit::RESOURCE_GRAPH : CAudit::RESOURCE_GRAPH_PROTOTYPE;
		$this->addAuditBulk(CAudit::ACTION_UPDATE, $resource, $graphs, $db_graphs);

		return ['graphids' => $graphids];
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

		$resource = ($this instanceof CGraph) ? CAudit::RESOURCE_GRAPH : CAudit::RESOURCE_GRAPH_PROTOTYPE;
		$this->addAuditBulk(CAudit::ACTION_ADD, $resource, $graphs);

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
			'output' => ['gitemid', 'itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'graphids' => array_column($graphs, 'graphid'),
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$ins_graph_items = [];
		$upd_graph_items = [];

		foreach ($graphs as $graph) {
			$sort_order = 0;

			foreach ($graph['gitems'] as $graph_item) {
				// Update an existing item.
				if (array_key_exists('gitemid', $graph_item)
						&& array_key_exists($graph_item['gitemid'], $db_graph_items)) {
					$db_graph_item = $db_graph_items[$graph_item['gitemid']];
					$upd_graph_item = DB::getUpdatedValues('graphs_items', $graph_item, $db_graph_item);

					if ($upd_graph_item) {
						$upd_graph_items[] = [
							'values' => $upd_graph_item,
							'where' => ['gitemid' => $graph_item['gitemid']]
						];
					}

					unset($db_graph_items[$graph_item['gitemid']]);
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

		if ($db_graph_items) {
			DB::delete('graphs_items', ['gitemid' => array_keys($db_graph_items)]);
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
	 * Adding graph items for selected graphs.
	 *
	 * @static
	 *
	 * @param array $graphs
	 * @param bool  $with_hostid
	 *
	 * @return array
	 */
	private static function addGraphItems(array $graphs, bool $with_hostid = false): array {
		$sql = $with_hostid
			? 'SELECT gi.gitemid,gi.graphid,gi.itemid,i.hostid'.
				' FROM graphs_items gi,items i'.
				' WHERE gi.itemid=i.itemid'.
					' AND '.dbConditionId('gi.graphid', array_keys($graphs)).
				' ORDER BY gi.sortorder'
			: 'SELECT gi.gitemid,gi.graphid,gi.itemid'.
				' FROM graphs_items gi'.
				' WHERE '.dbConditionId('gi.graphid', array_keys($graphs)).
				' ORDER BY gi.sortorder';
		$db_graph_items = DBselect($sql);

		while ($db_graph_item = DBfetch($db_graph_items)) {
			$graphid = $db_graph_item['graphid'];
			unset($db_graph_item['graphid']);

			$graphs[$graphid]['gitems'][] = $db_graph_item;
		}

		return $graphs;
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
				' FROM graphs_items gi,items i,hosts h,hosts_templates ht,hosts h2'.
				' WHERE gi.itemid=i.itemid'.
					' AND i.hostid=h.hostid'.
					' AND h.hostid=ht.templateid'.
					' AND ht.hostid=h2.hostid'.
					' AND '.dbConditionId('gi.graphid', array_keys($graphs)).
					' AND h.status='.HOST_STATUS_TEMPLATE.
					' AND '.dbConditionInt('h2.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED])
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

			if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid'] != 0) {
				$itemids[$graph['ymin_itemid']] = true;
			}

			if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid'] != 0) {
				$itemids[$graph['ymax_itemid']] = true;
			}

			foreach ($graph['gitems'] as $gitem) {
				$itemids[$gitem['itemid']] = true;
			}
		}

		$itemids = array_keys($itemids);

		$graph_templateids = [];
		$templateids = [];

		$db_graph_templates = DBselect(
			'SELECT DISTINCT gi.graphid,i.hostid'.
			' FROM graphs_items gi,items i'.
			' WHERE gi.itemid=i.itemid'.
				' AND '.dbConditionId('gi.graphid', array_keys($graphs))
		);

		while ($db_graph_template = DBfetch($db_graph_templates)) {
			$graph_templateids[$db_graph_template['graphid']] = $db_graph_template['hostid'];
			$templateids[$db_graph_template['hostid']] = true;
		}

		$templateids_hosts = [];

		$sql = 'SELECT ht.templateid,ht.hostid'.
			' FROM hosts_templates ht,hosts h2'.
			' WHERE ht.hostid=h2.hostid'.
				' AND '.dbConditionId('ht.templateid', array_keys($templateids)).
				' AND '.dbConditionInt('h2.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]);
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionId('ht.hostid', $hostids);
		}
		$db_host_templates = DBselect($sql);

		while ($db_host_template = DBfetch($db_host_templates)) {
			$templateids_hosts[$db_host_template['templateid']][$db_host_template['hostid']] = true;
		}

		foreach ($same_name_graphs as $name => $graphids) {
			if (count($graphids) > 1) {
				$_templateids = [];

				foreach ($graphids as $graphid) {
					$_templateids[] = $graph_templateids[$graphid];
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

		$sql = 'SELECT src.itemid AS src_itemid,dest.itemid AS dest_itemid,dest.hostid AS dest_hostid'.
			' FROM items src,items dest'.
			' WHERE src.itemid=dest.templateid'.
				' AND '.dbConditionId('src.itemid', $itemids);
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionId('dest.hostid', $hostids);
		}
		$db_items = DBselect($sql);

		while ($db_item = DBfetch($db_items)) {
			$item_links[$db_item['src_itemid']][$db_item['dest_hostid']] = $db_item['dest_itemid'];
		}

		$chd_graphs_tpl = [];
		$chd_graphs_name = [];

		// Preparing list of child graphs by graph templateid.
		$sql = 'SELECT DISTINCT g.graphid,g.name,g.templateid,i.hostid'.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND '.dbConditionId('g.templateid', array_keys($graphs));
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionId('i.hostid', $hostids);
		}
		$chd_graphs = DBfetchArrayAssoc(DBselect($sql), 'graphid');

		if ($chd_graphs) {
			$chd_graphs = self::addGraphItems($chd_graphs);

			foreach ($chd_graphs as $chd_graph) {
				$chd_graphs_tpl[$chd_graph['hostid']][$chd_graph['templateid']] = array_intersect_key($chd_graph,
					array_flip(['graphid', 'name', 'gitems'])
				);
			}
		}

		$hostids_by_name = [];

		// Preparing list of child graphs by graph name.
		foreach ($graph_templateids as $graphid => $templateid) {
			foreach (array_keys($templateids_hosts[$templateid]) as $hostid) {
				if (!array_key_exists($hostid, $chd_graphs_tpl)
						|| !array_key_exists($graphid, $chd_graphs_tpl[$hostid])) {
					$hostids_by_name[$graphs[$graphid]['name']][] = $hostid;
				}
			}
		}

		$chd_graphs = [];

		foreach ($hostids_by_name as $name => $_hostids) {
			$flags = $this instanceof CGraph
					? [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
					: [ZBX_FLAG_DISCOVERY_PROTOTYPE];
			$sql = 'SELECT g.graphid,g.name,g.templateid,g.flags'.
				' FROM graphs g'.
				' WHERE '.dbConditionString('g.name', [$name]).
					' AND '.dbConditionInt('g.flags', $flags).
					' AND EXISTS ('.
						'SELECT NULL'.
						' FROM graphs_items gi,items i'.
						' WHERE g.graphid=gi.graphid'.
							' AND gi.itemid=i.itemid'.
							' AND '.dbConditionId('i.hostid', $_hostids).
					')';
			$chd_graphs += DBfetchArrayAssoc(DBselect($sql), 'graphid');
		}

		if ($chd_graphs) {
			$chd_graphs = self::addGraphItems($chd_graphs, true);

			foreach ($chd_graphs as $chd_graph) {
				$hostid = $chd_graph['gitems'][0]['hostid'];

				if ($chd_graph['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Graph "%1$s" already exists on "%2$s" (inherited from another template).', $chd_graph['name'],
						self::getHostName($hostid)
					));
				}
				elseif ($this instanceof CGraph && $chd_graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Graph "%1$s" already exists on "%2$s" as a graph created from graph prototype.',
						$chd_graph['name'], self::getHostName($hostid)
					));
				}

				$chd_graphs_name[$hostid][$chd_graph['name']] = array_intersect_key($chd_graph,
					array_flip(['graphid', 'name', 'gitems'])
				);
			}
		}

		$ins_graphs = [];
		$upd_graphs = [];
		$upd_hostids_by_name = [];

		foreach ($graphs as $graphid => $graph) {
			$templateid = $graph_templateids[$graphid];

			foreach (array_keys($templateids_hosts[$templateid]) as $hostid) {
				$chd_graph = null;

				if (array_key_exists($hostid, $chd_graphs_tpl)
						&& array_key_exists($graphid, $chd_graphs_tpl[$hostid])) {
					$chd_graph = $chd_graphs_tpl[$hostid][$graphid];

					/*
					 * If template graph name was changed, we collect all that names to check whether graphs with the
					 * same name already exists on child hosts/templates.
					 */
					if ($graph['name'] !== $chd_graph['name']) {
						$upd_hostids_by_name[$graph['name']][] = $hostid;
					}

					$_graph = ['graphid' => $chd_graph['graphid'], 'templateid' => $graphid] + $graph;
				}
				elseif (array_key_exists($hostid, $chd_graphs_name)
						&& array_key_exists($graph['name'], $chd_graphs_name[$hostid])) {
					$chd_graph = $chd_graphs_name[$hostid][$graph['name']];
					$chd_graph_itemids = array_column($chd_graph['gitems'], 'itemid');

					if (count($graph['gitems']) !== count($chd_graph['gitems'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Graph "%1$s" already exists on "%2$s" (items are not identical).', $graph['name'],
							self::getHostName($hostid)
						));
					}

					foreach ($graph['gitems'] as $gitem) {
						$index = array_search($item_links[$gitem['itemid']][$hostid], $chd_graph_itemids);

						if ($index === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Graph "%1$s" already exists on "%2$s" (items are not identical).', $graph['name'],
								self::getHostName($hostid)
							));
						}

						unset($chd_graph_itemids[$index]);
					}

					$_graph = ['graphid' => $chd_graph['graphid'], 'templateid' => $graphid] + $graph;
				}
				else {
					$_graph = ['templateid' => $graphid] + array_diff_key($graph, ['graphid' => true]);
				}

				$_graph['uuid'] = '';

				if ($_graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $_graph['ymin_itemid'] != 0) {
					$_graph['ymin_itemid'] = $item_links[$_graph['ymin_itemid']][$hostid];
				}

				if ($_graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $_graph['ymax_itemid'] != 0) {
					$_graph['ymax_itemid'] = $item_links[$_graph['ymax_itemid']][$hostid];
				}

				CArrayHelper::sort($_graph['gitems'], ['sortorder']);

				foreach ($_graph['gitems'] as &$gitem) {
					$gitem['itemid'] = $item_links[$gitem['itemid']][$hostid];

					if ($chd_graph !== null && $chd_graph['gitems']) {
						$gitem['gitemid'] = array_shift($chd_graph['gitems'])['gitemid'];
					}
				}
				unset($gitem);

				if ($chd_graph !== null) {
					$upd_graphs[] = $_graph;
				}
				else {
					$ins_graphs[] = $_graph;
				}
			}
		}

		// Check if graph with a new name already exists on the child host.
		if ($upd_hostids_by_name) {
			$sql_where = [];
			foreach ($upd_hostids_by_name as $name => $_hostids) {
				$sql_where[] = dbConditionInt('i.hostid', $_hostids).' AND g.name='.zbx_dbstr($name);
			}

			$sql = 'SELECT i.hostid,g.name'.
				' FROM graphs g,graphs_items gi,items i'.
				' WHERE g.graphid=gi.graphid'.
					' AND gi.itemid=i.itemid'.
					' AND (('.implode(') OR (', $sql_where).'))';
			$db_graphs = DBselect($sql, 1);

			if ($db_graph = DBfetch($db_graphs)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s".',
					$db_graph['name'], self::getHostName($db_graph['hostid'])
				));
			}
		}

		if ($ins_graphs) {
			$this->createReal($ins_graphs);
		}

		if ($upd_graphs) {
			$this->updateReal($upd_graphs);
		}

		$this->inherit(array_merge($ins_graphs + $upd_graphs));
	}

	/**
	 * Inherit template graphs from template to host.
	 *
	 * @param array $data
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
			'preservekeys' => true,
			'nopermissions' => true
		]);

		if ($graphs) {
			$this->inherit($graphs, $data['hostids']);
		}
	}
}
