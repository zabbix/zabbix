<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Alert API implementation.
 */
class CAlert extends CApiService {

	public const ACCESS_RULES = [
		'get' => [
			'min_user_type' => USER_TYPE_ZABBIX_USER
		]
	];

	protected $tableName = 'alerts';
	protected $tableAlias = 'a';
	protected $sortColumns = ['alertid', 'clock', 'eventid', 'status', 'sendto', 'mediatypeid'];

	public const OUTPUT_FIELDS = ['alertid', 'actionid', 'eventid', 'userid', 'clock', 'mediatypeid', 'sendto',
		'subject', 'message', 'status', 'retries', 'error', 'esc_step', 'alerttype', 'p_eventid', 'acknowledgeid'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'alertids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'groupids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'objectids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'actionids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'eventids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'mediatypeids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'eventsource' =>			['type' => API_INT32, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]), 'default' => EVENT_SOURCE_TRIGGERS],
			'eventobject' =>			['type' => API_INT32, 'in' => implode(',', [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE, EVENT_OBJECT_AUTOREGHOST, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE]), 'default' => EVENT_OBJECT_TRIGGER],
			'time_from' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'groupCount' =>				['type' => API_FLAG, 'default' => false],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CHost::OUTPUT_FIELDS), 'default' => null],
			'selectMediatypes' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CMediatype::OUTPUT_FIELDS), 'default' => null],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CUser::OUTPUT_FIELDS), 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['alertid', 'actionid', 'eventid', 'userid', 'mediatypeid', 'status', 'acknowledgeid']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['sendto', 'subject', 'message', 'error']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = self::OUTPUT_FIELDS;
		}

		$res = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_alerts = [];

		while ($row = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$db_alerts[] = $row;
				}
				else {
					$db_alerts = $row['rowscount'];
				}
			}
			else {
				$db_alerts[$row['alertid']] = $row;
			}
		}

		if ($options['countOutput']) {
			return $db_alerts;
		}

		if ($db_alerts) {
			$db_alerts = $this->addRelatedObjects($options, $db_alerts);
			$db_alerts = $this->unsetExtraFields($db_alerts, ['alertid', 'userid', 'mediatypeid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_alerts = array_values($db_alerts);
			}
		}

		return $db_alerts;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sql_parts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,functions f,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
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
				$sql_parts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
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
				|| $options['eventsource'] == EVENT_SOURCE_AUTOREGISTRATION) {
			/*
			 * Performance optimization: events with such sources does not have multiple objects therefore we can ignore
			 * event object in SQL requests.
			 */
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM actions aa'.
				' WHERE a.actionid=aa.actionid'.
					' AND aa.eventsource='.zbx_dbstr($options['eventsource']).
			')';
		}
		else {
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e'.
				' WHERE a.eventid=e.eventid'.
					' AND e.source='.zbx_dbstr($options['eventsource']).
					' AND e.object='.zbx_dbstr($options['eventobject']).
			')';
		}

		// Allow user to get alerts sent only by users with same user group.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			// Filter by userid only if userid IS NOT NULL.
			$sql_parts['where'][] = '(a.userid IS NULL OR EXISTS ('.
				'SELECT NULL'.
				' FROM users_groups ug'.
				' WHERE ug.userid=a.userid'.
					' AND '.dbConditionInt('ug.usrgrpid', getUserGroupsByUserId(self::$userData['userid'])).
			'))';
		}

		// groupids
		if ($options['groupids'] !== null) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sql_parts['where'][] = 'EXISTS ('.
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
				$sql_parts['where'][] = 'EXISTS ('.
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
		if ($options['hostids'] !== null) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
				$sql_parts['where'][] = 'EXISTS ('.
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
				$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e,items i'.
				' WHERE a.eventid=e.eventid'.
					' AND e.objectid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $options['hostids']).
				')';
			}
		}

		// alertids
		if ($options['alertids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('a.alertid', $options['alertids']);
		}

		// objectids
		if ($options['objectids'] !== null && in_array($options['eventobject'],
				[EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE])) {
			// Oracle does not support using distinct with nclob fields, so we must use exists instead of joins
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM events e'.
				' WHERE a.eventid=e.eventid'.
					' AND '.dbConditionInt('e.objectid', $options['objectids']).
			')';
		}

		// eventids
		if ($options['eventids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('a.eventid', $options['eventids']);

			if ($options['groupCount']) {
				$sql_parts['group']['a'] = 'a.eventid';
			}
		}

		// actionids
		if ($options['actionids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('a.actionid', $options['actionids']);
		}

		// userids
		if ($options['userids'] !== null) {
			$field = 'a.userid';

			if ($options['time_from'] !== null || $options['time_till'] !== null) {
				$field = '(a.userid+0)';
			}
			$sql_parts['where'][] = dbConditionId($field, $options['userids']);
		}

		// mediatypeids
		if ($options['mediatypeids'] !== null) {
			$sql_parts['where'][] = dbConditionId('a.mediatypeid', $options['mediatypeids']);
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sql_parts['where'][] = 'a.clock>'.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sql_parts['where'][] = 'a.clock<'.zbx_dbstr($options['time_till']);
		}

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput']) {
			if ($options['selectUsers'] !== null) {
				$sql_parts = $this->addQuerySelect($this->fieldId('userid'), $sql_parts);
			}

			if ($options['selectMediatypes'] !== null) {
				$sql_parts = $this->addQuerySelect($this->fieldId('mediatypeid'), $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedHosts($options, $result);
		$this->addRelatedMediatypes($options, $result);
		$this->addRelatedUsers($options, $result);

		return $result;
	}

	private static function addRelatedHosts(array $options, array &$result): void {
		if ($options['selectHosts'] === null) {
			return;
		}

		$hosts = [];
		$relation_map = new CRelationMap();

		// trigger events
		if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
			$query = DBselect(
				'SELECT a.alertid,i.hostid'.
				' FROM alerts a,events e,functions f,items i'.
				' WHERE '.dbConditionInt('a.alertid', array_keys($result)).
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
				' WHERE '.dbConditionInt('a.alertid', array_keys($result)).
					' AND a.eventid=e.eventid'.
					' AND e.objectid=i.itemid'.
					' AND e.object='.zbx_dbstr($options['eventobject']).
					' AND e.source='.zbx_dbstr($options['eventsource'])
			);
		}

		while ($relation = DBfetch($query)) {
			$relation_map->addRelation($relation['alertid'], $relation['hostid']);
		}

		$related_ids = $relation_map->getRelatedIds();

		if ($related_ids) {
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $related_ids,
				'preservekeys' => true
			]);
		}

		$result = $relation_map->mapMany($result, $hosts, 'hosts');
	}

	private function addRelatedMediatypes(array $options, array &$result): void {
		if ($options['selectMediatypes'] === null) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'alertid', 'mediatypeid');

		$mediatypes = API::getApiService()->select('media_type', [
			'output' => $options['selectMediatypes'],
			'filter' => ['mediatypeid' => $relation_map->getRelatedIds()],
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $mediatypes, 'mediatypes');
	}

	private function addRelatedUsers(array $options, array &$result): void {
		if ($options['selectUsers'] === null) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'alertid', 'userid');

		$users = API::User()->get([
			'output' => $options['selectUsers'],
			'userids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $users, 'users');
	}
}
