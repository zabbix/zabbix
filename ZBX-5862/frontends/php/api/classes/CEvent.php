<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * File containing CEvent class for API.
 *
 * @package API
 */
class CEvent extends CZBXAPI {

	protected $tableName = 'events';
	protected $tableAlias = 'e';

	/**
	 * Get events data.
	 *
	 * @param _array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['eventids']
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
	public function get($options = array()) {
		$result = array();
		$nodeCheck = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('eventid', 'object', 'objectid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array($this->fieldId('eventid')),
			'from'		=> array('events' => 'events e'),
			'where'		=> array(),
			'order'		=> array(),
			'group'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'triggerids'				=> null,
			'eventids'					=> null,
			'editable'					=> null,
			'object'					=> null,
			'source'					=> null,
			'acknowledged'				=> null,
			'nopermissions'				=> null,
			// filter
			'showUnknown'				=> null,
			'value'						=> null,
			'time_from'					=> null,
			'time_till'					=> null,
			'eventid_from'				=> null,
			'eventid_till'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectTriggers'			=> null,
			'select_alerts'				=> null,
			'select_acknowledges'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
		}
		else {
			if (is_null($options['source']) && is_null($options['object'])) {
				$options['object'] = EVENT_OBJECT_TRIGGER;
			}

			if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['source'] == EVENT_SOURCE_TRIGGER) {
				if (!is_null($options['triggerids'])) {
					$triggers = API::Trigger()->get(array(
						'triggerids' => $options['triggerids'],
						'editable' => $options['editable']
					));
					$options['triggerids'] = zbx_objectValues($triggers, 'triggerid');
				}
				else {
					$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

					$sqlParts['from']['functions'] = 'functions f';
					$sqlParts['from']['items'] = 'items i';
					$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
					$sqlParts['from']['rights'] = 'rights r';
					$sqlParts['from']['users_groups'] = 'users_groups ug';
					$sqlParts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
					$sqlParts['where']['fe'] = 'f.triggerid=e.objectid';
					$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
					$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
					$sqlParts['where'][] = 'r.id=hg.groupid ';
					$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
					$sqlParts['where'][] = 'ug.userid='.$userid;
					$sqlParts['where'][] = 'r.permission>='.$permission;
					$sqlParts['where'][] = 'NOT EXISTS ('.
						' SELECT ff.triggerid'.
						' FROM functions ff,items ii'.
						' WHERE ff.triggerid=e.objectid'.
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
			}
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// eventids
		if (!is_null($options['eventids'])) {
			zbx_value2array($options['eventids']);
			$sqlParts['where'][] = dbConditionInt('e.eventid', $options['eventids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('e.objectid', $nodeids);
			}
		}

		// triggerids
		if (!is_null($options['triggerids']) && $options['object'] == EVENT_OBJECT_TRIGGER) {
			zbx_value2array($options['triggerids']);
			$sqlParts['where'][] = dbConditionInt('e.objectid', $options['triggerids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['objectid'] = 'e.objectid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('e.objectid', $nodeids);
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts = $this->addQuerySelect('hg.groupid', $sqlParts);
			}
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['fe'] = 'f.triggerid=e.objectid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=e.objectid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('e.eventid', $nodeids);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts = $this->addQuerySelect('e.*', $sqlParts);
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT e.eventid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// object
		if (!is_null($options['object'])) {
			$sqlParts['where']['o'] = 'e.object='.$options['object'];
		}

		// source
		if (!is_null($options['source'])) {
			$sqlParts['where'][] = 'e.source='.$options['source'];
		}

		// acknowledged
		if (!is_null($options['acknowledged'])) {
			$sqlParts['where'][] = 'e.acknowledged='.($options['acknowledged'] ? 1 : 0);
		}

		// showUnknown
		if (!is_null($options['showUnknown'])) {
			if (is_null($options['filter'])) {
				$options['filter'] = array();
			}
			$options['filter']['value_changed'] = null;
		}

		// time_from
		if (!is_null($options['time_from'])) {
			$sqlParts['where'][] = 'e.clock>='.$options['time_from'];
		}

		// time_till
		if (!is_null($options['time_till'])) {
			$sqlParts['where'][] = 'e.clock<='.$options['time_till'];
		}

		// eventid_from
		if (!is_null($options['eventid_from'])) {
			$sqlParts['where'][] = 'e.eventid>='.$options['eventid_from'];
		}

		// eventid_till
		if (!is_null($options['eventid_till'])) {
			$sqlParts['where'][] = 'e.eventid<='.$options['eventid_till'];
		}

		// value
		if (!is_null($options['value'])) {
			zbx_value2array($options['value']);
			$sqlParts['where'][] = dbConditionInt('e.value', $options['value']);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('events e', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('events e', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, $this->tableAlias());

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		// selectHosts, selectTriggers, selectItems
		if ($options['output'] != API_OUTPUT_EXTEND && (!is_null($options['selectHosts']) || !is_null($options['selectTriggers']) || !is_null($options['selectItems']))) {
			$sqlParts = $this->addQuerySelect($this->fieldId('object'), $sqlParts);
			$sqlParts = $this->addQuerySelect($this->fieldId('objectid'), $sqlParts);
		}

		$eventids = array();
		$triggerids = array();

		// event fields
		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);
		$sqlParts['group'] = array_unique($sqlParts['group']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		$sqlGroup = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		if (!empty($sqlParts['group'])) {
			$sqlGroup .= ' GROUP BY '.implode(',', $sqlParts['group']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.
					$sqlWhere.
					$sqlGroup.
					$sqlOrder;
		$dbRes = DBselect($sql, $sqlLimit);

		while ($event = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $event;
				}
				else {
					$result = $event['rowscount'];
				}
			}
			else {
				$eventids[$event['eventid']] = $event['eventid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$event['eventid']] = array('eventid' => $event['eventid']);
				}
				else {
					if (isset($event['object']) && ($event['object'] == EVENT_OBJECT_TRIGGER)) {
						$triggerids[$event['objectid']] = $event['objectid'];
					}
					if (!isset($result[$event['eventid']])) {
						$result[$event['eventid']]= array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$event['eventid']]['hosts'])) {
						$result[$event['eventid']]['hosts'] = array();
					}
					if (!is_null($options['selectTriggers']) && !isset($result[$event['eventid']]['triggers'])) {
						$result[$event['eventid']]['triggers'] = array();
					}
					if (!is_null($options['selectItems']) && !isset($result[$event['eventid']]['items'])) {
						$result[$event['eventid']]['items'] = array();
					}
					if (!is_null($options['select_alerts']) && !isset($result[$event['eventid']]['alerts'])) {
						$result[$event['eventid']]['alerts'] = array();
					}
					if (!is_null($options['select_acknowledges']) && !isset($result[$event['eventid']]['acknowledges'])) {
						$result[$event['eventid']]['acknowledges'] = array();
					}

					// hostids
					if (isset($event['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$event['eventid']]['hosts'])) {
							$result[$event['eventid']]['hosts'] = array();
						}
						$result[$event['eventid']]['hosts'][] = array('hostid' => $event['hostid']);
						unset($event['hostid']);
					}

					// triggerids
					if (isset($event['triggerid']) && is_null($options['selectTriggers'])) {
						if (!isset($result[$event['eventid']]['triggers'])) {
							$result[$event['eventid']]['triggers'] = array();
						}
						$result[$event['eventid']]['triggers'][] = array('triggerid' => $event['triggerid']);
						unset($event['triggerid']);
					}

					// itemids
					if (isset($event['itemid']) && is_null($options['selectItems'])) {
						if (!isset($result[$event['eventid']]['items'])) {
							$result[$event['eventid']]['items'] = array();
						}
						$result[$event['eventid']]['items'][] = array('itemid' => $event['itemid']);
						unset($event['itemid']);
					}
					$result[$event['eventid']] += $event;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding hosts
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			$hosts = API::Host()->get(array(
				'nodeids' => $nodeids,
				'output' => $options['selectHosts'],
				'triggerids' => $triggerids,
				'nopermissions' => true,
				'preservekeys' => true
			));

			$triggers = array();
			foreach ($hosts as $hostid => $host) {
				$htriggers = $host['triggers'];
				unset($host['triggers']);
				foreach ($htriggers as $tnum => $trigger) {
					$triggerid = $trigger['triggerid'];
					if (!isset($triggers[$triggerid])) {
						$triggers[$triggerid] = array('hosts' => array());
					}
					$triggers[$triggerid]['hosts'][] = $host;
				}
			}

			foreach ($result as $eventid => $event) {
				if (isset($triggers[$event['objectid']])) {
					$result[$eventid]['hosts'] = $triggers[$event['objectid']]['hosts'];
				}
				else {
					$result[$eventid]['hosts'] = array();
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers']) && str_in_array($options['selectTriggers'], $subselectsAllowedOutputs)) {
			$triggers = API::Trigger()->get(array(
				'nodeids' => $nodeids,
				'output' => $options['selectTriggers'],
				'triggerids' => $triggerids,
				'nopermissions' => true,
				'preservekeys' => true
			));
			foreach ($result as $eventid => $event) {
				if (isset($triggers[$event['objectid']])) {
					$result[$eventid]['triggers'][] = $triggers[$event['objectid']];
				}
				else {
					$result[$eventid]['triggers'] = array();
				}
			}
		}

		// adding items
		if (!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselectsAllowedOutputs)) {
			$dbItems = API::Item()->get(array(
				'nodeids' => $nodeids,
				'output' => $options['selectItems'],
				'triggerids' => $triggerids,
				'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true
			));
			$items = array();
			foreach ($dbItems as $itemid => $item) {
				$itriggers = $item['triggers'];
				unset($item['triggers']);
				foreach ($itriggers as $trigger) {
					if (!isset($items[$trigger['triggerid']])) {
						$items[$trigger['triggerid']] = array();
					}
					$items[$trigger['triggerid']][] = $item;
				}
			}

			foreach ($result as $eventid => $event) {
				if (isset($items[$event['objectid']])) {
					$result[$eventid]['items'] = $items[$event['objectid']];
				}
				else {
					$result[$eventid]['items'] = array();
				}
			}
		}

		// adding alerts
		if (!is_null($options['select_alerts']) && str_in_array($options['select_alerts'], $subselectsAllowedOutputs)) {
			$dbAlerts = API::Alert()->get(array(
				'output' => $options['select_alerts'],
				'selectMediatypes' => API_OUTPUT_EXTEND,
				'nodeids' => $nodeids,
				'eventids' => $eventids,
				'nopermissions' => true,
				'preservekeys' => true,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN
			));
			foreach ($dbAlerts as $alert) {
				$result[$alert['eventid']]['alerts'][] = $alert;
			}
		}

		// adding acknowledges
		if (!is_null($options['select_acknowledges'])) {
			if (is_array($options['select_acknowledges']) || str_in_array($options['select_acknowledges'], $subselectsAllowedOutputs)) {
				$res = DBselect(
					'SELECT a.*,u.alias'.
					' FROM acknowledges a'.
						' LEFT JOIN users u ON u.userid=a.userid'.
					' WHERE '.dbConditionInt('a.eventid', $eventids).
					' ORDER BY a.clock DESC'
				);
				while ($ack = DBfetch($res)) {
					$result[$ack['eventid']]['acknowledges'][] = $ack;
				}
			}
			elseif ($options['select_acknowledges'] == API_OUTPUT_COUNT) {
				$res = DBselect(
					'SELECT COUNT(a.acknowledgeid) AS rowscount,a.eventid'.
					' FROM acknowledges a'.
					' WHERE '.dbConditionInt('a.eventid', $eventids).
					' GROUP BY a.eventid'
				);
				while ($ack = DBfetch($res)) {
					$result[$ack['eventid']]['acknowledges'] = $ack['rowscount'];
				}
			}
			elseif ($options['select_acknowledges'] == API_OUTPUT_EXTEND) {
				$res = DBselect(
					'SELECT a.*'.
					' FROM acknowledges a'.
					' WHERE '.dbConditionInt('a.eventid', $eventids).
					' ORDER BY a.clock DESC'
				);
				while ($ack = DBfetch($res)) {
					$result[$ack['eventid']]['acknowledges'] = $ack['rowscount'];
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function acknowledge($data) {
		$eventids = isset($data['eventids']) ? zbx_toArray($data['eventids']) : array();
		$eventids = zbx_toHash($eventids);

		$allowedEvents = $this->get(array(
			'eventids' => $eventids,
			'output' => API_OUTPUT_REFER,
			'preservekeys' => true
		));
		foreach ($eventids as $eventid) {
			if (!isset($allowedEvents[$eventid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$sql = 'UPDATE events SET acknowledged=1 WHERE '.dbConditionInt('eventid', $eventids);
		if (!DBexecute($sql)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		$time = time();
		$dataInsert = array();
		foreach ($eventids as $eventid) {
			$dataInsert[] = array(
				'userid' => self::$userData['userid'],
				'eventid' => $eventid,
				'clock' => $time,
				'message'=> $data['message']
			);
		}

		DB::insert('acknowledges', $dataInsert);

		return array('eventids' => array_values($eventids));
	}
}
