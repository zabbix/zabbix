<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class containing methods for operations with trigger prototypes.
 *
 * @package API
 */
class CTriggerPrototype extends CTriggerGeneral {

	protected $tableName = 'triggers';
	protected $tableAlias = 't';
	protected $sortColumns = array('triggerid', 'description', 'status', 'priority');

	/**
	 * Get trigger prototypes from database.
	 *
	 * @see https://www.zabbix.com/documentation/2.4/manual/api/reference/triggerprototype/get
	 *
	 * @param array $options
	 *
	 * @return array|int
	 */
	public function get(array $options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userId = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('triggers' => 't.triggerid'),
			'from'		=> array('t' => 'triggers t'),
			'where'		=> array('t.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'triggerids'					=> null,
			'itemids'						=> null,
			'applicationids'				=> null,
			'discoveryids'					=> null,
			'functions'						=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored' 					=> null,
			'active' 						=> null,
			'maintenance'					=> null,
			'nopermissions'					=> null,
			'editable'						=> null,
			// filter
			'group'							=> null,
			'host'							=> null,
			'min_severity'					=> null,
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> null,
			'excludeSearch'					=> null,
			'searchWildcardsEnabled'		=> null,
			// output
			'expandExpression'				=> null,
			'output'						=> API_OUTPUT_EXTEND,
			'selectGroups'					=> null,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectFunctions'				=> null,
			'selectDiscoveryRule'			=> null,
			'countOutput'					=> null,
			'groupCount'					=> null,
			'preservekeys'					=> null,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + permission check
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userId);

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY i.hostid'.
				' HAVING MAX(permission)<'.zbx_dbstr($permission).
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
			')';
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['groupid'] = dbConditionInt('hg.groupid', $options['groupids']);

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if ($options['templateids'] !== null) {
			zbx_value2array($options['templateids']);

			if ($options['hostids'] !== null) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// triggerids
		if ($options['triggerids'] !== null) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where']['triggerid'] = dbConditionInt('t.triggerid', $options['triggerids']);
		}

		// itemids
		if ($options['itemids'] !== null) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['itemid'] = dbConditionInt('f.itemid', $options['itemids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['f'] = 'f.itemid';
			}
		}

		// applicationids
		if ($options['applicationids'] !== null) {
			zbx_value2array($options['applicationids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where']['a'] = dbConditionInt('a.applicationid', $options['applicationids']);
			$sqlParts['where']['ia'] = 'i.hostid=a.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['fid'] = 'f.itemid=id.itemid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// functions
		if ($options['functions'] !== null) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionString('f.function', $options['functions']);
		}

		// monitored
		if ($options['monitored'] !== null) {
			$sqlParts['where']['monitored'] =
				' NOT EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT NULL'.
								' FROM items ii,hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND ('.
										' ii.status<>'.ITEM_STATUS_ACTIVE.
										' OR hh.status<>'.HOST_STATUS_MONITORED.
									' )'.
						' )'.
				' )';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// active
		if ($options['active'] !== null) {
			$sqlParts['where']['active'] =
				' NOT EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT NULL'.
							' FROM items ii,hosts hh'.
							' WHERE ff.itemid=ii.itemid'.
								' AND hh.hostid=ii.hostid'.
								' AND  hh.status<>'.HOST_STATUS_MONITORED.
						' )'.
				' )';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// maintenance
		if ($options['maintenance'] !== null) {
			$sqlParts['where'][] = (($options['maintenance'] == 0) ? ' NOT ' : '').
				' EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT NULL'.
								' FROM items ii,hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND hh.maintenance_status=1'.
						' )'.
				' )';
			$sqlParts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// templated
		if ($options['templated'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if ($options['inherited'] !== null) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 't.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 't.templateid IS NULL';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('triggers t', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('triggers t', $options, $sqlParts);

			if (isset($options['filter']['host']) && $options['filter']['host'] !== null) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid']) && $options['filter']['hostid'] !== null) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// group
		if ($options['group'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['groups'] = 'groups g';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ghg'] = 'g.groupid=hg.groupid';
			$sqlParts['where']['group'] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if ($options['host'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['host'] = ' h.host='.zbx_dbstr($options['host']);
		}

		// min_severity
		if ($options['min_severity'] !== null) {
			$sqlParts['where'][] = 't.priority>='.zbx_dbstr($options['min_severity']);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($triggerPrototype = DBfetch($dbRes)) {
			if ($options['countOutput'] !== null) {
				if ($options['groupCount'] !== null) {
					$result[] = $triggerPrototype;
				}
				else {
					$result = $triggerPrototype['rowscount'];
				}
			}
			else {
				// expand expression
				if ($options['expandExpression'] !== null && isset($triggerPrototype['expression'])) {
					$triggerPrototype['expression'] = explode_exp($triggerPrototype['expression'], false, true);
				}

				$result[$triggerPrototype['triggerid']] = $triggerPrototype;
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create new trigger prototypes.
	 *
	 * @see https://www.zabbix.com/documentation/2.4/manual/api/reference/triggerprototype/create
	 *
	 * @param array $triggerPrototypes
	 *
	 * @return array
	 */
	public function create(array $triggerPrototypes) {
		$triggerPrototypes = zbx_toArray($triggerPrototypes);

		$this->validateCreate($triggerPrototypes);

		$this->createReal($triggerPrototypes);

		foreach ($triggerPrototypes as $triggerPrototype) {
			$this->inherit($triggerPrototype);
		}

		return array('triggerids' => zbx_objectValues($triggerPrototypes, 'triggerid'));
	}

	/**
	 * Validate trigger prototypes to be created.
	 *
	 * @param array $triggerPrototypes
	 *
	 * @throws APIException		if validation failed
	 *
	 * @return void
	 */
	protected function validateCreate(array $triggerPrototypes) {
		$triggerDbFields = array(
			'description' => null,
			'expression' => null,
			'error' => _('Trigger just added. No status update so far.')
		);
		foreach ($triggerPrototypes as $triggerPrototype) {
			if (!check_db_fields($triggerDbFields, $triggerPrototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for trigger.'));
			}

			if (array_key_exists('templateid', $triggerPrototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot set "templateid" for trigger prototype "%1$s".',
					$triggerPrototype['description']
				));
			}

			$this->checkIfExistsOnHost($triggerPrototype);

			$this->validateTriggerPrototypeExpression($triggerPrototype);
		}
	}

	/**
	 * Update existing trigger prototypes.
	 *
	 * @see https://www.zabbix.com/documentation/2.4/manual/api/reference/triggerprototype/update
	 *
	 * @param array $triggerPrototypes
	 *
	 * @return array
	 */
	public function update(array $triggerPrototypes) {
		$triggerPrototypes = zbx_toArray($triggerPrototypes);
		$triggerPrototypeIds = zbx_objectValues($triggerPrototypes, 'triggerid');

		$dbTriggerPrototypes = $this->get(array(
			'triggerids' => $triggerPrototypeIds,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$this->validateUpdate($triggerPrototypes, $dbTriggerPrototypes);

		foreach ($triggerPrototypes as &$triggerPrototype) {
			$dbTriggerPrototype = $dbTriggerPrototypes[$triggerPrototype['triggerid']];

			if (isset($triggerPrototype['expression'])) {
				$expressionFull = explode_exp($dbTriggerPrototype['expression']);
				if (strcmp($triggerPrototype['expression'], $expressionFull) == 0) {
					unset($triggerPrototype['expression']);
				}
			}

			if (isset($triggerPrototype['description'])
					&& strcmp($triggerPrototype['description'], $dbTriggerPrototype['comments']) == 0) {
				unset($triggerPrototype['description']);
			}

			if (isset($triggerPrototype['priority'])
					&& $triggerPrototype['priority'] == $dbTriggerPrototype['priority']) {
				unset($triggerPrototype['priority']);
			}

			if (isset($triggerPrototype['type']) && $triggerPrototype['type'] == $dbTriggerPrototype['type']) {
				unset($triggerPrototype['type']);
			}

			if (isset($triggerPrototype['comments'])
					&& strcmp($triggerPrototype['comments'], $dbTriggerPrototype['comments']) == 0) {
				unset($triggerPrototype['comments']);
			}

			if (isset($triggerPrototype['url']) && strcmp($triggerPrototype['url'], $dbTriggerPrototype['url']) == 0) {
				unset($triggerPrototype['url']);
			}

			if (isset($triggerPrototype['status']) && $triggerPrototype['status'] == $dbTriggerPrototype['status']) {
				unset($triggerPrototype['status']);
			}
		}
		unset($triggerPrototype);

		$this->updateReal($triggerPrototypes);

		foreach ($triggerPrototypes as $triggerPrototype) {
			$triggerPrototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
			$this->inherit($triggerPrototype);
		}

		return array('triggerids' => $triggerPrototypeIds);
	}

	/**
	 * Delete existing trigger prototypes.
	 *
	 * @see https://www.zabbix.com/documentation/2.4/manual/api/reference/triggerprototype/delete
	 *
	 * @param array $triggerPrototypeIds
	 * @param bool  $nopermissions
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $triggerPrototypeIds, $nopermissions = false) {
		if (!$triggerPrototypeIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			$dbTriggerPrototypes = $this->get(array(
				'triggerids' => $triggerPrototypeIds,
				'output' => array('description', 'expression', 'templateid'),
				'editable' => true,
				'preservekeys' => true
			));

			foreach ($triggerPrototypeIds as $triggerPrototypeId) {
				if (!isset($dbTriggerPrototypes[$triggerPrototypeId])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _(
						'No permissions to referred object or it does not exist!'
					));
				}
				$dbTriggerPrototype = $dbTriggerPrototypes[$triggerPrototypeId];

				if ($dbTriggerPrototype['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot delete templated trigger "%1$s:%2$s".',
						$dbTriggerPrototype['description'],
						explode_exp($dbTriggerPrototype['expression'])
					));
				}
			}
		}

		// get child trigger prototypes
		$parentTriggerPrototypeIds = $triggerPrototypeIds;
		do {
			$dbTriggerPrototypes = DBselect(
				'SELECT triggerid'.
				' FROM triggers'.
				' WHERE '.dbConditionInt('templateid', $parentTriggerPrototypeIds)
			);
			$parentTriggerPrototypeIds = array();
			while ($dbTriggerPrototype = DBfetch($dbTriggerPrototypes)) {
				$parentTriggerPrototypeIds[] = $dbTriggerPrototype['triggerid'];
				$triggerPrototypeIds[$dbTriggerPrototype['triggerid']] = $dbTriggerPrototype['triggerid'];
			}
		} while ($parentTriggerPrototypeIds);

		// delete triggers created from this prototype
		$createdTriggerIds = DBfetchColumn(DBselect(
			'SELECT triggerid'.
			' FROM trigger_discovery'.
			' WHERE '.dbConditionInt('parent_triggerid', $triggerPrototypeIds)
		), 'triggerid');
		if ($createdTriggerIds) {
			API::Trigger()->delete($createdTriggerIds, true);
		}

		// select all trigger prototypes which are deleted (include children)
		$dbTriggerPrototypes = $this->get(array(
			'triggerids' => $triggerPrototypeIds,
			'output' => array('triggerid', 'description', 'expression'),
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		));

		// TODO: REMOVE info
		foreach ($dbTriggerPrototypes as $dbTriggerPrototype) {
			info(_s('Deleted: Trigger prototype "%1$s" on "%2$s".', $dbTriggerPrototype['description'],
					implode(', ', zbx_objectValues($dbTriggerPrototype['hosts'], 'name'))));

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, $dbTriggerPrototype['triggerid'],
					$dbTriggerPrototype['description'].':'.$dbTriggerPrototype['expression'], null, null, null);
		}

		DB::delete('triggers', array('triggerid' => $triggerPrototypeIds));

		return array('triggerids' => $triggerPrototypeIds);
	}

	/**
	 * Inserts trigger prototype records into database.
	 *
	 * @param array $triggerPrototypes
	 *
	 * @throws APIException
	 *
	 * @return void
	 */
	protected function createReal(array &$triggerPrototypes) {
		$triggerPrototypes = zbx_toArray($triggerPrototypes);

		foreach ($triggerPrototypes as $key => $triggerPrototype) {
			$triggerPrototypes[$key]['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}

		// insert trigger prototypes without expression
		$triggerPrototypesCopy = $triggerPrototypes;
		for ($i = 0, $size = count($triggerPrototypesCopy); $i < $size; $i++) {
			unset($triggerPrototypesCopy[$i]['expression']);
		}

		$triggerPrototypeIds = DB::insert('triggers', $triggerPrototypesCopy);
		unset($triggerPrototypesCopy);

		foreach ($triggerPrototypes as $key => $triggerPrototype) {
			$triggerPrototypeId = $triggerPrototypes[$key]['triggerid'] = $triggerPrototypeIds[$key];
			$hosts = array();

			try {
				$expression = implode_exp($triggerPrototype['expression'], $triggerPrototypeId, $hosts);

				DB::update('triggers', array(
					'values' => array('expression' => $expression),
					'where' => array('triggerid' => $triggerPrototypeId)
				));

				info(_s('Created: Trigger prototype "%1$s" on "%2$s".', $triggerPrototype['description'], implode(', ', $hosts)));
			}
			catch (Exception $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot implode expression "%s".', $triggerPrototype['expression']).' '.$e->getMessage()
				);
			}
		}
	}

	/**
	 * Updates trigger prototype records in database.
	 *
	 * @param array $triggerPrototypes
	 *
	 * @throws APIException
	 *
	 * @return void
	 */
	protected function updateReal(array $triggerPrototypes) {
		$triggerPrototypes = zbx_toArray($triggerPrototypes);

		$dbTriggerPrototypes = $this->get(array(
			'triggerids' => zbx_objectValues($triggerPrototypes, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('name'),
			'preservekeys' => true,
			'nopermissions' => true
		));

		$descriptionChanged = false;
		$expressionChanged = false;
		foreach ($triggerPrototypes as &$triggerPrototype) {
			$dbTriggerPrototype = $dbTriggerPrototypes[$triggerPrototype['triggerid']];
			$hosts = zbx_objectValues($dbTriggerPrototype['hosts'], 'name');

			if (isset($triggerPrototype['description'])
					&& strcmp($dbTriggerPrototype['description'], $triggerPrototype['description']) != 0) {
				$descriptionChanged = true;
			}

			$expressionFull = explode_exp($dbTriggerPrototype['expression']);
			if (isset($triggerPrototype['expression'])
					&& strcmp($expressionFull, $triggerPrototype['expression']) != 0) {
				$expressionChanged = true;
				$expressionFull = $triggerPrototype['expression'];
			}

			if ($descriptionChanged || $expressionChanged) {
				$expressionData = new CTriggerExpression();
				if (!$expressionData->parse($expressionFull)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				if (!isset($expressionData->expressions[0])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _(
						'Trigger expression must contain at least one host:key reference.'
					));
				}
			}

			if ($expressionChanged) {
				DB::delete('functions', array('triggerid' => $triggerPrototype['triggerid']));

				try {
					$triggerPrototype['expression'] = implode_exp($expressionFull, $triggerPrototype['triggerid'], $hosts);
				}
				catch (Exception $e) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot implode expression "%s".', $expressionFull).' '.$e->getMessage());
				}
			}

			$triggerPrototypeUpdate = $triggerPrototype;
			if (!$descriptionChanged) {
				unset($triggerPrototypeUpdate['description']);
			}
			if (!$expressionChanged) {
				unset($triggerPrototypeUpdate['expression']);
			}

			// skip updating read only values
			unset(
				$triggerPrototypeUpdate['state'],
				$triggerPrototypeUpdate['value'],
				$triggerPrototypeUpdate['lastchange'],
				$triggerPrototypeUpdate['error']
			);

			DB::update('triggers', array(
				'values' => $triggerPrototypeUpdate,
				'where' => array('triggerid' => $triggerPrototype['triggerid'])
			));

			$description = isset($triggerPrototype['description']) ? $triggerPrototype['description'] : $dbTriggerPrototype['description'];

			info(_s('Updated: Trigger prototype "%1$s" on "%2$s".', $description, implode(', ', $hosts)));
		}
		unset($triggerPrototype);
	}

	/**
	 * Adds items from template to hosts.
	 *
	 * @param array		$data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$triggerPrototypes = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => array(
				'triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments', 'type'
			)
		));

		foreach ($triggerPrototypes as $triggerPrototype) {
			$triggerPrototype['expression'] = explode_exp($triggerPrototype['expression']);
			$this->inherit($triggerPrototype, $data['hostids']);
		}

		return true;
	}

	/**
	 * Retrieves and adds additional requested data (options 'selectHosts', 'selectGroups', etc.) to result set.
	 *
	 * @param array		$options
	 * @param array		$result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerPrototypeIds = array_keys($result);

		// adding items
		if ($options['selectItems'] !== null && $options['selectItems'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'triggerid', 'itemid', 'functions');
			$items = API::Item()->get(array(
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true,
				'filter' => array('flags' => null)
			));
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,functions f'.
					' WHERE '.dbConditionInt('f.triggerid', $triggerPrototypeIds).
					' AND f.itemid=id.itemid'
			);
			$relationMap = new CRelationMap();
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true,
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		return $result;
	}

	/**
	 * Check if trigger prototype has at least one item prototype and belongs to one discovery rule.
	 *
	 * @throws APIException if trigger prototype has no item prototype or items belong to multiple discovery rules.
	 *
	 * @param array $triggerPrototype	array of trigger data, uses 'description' element
	 * @param array $items				array of trigger items
	 *
	 * @return void
	 */
	protected function checkDiscoveryRuleCount(array $triggerPrototype, array $items) {
		if ($items) {
			$itemDiscoveries = API::getApiService()->select('item_discovery', array(
				'output' => array('parent_itemid'),
				'filter' => array('itemid' => zbx_objectValues($items, 'itemid')),
			));

			$itemDiscoveryIds = array_flip(zbx_objectValues($itemDiscoveries, 'parent_itemid'));

			if (count($itemDiscoveryIds) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Trigger prototype "%1$s" contains item prototypes from multiple discovery rules.',
					$triggerPrototype['description']
				));
			}
			elseif (!$itemDiscoveryIds) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Trigger prototype "%1$s" must contain at least one item prototype.',
					$triggerPrototype['description']
				));
			}
		}
	}

	/**
	 * Validates trigger prototype update data.
	 *
	 * @param array		$triggerPrototypes
	 * @param array		$dbTriggerPrototypes
	 *
	 * @throws APIException
	 *
	 * @return void
	 */
	protected function validateUpdate(array $triggerPrototypes, array $dbTriggerPrototypes) {
		$triggerPrototypes = $this->extendObjects($this->tableName(), $triggerPrototypes, array('description'));

		foreach ($triggerPrototypes as $triggerPrototype) {
			if (!isset($triggerPrototype['triggerid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for trigger.'));
			}

			if (!isset($dbTriggerPrototypes[$triggerPrototype['triggerid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			if (array_key_exists('templateid', $triggerPrototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot set "templateid" for trigger prototype "%1$s".',
					$triggerPrototype['description']
				));
			}

			$this->checkIfExistsOnHost($triggerPrototype);

			if (isset($triggerPrototype['expression'])) {
				$this->validateTriggerPrototypeExpression($triggerPrototype);
			}
		}
	}

	/**
	 * Checks that all hostnames are either from hosts or from templates.
	 *
	 * @param array $expressionHostnames
	 *
	 * @return void
	 */
	protected function checkTemplatesAndHostsTogether(array $expressionHostnames) {
		$dbExpressionHosts = API::Host()->get(array(
			'filter' => array('host' => $expressionHostnames),
			'editable' => true,
			'output' => array('hostid', 'host', 'status'),
			'templated_hosts' => true,
			'preservekeys' => true
		));
		$dbExpressionHosts = zbx_toHash($dbExpressionHosts, 'host');

		$hostsStatusFlags = 0x0;
		foreach ($expressionHostnames as $expressionHostname) {
			if (!isset($dbExpressionHosts[$expressionHostname])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect trigger prototype expression.'.
					' Host "%1$s" does not exist or you have no access to this host.',
					$expressionHostname
				));
			}
			$dbExpressionHost = $dbExpressionHosts[$expressionHostname];

			// find out if both templates and hosts are referenced in expression
			$hostsStatusFlags |= ($dbExpressionHost['status'] == HOST_STATUS_TEMPLATE) ? 0x1 : 0x2;
			if ($hostsStatusFlags == 0x3) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _(
					'Incorrect trigger prototype expression.'.
					' Trigger prototype expression elements should not belong to a template and a host simultaneously.'
				));
			}
		}
	}

	/**
	 * Checks if trigger prototype is in valid state. Checks trigger expression.
	 *
	 * @param array $triggerPrototype
	 *
	 * @return void
	 */
	protected function validateTriggerPrototypeExpression(array $triggerPrototype) {
		$triggerExpression = new CTriggerExpression();
		if (!$triggerExpression->parse($triggerPrototype['expression'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $triggerExpression->error);
		}

		$expressionHostnames = $triggerExpression->getHosts();
		$this->checkTemplatesAndHostsTogether($expressionHostnames);

		$triggerExpressionItems = getExpressionItems($triggerExpression);
		$this->checkDiscoveryRuleCount($triggerPrototype, $triggerExpressionItems);
	}
}
