<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * File containing CTrigger class for API.
 *
 * @package API
 */
class CTrigger extends CTriggerGeneral {

	protected $tableName = 'triggers';
	protected $tableAlias = 't';

	/**
	 * Get Triggers data
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

		// allowed columns for sorting
		$sortColumns = array('triggerid', 'description', 'status', 'priority', 'lastchange', 'hostname');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$fieldsToUnset = array();

		$sqlParts = array(
			'select'	=> array('triggers' => 't.triggerid'),
			'from'		=> array('t' => 'triggers t'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'						=> null,
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'triggerids'					=> null,
			'itemids'						=> null,
			'applicationids'				=> null,
			'functions'						=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored'						=> null,
			'active'						=> null,
			'maintenance'					=> null,
			'withUnacknowledgedEvents'		=> null,
			'withAcknowledgedEvents'		=> null,
			'withLastEventUnacknowledged'	=> null,
			'skipDependent'					=> null,
			'nopermissions'					=> null,
			'editable'						=> null,
			// timing
			'lastChangeSince'				=> null,
			'lastChangeTill'				=> null,
			// filter
			'group'							=> null,
			'host'							=> null,
			'only_true'						=> null,
			'min_severity'					=> null,
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> null,
			'excludeSearch'					=> null,
			'searchWildcardsEnabled'		=> null,
			// output
			'expandData'					=> null,
			'expandDescription'				=> null,
			'expandExpression'				=> null,
			'output'						=> API_OUTPUT_REFER,
			'selectGroups'					=> null,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectFunctions'				=> null,
			'selectDependencies'			=> null,
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

		if (is_array($options['output'])) {
			unset($sqlParts['select']['triggers']);

			$dbTable = DB::getSchema('triggers');
			$sqlParts['select']['triggerid'] = ' t.triggerid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 't.'.$field;
				}
			}

			if (!is_null($options['expandDescription'])) {
				if (!str_in_array('description', $options['output'])) {
					$options['expandDescription'] = null;
				}
				else {
					if (!str_in_array('expression', $options['output'])) {
						$sqlParts['select']['expression'] = ' t.expression';
						$fieldsToUnset[] = 'expression';
					}
				}
			}

			// ignore the "expandExpression" parameter if the expression is not requested
			if ($options['expandExpression'] !== null && !str_in_array('expression', $options['output'])) {
				$options['expandExpression'] = null;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS ('.
										' SELECT ff.triggerid'.
										' FROM functions ff,items ii'.
										' WHERE ff.triggerid=t.triggerid'.
											' AND ff.itemid=ii.itemid'.
											' AND EXISTS ('.
												' SELECT hgg.groupid'.
												' FROM hosts_groups hgg,rights rr,users_groups gg'.
												' WHERE hgg.hostid=ii.hostid'.
													' AND rr.id=hgg.groupid'.
													' AND rr.groupid=gg.usrgrpid'.
													' AND gg.userid='.$userid.
													' AND rr.permission<'.$permission.'))';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['groupid'] = DBcondition('hg.groupid', $options['groupids']);

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

			$sqlParts['select']['hostid'] = 'i.hostid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where']['triggerid'] = DBcondition('t.triggerid', $options['triggerids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'f.itemid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['itemid'] = DBcondition('f.itemid', $options['itemids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['f'] = 'f.itemid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['select']['applicationid'] = 'a.applicationid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where']['a'] = DBcondition('a.applicationid', $options['applicationids']);
			$sqlParts['where']['ia'] = 'i.hostid=a.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// functions
		if (!is_null($options['functions'])) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = DBcondition('f.function', $options['functions']);
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['where']['monitored'] = ''.
				' NOT EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT ii.itemid'.
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
			$sqlParts['where']['active'] = ''.
				' NOT EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT ii.itemid'.
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
			$sqlParts['where'][] = ($options['maintenance'] == 0 ? ' NOT ' : '').
				' EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT ii.itemid'.
							' FROM items ii,hosts hh'.
							' WHERE ff.itemid=ii.itemid'.
								' AND hh.hostid=ii.hostid'.
								' AND hh.maintenance_status=1'.
						' )'.
				' )';
			$sqlParts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// lastChangeSince
		if (!is_null($options['lastChangeSince'])) {
			$sqlParts['where']['lastchangesince'] = 't.lastchange>'.$options['lastChangeSince'];
		}

		// lastChangeTill
		if (!is_null($options['lastChangeTill'])) {
			$sqlParts['where']['lastchangetill'] = 't.lastchange<'.$options['lastChangeTill'];
		}

		// withUnacknowledgedEvents
		if (!is_null($options['withUnacknowledgedEvents'])) {
			$sqlParts['where']['unack'] = ' EXISTS ('.
				' SELECT e.eventid'.
				' FROM events e'.
				' WHERE e.objectid=t.triggerid'.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.acknowledged=0)';
		}
		// withAcknowledgedEvents
		if (!is_null($options['withAcknowledgedEvents'])) {
			$sqlParts['where']['ack'] = 'NOT EXISTS ('.
				' SELECT e.eventid'.
				' FROM events e'.
				' WHERE e.objectid=t.triggerid'.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.acknowledged=0)';
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
		if (is_null($options['filter'])) {
			$options['filter'] = array();
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = array(
					ZBX_FLAG_DISCOVERY_NORMAL,
					ZBX_FLAG_DISCOVERY_CREATED
				);
			}

			zbx_db_filter('triggers t', $options, $sqlParts);

			if (isset($options['filter']['host']) && !is_null($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = DBcondition('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid']) && !is_null($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['where']['hostid'] = DBcondition('i.hostid', $options['filter']['hostid']);
			}
		}

		// group
		if (!is_null($options['group'])) {
			$sqlParts['select']['name'] = 'g.name';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['groups'] = 'groups g';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ghg'] = 'g.groupid = hg.groupid';
			$sqlParts['where']['group'] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if (!is_null($options['host'])) {
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['i'] = DBcondition('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['host'] = ' h.host='.zbx_dbstr($options['host']);
		}

		// only_true
		if (!is_null($options['only_true'])) {
			$config = select_config();
			$sqlParts['where']['ot'] = '((t.value='.TRIGGER_VALUE_TRUE.')'.
					' OR '.
					'((t.value='.TRIGGER_VALUE_FALSE.') AND (t.lastchange>'.(time() - $config['ok_period']).')))';
		}

		// min_severity
		if (!is_null($options['min_severity'])) {
			$sqlParts['where'][] = 't.priority>='.$options['min_severity'];
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['triggers'] = 't.*';
		}

		// expandData
		if (!is_null($options['expandData'])) {
			$sqlParts['select']['hostname'] = 'h.name AS hostname';
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['select']['hostid'] = 'h.hostid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT t.triggerid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		if (!zbx_empty($options['sortfield'])) {
			if (!is_array($options['sortfield'])) {
				$options['sortfield'] = array($options['sortfield']);
			}

			foreach ($options['sortfield'] as $i => $sortfield) {
				// validate sortfield
				if (!str_in_array($sortfield, $sortColumns)) {
					throw new APIException(ZBX_API_ERROR_INTERNAL, _s('Sorting by field "%s" not allowed.', $sortfield));
				}

				// add sort field to order
				$sortorder = '';
				if (is_array($options['sortorder'])) {
					if (!empty($options['sortorder'][$i])) {
						$sortorder = $options['sortorder'][$i] == ZBX_SORT_DOWN ? ZBX_SORT_DOWN : '';
					}
				}
				else {
					$sortorder = $options['sortorder'] == ZBX_SORT_DOWN ? ZBX_SORT_DOWN : '';
				}

				// we will be using lastchange for ordering in any case
				if (!str_in_array('t.lastchange', $sqlParts['select']) && !str_in_array('t.*', $sqlParts['select'])) {
					$sqlParts['select']['lastchange'] = 't.lastchange';
				}

				switch ($sortfield) {
					case 'hostname':
						// the only way to sort by host name is to get it like this:
						// triggers -> functions -> items -> hosts
						$sqlParts['select']['hostname'] = 'h.name';
						$sqlParts['from']['functions'] = 'functions f';
						$sqlParts['from']['items'] = 'items i';
						$sqlParts['from']['hosts'] = 'hosts h';
						$sqlParts['where'][] = 't.triggerid = f.triggerid';
						$sqlParts['where'][] = 'f.itemid = i.itemid';
						$sqlParts['where'][] = 'i.hostid = h.hostid';
						$sqlParts['order'][] = 'h.name '.$sortorder;
						break;
					case 'lastchange':
						$sqlParts['order'][] = $sortfield.' '.$sortorder;
						break;
					default:
						// if lastchange is not used for ordering, it should be the second order criteria
						$sqlParts['order'][] = 't.'.$sortfield.' '.$sortorder;
						break;
				}

				// add sort field to select if distinct is used
				if (count($sqlParts['from']) > 1) {
					if (!str_in_array('t.'.$sortfield, $sqlParts['select']) && !str_in_array('t.*', $sqlParts['select'])) {
						$sqlParts['select'][$sortfield] = 't.'.$sortfield;
					}
				}
			}
			if (!empty($sqlParts['order'])) {
				$sqlParts['order'][] = 't.lastchange DESC';
			}
		}

		// limit
		$postLimit = false;
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			// to make limit work correctly with truncating filters (skipDependent, withLastEventUnacknowledged)
			// do select without limit, truncate result and then slice excess data
			if (!is_null($options['skipDependent']) || !is_null($options['withLastEventUnacknowledged'])) {
				$postLimit = $options['limit'];
				$sqlParts['limit'] = null;
			}
			else {
				$sqlParts['limit'] = $options['limit'];
			}
		}

		$triggerids = array();

		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
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
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];

				if (!isset($result[$trigger['triggerid']])) {
					$result[$trigger['triggerid']] = array();
				}
				if (!is_null($options['selectHosts']) && !isset($result[$trigger['triggerid']]['hosts'])) {
					$result[$trigger['triggerid']]['hosts'] = array();
				}
				if (!is_null($options['selectItems']) && !isset($result[$trigger['triggerid']]['items'])) {
					$result[$trigger['triggerid']]['items'] = array();
				}
				if (!is_null($options['selectFunctions']) && !isset($result[$trigger['triggerid']]['functions'])) {
					$result[$trigger['triggerid']]['functions'] = array();
				}
				if (!is_null($options['selectDependencies']) && !isset($result[$trigger['triggerid']]['dependencies'])) {
					$result[$trigger['triggerid']]['dependencies'] = array();
				}
				if (!is_null($options['selectDiscoveryRule']) && !isset($result[$trigger['triggerid']]['discoveryRule'])) {
					$result[$trigger['triggerid']]['discoveryRule'] = array();
				}

				// groups
				if (isset($trigger['groupid']) && is_null($options['selectGroups'])) {
					if (!isset($result[$trigger['triggerid']]['groups'])) {
						$result[$trigger['triggerid']]['groups'] = array();
					}

					$result[$trigger['triggerid']]['groups'][] = array('groupid' => $trigger['groupid']);
					unset($trigger['groupid']);
				}

				// hostids
				if (isset($trigger['hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$trigger['triggerid']]['hosts'])) {
						$result[$trigger['triggerid']]['hosts'] = array();
					}

					$result[$trigger['triggerid']]['hosts'][] = array('hostid' => $trigger['hostid']);

					if (is_null($options['expandData'])) {
						unset($trigger['hostid']);
					}
				}
				// itemids
				if (isset($trigger['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$trigger['triggerid']]['items'])) {
						$result[$trigger['triggerid']]['items'] = array();
					}

					$result[$trigger['triggerid']]['items'][] = array('itemid' => $trigger['itemid']);
					unset($trigger['itemid']);
				}

				$result[$trigger['triggerid']] += $trigger;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// skipDependent
		if (!is_null($options['skipDependent'])) {
			$tids = $triggerids;
			$map = array();

			do {
				$dbResult = DBselect(
					'SELECT d.triggerid_down,d.triggerid_up,t.value'.
					' FROM trigger_depends d,triggers t'.
					' WHERE '.DBcondition('d.triggerid_down', $tids).
						' AND d.triggerid_up=t.triggerid'
				);
				$tids = array();
				while ($row = DBfetch($dbResult)) {
					if (TRIGGER_VALUE_TRUE == $row['value']) {
						if (isset($map[$row['triggerid_down']])) {
							foreach ($map[$row['triggerid_down']] as $triggerid => $state) {
								unset($result[$triggerid]);
								unset($triggerids[$triggerid]);
							}
						}
						else {
							unset($result[$row['triggerid_down']]);
							unset($triggerids[$row['triggerid_down']]);
						}
					}
					else {
						if (isset($map[$row['triggerid_down']])) {
							if (!isset($map[$row['triggerid_up']])) {
								$map[$row['triggerid_up']] = array();
							}

							$map[$row['triggerid_up']] += $map[$row['triggerid_down']];
						}
						else {
							if (!isset($map[$row['triggerid_up']])) {
								$map[$row['triggerid_up']] = array();
							}

							$map[$row['triggerid_up']][$row['triggerid_down']] = 1;
						}
						$tids[] = $row['triggerid_up'];
					}
				}
			} while (!empty($tids));
		}

		// withLastEventUnacknowledged
		if (!is_null($options['withLastEventUnacknowledged'])) {
			$eventids = array();
			$eventsDb = DBselect(
				'SELECT MAX(e.eventid) AS eventid,e.objectid'.
				' FROM events e'.
				' WHERE e.object='.EVENT_OBJECT_TRIGGER.
					' AND '.DBcondition('e.objectid', $triggerids).
					' AND '.DBcondition('e.value', array(TRIGGER_VALUE_TRUE)).
					' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
				' GROUP BY e.objectid'
			);
			while ($event = DBfetch($eventsDb)) {
				$eventids[] = $event['eventid'];
			}

			$correctTriggerids = array();
			$triggersDb = DBselect(
				'SELECT e.objectid'.
				' FROM events e '.
				' WHERE '.DBcondition('e.eventid', $eventids).
					' AND e.acknowledged=0'
			);
			while ($trigger = DBfetch($triggersDb)) {
				$correctTriggerids[$trigger['objectid']] = $trigger['objectid'];
			}
			foreach ($result as $triggerid => $trigger) {
				if (!isset($correctTriggerids[$triggerid])) {
					unset($result[$triggerid]);
					unset($triggerids[$triggerid]);
				}
			}
		}

		// limit selected triggers after result set is truncated by previous filters (skipDependent, withLastEventUnacknowledged)
		if ($postLimit) {
			$result = array_slice($result, 0, $postLimit, true);
			$triggerids = array_slice($triggerids, 0, $postLimit, true);
		}

		/*
		 * Adding objects
		 */
		// adding trigger dependencies
		if (!is_null($options['selectDependencies']) && str_in_array($options['selectDependencies'], $subselectsAllowedOutputs)) {
			$deps = array();
			$depids = array();

			$dbDeps = DBselect(
				'SELECT td.triggerid_up,td.triggerid_down'.
				' FROM trigger_depends td'.
				' WHERE '.DBcondition('td.triggerid_down', $triggerids)
			);
			while ($dbDep = DBfetch($dbDeps)) {
				if (!isset($deps[$dbDep['triggerid_down']])) {
					$deps[$dbDep['triggerid_down']] = array();
				}
				$deps[$dbDep['triggerid_down']][$dbDep['triggerid_up']] = $dbDep['triggerid_up'];
				$depids[] = $dbDep['triggerid_up'];
			}

			$objParams = array(
				'triggerids' => $depids,
				'output' => $options['selectDependencies'],
				'expandData' => true,
				'preservekeys' => true
			);
			$allowed = $this->get($objParams); // allowed triggerids
			foreach ($deps as $triggerid => $deptriggers) {
				foreach ($deptriggers as $deptriggerid) {
					if (isset($allowed[$deptriggerid])) {
						$result[$triggerid]['dependencies'][] = $allowed[$deptriggerid];
					}
				}
			}
		}

		// adding groups
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectGroups'],
				'triggerids' => $triggerids,
				'preservekeys' => true
			);
			$groups = API::HostGroup()->get($objParams);
			foreach ($groups as $groupid => $group) {
				$gtriggers = $group['triggers'];
				unset($group['triggers']);

				foreach ($gtriggers as $trigger) {
					$result[$trigger['triggerid']]['groups'][] = $group;
				}
			}
		}

		// adding hosts
		if (!is_null($options['selectHosts'])) {
			$objParams = array(
				'nodeids' => $options['nodeids'],
				'triggerids' => $triggerids,
				'templated_hosts' => true,
				'nopermissions' => true,
				'preservekeys' => true
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($objParams);

				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}
				foreach ($hosts as $hostid => $host) {
					unset($hosts[$hostid]['triggers']);

					$count = array();
					foreach ($host['triggers'] as $trigger) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$trigger['triggerid']])) {
								$count[$trigger['triggerid']] = 0;
							}
							$count[$trigger['triggerid']]++;

							if ($count[$trigger['triggerid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$trigger['triggerid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else {
				if (API_OUTPUT_COUNT == $options['selectHosts']) {
					$objParams['countOutput'] = 1;
					$objParams['groupCount'] = 1;

					$hosts = API::Host()->get($objParams);
					$hosts = zbx_toHash($hosts, 'hostid');
					foreach ($result as $triggerid => $trigger) {
						if (isset($hosts[$triggerid])) {
							$result[$triggerid]['hosts'] = $hosts[$triggerid]['rowscount'];
						}
						else {
							$result[$triggerid]['hosts'] = 0;
						}
					}
				}
			}
		}

		// adding functions
		if (!is_null($options['selectFunctions']) && str_in_array($options['selectFunctions'], $subselectsAllowedOutputs)) {
			if ($options['selectFunctions'] == API_OUTPUT_EXTEND) {
				$sqlSelect = 'f.*';
			}
			else {
				$sqlSelect = 'f.functionid,f.triggerid';
			}

			$res = DBselect(
				'SELECT '.$sqlSelect.
				' FROM functions f'.
				' WHERE '.DBcondition('f.triggerid', $triggerids)
			);
			while ($function = DBfetch($res)) {
				$triggerid = $function['triggerid'];
				unset($function['triggerid']);

				$result[$triggerid]['functions'][] = $function;
			}
		}

		// adding items
		if (!is_null($options['selectItems']) && (is_array($options['selectItems']) || str_in_array($options['selectItems'], $subselectsAllowedOutputs))) {
			$objParams = array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectItems'],
				'triggerids' => $triggerids,
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$items = API::Item()->get($objParams);
			foreach ($items as $item) {
				$itriggers = $item['triggers'];
				unset($item['triggers']);

				foreach ($itriggers as $trigger) {
					$result[$trigger['triggerid']]['items'][] = $item;
				}
			}
		}

		// adding discoveryrule
		if (!is_null($options['selectDiscoveryRule'])) {
			$ruleids = $ruleMap = array();

			$dbRules = DBselect(
				'SELECT id.parent_itemid,td.triggerid'.
				' FROM trigger_discovery td,item_discovery id,functions f'.
				' WHERE '.DBcondition('td.triggerid', $triggerids).
					' AND td.parent_triggerid=f.triggerid'.
					' AND f.itemid=id.itemid'
			);
			while ($rule = DBfetch($dbRules)) {
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$ruleMap[$rule['triggerid']] = $rule['parent_itemid'];
			}

			$objParams = array(
				'nodeids' => $options['nodeids'],
				'itemids' => $ruleids,
				'nopermissions' => true,
				'preservekeys' => true,
			);

			if (is_array($options['selectDiscoveryRule']) || str_in_array($options['selectDiscoveryRule'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDiscoveryRule'];
				$discoveryRules = API::Item()->get($objParams);

				foreach ($result as $triggerid => $trigger) {
					if (isset($ruleMap[$triggerid]) && isset($discoveryRules[$ruleMap[$triggerid]])) {
						$result[$triggerid]['discoveryRule'] = $discoveryRules[$ruleMap[$triggerid]];
					}
				}
			}
		}

		// expandDescription
		if (!is_null($options['expandDescription']) && $result && array_key_exists('description', reset($result))) {
			$result = CTriggerHelper::batchExpandDescription($result);
		}

		// expand expression
		if ($options['expandExpression'] !== null) {
			foreach ($result as &$trigger) {
				if ($trigger['expression']) {
					$trigger['expression'] = explode_exp($trigger['expression'], false, true);
				}
			}
			unset($trigger);
		}

		if (!empty($fieldsToUnset)) {
			foreach ($result as $tnum => $trigger) {
				foreach ($fieldsToUnset as $fieldToUnset) {
					unset($result[$tnum][$fieldToUnset]);
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get triggerid by host.host and trigger.expression.
	 *
	 * @param array $triggerData multidimensional array with trigger objects
	 * @param array $triggerData[0,...]['expression']
	 * @param array $triggerData[0,...]['host']
	 * @param array $triggerData[0,...]['hostid'] OPTIONAL
	 * @param array $triggerData[0,...]['description'] OPTIONAL
	 *
	 * @return array|int
	 */
	public function getObjects(array $triggerData) {
		$options = array(
			'filter' => $triggerData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($triggerData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($triggerData['node']);
		}
		else {
			if (isset($triggerData['nodeids'])) {
				$options['nodeids'] = $triggerData['nodeids'];
			}
		}

		// expression is checked later
		unset($options['filter']['expression']);
		$result = $this->get($options);
		if (isset($triggerData['expression'])) {
			foreach ($result as $tnum => $trigger) {
				$tmpExp = explode_exp($trigger['expression']);

				if (strcmp(trim($tmpExp, ' '), trim($triggerData['expression'], ' ')) != 0) {
					unset($result[$tnum]);
				}
			}
		}
		return $result;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	public function exists(array $object) {
		$keyFields = array(
			array(
				'hostid',
				'host'
			),
			'description'
		);

		$result = false;

		if (!isset($object['hostid']) && !isset($object['host'])) {
			$expressionData = new CTriggerExpression($object['expression']);
			if (!$expressionData->isValid) {
				return false;
			}
			$object['host'] = reset($expressionData->getHosts());
		}

		$options = array(
			'filter' => array_merge(zbx_array_mintersect($keyFields, $object), array('flags' => null)),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		);

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$triggers = $this->get($options);
		foreach ($triggers as $trigger) {
			$tmpExp = explode_exp($trigger['expression']);
			if (strcmp($tmpExp, $object['expression']) == 0) {
				$result = true;
				break;
			}
		}
		return $result;
	}

	/**
	 * @param $triggers
	 * @param $method
	 */
	public function checkInput(array &$triggers, $method) {
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		// permissions
		if ($update || $delete) {
			$triggerDbFields = array('triggerid' => null);
			$dbTriggers = $this->get(array(
				'triggerids' => zbx_objectValues($triggers, 'triggerid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true,
				'selectDependencies' => API_OUTPUT_REFER
			));
		}
		else {
			$triggerDbFields = array(
				'description' => null,
				'expression' => null,
				'error' => 'Trigger just added. No status update so far.',
				'value' => TRIGGER_VALUE_FALSE,
				'value_flags' => TRIGGER_VALUE_FLAG_UNKNOWN,
				'lastchange' => time()
			);
		}

		if ($update){
			$triggers = $this->extendObjects($this->tableName(), $triggers, array('description'));
		}

		foreach ($triggers as $tnum => &$trigger) {
			$currentTrigger = $triggers[$tnum];

			if (($update || $delete) && !isset($dbTriggers[$trigger['triggerid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $trigger)) {
				if ($update) {
					$error = _s('Cannot update "templateid" for trigger "%1$s".', $trigger['description']);
				}
				else {
					$error = _s('Cannot set "templateid" for trigger "%1$s".', $trigger['description']);
				}
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if (!check_db_fields($triggerDbFields, $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect fields for trigger.'));
			}

			if ($update) {
				$dbTrigger = $dbTriggers[$trigger['triggerid']];
			}
			elseif ($delete) {
				if ($dbTriggers[$trigger['triggerid']]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot delete templated trigger "%1$s:%2$s".', $dbTriggers[$trigger['triggerid']]['description'],
							explode_exp($dbTriggers[$trigger['triggerid']]['expression']))
					);
				}
				continue;
			}

			$expressionChanged = true;
			if ($update) {
				if (isset($trigger['expression'])) {
					$expressionFull = explode_exp($dbTrigger['expression']);
					if (strcmp($trigger['expression'], $expressionFull) == 0) {
						$expressionChanged = false;
					}
				}
				if (isset($trigger['description']) && strcmp($trigger['description'], $dbTrigger['description']) == 0) {
					unset($trigger['description']);
				}
				if (isset($trigger['priority']) && $trigger['priority'] == $dbTrigger['priority']) {
					unset($trigger['priority']);
				}
				if (isset($trigger['type']) && $trigger['type'] == $dbTrigger['type']) {
					unset($trigger['type']);
				}
				if (isset($trigger['comments']) && strcmp($trigger['comments'], $dbTrigger['comments']) == 0) {
					unset($trigger['comments']);
				}
				if (isset($trigger['url']) && strcmp($trigger['url'], $dbTrigger['url']) == 0) {
					unset($trigger['url']);
				}
				if (isset($trigger['status']) && $trigger['status'] == $dbTrigger['status']) {
					unset($trigger['status']);
				}
				if (isset($trigger['dependencies'])) {
					$dbTrigger['dependencies'] = zbx_objectValues($dbTrigger['dependencies'], 'triggerid');
					if (array_equal($dbTrigger['dependencies'], $trigger['dependencies'])) {
						unset($trigger['dependencies']);
					}
				}
			}

			// if some of the properties are unchanged, no need to update them in DB
			// validating trigger expression
			if (isset($trigger['expression']) && $expressionChanged) {
				// expression permissions
				$expressionData = new CTriggerExpression($trigger['expression']);
				if (!$expressionData->isValid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				$expressionHosts = $expressionData->getHosts();

				$hosts = API::Host()->get(array(
					'filter' => array('host' => $expressionHosts),
					'editable' => true,
					'output' => array(
						'hostid',
						'host',
						'status'
					),
					'templated_hosts' => true,
					'preservekeys' => true
				));
				$hosts = zbx_toHash($hosts, 'host');
				$hostsStatusFlags = 0x0;
				foreach ($expressionHosts as $host) {
					if (!isset($hosts[$host])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect trigger expression. Host "%s" does not exist or you have no access to this host.', $host));
					}

					// find out if both templates and hosts are referenced in expression
					$hostsStatusFlags |= ($hosts[$host]['status'] == HOST_STATUS_TEMPLATE) ? 0x1 : 0x2;
					if ($hostsStatusFlags == 0x3) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect trigger expression. Trigger expression elements should not belong to a template and a host simultaneously.'));
					}
				}

				foreach ($expressionData->expressions as $exprPart) {
					$sql = 'SELECT i.itemid,i.value_type'.
							' FROM items i,hosts h'.
							' WHERE i.key_='.zbx_dbstr($exprPart['item']).
								' AND'.DBcondition('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
								' AND h.host='.zbx_dbstr($exprPart['host']).
								' AND h.hostid=i.hostid'.
								' AND '.DBin_node('i.itemid');
					if (!DBfetch(DBselect($sql))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect item key "%1$s" provided for trigger expression on "%2$s".', $exprPart['item'], $exprPart['host']));
					}
				}
			}

			// check existing
			$this->checkIfExistsOnHost($currentTrigger);
		}
		unset($trigger);
	}

	/**
	 * Add triggers
	 *
	 * Trigger params: expression, description, type, priority, status, comments, url, templateid
	 *
	 * @param array $triggers
	 *
	 * @return boolean
	 */
	public function create(array $triggers) {
		$triggers = zbx_toArray($triggers);

		$this->checkInput($triggers, __FUNCTION__);
		$this->createReal($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger);
		}

		// clear all dependencies on inherited triggers
		$this->deleteDependencies($triggers);

		// add new dependencies
		foreach ($triggers as $trigger) {
			if (!empty($trigger['dependencies'])) {
				$newDeps = array();
				foreach ($trigger['dependencies'] as $depTrigger) {
					$newDeps[] = array(
						'triggerid' => $trigger['triggerid'],
						'dependsOnTriggerid' => $depTrigger['triggerid']
					);
				}
				$this->addDependencies($newDeps);
			}
		}
		return array('triggerids' => zbx_objectValues($triggers, 'triggerid'));
	}

	/**
	 * Update triggers.
	 *
	 * If a trigger expression is passed in any of the triggers, it must be in it's exploded form.
	 *
	 * @param array $triggers
	 *
	 * @return boolean
	 */
	public function update(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$triggerids = zbx_objectValues($triggers, 'triggerid');

		$this->checkInput($triggers, __FUNCTION__);
		$this->updateReal($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger);

			// replace dependencies
			if (isset($trigger['dependencies'])) {
				$this->deleteDependencies($trigger);

				if ($trigger['dependencies']) {
					$newDeps = array();
					foreach ($trigger['dependencies'] as $depTrigger) {
						$newDeps[] = array(
							'triggerid' => $trigger['triggerid'],
							'dependsOnTriggerid' => $depTrigger['triggerid']
						);
					}
					$this->addDependencies($newDeps);
				}
			}
		}
		return array('triggerids' => $triggerids);
	}

	/**
	 * Delete triggers
	 *
	 * @param array $triggerids array with trigger ids
	 *
	 * @return array
	 */
	public function delete($triggerids, $nopermissions = false) {
		$triggerids = zbx_toArray($triggerids);
		$triggers = zbx_toObject($triggerids, 'triggerid');
		$delTriggerIds = $triggerids;

		if (empty($triggerids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			$this->checkInput($triggers, __FUNCTION__);
		}

		// get child triggers
		$parentTriggerids = $triggerids;
		do {
			$dbItems = DBselect('SELECT triggerid FROM triggers WHERE '.DBcondition('templateid', $parentTriggerids));
			$parentTriggerids = array();
			while ($dbTrigger = DBfetch($dbItems)) {
				$parentTriggerids[] = $dbTrigger['triggerid'];
				$triggerids[] = $dbTrigger['triggerid'];
			}
		} while (!empty($parentTriggerids));

		// select all triggers which are deleted (including children)
		$delTriggers = $this->get(array(
			'triggerids' => $triggerids,
			'output' => array('triggerid', 'description', 'expression'),
			'nopermissions' => true,
			'selectHosts' => array('name')
		));
		// TODO: REMOVE info
		foreach ($delTriggers as $trigger) {
			info(_s('Deleted: Trigger "%1$s" on "%2$s".', $trigger['description'],
					implode(', ', zbx_objectValues($trigger['hosts'], 'name'))));
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $trigger['triggerid'],
					$trigger['description'], null, null, null);
		}

		// execute delete
		$this->deleteByPks($triggerids);

		return array('triggerids' => $delTriggerIds);
	}


	protected function deleteByPks(array $pks) {

		// others idx should be deleted as well if they arise at some point
		DB::delete('profiles', array(
			'idx' => 'web.events.filter.triggerid',
			'value_id' => $pks
		));

		DB::delete('events', array(
			'objectid' => $pks,
			'object' => EVENT_OBJECT_TRIGGER
		));

		DB::delete('sysmaps_elements', array(
			'elementid' => $pks,
			'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER
		));

		// disable actions
		$actionids = array();
		$dbActions = DBselect(
			'SELECT DISTINCT actionid'.
			' FROM conditions'.
			' WHERE conditiontype='.CONDITION_TYPE_TRIGGER.
				' AND '.DBcondition('value', $pks, false, true)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		DBexecute('UPDATE actions SET status='.ACTION_STATUS_DISABLED.' WHERE '.DBcondition('actionid', $actionids));

		// delete action conditions
		DB::delete('conditions', array(
			'conditiontype' => CONDITION_TYPE_TRIGGER,
			'value' => $pks
		));

		// update linked services
		foreach ($pks as $triggerId) {
			update_services($triggerId, SERVICE_STATUS_OK);
		}

		parent::deleteByPks($pks);
	}

	/**
	 * Validates the input for the addDependencies() method.
	 *
	 * @throws APIException if the given dependencies are invalid
	 *
	 * @param array $triggersData
	 */
	protected function validateAddDependencies(array $triggersData) {
		if (!$triggersData) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$depTtriggerIds = array();
		$triggers = array();
		foreach ($triggersData as $dep) {
			$triggerId = $dep['triggerid'];

			if (!isset($triggers[$dep['triggerid']])) {
				$triggers[$triggerId] = array(
					'triggerid' => $triggerId,
					'dependencies' => array(),
				);
			}
			$triggers[$triggerId]['dependencies'][] = $dep['dependsOnTriggerid'];
			$depTtriggerIds[$dep['dependsOnTriggerid']] = $dep['dependsOnTriggerid'];
		}

		if (!$this->isReadable($depTtriggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		};

		$this->checkDependencies($triggers);
		$this->checkDependencyParents($triggers);
		$this->checkDependencyDuplicates($triggers);
	}

	/**
	 * Add the given dependencies and inherit them on all child triggers.
	 *
	 * @param array $triggersData   an array of trigger dependency pairs, each pair in the form of
	 *                              array('triggerid' => 1, 'dependsOnTriggerid' => 2)
	 *
	 * @return array
	 */
	public function addDependencies(array $triggersData) {
		$triggersData = zbx_toArray($triggersData);

		$triggerIds = array();
		foreach ($triggersData as $dep) {
			$triggerIds[$dep['triggerid']] = $dep['triggerid'];
		}
		if (!$this->isWritable($triggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		};

		$this->validateAddDependencies($triggersData);


		foreach ($triggersData as $dep) {
			$triggerId = $dep['triggerid'];
			$depTriggerId = $dep['dependsOnTriggerid'];

			DB::insert('trigger_depends', array(array(
				'triggerid_down' => $triggerId,
				'triggerid_up' => $depTriggerId
			)));

			// propagate the dependencies to the child triggers
			$childTriggers = API::getApi()->select($this->tableName(), array(
				'output' => array('triggerid'),
				'filter' => array(
					'templateid' => $triggerId
				)
			));
			if ($childTriggers) {
				foreach ($childTriggers as $childTrigger) {
					$childHostsQuery = get_hosts_by_triggerid($childTrigger['triggerid']);
					while ($childHost = DBfetch($childHostsQuery)) {
						$newDep = array($childTrigger['triggerid'] => $depTriggerId);
						$newDep = replace_template_dependencies($newDep, $childHost['hostid']);

						$this->addDependencies(array(array(
							'triggerid' => $childTrigger['triggerid'],
							'dependsOnTriggerid' => $newDep[$childTrigger['triggerid']]
						)));
					}
				}
			}
		}
		return array('triggerids' => $triggerIds);
	}

	/**
	 * Validates the input for the deleteDependencies() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $triggers
	 */
	protected function validateDeleteDependencies(array $triggers) {
		if (!$triggers) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
	}

	/**
	 * Deletes all trigger dependencies from the given triggers and their children.
	 *
	 * @param array $triggers   an array of triggers with the 'triggerid' field defined
	 *
	 * @return boolean
	 */
	public function deleteDependencies(array $triggers) {
		$triggers = zbx_toArray($triggers);

		$this->validateDeleteDependencies($triggers);

		$triggerids = zbx_objectValues($triggers, 'triggerid');

		try {
			// delete the dependencies from the child triggers
			$childTriggers = API::getApi()->select($this->tableName(), array(
				'output' => array('triggerid'),
				'filter' => array(
					'templateid' => $triggerids
				)
			));
			if ($childTriggers) {
				$this->deleteDependencies($childTriggers);
			}

			DB::delete('trigger_depends', array(
				'triggerid_down' => $triggerids
			));
		}
		catch (APIException $e) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete dependency'));
		}
		return array('triggerids' => $triggerids);
	}

	/**
	 * @param $triggers
	 */
	protected function createReal(array &$triggers) {
		$triggers = zbx_toArray($triggers);

		// insert triggers without expression
		$triggersCopy = $triggers;
		for ($i = 0, $size = count($triggersCopy); $i < $size; $i++) {
			unset($triggersCopy[$i]['expression']);
		}
		$triggerids = DB::insert('triggers', $triggersCopy);
		unset($triggersCopy);

		// update triggers expression
		foreach ($triggers as $tnum => $trigger) {
			$triggerid = $triggers[$tnum]['triggerid'] = $triggerids[$tnum];

			addUnknownEvent($triggerid);

			$hosts = array();
			$expression = implode_exp($trigger['expression'], $triggerid, $hosts);
			if (is_null($expression)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot implode expression "%s".', $trigger['expression']));
			}

			$this->validateItems($trigger);

			DB::update('triggers', array(
				'values' => array('expression' => $expression),
				'where' => array('triggerid' => $triggerid)
			));

			info(_s('Created: Trigger "%1$s" on "%2$s".', $trigger['description'], implode(', ', $hosts)));
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TRIGGER, $triggerid,
					$trigger['description'], null, null, null);
		}
	}

	/**
	 * @param $triggers
	 */
	protected function updateReal(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$infos = array();

		$dbTriggers = $this->get(array(
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('name'),
			'selectDependencies' => API_OUTPUT_REFER,
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
			else {
				$trigger['description'] = $dbTrigger['description'];
			}

			$oldExpression = explode_exp($dbTrigger['expression']);
			if (isset($trigger['expression']) && strcmp($oldExpression, $trigger['expression']) != 0) {
				$this->validateItems($trigger);

				$expressionChanged = true;
				$expressionFull = $trigger['expression'];
				$trigger['error'] = 'Trigger expression updated. No status update so far.';
			}

			if ($expressionChanged) {
				// check the expression
				$expressionData = new CTriggerExpression($expressionFull);
				if (!$expressionData->isValid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				// remove triggers if expression is changed in a way that trigger will not appear in current host
				$oldExpressionData = new CTriggerExpression($oldExpression);
				// chech if at least one template has stayed in expression, this means that child trigger will stay in host
				$oldTemplates = $oldExpressionData->getHosts();
				$newTemplates = zbx_toHash($expressionData->getHosts());
				$proceed = true;
				foreach ($oldTemplates as $oldTemplate) {
					if (isset($newTemplates[$oldTemplate])) {
						$proceed = false;
						break;
					}
				}
				// proceed if there is possibility that child triggers should be deleted
				if ($proceed) {
					$sql = 'SELECT t.triggerid'.
							' FROM triggers t'.
							' WHERE t.templateid='.zbx_dbstr($trigger['triggerid']);
					$cTrigCursor = DBselect($sql);
					$cTrigIds = array();
					while ($cTrig = DBfetch($cTrigCursor)) {
						// get templates linked to templated trigger host
						$templateNames = DBfetchArrayAssoc(DBselect('SELECT h.name'.
							' FROM hosts h, hosts_templates ht, items i, functions f'.
							' WHERE h.hostid = ht.templateid AND ht.hostid = i.hostid AND i.itemid = f.itemid AND'.
							' f.triggerid='.zbx_dbstr($cTrig['triggerid'])), 'name');

						// if we have at least one template linked to trigger host inside trigger expression,
						// then we don't delete this trigger
						$templateNames = $expressionData->getHosts();
						foreach ($templateNames as $templateName) {
							if (isset($templateNames[$templateName])) {
								continue 2;
							}
						}
						$cTrigIds[] = $cTrig['triggerid'];
					}
					$this->deleteByPks($cTrigIds);
				}

				// if the trigger contains templates, delete any events that may exist
				if ($this->expressionHasTemplates($expressionData)) {
					DB::delete('events', array(
						'object' => EVENT_OBJECT_TRIGGER,
						'objectid' => $trigger['triggerid']
					));
				}

				DB::delete('functions', array('triggerid' => $trigger['triggerid']));

				$trigger['expression'] = implode_exp($expressionFull, $trigger['triggerid'], $hosts);
				if (is_null($trigger['expression'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot implode expression "%s".', $expressionFull));
				}

				if (isset($trigger['status']) && ($trigger['status'] != TRIGGER_STATUS_ENABLED)) {
					if ($trigger['value_flags'] == TRIGGER_VALUE_FLAG_NORMAL) {
						addUnknownEvent($trigger['triggerid']);

						$trigger['value_flags'] = TRIGGER_VALUE_FLAG_UNKNOWN;
					}
				}

				// if the expression has changed, we must revalidate the existing dependencies
				if (!isset($trigger['dependencies'])) {
					$trigger['dependencies'] = zbx_objectValues($dbTrigger['dependencies'], 'triggerid');
				}
			}

			$triggerUpdate = $trigger;
			if (!$descriptionChanged) {
				unset($triggerUpdate['description']);
			}
			if (!$expressionChanged) {
				unset($triggerUpdate['expression']);
			}

			DB::update('triggers', array(
				'values' => $triggerUpdate,
				'where' => array('triggerid' => $trigger['triggerid'])
			));

			// update service status
			if (isset($trigger['priority']) && $trigger['priority'] != $dbTrigger['priority']) {
				$serviceStatus = ($dbTrigger['value'] == TRIGGER_VALUE_TRUE) ? $trigger['priority'] : 0;
				update_services($trigger['triggerid'], $serviceStatus);
			}

			// restore the full expression to properly validate dependencies
			$trigger['expression'] = $expressionChanged ? explode_exp($trigger['expression']) : $oldExpression;

			$infos[] = _s('Updated: Trigger "%1$s" on "%2$s".', $trigger['description'], implode(', ', $hosts));
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, $dbTrigger['triggerid'],
					$dbTrigger['description'], null, $dbTrigger, $triggerUpdate);
		}
		unset($trigger);

		foreach ($infos as $info) {
			info($info);
		}
	}

	/**
	 * @param $data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if (!API::Host()->isWritable($data['hostids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
		if (!API::Template()->isWritable($data['templateids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$triggers = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
		));

		foreach ($triggers as $trigger) {
			$trigger['expression'] = explode_exp($trigger['expression']);
			$this->inherit($trigger, $data['hostids']);
		}

		return true;
	}

	/**
	 * Synchronizes the templated trigger dependencies on the given hosts inherited from the given
	 * templates.
	 * Update dependencies, do it after all triggers that can be dependent were created/updated on
	 * all child hosts/templates. Starting from highest level template triggers select triggers from
	 * one level lower, then for each lower trigger look if it's parent has dependencies, if so
	 * find this dependency trigger child on dependent trigger host and add new dependency.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function syncTemplateDependencies(array $data) {
		$templateIds = zbx_toArray($data['templateids']);
		$hostIds = zbx_toArray($data['hostids']);

		$parentTriggers = $this->get(array(
			'hostids' => $templateIds,
			'preservekeys' => true,
			'output' => array('triggerid'),
			'selectDependencies' => API_OUTPUT_REFER
		));

		if ($parentTriggers) {
			$childTriggers = $this->get(array(
				'hostids' => ($hostIds) ? $hostIds : null,
				'filter' => array('templateid' => array_keys($parentTriggers)),
				'nopermissions' => true,
				'preservekeys' => true,
				'output' => array('triggerid', 'templateid'),
				'selectDependencies' => API_OUTPUT_REFER,
				'selectHosts' => array('hostid')
			));

			if ($childTriggers) {
				$newDependencies = array();
				foreach ($childTriggers as $childTrigger) {
					$parentDependencies = $parentTriggers[$childTrigger['templateid']]['dependencies'];
					if ($parentDependencies) {
						$dependencies = array();
						foreach ($parentDependencies as $depTrigger) {
							$dependencies[] = $depTrigger['triggerid'];
						}
						$host = reset($childTrigger['hosts']);
						$dependencies = replace_template_dependencies($dependencies, $host['hostid']);
						foreach ($dependencies as $triggerId => $depTriggerId) {
							$newDependencies[] = array(
								'triggerid' => $childTrigger['triggerid'],
								'dependsOnTriggerid' => $depTriggerId
							);
						}
					}
				}
				$this->deleteDependencies($childTriggers);

				if ($newDependencies) {
					$this->addDependencies($newDependencies);
				}
			}
		}
	}

	/**
	 * Validates the dependencies of the given triggers.
	 *
	 * @param array $triggers list of triggers and corresponding dependencies
	 * @param int $triggers[]['triggerid'] trigger id
	 * @param array $triggers[]['dependencies'] list of trigger ids on which depends given trigger
	 *
	 * @trows APIException if any of the dependencies is invalid
	 */
	protected function checkDependencies(array $triggers) {
		foreach ($triggers as $trigger) {
			if (empty($trigger['dependencies'])) {
				continue;
			}

			// trigger templates
			$triggerTemplates = API::Template()->get(array(
				'output' => array('status', 'hostid'),
				'triggerids' => $trigger['triggerid'],
				'nopermissions' => true
			));

			// forbid dependencies from hosts to templates
			if (!$triggerTemplates) {
				$triggerDependencyTemplates = API::Template()->get(array(
					'triggerids' => $trigger['dependencies'],
					'output' => array('templateid'),
					'nopermissions' => true,
					'limit' => 1
				));
				if ($triggerDependencyTemplates) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add dependency from a host to a template.'));
				}
			}

			// the trigger can't depend on itself
			if (in_array($trigger['triggerid'], $trigger['dependencies'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create dependency on trigger itself.'));
			}

			// check circular dependency
			$downTriggerIds = array($trigger['triggerid']);
			do {
				// triggerid_down depends on triggerid_up
				$res = DBselect(
					'SELECT td.triggerid_up'.
					' FROM trigger_depends td'.
					' WHERE'.DBcondition('td.triggerid_down', $downTriggerIds)
				);

				// combine db dependencies with thouse to be added
				$upTriggersIds = array();
				while ($row = DBfetch($res)) {
					$upTriggersIds[] = $row['triggerid_up'];
				}
				foreach ($downTriggerIds as $id) {
					if (isset($triggers[$id]) && isset($triggers[$id]['dependencies'])) {
						$upTriggersIds = array_merge($upTriggersIds, $triggers[$id]['dependencies']);
					}
				}

				// if found trigger id in dependant triggerids, then there is dependency loop
				$downTriggerIds = array();
				foreach ($upTriggersIds as $id) {
					if (bccomp($id, $trigger['triggerid']) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create circular dependencies.'));
					}
					$downTriggerIds[] = $id;
				}
			} while (!empty($downTriggerIds));

			// fetch all templates that are used in dependencies
			$triggerDependencyTemplates = API::Template()->get(array(
				'triggerids' => $trigger['dependencies'],
				'output' => array('templateid'),
				'nopermissions' => true,
			));
			$depTemplateIds = zbx_toHash(zbx_objectValues($triggerDependencyTemplates, 'templateid'));

			// run the check only if a templated trigger has dependencies on other templates
			$triggerTemplateIds = zbx_toHash(zbx_objectValues($triggerTemplates, 'hostid'));
			$tdiff = array_diff($depTemplateIds, $triggerTemplateIds);
			if (!empty($triggerTemplateIds) && !empty($depTemplateIds) && !empty($tdiff)) {
				$affectedTemplateIds = zbx_array_merge($triggerTemplateIds, $depTemplateIds);

				// create a list of all hosts, that are children of the affected templates
				$dbLowlvltpl = DBselect(
					'SELECT DISTINCT ht.templateid,ht.hostid,h.host'.
					' FROM hosts_templates ht,hosts h'.
					' WHERE h.hostid=ht.hostid'.
						' AND'.DBcondition('ht.templateid', $affectedTemplateIds)
				);
				$map = array();
				while ($lowlvltpl = DBfetch($dbLowlvltpl)) {
					if (!isset($map[$lowlvltpl['hostid']])) {
						$map[$lowlvltpl['hostid']] = array();
					}
					$map[$lowlvltpl['hostid']][$lowlvltpl['templateid']] = $lowlvltpl['host'];
				}

				// check that if some host is linked to the template, that the trigger belongs to,
				// the host must also be linked to all of the templates, that trigger dependencies point to
				foreach ($map as $templates) {
					foreach ($triggerTemplateIds as $triggerTemplateId) {
						// is the host linked to one of the trigger templates?
						if (isset($templates[$triggerTemplateId])) {
							// then make sure all of the dependency templates are also linked
							foreach ($depTemplateIds as $depTemplateId) {
								if (!isset($templates[$depTemplateId])) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Not all templates are linked to "%s".', reset($templates))
									);
								}
							}
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Check that none of the triggers have dependencies on their children. Checks only one level of inheritance, but
	 * since is is called on each inheritance step, also works for multiple inheritance levels.
	 *
	 * @throws APIException     if at least one trigger is dependant on it's child
	 *
	 * @param array $triggers
	 */
	protected function checkDependencyParents(array $triggers) {
		// fetch all templated dependency trigger parents
		$depTriggerIds = array();
		foreach ($triggers as $trigger) {
			foreach ($trigger['dependencies'] as $depTriggerId) {
				$depTriggerIds[$depTriggerId] = $depTriggerId;
			}
		}
		$parentDepTriggers = DBfetchArray(DBSelect(
			'SELECT templateid,triggerid'.
			' FROM triggers'.
			' WHERE templateid>0'.
				' AND '.DBcondition('triggerid', $depTriggerIds)
		));
		if ($parentDepTriggers) {
			$parentDepTriggers = zbx_toHash($parentDepTriggers, 'triggerid');
			foreach ($triggers as $trigger) {
				foreach ($trigger['dependencies'] as $depTriggerId) {
					// check if the current trigger is the parent of the dependency trigger
					if (isset($parentDepTriggers[$depTriggerId])
							&& $parentDepTriggers[$depTriggerId]['templateid'] == $trigger['triggerid']) {

						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Trigger cannot be dependant on a trigger, that is inherited from it.')
						);
					}
				}
			}
		}
	}

	/**
	 * Checks if the given dependencies contain duplicates.
	 *
	 * @throws APIException if the given dependencies contain duplicates
	 *
	 * @param array $triggers
	 */
	protected function checkDependencyDuplicates(array $triggers) {
		// check duplicates in array
		$uniqueTriggers = array();
		$duplicateTriggerId = null;
		foreach ($triggers as $trigger) {
			foreach ($trigger['dependencies'] as $dep) {
				if (isset($uniqueTriggers[$trigger['triggerid']][$dep])) {
					$duplicateTriggerId = $trigger['triggerid'];
					break 2;
				}
				else {
					$uniqueTriggers[$trigger['triggerid']][$dep] = 1;
				}
			}
		}

		if ($duplicateTriggerId === null) {
			// check if dependency already exists in DB
			foreach ($triggers as $trigger) {
				$dbUpTriggers = DBselect(
					'SELECT td.triggerid_up'.
					' FROM trigger_depends td'.
					' WHERE '.DBcondition('td.triggerid_up', $trigger['dependencies']).
					' AND td.triggerid_down='.zbx_dbstr($trigger['triggerid'])
				, 1);
				if (DBfetch($dbUpTriggers)) {
					$duplicateTriggerId = $trigger['triggerid'];
					break;
				}
			}
		}

		if ($duplicateTriggerId) {
			$dplTrigger = DBfetch(DBselect(
				'SELECT t.description'.
				' FROM triggers t'.
				' WHERE t.triggerid='.zbx_dbstr($duplicateTriggerId)
			));
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate dependencies in trigger "%1$s".', $dplTrigger['description'])
			);
		}
	}

	/**
	 * Check if all templates trigger belongs to are linked to same hosts.
	 *
	 * @throws APIException
	 *
	 * @param $trigger
	 *
	 * @return bool
	 */
	protected function validateItems(array $trigger) {
		$expressionData = new CTriggerExpression($trigger['expression']);

		$templatesData = API::Template()->get(array(
			'output' => API_OUTPUT_REFER,
			'selectHosts' => API_OUTPUT_REFER,
			'selectTemplates' => API_OUTPUT_REFER,
			'filter' => array('host' => $expressionData->getHosts()),
			'nopermissions' => true,
			'preservekeys' => true
		));
		$firstTemplate = array_pop($templatesData);
		if ($firstTemplate) {
			$compareLinks = array_merge(
				zbx_objectValues($firstTemplate['hosts'], 'hostid'),
				zbx_objectValues($firstTemplate['templates'], 'templateid')
			);

			foreach ($templatesData as $data) {
				$linkedTo = array_merge(
					zbx_objectValues($data['hosts'], 'hostid'),
					zbx_objectValues($data['templates'], 'templateid')
				);

				if (array_diff($compareLinks, $linkedTo) || array_diff($linkedTo, $compareLinks)) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Trigger "%s" belongs to templates with different linkages.', $trigger['description'])
					);
				}
			}
		}
		return true;
	}

	/**
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}
		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'triggerids' => $ids,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	/**
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}
		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'triggerids' => $ids,
			'editable' => true,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	/**
	 * Returns true if the given expression contains templates.
	 *
	 * @param CTriggerExpression $exp
	 *
	 * @return bool
	 */
	protected function expressionHasTemplates(CTriggerExpression $expressionData) {
		$hosts = API::Host()->get(array(
			'output' => array('status'),
			'filter' => array('name' => $expressionData->getHosts()),
			'templated_hosts' => true
		));

		foreach ($hosts as $host) {
			if ($host['status'] == HOST_STATUS_TEMPLATE) {
				return true;
			}
		}

		return false;
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['groupids'] === null &&
			$options['templateids'] === null &&
			$options['hostids'] === null &&
			$options['triggerids'] === null &&
			$options['itemids'] === null &&
			$options['applicationids'] === null) {

			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}
}
