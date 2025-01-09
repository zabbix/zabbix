<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
		$mediatype_output_fields = self::$userData['type'] == USER_TYPE_SUPER_ADMIN
			? CMediatype::OUTPUT_FIELDS
			: CMediatype::LIMITED_OUTPUT_FIELDS;

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
			'selectMediatypes' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $mediatype_output_fields), 'default' => null],
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

		$sql_parts = $this->createSelectQueryParts($this->tableName, $this->tableAlias(), $options);
		$res = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

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

	protected function createSelectQueryParts($tableName, $tableAlias, array $options) {
		$sql_parts = [
			'select' => ['a.alertid'],
			'from' => [],
			'where' => [],
			'group' => [],
			'order' => [],
			'limit' => null
		];

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$sql_parts['from']['e'] = 'events e';
			$sql_parts['from'][] = 'alerts a';
			$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
			$sql_parts['where'][] = dbConditionInt('e.source', [$options['eventsource']]);
			$sql_parts['where'][] = dbConditionInt('e.object', [$options['eventobject']]);
		}
		else {
			if ($options['eventsource'] == EVENT_SOURCE_TRIGGERS
					|| $options['eventsource'] == EVENT_SOURCE_AUTOREGISTRATION) {
				$sql_parts['from'][] = 'alerts a';
				$sql_parts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM actions aa'.
					' WHERE a.actionid=aa.actionid'.
						' AND '.dbConditionInt('aa.eventsource', [$options['eventsource']]).
				')';
			}
			else {
				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from'][] = 'alerts a';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where'][] = dbConditionInt('e.source', [$options['eventsource']]);
				$sql_parts['where'][] = dbConditionInt('e.object', [$options['eventobject']]);
			}
		}

		// add filter options
		$sql_parts = $this->applyQueryFilterOptions($tableName, $tableAlias, $options, $sql_parts);

		// add output options
		$sql_parts = $this->applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		// add sort options
		$sql_parts = $this->applyQuerySortOptions($tableName, $tableAlias, $options, $sql_parts);

		return $sql_parts;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				if (self::$userData['ugsetid'] == 0) {
					$sql_parts['where'][] = '1=0';
				}

				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'host_hgset hh';
				$sql_parts['from'][] = 'permission p';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where'][] = 'i.hostid=hh.hostid';
				$sql_parts['where'][] = 'hh.hgsetid=p.hgsetid';
				$sql_parts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

				if ($options['editable']) {
					$sql_parts['where'][] = 'p.permission='.PERM_READ_WRITE;
				}

				$sql_parts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM functions f1'.
					' JOIN items i1 ON f1.itemid=i1.itemid'.
					' JOIN host_hgset hh1 ON i1.hostid=hh1.hostid'.
					' LEFT JOIN permission p1 ON hh1.hgsetid=p1.hgsetid'.
						' AND p1.ugsetid=p.ugsetid'.
					' WHERE e.objectid=f1.triggerid'.
						' AND p1.permission IS NULL'.
				')';
			}
			elseif (in_array($options['eventobject'], [EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE])) {
				if (self::$userData['ugsetid'] == 0) {
					$sql_parts['where'][] = '1=0';
				}

				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'host_hgset hh';
				$sql_parts['from'][] = 'permission p';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where'][] = 'i.hostid=hh.hostid';
				$sql_parts['where'][] = 'hh.hgsetid=p.hgsetid';
				$sql_parts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

				if ($options['editable']) {
					$sql_parts['where'][] = 'p.permission='.PERM_READ_WRITE;
				}
			}
		}

		// Allow user to get alerts sent only by users with same user group.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql_parts['from'][] = 'users_groups ug';
			$sql_parts['where'][] = '(a.userid IS NULL'.
				' OR (a.userid=ug.userid'.
					' AND '.dbConditionId('ug.usrgrpid', getUserGroupsByUserId(self::$userData['userid'])).
				')'.
			')';
		}

		// groupids
		if ($options['groupids'] !== null) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'hosts_groups hg';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where'][] = 'i.hostid=hg.hostid';
				$sql_parts['where'][] = dbConditionId('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['eventobject'] == EVENT_OBJECT_LLDRULE || $options['eventobject'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'hosts_groups hg';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where'][] = 'i.hostid=hg.hostid';
				$sql_parts['where'][] = dbConditionId('hg.groupid', $options['groupids']);
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			// triggers
			if ($options['eventobject'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where'][] = dbConditionId('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['eventobject'] == EVENT_OBJECT_LLDRULE || $options['eventobject'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['e'] = 'events e';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where'][] = dbConditionId('i.hostid', $options['hostids']);
			}
		}

		// alertids
		if ($options['alertids'] !== null) {
			$sql_parts['where'][] = dbConditionId('a.alertid', $options['alertids']);
		}

		// objectids
		if ($options['objectids'] !== null && in_array($options['eventobject'],
				[EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE])) {
			$sql_parts['from']['e'] = 'events e';
			$sql_parts['where']['e-a'] = 'e.eventid=a.eventid';
			$sql_parts['where'][] = dbConditionId('e.objectid', $options['objectids']);
		}

		// eventids
		if ($options['eventids'] !== null) {
			$sql_parts['where'][] = dbConditionId('a.eventid', $options['eventids']);

			if ($options['groupCount']) {
				$sql_parts['group']['a'] = 'a.eventid';
			}
		}

		// actionids
		if ($options['actionids'] !== null) {
			$sql_parts['where'][] = dbConditionId('a.actionid', $options['actionids']);
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

		$mediatypes = API::MediaType()->get([
			'output' => $options['selectMediatypes'],
			'filter' => ['mediatypeid' => $relation_map->getRelatedIds()],
			'preservekeys' => true
		]);

		$mediatypes = $this->unsetExtraFields($mediatypes, ['mediatypeid'], $options['selectMediatypes']);

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
