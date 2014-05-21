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
 * Class containing methods for operations with trigger prototypes.
 *
 * @package API
 */
class CTriggerPrototype extends CTriggerGeneral {

	protected $tableName = 'triggers';
	protected $tableAlias = 't';
	protected $sortColumns = array('triggerid', 'description', 'status', 'priority');

	/**
	 * Get TriggerPrototypes data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['editable']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get(array $options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

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
			'expandData'					=> null,
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

			$userGroups = getUserGroupsByUserId($userid);

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
				' HAVING MAX(permission)<'.$permission.
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
			')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['groupid'] = dbConditionInt('hg.groupid', $options['groupids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
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

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where']['triggerid'] = dbConditionInt('t.triggerid', $options['triggerids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['itemid'] = dbConditionInt('f.itemid', $options['itemids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['f'] = 'f.itemid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
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
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['fid'] = 'f.itemid=id.itemid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// functions
		if (!is_null($options['functions'])) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionString('f.function', $options['functions']);
		}

		// monitored
		if (!is_null($options['monitored'])) {
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
		if (!is_null($options['active'])) {
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
		if (!is_null($options['maintenance'])) {
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
		if (!is_null($options['templated'])) {
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
		if (!is_null($options['inherited'])) {
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

			if (isset($options['filter']['host']) && !is_null($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid']) && !is_null($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// group
		if (!is_null($options['group'])) {
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
		if (!is_null($options['host'])) {
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
		if (!is_null($options['min_severity'])) {
			$sqlParts['where'][] = 't.priority>='.zbx_dbstr($options['min_severity']);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($trigger = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $trigger;
				}
				else {
					$result = $trigger['rowscount'];
				}
			}
			else {
				// expand expression
				if ($options['expandExpression'] !== null && isset($trigger['expression'])) {
					$trigger['expression'] = explode_exp($trigger['expression'], false, true);
				}

				$result[$trigger['triggerid']] = $trigger;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create triggers.
	 *
	 * @param array  $triggers
	 * @param string $triggers['expression']
	 * @param string $triggers['description']
	 * @param int    $triggers['type']
	 * @param int    $triggers['priority']
	 * @param int    $triggers['status']
	 * @param string $triggers['comments']
	 * @param string $triggers['url']
	 * @param string $triggers['flags']
	 * @param int    $triggers['templateid']
	 *
	 * @return boolean
	 */
	public function create(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$triggerIds = array();

		foreach ($triggers as $trigger) {
			$triggerDbFields = array(
				'description' => null,
				'expression' => null,
				'error' => _('Trigger just added. No status update so far.')
			);
			if (!check_db_fields($triggerDbFields, $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for trigger.'));
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot set "templateid" for trigger prototype "%1$s".', $trigger['description']));
			}

			$triggerExpression = new CTriggerExpression();
			if (!$triggerExpression->parse($trigger['expression'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $triggerExpression->error);
			}

			$this->checkIfExistsOnHost($trigger);

			// check item prototypes
			$items = getExpressionItems($triggerExpression);
			$this->checkDiscoveryRuleCount($trigger, $items);
		}

		$this->createReal($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger);
		}

		return array('triggerids' => zbx_objectValues($triggers, 'triggerid'));
	}

	/**
	 * Update triggers.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function update(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$triggerIds = zbx_objectValues($triggers, 'triggerid');

		$dbTriggers = $this->get(array(
			'triggerids' => $triggerIds,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$triggers = $this->extendObjects($this->tableName(), $triggers, array('description'));

		foreach ($triggers as $key => $trigger) {
			if (!isset($dbTriggers[$trigger['triggerid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			if (!isset($trigger['triggerid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for trigger.'));
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "templateid" for trigger prototype "%1$s".', $trigger['description']));
			}

			$dbTrigger = $dbTriggers[$trigger['triggerid']];

			if (isset($trigger['expression'])) {
				$expressionFull = explode_exp($dbTrigger['expression']);
				if (strcmp($trigger['expression'], $expressionFull) == 0) {
					unset($triggers[$key]['expression']);
				}

				// check item prototypes
				$triggerExpression = new CTriggerExpression();
				if (!$triggerExpression->parse($trigger['expression'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $triggerExpression->error);
				}

				// check item prototypes
				$items = getExpressionItems($triggerExpression);
				$this->checkDiscoveryRuleCount($trigger, $items);
			}

			if (isset($trigger['description']) && strcmp($trigger['description'], $dbTrigger['comments']) == 0) {
				unset($triggers[$key]['description']);
			}
			if (isset($trigger['priority']) && $trigger['priority'] == $dbTrigger['priority']) {
				unset($triggers[$key]['priority']);
			}
			if (isset($trigger['type']) && $trigger['type'] == $dbTrigger['type']) {
				unset($triggers[$key]['type']);
			}
			if (isset($trigger['comments']) && strcmp($trigger['comments'], $dbTrigger['comments']) == 0) {
				unset($triggers[$key]['comments']);
			}
			if (isset($trigger['url']) && strcmp($trigger['url'], $dbTrigger['url']) == 0) {
				unset($triggers[$key]['url']);
			}
			if (isset($trigger['status']) && $trigger['status'] == $dbTrigger['status']) {
				unset($triggers[$key]['status']);
			}

			$this->checkIfExistsOnHost($trigger);
		}

		$this->updateReal($triggers);

		foreach ($triggers as $trigger) {
			$trigger['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
			$this->inherit($trigger);
		}

		return array('triggerids' => $triggerIds);
	}

	/**
	 * Delete trigger prototypes.
	 *
	 * @param array 	$triggerIds array with trigger ids
	 * @param bool      $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $triggerIds, $nopermissions = false) {
		if (empty($triggerIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$delTriggers = $this->get(array(
			'triggerids' => $triggerIds,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'preservekeys' => true
		));

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($triggerIds as $triggerId) {
				if (!isset($delTriggers[$triggerId])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				if ($delTriggers[$triggerId]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot delete templated trigger "%1$s:%2$s".',
							$delTriggers[$triggerId]['description'],
							explode_exp($delTriggers[$triggerId]['expression']))
					);
				}
			}
		}

		// get child triggers
		$parentTriggerids = $triggerIds;
		do {
			$dbItems = DBselect('SELECT triggerid FROM triggers WHERE '.dbConditionInt('templateid', $parentTriggerids));
			$parentTriggerids = array();
			while ($dbTrigger = DBfetch($dbItems)) {
				$parentTriggerids[] = $dbTrigger['triggerid'];
				$triggerIds[$dbTrigger['triggerid']] = $dbTrigger['triggerid'];
			}
		} while (!empty($parentTriggerids));

		// select all triggers which are deleted (include childs)
		$delTriggers = $this->get(array(
			'triggerids' => $triggerIds,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		));

		// created triggers
		$createdTriggers = array();
		$sql = 'SELECT triggerid FROM trigger_discovery WHERE '.dbConditionInt('parent_triggerid', $triggerIds);
		$dbTriggers = DBselect($sql);
		while ($trigger = DBfetch($dbTriggers)) {
			$createdTriggers[$trigger['triggerid']] = $trigger['triggerid'];
		}
		if (!empty($createdTriggers)) {
			$result = API::Trigger()->delete($createdTriggers, true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete triggers created by low level discovery.'));
			}
		}

		// TODO: REMOVE info
		foreach ($delTriggers as $trigger) {
			info(_s('Deleted: Trigger prototype "%1$s" on "%2$s".', $trigger['description'],
					implode(', ', zbx_objectValues($trigger['hosts'], 'name'))));

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, $trigger['triggerid'],
					$trigger['description'].':'.$trigger['expression'], null, null, null);
		}

		DB::delete('triggers', array('triggerid' => $triggerIds));

		return array('triggerids' => $triggerIds);
	}

	protected function createReal(array &$triggers) {
		$triggers = zbx_toArray($triggers);

		foreach ($triggers as $key => $trigger) {
			$triggers[$key]['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}

		// insert triggers without expression
		$triggersCopy = $triggers;
		for ($i = 0, $size = count($triggersCopy); $i < $size; $i++) {
			unset($triggersCopy[$i]['expression']);
		}

		$triggerIds = DB::insert('triggers', $triggersCopy);
		unset($triggersCopy);

		foreach ($triggers as $key => $trigger) {
			$triggerId = $triggers[$key]['triggerid'] = $triggerIds[$key];
			$hosts = array();

			try {
				$expression = implode_exp($trigger['expression'], $triggerId, $hosts);
			}
			catch (Exception $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot implode expression "%s".', $trigger['expression']).' '.$e->getMessage());
			}

			DB::update('triggers', array(
				'values' => array('expression' => $expression),
				'where' => array('triggerid' => $triggerId)
			));

			info(_s('Created: Trigger prototype "%1$s" on "%2$s".', $trigger['description'], implode(', ', $hosts)));
		}
	}

	protected function updateReal(array $triggers) {
		$triggers = zbx_toArray($triggers);

		$dbTriggers = $this->get(array(
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('name'),
			'preservekeys' => true,
			'nopermissions' => true
		));

		$descriptionChanged = $expressionChanged = false;
		foreach ($triggers as &$trigger) {
			$dbTrigger = $dbTriggers[$trigger['triggerid']];
			$hosts = zbx_objectValues($dbTrigger['hosts'], 'name');

			if (isset($trigger['description']) && strcmp($dbTrigger['description'], $trigger['description']) != 0) {
				$descriptionChanged = true;
			}

			$expressionFull = explode_exp($dbTrigger['expression']);
			if (isset($trigger['expression']) && strcmp($expressionFull, $trigger['expression']) != 0) {
				$expressionChanged = true;
				$expressionFull = $trigger['expression'];
			}

			if ($descriptionChanged || $expressionChanged) {
				$expressionData = new CTriggerExpression();
				if (!$expressionData->parse($expressionFull)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				if (!isset($expressionData->expressions[0])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Trigger expression must contain at least one host:key reference.'));
				}
			}

			if ($expressionChanged) {
				DB::delete('functions', array('triggerid' => $trigger['triggerid']));

				try {
					$trigger['expression'] = implode_exp($expressionFull, $trigger['triggerid'], $hosts);
				}
				catch (Exception $e) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot implode expression "%s".', $expressionFull).' '.$e->getMessage());
				}
			}

			$triggerUpdate = $trigger;
			if (!$descriptionChanged) {
				unset($triggerUpdate['description']);
			}
			if (!$expressionChanged) {
				unset($triggerUpdate['expression']);
			}

			// skip updating read only values
			unset(
				$triggerUpdate['state'],
				$triggerUpdate['value'],
				$triggerUpdate['lastchange'],
				$triggerUpdate['error']
			);

			DB::update('triggers', array(
				'values' => $triggerUpdate,
				'where' => array('triggerid' => $trigger['triggerid'])
			));

			$description = isset($trigger['description']) ? $trigger['description'] : $dbTrigger['description'];

			info(_s('Updated: Trigger prototype "%1$s" on "%2$s".', $description, implode(', ', $hosts)));
		}
		unset($trigger);
	}

	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$triggers = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => array(
				'triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments', 'type'
			)
		));

		foreach ($triggers as $trigger) {
			$trigger['expression'] = explode_exp($trigger['expression']);
			$this->inherit($trigger, $data['hostids']);
		}

		return true;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput'] !== null) {
			// expandData
			if (!is_null($options['expandData'])) {
				$sqlParts['select']['host'] = 'h.host';
				$sqlParts['select']['hostid'] = 'h.hostid';
				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerids = array_keys($result);

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

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,functions f'.
					' WHERE '.dbConditionInt('f.triggerid', $triggerids).
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
	 * @param array  $trigger						array of trigger data
	 * @param string $trigger['description']		trigger description
	 * @param array  $items							array of trigger items
	 */
	protected function checkDiscoveryRuleCount(array $trigger, array $items) {
		if ($items) {
			$itemDiscoveries = API::getApiService()->select('item_discovery', array(
				'output' => array('parent_itemid'),
				'filter' => array('itemid' => zbx_objectValues($items, 'itemid')),
			));

			$itemDiscoveryIds = array_flip(zbx_objectValues($itemDiscoveries, 'parent_itemid'));

			if (count($itemDiscoveryIds) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Trigger prototype "%1$s" contains item prototypes from multiple discovery rules.',
					$trigger['description']
				));
			}
			elseif (!$itemDiscoveryIds) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Trigger prototype "%1$s" must contain at least one item prototype.',
					$trigger['description']
				));
			}
		}
	}
}
