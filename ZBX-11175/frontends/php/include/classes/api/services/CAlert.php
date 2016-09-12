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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Class containing methods for operations with alerts.
 */
class CAlert extends CApiService {

	protected $tableName = 'alerts';
	protected $tableAlias = 'a';
	protected $sortColumns = ['alertid', 'clock', 'eventid', 'status'];

	/**
	 * Get alerts data.
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
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['alerts' => 'a.alertid'],
			'from'		=> ['alerts' => 'alerts a'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'eventsource'				=> EVENT_SOURCE_TRIGGERS,
			'eventobject'				=> EVENT_OBJECT_TRIGGER,
			'groupids'					=> null,
			'hostids'					=> null,
			'alertids'					=> null,
			'objectids'					=> null,
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
			'output'					=> API_OUTPUT_EXTEND,
			'selectMediatypes'			=> null,
			'selectUsers'				=> null,
			'selectHosts'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'editable'					=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		$this->validateGet($options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,functions f,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId($userid)).
					' WHERE a.eventid=e.eventid'.
						' AND e.objectid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
					' GROUP BY f.triggerid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
			}
			// items and LLD rules
			elseif ($options['eventobject'] == EVENT_OBJECT_ITEM || $options['eventobject'] == EVENT_OBJECT_LLDRULE) {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId($userid)).
					' WHERE a.eventid=e.eventid'.
						' AND e.objectid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
			}
		}

		// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
		if ($options['eventsource'] == EVENT_SOURCE_TRIGGERS
				|| $options['eventsource'] == EVENT_SOURCE_AUTO_REGISTRATION) {
			/*
			 * Performance optimization: events with such sources does not have multiple objects therefore we can ignore
			 * event object in SQL requests.
			 */
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM actions aa'.
				' WHERE a.actionid=aa.actionid'.
					' AND aa.eventsource='.zbx_dbstr($options['eventsource']).
			')';
		}
		else {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e'.
				' WHERE a.eventid=e.eventid'.
					' AND e.source='.zbx_dbstr($options['eventsource']).
					' AND e.object='.zbx_dbstr($options['eventobject']).
			')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,functions f,items i,hosts_groups hg'.
					' WHERE a.eventid=e.eventid'.
						' AND e.objectid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=hg.hostid'.
						' AND '.dbConditionInt('hg.groupid', $options['groupids']).
				')';
			}
			// lld rules and items
			elseif ($options['eventobject'] == EVENT_OBJECT_LLDRULE || $options['eventobject'] == EVENT_OBJECT_ITEM) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,items i,hosts_groups hg'.
					' WHERE a.eventid=e.eventid'.
						' AND e.objectid=i.itemid'.
						' AND i.hostid=hg.hostid'.
						' AND '.dbConditionInt('hg.groupid', $options['groupids']).
				')';
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e,functions f,items i'.
				' WHERE a.eventid=e.eventid'.
					' AND e.objectid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $options['hostids']).
				')';
			}
			// lld rules and items
			elseif ($options['eventobject'] == EVENT_OBJECT_LLDRULE || $options['eventobject'] == EVENT_OBJECT_ITEM) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e,items i'.
				' WHERE a.eventid=e.eventid'.
					' AND e.objectid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $options['hostids']).
				')';
			}
		}

		// alertids
		if (!is_null($options['alertids'])) {
			zbx_value2array($options['alertids']);

			$sqlParts['where'][] = dbConditionInt('a.alertid', $options['alertids']);
		}

		// objectids
		if ($options['objectids'] !== null
				&& in_array($options['eventobject'], [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE])) {
			zbx_value2array($options['objectids']);

			// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e'.
				' WHERE a.eventid=e.eventid'.
					' AND '.dbConditionInt('e.objectid', $options['objectids']).
			')';
		}

		// eventids
		if (!is_null($options['eventids'])) {
			zbx_value2array($options['eventids']);

			$sqlParts['where'][] = dbConditionInt('a.eventid', $options['eventids']);
		}

		// actionids
		if (!is_null($options['actionids'])) {
			zbx_value2array($options['actionids']);

			$sqlParts['where'][] = dbConditionInt('a.actionid', $options['actionids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);
			$field = 'a.userid';

			if (!is_null($options['time_from']) || !is_null($options['time_till'])) {
				$field = '(a.userid+0)';
			}
			$sqlParts['where'][] = dbConditionInt($field, $options['userids']);
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['where'][] = dbConditionInt('a.mediatypeid', $options['mediatypeids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('alerts a', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('alerts a', $options, $sqlParts);
		}

		// time_from
		if (!is_null($options['time_from'])) {
			$sqlParts['where'][] = 'a.clock>'.zbx_dbstr($options['time_from']);
		}

		// time_till
		if (!is_null($options['time_till'])) {
			$sqlParts['where'][] = 'a.clock<'.zbx_dbstr($options['time_till']);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($alert = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $alert['rowscount'];
			}
			else {
				$result[$alert['alertid']] = $alert;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['userid', 'mediatypeid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the get() method.
	 *
	 * @throws APIException     if the input is invalid
	 *
	 * @param array     $options
	 */
	protected function validateGet(array $options) {
		$sourceValidator = new CLimitedSetValidator([
			'values' => array_keys(eventSource())
		]);
		if (!$sourceValidator->validate($options['eventsource'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect eventsource value.'));
		}

		$objectValidator = new CLimitedSetValidator([
			'values' => array_keys(eventObject())
		]);
		if (!$objectValidator->validate($options['eventobject'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect eventobject value.'));
		}

		$sourceObjectValidator = new CEventSourceObjectValidator();
		if (!$sourceObjectValidator->validate(['source' => $options['eventsource'], 'object' => $options['eventobject']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
		}
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

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$alertIds = array_keys($result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {
			// trigger events
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				$query = DBselect(
					'SELECT a.alertid,i.hostid'.
						' FROM alerts a,events e,functions f,items i'.
						' WHERE '.dbConditionInt('a.alertid', $alertIds).
						' AND a.eventid=e.eventid'.
						' AND e.objectid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND e.object='.zbx_dbstr($options['eventobject']).
						' AND e.source='.zbx_dbstr($options['eventsource'])
				);
			}
			// item and LLD rule events
			elseif ($options['eventobject'] == EVENT_OBJECT_ITEM || $options['eventobject'] == EVENT_OBJECT_LLDRULE) {
				$query = DBselect(
					'SELECT a.alertid,i.hostid'.
						' FROM alerts a,events e,items i'.
						' WHERE '.dbConditionInt('a.alertid', $alertIds).
						' AND a.eventid=e.eventid'.
						' AND e.objectid=i.itemid'.
						' AND e.object='.zbx_dbstr($options['eventobject']).
						' AND e.source='.zbx_dbstr($options['eventsource'])
				);
			}

			$relationMap = new CRelationMap();
			while ($relation = DBfetch($query)) {
				$relationMap->addRelation($relation['alertid'], $relation['hostid']);
			}
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'alertid', 'userid');
			$users = API::User()->get([
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		// adding media types
		if ($options['selectMediatypes'] !== null && $options['selectMediatypes'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'alertid', 'mediatypeid');
			$mediatypes = API::getApiService()->select('media_type', [
				'output' => $options['selectMediatypes'],
				'filter' => ['mediatypeid' => $relationMap->getRelatedIds()],
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $mediatypes, 'mediatypes');
		}

		return $result;
	}
}
