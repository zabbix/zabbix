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


/**
 * Class containing methods for operations with discovery rules.
 */
class CDiscoveryRule extends CItemGeneral {

	const MIN_LIFETIME = 0;
	const MAX_LIFETIME = 3650;

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_EXISTS_TEMPLATE => _('Discovery rule "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Discovery rule "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for discovery rule "%2$s" on "%3$s": %4$s.')
		]);
	}

	/**
	 * Get DiscoveryRule data
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['i.flags='.ZBX_FLAG_DISCOVERY_RULE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'itemids'					=> null,
			'interfaceids'				=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectHostPrototypes'		=> null,
			'selectFilter'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['where']['interfaceid'] = dbConditionInt('i.interfaceid', $options['interfaceids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.interfaceid';
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = dbConditionString('h.host', $options['filter']['host']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $item;
				}
				else {
					$result = $item['rowscount'];
				}
			}
			else {
				$result[$item['itemid']] = $item;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);

			foreach ($result as &$rule) {
				// unset the fields that are returned in the filter
				unset($rule['formula'], $rule['evaltype']);

				if ($options['selectFilter'] !== null) {
					$filter = $this->unsetExtraFields([$rule['filter']],
						['conditions', 'formula', 'evaltype'],
						$options['selectFilter']
					);
					$filter = reset($filter);
					if (isset($filter['conditions'])) {
						foreach ($filter['conditions'] as &$condition) {
							unset($condition['item_conditionid'], $condition['itemid']);
						}
						unset($condition);
					}

					$rule['filter'] = $filter;
				}
			}
			unset($rule);
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add DiscoveryRule.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function create($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items);
		$this->createReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Update DiscoveryRule.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function update($items) {
		$items = zbx_toArray($items);

		$dbItems = $this->get([
			'output' => ['itemid', 'name'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'itemids' => zbx_objectValues($items, 'itemid'),
			'preservekeys' => true
		]);

		$this->checkInput($items, true, $dbItems);

		// set the default values required for updating
		foreach ($items as &$item) {
			if (isset($item['filter'])) {
				foreach ($item['filter']['conditions'] as &$condition) {
					$condition += [
						'operator' => DB::getDefault('item_condition', 'operator')
					];
				}
				unset($condition);
			}
		}
		unset($item);

		// update
		$this->updateReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Delete DiscoveryRules.
	 *
	 * @param array $ruleids
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $ruleids, $nopermissions = false) {
		if (empty($ruleids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$ruleids = array_keys(array_flip($ruleids));

		$delRules = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $ruleids,
			'editable' => true,
			'preservekeys' => true,
			'selectHosts' => ['name']
		]);

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($ruleids as $ruleid) {
				if (!isset($delRules[$ruleid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delRules[$ruleid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated items.'));
				}
			}
		}

		// get child discovery rules
		$parentItemids = $ruleids;
		$childTuleids = [];
		do {
			$dbItems = DBselect('SELECT i.itemid FROM items i WHERE '.dbConditionInt('i.templateid', $parentItemids));
			$parentItemids = [];
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[$dbItem['itemid']] = $dbItem['itemid'];
				$childTuleids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while (!empty($parentItemids));

		$delRulesChildren = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $childTuleids,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => ['name']
		]);

		$delRules = array_merge($delRules, $delRulesChildren);
		$ruleids = array_merge($ruleids, $childTuleids);

		$iprototypeids = [];
		$dbItems = DBselect(
			'SELECT i.itemid'.
			' FROM item_discovery id,items i'.
			' WHERE i.itemid=id.itemid'.
				' AND '.dbConditionInt('parent_itemid', $ruleids)
		);
		while ($item = DBfetch($dbItems)) {
			$iprototypeids[$item['itemid']] = $item['itemid'];
		}
		if (!empty($iprototypeids)) {
			if (!API::ItemPrototype()->delete($iprototypeids, true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete discovery rule'));
			}
		}

		// delete host prototypes
		$hostPrototypeIds = DBfetchColumn(DBselect(
			'SELECT hd.hostid'.
			' FROM host_discovery hd'.
			' WHERE '.dbConditionInt('hd.parent_itemid', $ruleids)
		), 'hostid');
		if ($hostPrototypeIds) {
			if (!API::HostPrototype()->delete($hostPrototypeIds, true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete host prototype.'));
			}
		}

		// delete LLD rules
		DB::delete('items', ['itemid' => $ruleids]);

		// TODO: remove info from API
		foreach ($delRules as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}

		return ['ruleids' => $ruleids];
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids    an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::Template()->get([
				'countOutput' => true,
				'templateids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Copies the given discovery rules to the specified hosts.
	 *
	 * @throws APIException if no discovery rule IDs or host IDs are given or
	 * the user doesn't have the necessary permissions.
	 *
	 * @param array $data
	 * @param array $data['discoveryruleids']	An array of item ids to be cloned
	 * @param array $data['hostids']			An array of host ids were the items should be cloned to
	 *
	 * @return bool
	 */
	public function copy(array $data) {
		// validate data
		if (!isset($data['discoveryids']) || !$data['discoveryids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No discovery rule IDs given.'));
		}
		if (!isset($data['hostids']) || !$data['hostids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No host IDs given.'));
		}

		$this->checkHostPermissions($data['hostids']);

		// check if the given discovery rules exist
		$count = $this->get([
			'countOutput' => true,
			'itemids' => $data['discoveryids']
		]);

		if ($count != count($data['discoveryids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// copy
		foreach ($data['discoveryids'] as $discoveryid) {
			foreach ($data['hostids'] as $hostid) {
				$this->copyDiscoveryRule($discoveryid, $hostid);
			}
		}

		return true;
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$selectFields = [];
		foreach ($this->fieldRules as $key => $rules) {
			if (!isset($rules['system']) && !isset($rules['host'])) {
				$selectFields[] = $key;
			}
		}

		$items = $this->get([
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => $selectFields,
			'selectFilter' => ['formula', 'evaltype', 'conditions']
		]);

		$this->inherit($items, $data['hostids']);

		return true;
	}

	/**
	 * Copies all of the triggers from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if trigger saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 * @param array $srcHost         The host the source discovery belongs to
	 * @param array $dstHost         The host the target discovery belongs to
	 *
	 * @return array
	 */
	protected function copyTriggerPrototypes(array $srcDiscovery, array $dstDiscovery, array $srcHost, array $dstHost) {
		$srcTriggers = API::TriggerPrototype()->get([
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => ['triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments',
				'templateid', 'type', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag'
			],
			'selectHosts' => API_OUTPUT_EXTEND,
			'selectItems' => ['itemid', 'type'],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND,
			'selectDependencies' => ['triggerid'],
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true
		]);

		if (!$srcTriggers) {
			return [];
		}

		foreach ($srcTriggers as $id => $trigger) {
			// Skip trigger prototypes with web items and remove them from source.
			if (httpItemExists($trigger['items'])) {
				unset($srcTriggers[$id]);
			}
		}

		/*
		 * Copy the remaining trigger prototypes to a new source. These will contain IDs and original dependencies.
		 * The dependencies from $srcTriggers will be removed.
		 */
		$trigger_prototypes = $srcTriggers;

		// Contains original trigger prototype dependency IDs.
		$dep_triggerids = [];

		/*
		 * Collect dependency trigger IDs and remove them from source. Otherwise these IDs do not pass
		 * validation, since they don't belong to destination discovery rule.
		 */
		$add_dependencies = false;

		foreach ($srcTriggers as $id => &$trigger) {
			if ($trigger['dependencies']) {
				foreach ($trigger['dependencies'] as $dep_trigger) {
					$dep_triggerids[] = $dep_trigger['triggerid'];
				}
				$add_dependencies = true;
			}
			unset($trigger['dependencies']);
		}
		unset($trigger);

		// Save new trigger prototypes and without dependencies for now.
		$dstTriggers = $srcTriggers;
		$dstTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dstTriggers,
			['sources' => ['expression', 'recovery_expression']]
		);
		foreach ($dstTriggers as $id => &$trigger) {
			unset($dstTriggers[$id]['triggerid'], $dstTriggers[$id]['templateid']);

			// Update the destination expressions.
			$trigger['expression'] = triggerExpressionReplaceHost($trigger['expression'], $srcHost['host'],
				$dstHost['host']
			);
			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$trigger['recovery_expression'] = triggerExpressionReplaceHost($trigger['recovery_expression'],
					$srcHost['host'], $dstHost['host']
				);
			}
		}
		unset($trigger);

		$result = API::TriggerPrototype()->create($dstTriggers);
		if (!$result) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone trigger prototypes.'));
		}

		// Process dependencies, if at least one trigger prototype has a dependency.
		if ($add_dependencies) {
			$trigger_prototypeids = array_keys($trigger_prototypes);

			foreach ($result['triggerids'] as $i => $triggerid) {
				$new_trigger_prototypes[$trigger_prototypeids[$i]] = [
					'new_triggerid' => $triggerid,
					'new_hostid' => $dstHost['hostid'],
					'new_host' => $dstHost['host'],
					'src_hostid' => $srcHost['hostid'],
					'src_host' => $srcHost['host']
				];
			}

			/*
			 * Search for original dependent triggers and expressions to find corresponding triggers on destination host
			 * with same expression.
			 */
			$dep_triggers = API::Trigger()->get([
				'output' => ['description', 'expression'],
				'selectHosts' => ['hostid'],
				'triggerids' => $dep_triggerids,
				'preservekeys' => true
			]);
			$dep_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dep_triggers);

			// Map dependencies to the new trigger IDs and save.
			foreach ($trigger_prototypes as &$trigger_prototype) {
				// Get corresponding created trigger prototype ID.
				$new_trigger_prototype = $new_trigger_prototypes[$trigger_prototype['triggerid']];

				if ($trigger_prototype['dependencies']) {
					foreach ($trigger_prototype['dependencies'] as &$dependency) {
						$dep_triggerid = $dependency['triggerid'];

						/*
						 * We have added a dependent trigger prototype and we know corresponding trigger prototype ID
						 * for newly created trigger prototype.
						 */
						if (array_key_exists($dependency['triggerid'], $new_trigger_prototypes)) {
							/*
							 * Dependency is within same host according to $srcHostId parameter or dep trigger has
							 * single host.
							 */
							if ($new_trigger_prototype['src_hostid'] ==
									$new_trigger_prototypes[$dep_triggerid]['src_hostid']) {
								$dependency['triggerid'] = $new_trigger_prototypes[$dep_triggerid]['new_triggerid'];
							}
						}
						elseif (in_array(['hostid' => $new_trigger_prototype['src_hostid']],
								$dep_triggers[$dep_triggerid]['hosts'])) {
							// Get all possible $depTrigger matching triggers by description.
							$target_triggers = API::Trigger()->get([
								'output' => ['hosts', 'triggerid', 'expression'],
								'hostids' => $new_trigger_prototype['new_hostid'],
								'filter' => ['description' => $dep_triggers[$dep_triggerid]['description']],
								'preservekeys' => true
							]);
							$target_triggers = CMacrosResolverHelper::resolveTriggerExpressions($target_triggers);

							// Compare exploded expressions for exact match.
							$expr1 = $dep_triggers[$dep_triggerid]['expression'];
							$dependency['triggerid'] = null;

							foreach ($target_triggers as $target_trigger) {
								$expr2 = triggerExpressionReplaceHost($target_trigger['expression'],
									$new_trigger_prototype['new_host'],
									$new_trigger_prototype['src_host']
								);

								if ($expr2 === $expr1) {
									// Matching trigger has been found.
									$dependency['triggerid'] = $target_trigger['triggerid'];
									break;
								}
							}

							// If matching trigger was not found, raise exception.
							if ($dependency['triggerid'] === null) {
								$expr2 = triggerExpressionReplaceHost($dep_triggers[$dep_triggerid]['expression'],
									$new_trigger_prototype['src_host'],
									$new_trigger_prototype['new_host']
								);
								error(_s(
									'Cannot add dependency from trigger "%1$s:%2$s" to non existing trigger "%3$s:%4$s".',
									$trigger_prototype['description'],
									$trigger_prototype['expression'],
									$dep_triggers[$dep_triggerid]['description'],
									$expr2
								));

								return false;
							}
						}
					}
					unset($dependency);

					$trigger_prototype['triggerid'] = $new_trigger_prototype['new_triggerid'];
				}
			}
			unset($trigger_prototype);

			// If adding a dependency fails, the exception will be raised in TriggerPrototype API.
			API::TriggerPrototype()->addDependencies($trigger_prototypes);
		}

		return $result;
	}

	protected function createReal(&$items) {
		// create items without formulas, they will be updated when items and conditions are saved
		$createItems = [];
		foreach ($items as $item) {
			if (isset($item['filter'])) {
				$item['evaltype'] = $item['filter']['evaltype'];
				unset($item['filter']);
			}

			$createItems[] = $item;
		}
		$createItems = DB::save('items', $createItems);

		$conditions = [];
		foreach ($items as $key => &$item) {
			$item['itemid'] = $createItems[$key]['itemid'];

			// conditions
			if (isset($item['filter'])) {
				foreach ($item['filter']['conditions'] as $condition) {
					$condition['itemid'] = $item['itemid'];

					$conditions[] = $condition;
				}
			}
		}
		unset($item);

		$conditions = DB::save('item_condition', $conditions);

		// update formulas
		$itemConditions = [];
		foreach ($conditions as $condition) {
			$itemConditions[$condition['itemid']][] = $condition;
		}
		foreach ($items as $item) {
			if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($item['itemid'], $item['filter']['formula'], $itemConditions[$item['itemid']]);
			}
		}

		// TODO: REMOVE info
		$itemHosts = $this->get([
			'itemids' => zbx_objectValues($items, 'itemid'),
			'output' => ['key_', 'name'],
			'selectHosts' => ['name'],
			'nopermissions' => true
		]);
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	protected function updateReal($items) {
		$items = zbx_toArray($items);

		$ruleIds = zbx_objectValues($items, 'itemid');
		$exRules = $this->get([
			'itemids' => $ruleIds,
			'output' => ['key_', 'name'],
			'selectHosts' => ['name'],
			'selectFilter' => ['evaltype'],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$data = [];
		foreach ($items as $item) {
			$values = $item;

			if (isset($item['filter'])) {
				// clear the formula for non-custom expression rules
				if ($item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					$values['formula'] = '';
				}

				$values['evaltype'] = $item['filter']['evaltype'];
				unset($values['filter']);
			}

			$data[] = ['values' => $values, 'where' => ['itemid' => $item['itemid']]];
		}
		DB::update('items', $data);

		$newRuleConditions = null;
		foreach ($items as $item) {
			// conditions
			if (isset($item['filter'])) {
				if ($newRuleConditions === null) {
					$newRuleConditions = [];
				}

				$newRuleConditions[$item['itemid']] = [];
				foreach ($item['filter']['conditions'] as $condition) {
					$condition['itemid'] = $item['itemid'];

					$newRuleConditions[$item['itemid']][] = $condition;
				}
			}
		}

		// replace conditions
		$ruleConditions = [];
		if ($newRuleConditions !== null) {
			// fetch existing conditions
			$exConditions = DBfetchArray(DBselect(
				'SELECT item_conditionid,itemid,macro,value,operator'.
				' FROM item_condition'.
				' WHERE '.dbConditionInt('itemid', $ruleIds).
				' ORDER BY item_conditionid'
			));
			$exRuleConditions = [];
			foreach ($exConditions as $condition) {
				$exRuleConditions[$condition['itemid']][] = $condition;
			}

			// replace and add the new IDs
			$conditions = DB::replaceByPosition('item_condition', $exRuleConditions, $newRuleConditions);
			foreach ($conditions as $condition) {
				$ruleConditions[$condition['itemid']][] = $condition;
			}
		}

		// update formulas
		foreach ($items as $item) {
			if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($item['itemid'], $item['filter']['formula'], $ruleConditions[$item['itemid']]);
			}
		}

		// TODO: REMOVE info
		foreach ($exRules as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Converts a formula with letters to a formula with IDs and updates it.
	 *
	 * @param string 	$itemId
	 * @param string 	$evalFormula		formula with letters
	 * @param array 	$conditions
	 */
	protected function updateFormula($itemId, $evalFormula, array $conditions) {
		$ids = [];
		foreach ($conditions as $condition) {
			$ids[$condition['formulaid']] = $condition['item_conditionid'];
		}
		$formula = CConditionHelper::replaceLetterIds($evalFormula, $ids);

		DB::updateByPk('items', $itemId, [
			'formula' => $formula
		]);
	}

	/**
	 * Check item data and set missing default values.
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 * @param array $dbItems
	 */
	protected function checkInput(array &$items, $update = false, array $dbItems = []) {
		// add the values that cannot be changed, but are required for further processing
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_RULE;
			$item['value_type'] = ITEM_VALUE_TYPE_TEXT;

			// unset fields that are updated using the 'filter' parameter
			unset($item['evaltype']);
			unset($item['formula']);
		}
		unset($item);

		parent::checkInput($items, $update);

		$validateItems = $items;
		if ($update) {
			$validateItems = $this->extendFromObjects(zbx_toHash($validateItems, 'itemid'), $dbItems, ['name']);
		}

		// filter validator
		$filterValidator = new CSchemaValidator($this->getFilterSchema());

		// condition validation
		$conditionValidator = new CSchemaValidator($this->getFilterConditionSchema());
		foreach ($validateItems as $item) {
			// validate custom formula and conditions
			if (isset($item['filter'])) {
				$filterValidator->setObjectName($item['name']);
				$this->checkValidator($item['filter'], $filterValidator);

				foreach ($item['filter']['conditions'] as $condition) {
					$conditionValidator->setObjectName($item['name']);
					$this->checkValidator($condition, $conditionValidator);
				}
			}
		}
	}

	/**
	 * Returns the parameters for creating a discovery rule filter validator.
	 *
	 * @return array
	 */
	protected function getFilterSchema() {
		return [
			'validators' => [
				'evaltype' => new CLimitedSetValidator([
					'values' => [
						CONDITION_EVAL_TYPE_OR,
						CONDITION_EVAL_TYPE_AND,
						CONDITION_EVAL_TYPE_AND_OR,
						CONDITION_EVAL_TYPE_EXPRESSION
					],
					'messageInvalid' => _('Incorrect type of calculation for discovery rule "%1$s".')
				]),
				'formula' => new CStringValidator([
					'empty' => true
				]),
				'conditions' => new CCollectionValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect conditions for discovery rule "%1$s".')
				])
			],
			'postValidators' => [
				new CConditionValidator([
					'messageInvalidFormula' => _('Incorrect custom expression "%2$s" for discovery rule "%1$s": %3$s.'),
					'messageMissingCondition' => _('Condition "%2$s" used in formula "%3$s" for discovery rule "%1$s" is not defined.'),
					'messageUnusedCondition' => _('Condition "%2$s" is not used in formula "%3$s" for discovery rule "%1$s".')
				])
			],
			'required' => ['evaltype', 'conditions'],
			'messageRequired' => _('No "%2$s" given for the filter of discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for the filter of discovery rule "%1$s".')
		];
	}

	/**
	 * Returns the parameters for creating a discovery rule filter condition validator.
	 *
	 * @return array
	 */
	protected function getFilterConditionSchema() {
		return [
			'validators' => [
				'macro' => new CStringValidator([
					'regex' => '/^'.ZBX_PREG_EXPRESSION_LLD_MACROS.'$/',
					'messageEmpty' => _('Empty filter condition macro for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect filter condition macro for discovery rule "%1$s".')
				]),
				'value' => new CStringValidator([
					'empty' => true
				]),
				'formulaid' => new CStringValidator([
					'regex' => '/[A-Z]+/',
					'messageEmpty' => _('Empty filter condition formula ID for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect filter condition formula ID for discovery rule "%1$s".')
				]),
				'operator' => new CLimitedSetValidator([
					'values' => [CONDITION_OPERATOR_REGEXP],
					'messageInvalid' => _('Incorrect filter condition operator for discovery rule "%1$s".')
				])
			],
			'required' => ['macro', 'value'],
			'messageRequired' => _('No "%2$s" given for a filter condition of discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for a filter condition of discovery rule "%1$s".')
		];
	}

	/**
	 * Check discovery rule specific fields.
	 *
	 * @param array  $item			An array of single discovery rule data.
	 * @param string $method		A string of "create" or "update" method.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkSpecificFields(array $item, $method) {
		if (isset($item['lifetime']) && !$this->validateLifetime($item['lifetime'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s:%2$s" has incorrect lifetime: "%3$s". (min: %4$d, max: %5$d, user macro allowed)',
					$item['name'], $item['key_'], $item['lifetime'], self::MIN_LIFETIME, self::MAX_LIFETIME)
			);
		}

		if (array_key_exists('preprocessing', $item)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Item pre-processing is not allowed for discovery rules.'));
		}
	}

	protected function inherit(array $items, array $hostids = null) {
		if (empty($items)) {
			return true;
		}

		// prepare the child items
		$newItems = $this->prepareInheritedItems($items, $hostids);
		if (!$newItems) {
			return true;
		}

		$insertItems = [];
		$updateItems = [];
		foreach ($newItems as $newItem) {
			if (isset($newItem['itemid'])) {
				$updateItems[] = $newItem;
			}
			else {
				$newItem['flags'] = ZBX_FLAG_DISCOVERY_RULE;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		$this->createReal($insertItems);
		$this->updateReal($updateItems);

		// propagate the inheritance to the children
		return $this->inherit(array_merge($updateItems, $insertItems));
	}

	/**
	 * Copies the given discovery rule to the specified host.
	 *
	 * @throws APIException if the discovery rule interfaces could not be mapped
	 * to the new host interfaces.
	 *
	 * @param string $discoveryid  The ID of the discovery rule to be copied
	 * @param string $hostid       Destination host id
	 *
	 * @return bool
	 */
	protected function copyDiscoveryRule($discoveryid, $hostid) {
		// fetch discovery to clone
		$srcDiscovery = $this->get([
			'itemids' => $discoveryid,
			'output' => ['itemid', 'type', 'snmp_community', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'snmpv3_securityname',
				'snmpv3_securitylevel',	'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'lastlogsize', 'logtimefmt',
				'valuemapid', 'delay_flex', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey',
				'privatekey', 'mtime', 'flags', 'interfaceid', 'port', 'description', 'inventory_link', 'lifetime',
				'snmpv3_authprotocol', 'snmpv3_privprotocol', 'snmpv3_contextname'
			],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'preservekeys' => true
		]);
		$srcDiscovery = reset($srcDiscovery);

		// fetch source and destination hosts
		$hosts = API::Host()->get([
			'hostids' => [$srcDiscovery['hostid'], $hostid],
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		$srcHost = $hosts[$srcDiscovery['hostid']];
		$dstHost = $hosts[$hostid];

		$dstDiscovery = $srcDiscovery;
		$dstDiscovery['hostid'] = $hostid;
		unset($dstDiscovery['itemid']);
		if ($dstDiscovery['filter']) {
			foreach ($dstDiscovery['filter']['conditions'] as &$condition) {
				unset($condition['itemid'], $condition['item_conditionid']);
			}
			unset($condition);
		}

		// if this is a plain host, map discovery interfaces
		if ($srcHost['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = self::findInterfaceForItem($dstDiscovery, $dstHost['interfaces']);
			if ($interface) {
				$dstDiscovery['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot find host interface on "%1$s" for item key "%2$s".',
					$dstHost['name'],
					$dstDiscovery['key_']
				));
			}
		}

		// save new discovery
		$newDiscovery = $this->create([$dstDiscovery]);
		$dstDiscovery['itemid'] = $newDiscovery['itemids'][0];

		// copy prototypes
		$newPrototypes = $this->copyItemPrototypes($srcDiscovery, $dstDiscovery, $dstHost);

		// if there were prototypes defined, clone everything else
		if ($newPrototypes) {
			// fetch new prototypes
			$newPrototypes = API::ItemPrototype()->get([
				'itemids' => $newPrototypes['itemids'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			]);

			foreach ($newPrototypes as $i => $newPrototype) {
				unset($newPrototypes[$i]['templateid']);
			}

			$dstDiscovery['items'] = $newPrototypes;

			// copy graphs
			$this->copyGraphPrototypes($srcDiscovery, $dstDiscovery);

			// copy triggers
			$this->copyTriggerPrototypes($srcDiscovery, $dstDiscovery, $srcHost, $dstHost);
		}

		// copy host prototypes
		$this->copyHostPrototypes($srcDiscovery, $dstDiscovery);

		return true;
	}

	/**
	 * Copies all of the item prototypes from the source discovery to the target
	 * discovery rule.
	 *
	 * @throws APIException if prototype saving fails
	 *
	 * @param array $srcDiscovery   The source discovery rule to copy from
	 * @param array $dstDiscovery   The target discovery rule to copy to
	 * @param array $dstHost        The target host to copy the deiscovery rule to
	 *
	 * @return array
	 */
	protected function copyItemPrototypes(array $srcDiscovery, array $dstDiscovery, array $dstHost) {
		$prototypes = API::ItemPrototype()->get([
			'output' => ['itemid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'logtimefmt', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'port',
				'description', 'snmpv3_authprotocol', 'snmpv3_privprotocol', 'snmpv3_contextname'
			],
			'selectApplications' => ['applicationid'],
			'selectApplicationPrototypes' => ['name'],
			'selectPreprocessing' => ['type', 'params'],
			'discoveryids' => $srcDiscovery['itemid'],
			'preservekeys' => true
		]);

		$rs = [];
		if ($prototypes) {
			foreach ($prototypes as $key => $prototype) {
				$prototype['ruleid'] = $dstDiscovery['itemid'];
				$prototype['hostid'] = $dstDiscovery['hostid'];

				// map prototype interfaces
				if ($dstHost['status'] != HOST_STATUS_TEMPLATE) {
					// find a matching interface
					$interface = self::findInterfaceForItem($prototype, $dstHost['interfaces']);
					if ($interface) {
						$prototype['interfaceid'] = $interface['interfaceid'];
					}
					// no matching interface found, throw an error
					elseif ($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot find host interface on "%1$s" for item key "%2$s".',
							$dstHost['name'],
							$prototype['key_']
						));
					}
				}

				// add new applications
				$prototype['applications'] = get_same_applications_for_host(
					zbx_objectValues($prototype['applications'], 'applicationid'),
					$dstHost['hostid']
				);

				if (!$prototype['preprocessing']) {
					unset($prototype['preprocessing']);
				}

				$prototypes[$key] = $prototype;
			}

			$rs = API::ItemPrototype()->create($prototypes);
			if (!$rs) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone item prototypes.'));
			}
		}

		return $rs;
	}

	/**
	 * Copies all of the graphs from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if graph saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 *
	 * @return array
	 */
	protected function copyGraphPrototypes(array $srcDiscovery, array $dstDiscovery) {
		// fetch source graphs
		$srcGraphs = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period',
				'show_triggers', 'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right',
				'ymin_type', 'ymax_type', 'ymin_itemid', 'ymax_itemid'
			],
			'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'selectHosts' => ['hostid'],
			'discoveryids' => $srcDiscovery['itemid'],
			'preservekeys' => true
		]);

		if (!$srcGraphs) {
			return [];
		}

		$srcItemIds = [];
		foreach ($srcGraphs as $key => $graph) {
			// skip graphs with items from multiple hosts
			if (count($graph['hosts']) > 1) {
				unset($srcGraphs[$key]);
				continue;
			}

			// skip graphs with http items
			if (httpItemExists($graph['gitems'])) {
				unset($srcGraphs[$key]);
				continue;
			}

			// save all used item ids to map them to the new items
			foreach ($graph['gitems'] as $item) {
				$srcItemIds[$item['itemid']] = $item['itemid'];
			}
			if ($graph['ymin_itemid']) {
				$srcItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
			}
			if ($graph['ymax_itemid']) {
				$srcItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
			}
		}

		// fetch source items
		$items = API::Item()->get([
			'itemids' => $srcItemIds,
			'output' => ['itemid', 'key_'],
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		$srcItems = [];
		$itemKeys = [];
		foreach ($items as $item) {
			$srcItems[$item['itemid']] = $item;
			$itemKeys[$item['key_']] = $item['key_'];
		}

		// fetch newly cloned items
		$newItems = API::Item()->get([
			'hostids' => $dstDiscovery['hostid'],
			'filter' => [
				'key_' => $itemKeys,
				'flags' => null
			],
			'output' => ['itemid', 'key_'],
			'preservekeys' => true
		]);

		$items = array_merge($dstDiscovery['items'], $newItems);
		$dstItems = [];
		foreach ($items as $item) {
			$dstItems[$item['key_']] = $item;
		}

		$dstGraphs = $srcGraphs;
		foreach ($dstGraphs as &$graph) {
			unset($graph['graphid']);

			foreach ($graph['gitems'] as &$gitem) {
				// replace the old item with the new one with the same key
				$item = $srcItems[$gitem['itemid']];
				$gitem['itemid'] = $dstItems[$item['key_']]['itemid'];
			}
			unset($gitem);

			// replace the old axis items with the new one with the same key
			if ($graph['ymin_itemid']) {
				$yMinSrcItem = $srcItems[$graph['ymin_itemid']];
				$graph['ymin_itemid'] = $dstItems[$yMinSrcItem['key_']]['itemid'];
			}
			if ($graph['ymax_itemid']) {
				$yMaxSrcItem = $srcItems[$graph['ymax_itemid']];
				$graph['ymax_itemid'] = $dstItems[$yMaxSrcItem['key_']]['itemid'];
			}
		}
		unset($graph);

		// save graphs
		$rs = API::GraphPrototype()->create($dstGraphs);
		if (!$rs) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone graph prototypes.'));
		}

		return $rs;
	}

	/**
	 * Copies all of the host prototypes from the source discovery to the target
	 * discovery rule.
	 *
	 * @throws APIException if prototype saving fails
	 *
	 * @param array $srcDiscovery   The source discovery rule to copy from
	 * @param array $dstDiscovery   The target discovery rule to copy to
	 *
	 * @return array
	 */
	protected function copyHostPrototypes(array $srcDiscovery, array $dstDiscovery) {
		$prototypes = API::HostPrototype()->get([
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => ['host', 'name', 'status'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectInventory' => ['inventory_mode'],
			'selectTemplates' => ['templateid'],
			'preservekeys' => true
		]);

		$rs = [];
		if ($prototypes) {
			foreach ($prototypes as &$prototype) {
				$prototype['ruleid'] = $dstDiscovery['itemid'];
				unset($prototype['hostid'], $prototype['inventory']['hostid']);

				foreach ($prototype['groupLinks'] as &$groupLinks) {
					unset($groupLinks['group_prototypeid']);
				}
				unset($groupLinks);

				foreach ($prototype['groupPrototypes'] as &$groupPrototype) {
					unset($groupPrototype['group_prototypeid']);
				}
				unset($groupPrototype);
			}
			unset($prototype);

			$rs = API::HostPrototype()->create($prototypes);
			if (!$rs) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone host prototypes.'));
			}
		}
		return $rs;
	}

	private function validateLifetime($lifetime) {
		return (validateNumber($lifetime, self::MIN_LIFETIME, self::MAX_LIFETIME) || validateUserMacro($lifetime));
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			// add filter fields
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sqlParts = $this->addQuerySelect('i.formula', $sqlParts);
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}
			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}

			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemIds = array_keys($result);

		// adding items
		if (!is_null($options['selectItems'])) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'itemid', 'item_discovery');
				$items = API::ItemPrototype()->get([
					'output' => $options['selectItems'],
					'itemids' => $relationMap->getRelatedIds(),
					'nopermissions' => true,
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::ItemPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$items = zbx_toHash($items, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['items'] = isset($items[$itemid]) ? $items[$itemid]['rowscount'] : 0;
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,items i,functions f'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=f.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['triggerid']);
				}

				$triggers = API::TriggerPrototype()->get([
					'output' => $options['selectTriggers'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['triggers'] = isset($triggers[$itemid]) ? $triggers[$itemid]['rowscount'] : 0;
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,gi.graphid'.
					' FROM item_discovery id,items i,graphs_items gi'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=gi.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['graphid']);
				}

				$graphs = API::GraphPrototype()->get([
					'output' => $options['selectGraphs'],
					'graphids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['graphs'] = isset($graphs[$itemid]) ? $graphs[$itemid]['rowscount'] : 0;
				}
			}
		}

		// adding hosts
		if ($options['selectHostPrototypes'] !== null) {
			if ($options['selectHostPrototypes'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'hostid', 'host_discovery');
				$hostPrototypes = API::HostPrototype()->get([
					'output' => $options['selectHostPrototypes'],
					'hostids' => $relationMap->getRelatedIds(),
					'nopermissions' => true,
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $hostPrototypes, 'hostPrototypes', $options['limitSelects']);
			}
			else {
				$hostPrototypes = API::HostPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hostPrototypes = zbx_toHash($hostPrototypes, 'parent_itemid');

				foreach ($result as $itemid => $item) {
					$result[$itemid]['hostPrototypes'] = isset($hostPrototypes[$itemid]) ? $hostPrototypes[$itemid]['rowscount'] : 0;
				}
			}
		}

		if ($options['selectFilter'] !== null) {
			$formulaRequested = $this->outputIsRequested('formula', $options['selectFilter']);
			$evalFormulaRequested = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$conditionsRequested = $this->outputIsRequested('conditions', $options['selectFilter']);

			$filters = [];
			foreach ($result as $rule) {
				$filters[$rule['itemid']] = [
					'evaltype' => $rule['evaltype'],
					'formula' => isset($rule['formula']) ? $rule['formula'] : ''
				];
			}

			// adding conditions
			if ($formulaRequested || $evalFormulaRequested || $conditionsRequested) {
				$conditions = API::getApiService()->select('item_condition', [
					'output' => ['item_conditionid', 'macro', 'value', 'itemid', 'operator'],
					'filter' => ['itemid' => $itemIds],
					'preservekeys' => true,
					'sortfield' => 'item_conditionid'
				]);
				$relationMap = $this->createRelationMap($conditions, 'itemid', 'item_conditionid');

				$filters = $relationMap->mapMany($filters, $conditions, 'conditions');

				foreach ($filters as &$filter) {
					// in case of a custom expression - use the given formula
					if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $filter['formula'];
					}
					// in other cases - generate the formula automatically
					else {
						// sort the conditions by macro before generating the formula
						$conditions = zbx_toHash($filter['conditions'], 'item_conditionid');
						$conditions = order_macros($conditions, 'macro');

						$formulaConditions = [];
						foreach ($conditions as $condition) {
							$formulaConditions[$condition['item_conditionid']] = $condition['macro'];
						}
						$formula = CConditionHelper::getFormula($formulaConditions, $filter['evaltype']);
					}

					// generate formulaids from the effective formula
					$formulaIds = CConditionHelper::getFormulaIds($formula);
					foreach ($filter['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaIds[$condition['item_conditionid']];
					}
					unset($condition);

					// generated a letter based formula only for rules with custom expressions
					if ($formulaRequested && $filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$filter['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}

					if ($evalFormulaRequested) {
						$filter['eval_formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}
				}
				unset($filter);
			}

			// add filters to the result
			foreach ($result as &$rule) {
				$rule['filter'] = $filters[$rule['itemid']];
			}
			unset($rule);
		}

		return $result;
	}
}
