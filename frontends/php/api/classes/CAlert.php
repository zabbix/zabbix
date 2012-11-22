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
 * Class containing methods for operations with Alerts.
 */
class CAlert extends CZBXAPI {

	protected $tableName = 'alerts';
	protected $tableAlias = 'a';

	/**
	 * Get Alerts data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['alertids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['editable']
	 * @param array $options['extendoutput']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('alertid', 'clock', 'eventid', 'status');

		$sqlParts = array(
			'select'	=> array('alerts' => 'a.alertid'),
			'from'		=> array('alerts' => 'alerts a'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'alertids'					=> null,
			'triggerids'				=> null,
			'eventids'					=> null,
			'actionids'					=> null,
			'mediatypeids'				=> null,
			'userids'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'time_from'					=> null,
			'time_till'					=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectMediatypes'			=> null,
			'selectUsers'				=> null,
			'selectHosts'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'editable'					=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['alerts']);

			$dbTable = DB::getSchema('alerts');
			$sqlParts['select']['alertid'] = 'a.alertid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'a.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['from']['events'] = 'events e';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ae'] = 'a.eventid=e.eventid';
			$sqlParts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sqlParts['where']['ef'] = 'e.objectid=f.triggerid';
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
							' AND rr.permission='.PERM_DENY.'))';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['events'] = 'events e';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sqlParts['where']['ef'] = 'e.objectid=f.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hg'] = DBcondition('hg.groupid', $options['groupids']);
		}

		// hostids
		if ($options['hostids'] !== null
			|| $options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {

			zbx_value2array($options['hostids']);

			$sqlParts['select']['hostid'] = 'i.hostid';
			$sqlParts['leftJoin']['events'] = 'events e ON a.eventid=e.eventid';
			$sqlParts['leftJoin']['functions'] = 'functions f ON e.objectid=f.triggerid';
			$sqlParts['leftJoin']['items'] = 'items i ON i.itemid=f.itemid';
			$sqlParts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;

			if ($options['hostids'] !== null) {
				$sqlParts['where']['i'] = DBcondition('i.hostid', $options['hostids']);
			}
		}

		// alertids
		if (!is_null($options['alertids'])) {
			zbx_value2array($options['alertids']);

			$sqlParts['where'][] = DBcondition('a.alertid', $options['alertids']);
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['select']['actionid'] = 'a.actionid';
			$sqlParts['where']['ae'] = 'a.eventid=e.eventid';
			$sqlParts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sqlParts['where'][] = DBcondition('e.objectid', $options['triggerids']);
		}

		// eventids
		if (!is_null($options['eventids'])) {
			zbx_value2array($options['eventids']);

			$sqlParts['where'][] = DBcondition('a.eventid', $options['eventids']);
		}

		// actionids
		if (!is_null($options['actionids'])) {
			zbx_value2array($options['actionids']);

			$sqlParts['select']['actionid'] = 'a.actionid';
			$sqlParts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);
			$field = 'a.userid';

			if (!is_null($options['time_from']) || !is_null($options['time_till'])) {
				$field = '(a.userid+0)';
			}
			$sqlParts['where'][] = DBcondition($field, $options['userids']);
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['select']['mediatypeid'] = 'a.mediatypeid';
			$sqlParts['where'][] = DBcondition('a.mediatypeid', $options['mediatypeids']);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('alerts a', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('alerts a', $options, $sqlParts);
		}

		// time_from
		if (!is_null($options['time_from'])) {
			$sqlParts['where'][] = 'a.clock>'.$options['time_from'];
		}

		// time_till
		if (!is_null($options['time_till'])) {
			$sqlParts['where'][] = 'a.clock<'.$options['time_till'];
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['alerts'] = 'a.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT a.alertid) AS rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'a');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$alertids = array();

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		$relationMap = new CRelationMap();
		while ($alert = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $alert['rowscount'];
			}
			else {
				$alertids[$alert['alertid']] = $alert['alertid'];

				if (!isset($result[$alert['alertid']])) {
					$result[$alert['alertid']] = array();
				}
				if (!is_null($options['selectMediatypes']) && !isset($result[$alert['alertid']]['mediatypes'])) {
					$result[$alert['alertid']]['mediatypes'] = array();
				}

				// hostids
				if (isset($alert['hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$alert['alertid']]['hosts'])) {
						$result[$alert['alertid']]['hosts'] = array();
					}
					$result[$alert['alertid']]['hosts'][] = array('hostid' => $alert['hostid']);
				}

				// userids
				if (isset($alert['userid']) && is_null($options['selectUsers'])) {
					if (!isset($result[$alert['alertid']]['users'])) {
						$result[$alert['alertid']]['users'] = array();
					}
					$result[$alert['alertid']]['users'][] = array('userid' => $alert['userid']);
				}

				// mediatypeids
				if (isset($alert['mediatypeid']) && is_null($options['selectMediatypes'])) {
					if (!isset($result[$alert['alertid']]['mediatypes'])) {
						$result[$alert['alertid']]['mediatypes'] = array();
					}
					$result[$alert['alertid']]['mediatypes'][] = array('mediatypeid' => $alert['mediatypeid']);
				}

				// populate relation map
				if (isset($alert['hostid']) && $alert['hostid']) {
					$relationMap->addRelation($alert['alertid'], 'hosts', $alert['hostid']);
				}
				unset($alert['hostid']);
				if (isset($alert['userid']) && $alert['userid']) {
					$relationMap->addRelation($alert['alertid'], 'users', $alert['userid']);
				}
				if (isset($alert['mediatypeid']) && $alert['mediatypeid']) {
					$relationMap->addRelation($alert['alertid'], 'mediatypes', $alert['mediatypeid']);
				}

				$result[$alert['alertid']] += $alert;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {
			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds('hosts'),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] !== API_OUTPUT_COUNT) {
			$users = API::User()->get(array(
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds('users'),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		if ($options['selectMediatypes'] !== null && $options['selectMediatypes'] !== API_OUTPUT_COUNT) {
			$mediatypes = API::getApi()->select('media_type', array(
				'output' => $options['selectMediatypes'],
				'filter' => array('mediatypeid' => $relationMap->getRelatedIds('mediatypes')),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $mediatypes, 'mediatypes');
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectUsers'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('userid'), $sqlParts);
			}
			if ($options['selectMediatypes'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('mediatypeid'), $sqlParts);
			}
		}

		return $sqlParts;
	}
}
