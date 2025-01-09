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
 * Event API implementation.
 */
class CEvent extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'acknowledge' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'events';
	protected $tableAlias = 'e';
	protected $sortColumns = ['eventid', 'objectid', 'clock'];

	public const OUTPUT_FIELDS = ['eventid', 'source', 'object', 'objectid', 'clock', 'value', 'acknowledged', 'ns',
		'name', 'severity', 'r_eventid', 'c_eventid', 'correlationid', 'userid', 'cause_eventid', 'opdata',
		'suppressed', 'urls'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$acknowledge_output_fields = ['acknowledgeid', 'userid', 'clock', 'message', 'action', 'old_severity',
			'new_severity', 'suppress_until', 'taskid', 'username', 'name', 'surname'
		];
		$alert_output_fields = array_diff(CAlert::OUTPUT_FIELDS, ['eventid']);

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'eventids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'groupids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'objectids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'source' =>					['type' => API_INT32, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]), 'default' => EVENT_SOURCE_TRIGGERS],
			'object' =>					['type' => API_INT32, 'in' => implode(',', [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE, EVENT_OBJECT_AUTOREGHOST, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE]), 'default' => EVENT_OBJECT_TRIGGER],
			'value' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'uniq' => true, 'default' => null],
			'severities' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)), 'uniq' => true, 'default' => null],
			'trigger_severities' =>		['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)), 'uniq' => true, 'default' => null],
			'eventid_from' =>			['type' => API_ID, 'flags' => API_ALLOW_NULL, 'default' => null],
			'eventid_till' =>			['type' => API_ID, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_from' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'problem_time_from' =>		['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'problem_time_till' =>		['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'acknowledged' =>			['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'action' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => ZBX_PROBLEM_UPDATE_CLOSE.':'.(ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_ACKNOWLEDGE | ZBX_PROBLEM_UPDATE_MESSAGE | ZBX_PROBLEM_UPDATE_SEVERITY | ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE | ZBX_PROBLEM_UPDATE_SUPPRESS | ZBX_PROBLEM_UPDATE_UNSUPPRESS | ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE | ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM), 'default' => null],
			'action_userids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'suppressed' =>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'symptom' =>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'evaltype' =>				['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'tags' =>					['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
				'value' =>					['type' => API_STRING_UTF8]
			]],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['eventid', 'source', 'object', 'objectid', 'value', 'acknowledged', 'name', 'severity', 'cause_eventid']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'groupBy' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => 'objectid', 'uniq' => true, 'default' => null],
			'selectAcknowledges' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $acknowledge_output_fields), 'default' => null],
			'selectAlerts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $alert_output_fields), 'default' => null],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CHost::OUTPUT_FIELDS), 'default' => null],
			'selectRelatedObject' =>	['type' => API_MULTIPLE, 'default' => null, 'rules' => [
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_TRIGGER], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CTrigger::OUTPUT_FIELDS)],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_DHOST], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CDHost::OUTPUT_FIELDS)],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_DSERVICE], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CDService::OUTPUT_FIELDS)],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_AUTOREGHOST], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => ''],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_ITEM], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CItem::OUTPUT_FIELDS)],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_LLDRULE], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CDiscoveryRule::OUTPUT_FIELDS)],
											['if' => ['field' => 'object', 'in' => EVENT_OBJECT_SERVICE], 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CService::OUTPUT_FIELDS)]
			]],
			'selectSuppressionData' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['maintenanceid', 'suppress_until', 'userid']), 'default' => null],
			'selectTags' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', array_merge($this->sortColumns, ['rowscount'])), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>					['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (($options['source'] == EVENT_SOURCE_TRIGGERS && $options['object'] == EVENT_OBJECT_TRIGGER)
				|| ($options['source'] == EVENT_SOURCE_SERVICE && $options['object'] == EVENT_OBJECT_SERVICE)) {
			if ($options['value'] === null) {
				$options['value'] = $options['problem_time_from'] !== null && $options['problem_time_till'] !== null
					? [TRIGGER_VALUE_TRUE]
					: [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE];
			}

			$problems = in_array(TRIGGER_VALUE_TRUE, $options['value'])
				? $this->getEvents(['value' => [TRIGGER_VALUE_TRUE]] + $options)
				: [];
			$recovery = in_array(TRIGGER_VALUE_FALSE, $options['value'])
				? $this->getEvents(['value' => [TRIGGER_VALUE_FALSE]] + $options)
				: [];

			if ($options['countOutput']) {
				if ($options['groupBy']) {
					$problems = array_column($problems, null, 'objectid');
					$recovery = array_column($recovery, null, 'objectid');

					foreach ($problems as $objectid => &$problem) {
						if (array_key_exists($objectid, $recovery)) {
							$problem['rowscount'] = (string) (
								$problem['rowscount'] + $recovery[$objectid]['rowscount']
							);
							unset($recovery[$objectid]);
						}
					}
					unset($problem);

					$db_events = array_values($problems + $recovery);
				}
				else {
					$db_events = (int) $problems + (int) $recovery;
				}
			}
			else {
				$db_events = self::sortResult($problems + $recovery, $options['sortfield'], $options['sortorder']);

				if ($options['limit'] !== null) {
					$db_events = array_slice($db_events, 0, $options['limit'], true);
				}
			}
		}
		else {
			$db_events = $this->getEvents($options);
		}

		if ($options['countOutput'] || $options['groupBy']) {
			return is_array($db_events) ? $db_events : (string) $db_events;
		}

		if ($db_events) {
			$db_events = $this->addRelatedObjects($options, $db_events);
			$db_events = $this->unsetExtraFields($db_events, ['eventid', 'object', 'objectid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_events = array_values($db_events);
			}
		}

		return $db_events;
	}

	/**
	 * Returns the list of events.
	 *
	 * @param array $options
	 *
	 * @return array|string
	 */
	private function getEvents(array $options) {
		$db_events = [];

		$res = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		while ($event = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupBy']) {
					$db_events[] = $event;
				}
				else {
					$db_events = $event['rowscount'];
				}
			}
			elseif ($options['groupBy']) {
				$db_events[] = $event;
			}
			else {
				$db_events[$event['eventid']] = $event;
			}
		}

		return $db_events;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		// source and object
		$sql_parts['where'][] = dbConditionInt('e.source', [$options['source']]);
		$sql_parts['where'][] = dbConditionInt('e.object', [$options['object']]);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				$sql_parts['where'][] = '1=0';
			}
			elseif ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'host_hgset hh';
				$sql_parts['from'][] = 'permission p';
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
					' LEFT JOIN permission p1 ON p1.hgsetid=hh1.hgsetid'.
						' AND p1.ugsetid=p.ugsetid'.
					' WHERE e.objectid=f1.triggerid'.
						' AND p1.permission IS NULL'.
				')';

				if ($options['source'] == EVENT_SOURCE_TRIGGERS) {
					$sql_parts = self::addTagFilterSqlParts(getUserGroupsByUserId(self::$userData['userid']),
						$sql_parts, $options['value'][0]
					);
				}
			}
			elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from'][] = 'host_hgset hh';
				$sql_parts['from'][] = 'permission p';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where'][] = 'i.hostid=hh.hostid';
				$sql_parts['where'][] = 'hh.hgsetid=p.hgsetid';
				$sql_parts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

				if ($options['editable']) {
					$sql_parts['where'][] = 'p.permission='.PERM_READ_WRITE;
				}
			}
		}

		if (($options['source'] == EVENT_SOURCE_TRIGGERS && $options['object'] == EVENT_OBJECT_TRIGGER)
				|| ($options['source'] == EVENT_SOURCE_SERVICE && $options['object'] == EVENT_OBJECT_SERVICE)) {
			if ($options['problem_time_from'] !== null && $options['problem_time_till'] !== null) {
				if ($options['value'][0] == TRIGGER_VALUE_TRUE) {
					$sql_parts['where'][] =
						'e.clock<='.zbx_dbstr($options['problem_time_till']).' AND ('.
							'NOT EXISTS ('.
								'SELECT NULL'.
								' FROM event_recovery er'.
								' WHERE e.eventid=er.eventid'.
							')'.
							' OR EXISTS ('.
								'SELECT NULL'.
								' FROM event_recovery er,events e2'.
								' WHERE e.eventid=er.eventid'.
									' AND er.r_eventid=e2.eventid'.
									' AND e2.clock>='.zbx_dbstr($options['problem_time_from']).
							')'.
						')';
				}
				else {
					$sql_parts['where'][] =
						'e.clock>='.zbx_dbstr($options['problem_time_from']).
						' AND EXISTS ('.
							'SELECT NULL'.
							' FROM event_recovery er,events e2'.
							' WHERE e.eventid=er.r_eventid'.
								' AND er.eventid=e2.eventid'.
								' AND e2.clock<='.zbx_dbstr($options['problem_time_till']).
						')';
				}
			}
		}

		// eventids
		if ($options['eventids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('e.eventid', $options['eventids']);
		}

		// objectids
		if ($options['objectids'] !== null && in_array($options['object'], [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM,
				EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE])) {
			$sql_parts['where'][] = dbConditionInt('e.objectid', $options['objectids']);
		}

		// groupids
		if ($options['groupids'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from']['hg'] = 'hosts_groups hg';
				$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from']['hg'] = 'hosts_groups hg';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['e-i'] = 'e.objectid=i.itemid';
				$sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
		}

		// severities
		if ($options['severities'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['object'] == EVENT_OBJECT_SERVICE) {
				sort($options['severities']);

				if ($options['severities'] != range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)) {
					$sql_parts['where'][] = dbConditionInt('e.severity', $options['severities']);
				}
			}
			// ignore this filter for items and lld rules
		}

		// trigger_severities
		if ($options['trigger_severities'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				sort($options['trigger_severities']);

				if ($options['trigger_severities']
						!= range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)) {
					$sql_parts['from']['t'] = 'triggers t';
					$sql_parts['where']['e-t'] = 'e.objectid=t.triggerid';
					$sql_parts['where'][] = dbConditionInt('t.priority', $options['trigger_severities']);
				}
			}
			// ignore this filter for items and lld rules
		}

		// acknowledged
		if ($options['acknowledged'] !== null) {
			$acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
			$sql_parts['where'][] = 'e.acknowledged='.$acknowledged;
		}

		// Acknowledge action and users that have performed the action.
		$acknowledge_actions = [];

		if ($options['action'] !== null) {
			$acknowledge_actions[] = 'ack.action & '.$options['action'].'='.$options['action'];
		}

		if ($options['action_userids'] !== null) {
			$acknowledge_actions[] = dbConditionId('ack.userid', $options['action_userids']);
		}

		if ($acknowledge_actions) {
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM acknowledges ack'.
				' WHERE e.eventid=ack.eventid'.
					' AND '.implode(' AND ', $acknowledge_actions).
			')';
		}

		// suppressed
		if ($options['suppressed'] !== null) {
			$sql_parts['where'][] = (!$options['suppressed'] ? 'NOT ' : '').
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_suppress es'.
					' WHERE es.eventid=e.eventid'.
				')';
		}

		// symptom
		if ($options['symptom'] !== null) {
			$sql_parts['where'][] = (!$options['symptom'] ? 'NOT ' : '').
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_symptom es'.
					' WHERE es.eventid=e.eventid'.
				')';
		}

		// tags
		if ($options['tags'] !== null) {
			$sql_parts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'e',
				'event_tag', 'eventid'
			);
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sql_parts['where'][] = 'e.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sql_parts['where'][] = 'e.clock<='.zbx_dbstr($options['time_till']);
		}

		// eventid_from
		if ($options['eventid_from'] !== null) {
			$sql_parts['where'][] = 'e.eventid>='.zbx_dbstr($options['eventid_from']);
		}

		// eventid_till
		if ($options['eventid_till'] !== null) {
			$sql_parts['where'][] = 'e.eventid<='.zbx_dbstr($options['eventid_till']);
		}

		// value
		if ($options['value'] !== null) {
			$sql_parts['where'][] = dbConditionInt('e.value', $options['value']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->applyFilters($options, $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * Returns the list of unique tag filters.
	 *
	 * @param array $usrgrpids
	 *
	 * @return array
	 */
	public static function getTagFilters(array $usrgrpids): array {
		$tag_filters = uniqTagFilters(DB::select('tag_filter', [
			'output' => ['groupid', 'tag', 'value'],
			'filter' => ['usrgrpid' => $usrgrpids]
		]));

		$result = [];

		foreach ($tag_filters as $tag_filter) {
			$result[$tag_filter['groupid']][] = [
				'tag' => $tag_filter['tag'],
				'value' => $tag_filter['value']
			];
		}

		return $result;
	}

	/**
	 * Add SQL parts related to tag-based permissions.
	 *
	 * @param array $usrgrpids
	 * @param array $sql_parts
	 * @param int   $value
	 *
	 * @return array
	 */
	private static function addTagFilterSqlParts(array $usrgrpids, array $sql_parts, int $value): array {
		$tag_filters = self::getTagFilters($usrgrpids);

		if (!$tag_filters) {
			return $sql_parts;
		}

		$sql_parts['from']['f'] = 'functions f';
		$sql_parts['from']['i'] = 'items i';
		$sql_parts['from']['hg'] = 'hosts_groups hg';
		$sql_parts['where']['e-f'] = 'e.objectid=f.triggerid';
		$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
		$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';

		$tag_conditions = [];
		$full_access_groupids = [];

		foreach ($tag_filters as $groupid => $filters) {
			$tags = [];
			$tag_values = [];

			foreach ($filters as $filter) {
				if ($filter['tag'] === '') {
					$full_access_groupids[] = $groupid;

					continue 2;
				}

				if ($filter['value'] === '') {
					$tags[] = $filter['tag'];
				}
				else {
					$tag_values[$filter['tag']][] = $filter['value'];
				}
			}

			$conditions = [];

			if ($tags) {
				$conditions[] = dbConditionString('et.tag', $tags);
			}
			$parenthesis = $tags || count($tag_values) > 1;

			foreach ($tag_values as $tag => $values) {
				$condition = 'et.tag='.zbx_dbstr($tag).' AND '.dbConditionString('et.value', $values);
				$conditions[] = $parenthesis ? '('.$condition.')' : $condition;
			}

			$conditions = count($conditions) > 1 ? '('.implode(' OR ', $conditions).')' : $conditions[0];

			$tag_conditions[] = 'hg.groupid='.zbx_dbstr($groupid).' AND '.$conditions;
		}

		if ($tag_conditions) {
			if ($value == TRIGGER_VALUE_TRUE) {
				$sql_parts['from']['et'] = 'event_tag et';
				$sql_parts['where']['e-et'] = 'e.eventid=et.eventid';
			}
			else {
				$sql_parts['from']['er'] = 'event_recovery er';
				$sql_parts['from']['et'] = 'event_tag et';
				$sql_parts['where']['e-er'] = 'e.eventid=er.r_eventid';
				$sql_parts['where']['er-et'] = 'er.eventid=et.eventid';
			}

			if ($full_access_groupids || count($tag_conditions) > 1) {
				foreach ($tag_conditions as &$tag_condition) {
					$tag_condition = '('.$tag_condition.')';
				}
				unset($tag_condition);
			}
		}

		if ($full_access_groupids) {
			$tag_conditions[] = dbConditionInt('hg.groupid', $full_access_groupids);
		}

		$sql_parts['where'][] = count($tag_conditions) > 1
			? '('.implode(' OR ', $tag_conditions).')'
			: $tag_conditions[0];

		return $sql_parts;
	}

	/**
	 * Apply filter conditions to SQL built query.
	 *
	 * @param array $options
	 *        array $options['filter']['cause_eventids']  Cause event IDs to filter by.
	 * @param array $sql_parts
	 */
	private function applyFilters(array $options, array &$sql_parts): void {
		if ($options['countOutput'] || $options['groupBy']) {
			return;
		}

		// Filter symptom events for given cause.
		if (array_key_exists('cause_eventid', $options['filter']) && $options['filter']['cause_eventid'] !== null) {
			$sql_parts['from']['event_symptom'] = 'event_symptom es';
			$sql_parts['where']['ese'] = 'es.eventid=e.eventid';
			$sql_parts['where']['es'] = dbConditionId('es.cause_eventid', $options['filter']['cause_eventid']);
		}
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['countOutput'] || $options['groupBy']) {
			return $sql_parts;
		}

		// Select fields from event_recovery table using LEFT JOIN.
		if ($this->outputIsRequested('r_eventid', $options['output'])) {
			$sql_parts['select']['r_eventid'] = 'er1.r_eventid';
			$sql_parts['left_join'][] = ['alias' => 'er1', 'table' => 'event_recovery', 'using' => 'eventid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		// Select fields from event_recovery table using LEFT JOIN.
		$left_join_recovery = false;
		foreach (['c_eventid', 'correlationid', 'userid'] as $field) {
			if ($this->outputIsRequested($field, $options['output'])) {
				$sql_parts['select'][$field] = 'er2.'.$field;
				$left_join_recovery = true;
			}
		}

		if ($left_join_recovery) {
			$sql_parts['left_join'][] = ['alias' => 'er2', 'table' => 'event_recovery', 'using' => 'r_eventid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if ($options['selectRelatedObject'] !== null || $options['selectHosts'] !== null) {
			$sql_parts = $this->addQuerySelect('e.object', $sql_parts);
			$sql_parts = $this->addQuerySelect('e.objectid', $sql_parts);
		}

		$left_join_symptom = false;
		if ($this->outputIsRequested('cause_eventid', $options['output'])) {
			$sql_parts['select']['cause_eventid'] = 'es1.cause_eventid';
			$left_join_symptom = true;
		}

		if ($left_join_symptom) {
			$sql_parts['left_join'][] = ['alias' => 'es1', 'table' => 'event_symptom', 'using' => 'eventid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		return $sql_parts;
	}

	/**
	 * Returns sorted array of events.
	 *
	 * @param array        $result     Events.
	 * @param string|array $sortfield
	 * @param string|array $sortorder
	 *
	 * @return array
	 */
	private static function sortResult(array $result, $sortfield, $sortorder): array {
		if ($sortfield === '' || $sortfield === []) {
			return $result;
		}

		$fields = [];

		foreach ((array) $sortfield as $i => $field) {
			if (is_string($sortorder) && $sortorder === ZBX_SORT_DOWN) {
				$order = ZBX_SORT_DOWN;
			}
			elseif (is_array($sortorder) && array_key_exists($i, $sortorder) && $sortorder[$i] === ZBX_SORT_DOWN) {
				$order = ZBX_SORT_DOWN;
			}
			else {
				$order = ZBX_SORT_UP;
			}

			$fields[] = ['field' => $field, 'order' => $order];
		}

		CArrayHelper::sort($result, $fields);

		return $result;
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedAcknowledges($options, $result);
		$this->addRelatedAlerts($options, $result);
		self::addRelatedHosts($options, $result);
		self::addRelatedObject($options, $result);
		$this->addRelatedOpdata($options, $result);
		self::addRelatedSuppressionData($options, $result);
		$this->addRelatedSuppressed($options, $result);
		self::addRelatedTags($options, $result);
		$this->addRelatedUrls($options, $result);

		return $result;
	}

	private function addRelatedAcknowledges(array $options, array &$result): void {
		if ($options['selectAcknowledges'] === null) {
			return;
		}

		if ($options['selectAcknowledges'] != API_OUTPUT_COUNT) {
			$output = $options['selectAcknowledges'] === API_OUTPUT_EXTEND
				? ['acknowledgeid', 'userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
					'suppress_until', 'taskid'
				]
				: array_diff($options['selectAcknowledges'], ['username', 'name', 'surname']);

			$db_acknowledges = DB::select('acknowledges', [
				'output' => $this->outputExtend($output, ['acknowledgeid', 'eventid', 'userid']),
				'filter' => ['eventid' => array_keys($result)],
				'sortfield' => ['clock'],
				'sortorder' => [ZBX_SORT_DOWN],
				'preservekeys' => true
			]);

			$user_fields = [];
			foreach (['username', 'name', 'surname'] as $field) {
				if ($this->outputIsRequested($field, $options['selectAcknowledges'])) {
					$user_fields[] = $field;
				}
			}

			if ($user_fields) {
				$db_users = API::User()->get([
					'output' => $user_fields,
					'userids' => array_unique(array_column($db_acknowledges, 'userid')),
					'preservekeys' => true
				]);

				foreach ($db_acknowledges as &$db_acknowledge) {
					if (array_key_exists($db_acknowledge['userid'], $db_users)) {
						$db_acknowledge += $db_users[$db_acknowledge['userid']];
					}
				}
				unset($db_acknowledge);
			}

			$relation_map = $this->createRelationMap($db_acknowledges, 'eventid', 'acknowledgeid');
			$db_acknowledges = $this->unsetExtraFields($db_acknowledges, ['eventid', 'acknowledgeid', 'userid'],
				$output
			);
			$result = $relation_map->mapMany($result, $db_acknowledges, 'acknowledges');
		}
		else {
			$db_acknowledges = DBFetchArrayAssoc(DBselect(
				'SELECT a.eventid,COUNT(a.acknowledgeid) AS rowscount'.
				' FROM acknowledges a'.
				' WHERE '.dbConditionInt('a.eventid', array_keys($result)).
				' GROUP BY a.eventid'
			), 'eventid');

			foreach ($result as $eventid => $event) {
				$result[$eventid]['acknowledges'] = array_key_exists($eventid, $db_acknowledges)
					? $db_acknowledges[$eventid]['rowscount']
					: '0';
			}
		}
	}

	private function addRelatedAlerts(array $options, array &$result): void {
		if ($options['selectAlerts'] === null) {
			return;
		}

		$alerts = [];
		$relation_map = $this->createRelationMap($result, 'eventid', 'alertid', 'alerts');
		$related_ids = $relation_map->getRelatedIds();

		if ($related_ids) {
			$alerts = API::Alert()->get([
				'output' => $options['selectAlerts'] === API_OUTPUT_EXTEND
					? array_diff(CAlert::OUTPUT_FIELDS, ['eventid'])
					: $options['selectAlerts'],
				'alertids' => $related_ids,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'nopermissions' => true,
				'preservekeys' => true
			]);
		}

		$result = $relation_map->mapMany($result, $alerts, 'alerts');
	}

	private static function addRelatedHosts(array $options, array &$result): void {
		if ($options['selectHosts'] === null) {
			return;
		}

		$hosts = [];
		$relation_map = new CRelationMap();

		// trigger events
		if ($options['object'] == EVENT_OBJECT_TRIGGER) {
			$query = DBselect(
				'SELECT e.eventid,i.hostid'.
				' FROM events e,functions f,items i'.
				' WHERE '.dbConditionInt('e.eventid', array_keys($result)).
					' AND e.objectid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND e.object='.zbx_dbstr($options['object']).
					' AND e.source='.zbx_dbstr($options['source'])
			);
		}
		// item and LLD rule events
		elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
			$query = DBselect(
				'SELECT e.eventid,i.hostid'.
				' FROM events e,items i'.
				' WHERE '.dbConditionInt('e.eventid', array_keys($result)).
					' AND e.objectid=i.itemid'.
					' AND e.object='.zbx_dbstr($options['object']).
					' AND e.source='.zbx_dbstr($options['source'])
			);
		}

		while ($relation = DBfetch($query)) {
			$relation_map->addRelation($relation['eventid'], $relation['hostid']);
		}

		$related_ids = $relation_map->getRelatedIds();

		if ($related_ids) {
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $related_ids,
				'nopermissions' => true,
				'preservekeys' => true
			]);
		}

		$result = $relation_map->mapMany($result, $hosts, 'hosts');
	}

	private static function addRelatedObject(array $options, array &$result): void {
		if ($options['selectRelatedObject'] === null || $options['object'] == EVENT_OBJECT_AUTOREGHOST) {
			return;
		}

		$relation_map = new CRelationMap();

		foreach ($result as $event) {
			$relation_map->addRelation($event['eventid'], $event['objectid']);
		}

		switch ($options['object']) {
			case EVENT_OBJECT_TRIGGER:
				$api = API::Trigger();
				break;
			case EVENT_OBJECT_DHOST:
				$api = API::DHost();
				break;
			case EVENT_OBJECT_DSERVICE:
				$api = API::DService();
				break;
			case EVENT_OBJECT_ITEM:
				$api = API::Item();
				break;
			case EVENT_OBJECT_LLDRULE:
				$api = API::DiscoveryRule();
				break;
			case EVENT_OBJECT_SERVICE:
				$api = API::Service();
				break;
		}

		$objects = $api->get([
			'output' => $options['selectRelatedObject'],
			$api->pkOption() => $relation_map->getRelatedIds(),
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$result = $relation_map->mapOne($result, $objects, 'relatedObject');
	}

	private function addRelatedOpdata(array $options, array &$result): void {
		if (!$this->outputIsRequested('opdata', $options['output'])) {
			return;
		}

		$events = DBFetchArrayAssoc(DBselect(
			'SELECT e.eventid,e.clock,e.ns,t.triggerid,t.expression,t.opdata'.
			' FROM events e'.
				' JOIN triggers t'.
					' ON t.triggerid=e.objectid'.
			' WHERE '.dbConditionInt('e.eventid', array_keys($result))
		), 'eventid');

		foreach ($result as $eventid => $event) {
			$result[$eventid]['opdata'] = array_key_exists($eventid, $events) && $events[$eventid]['opdata'] !== ''
				? CMacrosResolverHelper::resolveTriggerOpdata($events[$eventid], ['events' => true])
				: '';
		}
	}

	private static function addRelatedSuppressionData(array $options, array &$result): void {
		if ($options['selectSuppressionData'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['suppression_data'] = [];
		}
		unset($row);

		$output = $options['selectSuppressionData'] === API_OUTPUT_EXTEND
			? ['event_suppressid', 'eventid', 'maintenanceid', 'suppress_until', 'userid']
			: array_unique(array_merge(['event_suppressid', 'eventid'], $options['selectSuppressionData']));

		$sql_options = [
			'output' => $output,
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_event_suppress = DBselect(DB::makeSql('event_suppress', $sql_options));

		while ($db_suppression_data = DBfetch($db_event_suppress)) {
			$eventid = $db_suppression_data['eventid'];

			unset($db_suppression_data['event_suppressid'], $db_suppression_data['eventid']);

			$result[$eventid]['suppression_data'][] = $db_suppression_data;
		}
	}

	private function addRelatedSuppressed(array $options, array &$result): void {
		if (!$this->outputIsRequested('suppressed', $options['output'])) {
			return;
		}

		if ($options['selectSuppressionData'] !== null) {
			foreach ($result as &$row) {
				$row['suppressed'] = $row['suppression_data']
					? (string) ZBX_PROBLEM_SUPPRESSED_TRUE
					: (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
			}
			unset($row);
		}
		else {
			foreach ($result as &$row) {
				$row['suppressed'] = (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
			}
			unset($row);

			$sql_options = [
				'output' => ['eventid'],
				'filter' => ['eventid' => array_keys($result)]
			];
			$db_event_suppress = DBselect(DB::makeSql('event_suppress', $sql_options));

			while ($db_suppression_data = DBfetch($db_event_suppress)) {
				$result[$db_suppression_data['eventid']]['suppressed'] = (string) ZBX_PROBLEM_SUPPRESSED_TRUE;
			}
		}
	}

	private static function addRelatedTags(array $options, array &$result): void {
		if ($options['selectTags'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['tags'] = [];
		}
		unset($row);

		$output = $options['selectTags'] === API_OUTPUT_EXTEND
			? ['eventtagid', 'eventid', 'tag', 'value']
			: array_unique(array_merge(['eventtagid', 'eventid'], $options['selectTags']));

		$sql_options = [
			'output' => $output,
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_tags = DBselect(DB::makeSql('event_tag', $sql_options));

		while ($db_tag = DBfetch($db_tags)) {
			$eventid = $db_tag['eventid'];

			unset($db_tag['eventtagid'], $db_tag['eventid']);

			$result[$eventid]['tags'][] = $db_tag;
		}
	}

	private function addRelatedUrls(array $options, array &$result): void {
		if (!$this->outputIsRequested('urls', $options['output'])) {
			return;
		}

		$sql_options = [
			'output' => ['eventid', 'tag', 'value'],
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_tags = DBselect(DB::makeSql('event_tag', $sql_options));

		$events = [];

		foreach ($result as $event) {
			$events[$event['eventid']]['tags'] = [];
		}

		while ($db_tag = DBfetch($db_tags)) {
			$events[$db_tag['eventid']]['tags'][] = [
				'tag' => $db_tag['tag'],
				'value' => $db_tag['value']
			];
		}

		$urls = DB::select('media_type', [
			'output' => ['event_menu_url', 'event_menu_name'],
			'filter' => [
				'type' => MEDIA_TYPE_WEBHOOK,
				'status' => MEDIA_TYPE_STATUS_ACTIVE,
				'show_event_menu' => ZBX_EVENT_MENU_SHOW
			]
		]);

		$events = CMacrosResolverHelper::resolveMediaTypeUrls($events, $urls);

		foreach ($events as $eventid => $event) {
			$result[$eventid]['urls'] = $event['urls'];
		}
	}

	/**
	 * Acknowledges the given events and closes them if necessary.
	 *
	 * @param array        $data                    An array of operation data.
	 *        array|string $data['eventids']        An event ID or an array of event IDs.
	 *        string       $data['cause_eventid']   Cause event ID. Used if $data['action'] yields 0x100.
	 *        string       $data['message']         Message if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 *        string       $data['severity']        New severity level if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 *        string       $data['suppress_until']  Suppress until time if ZBX_PROBLEM_UPDATE_SUPPRESS flag is passed.
	 *        int          $data['action']          Flags of performed operations combined:
	 *                                                - 0x01  - ZBX_PROBLEM_UPDATE_CLOSE
	 *                                                - 0x02  - ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
	 *                                                - 0x04  - ZBX_PROBLEM_UPDATE_MESSAGE
	 *                                                - 0x08  - ZBX_PROBLEM_UPDATE_SEVERITY
	 *                                                - 0x10  - ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE
	 *                                                - 0x20  - ZBX_PROBLEM_UPDATE_SUPPRESS
	 *                                                - 0x40  - ZBX_PROBLEM_UPDATE_UNSUPPRESS
	 *                                                - 0x80  - ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
	 *                                                - 0x100 - ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function acknowledge(array $data): array {
		$time = time();
		$this->validateAcknowledge($data, $time);

		$data['eventids'] = zbx_toArray($data['eventids']);
		$data['eventids'] = array_keys(array_flip($data['eventids']));

		$has_close_action = ($data['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE;
		$has_suppress_action = ($data['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS;
		$has_unsuppress_action = ($data['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS;
		$has_change_rank_to_symptom_action =
			($data['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM;

		// Validation of event permissions has already been done in validateAcknowledge().
		$events = $this->get([
			'output' => ['objectid', 'acknowledged', 'severity', 'r_eventid', 'cause_eventid'],
			// "acknowledges" used in CEvent::isEventClosed().
			'selectAcknowledges' => $has_close_action || $has_suppress_action || $has_unsuppress_action
				? ['action']
				: null,
			// "suppression_data" used in CEvent::isEventSuppressed().
			'selectSuppressionData' => $has_unsuppress_action ? ['maintenanceid'] : null,
			'eventids' => $data['eventids'],
			'value' => TRIGGER_VALUE_TRUE,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		// Get current data of the new cause event and get symptom events of the given cause events.
		if ($has_change_rank_to_symptom_action) {
			$update_symptom_eventids = validateEventRankChangeToSymptom($data['eventids'], $data['cause_eventid']);
		}

		$ack_eventids = [];
		$unack_eventids = [];
		$sev_change_eventids = [];
		$acknowledges = [];
		$suppress_eventids = [];
		$unsuppress_eventids = [];
		$tasks_update_event_rank_cause = [];
		$tasks_update_event_rank_symptom = [];
		$n = 0;

		foreach ($events as $eventid => $event) {
			$action = ZBX_PROBLEM_UPDATE_NONE;
			$old_severity = 0;
			$new_severity = 0;
			$message = '';
			$suppress_until = 0;

			// Perform ZBX_PROBLEM_UPDATE_CLOSE action flag.
			if ($has_close_action && !$this->isEventClosed($event)) {
				$action |= ZBX_PROBLEM_UPDATE_CLOSE;
			}

			// Perform ZBX_PROBLEM_UPDATE_ACKNOWLEDGE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
					&& $event['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
				$action |= ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;
				$ack_eventids[] = $eventid;
			}

			// Perform ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE
					&& $event['acknowledged'] == EVENT_ACKNOWLEDGED) {
				$action |= ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE;
				$unack_eventids[] = $eventid;
			}

			// Perform ZBX_PROBLEM_UPDATE_MESSAGE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
				$action |= ZBX_PROBLEM_UPDATE_MESSAGE;
				$message = $data['message'];
			}

			// Perform ZBX_PROBLEM_UPDATE_SEVERITY action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY
					&& $data['severity'] != $event['severity']) {
				$action |= ZBX_PROBLEM_UPDATE_SEVERITY;
				$old_severity = $event['severity'];
				$new_severity = $data['severity'];
				$sev_change_eventids[] = $eventid;
			}

			// Perform ZBX_PROBLEM_UPDATE_SUPPRESS action flag.
			if ($has_suppress_action && !$this->isEventClosed($event)) {
				$action |= ZBX_PROBLEM_UPDATE_SUPPRESS;
				$suppress_until = $data['suppress_until'];
				$suppress_eventids[] = $eventid;
			}

			// Perform ZBX_PROBLEM_UPDATE_UNSUPPRESS action flag.
			if ($has_unsuppress_action && $this->isEventSuppressed($event) && !$this->isEventClosed($event)) {
				$action |= ZBX_PROBLEM_UPDATE_UNSUPPRESS;
				$unsuppress_eventids[] = $eventid;
			}

			// Perform ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
					&& $event['cause_eventid'] != 0) {
				$action |= ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE;
				$tasks_update_event_rank_cause[$n] = ['eventid' => $eventid];
			}

			// Perform ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM action flag.
			if ($has_change_rank_to_symptom_action && $update_symptom_eventids
					&& in_array($eventid, $update_symptom_eventids)) {
				$action |= ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM;
				$tasks_update_event_rank_symptom[$n] = [
					'eventid' => $eventid,
					'cause_eventid' => $data['cause_eventid']
				];
			}

			// For some of selected events action might not be performed, as event is already with given change.
			if ($action != ZBX_PROBLEM_UPDATE_NONE) {
				$acknowledges[$n] = [
					'userid' => self::$userData['userid'],
					'eventid' => $eventid,
					'clock' => $time,
					'message' => $message,
					'action' => $action,
					'old_severity' => $old_severity,
					'new_severity' => $new_severity,
					'suppress_until' => $suppress_until
				];
				$n++;
			}
		}

		// Make changes in problem and events tables.
		if ($acknowledges) {
			// Unacknowledge problems and events.
			if ($unack_eventids) {
				DB::update('problem', [
					'values' => ['acknowledged' => EVENT_NOT_ACKNOWLEDGED],
					'where' => ['eventid' => $unack_eventids]
				]);

				DB::update('events', [
					'values' => ['acknowledged' => EVENT_NOT_ACKNOWLEDGED],
					'where' => ['eventid' => $unack_eventids]
				]);
			}

			// Acknowledge problems and events.
			if ($ack_eventids) {
				DB::update('problem', [
					'values' => ['acknowledged' => EVENT_ACKNOWLEDGED],
					'where' => ['eventid' => $ack_eventids]
				]);

				DB::update('events', [
					'values' => ['acknowledged' => EVENT_ACKNOWLEDGED],
					'where' => ['eventid' => $ack_eventids]
				]);
			}

			// Change severity.
			if ($sev_change_eventids) {
				DB::update('problem', [
					'values' => ['severity' => $data['severity']],
					'where' => ['eventid' => $sev_change_eventids]
				]);

				DB::update('events', [
					'values' => ['severity' => $data['severity']],
					'where' => ['eventid' => $sev_change_eventids]
				]);
			}

			// Store operation history data.
			$acknowledgeids = DB::insertBatch('acknowledges', $acknowledges);

			// Create tasks to close problems manually.
			$tasks = [];
			$task_close = [];

			foreach ($acknowledgeids as $k => $id) {
				$acknowledgement = $acknowledges[$k];

				if (($acknowledgement['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
					$tasks[$k] = [
						'type' => ZBX_TM_TASK_CLOSE_PROBLEM,
						'status' => ZBX_TM_STATUS_NEW,
						'clock' => $time
					];

					$task_close[$k] = [
						'acknowledgeid' => $id
					];
				}
			}

			if ($tasks) {
				$taskids = DB::insertBatch('task', $tasks);
				$task_close = array_replace_recursive($task_close, zbx_toObject($taskids, 'taskid', true));
				DB::insertBatch('task_close_problem', $task_close, false);
			}

			// Create tasks for suppress/unsuppress actions.
			$tasks = [];
			$task_suppress = [];

			foreach ($acknowledgeids as $k => $id) {
				$acknowledgement = $acknowledges[$k];

				// Create tasks to suppress problems manually.
				if (($acknowledgement['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS) {
					$tasks[$k] = [
						'type' => ZBX_TM_TASK_DATA,
						'status' => ZBX_TM_STATUS_NEW,
						'clock' => $time
					];

					$task_suppress[$k] = [
						'taskid' => $id,
						'type' => ZBX_TM_DATA_TYPE_TEMP_SUPPRESSION,
						'data' => json_encode([
							'eventid' => strval($suppress_eventids[$k]),
							'action' => ZBX_PROTO_VALUE_SUPPRESSION_SUPPRESS,
							'userid' => $acknowledgement['userid'],
							'suppress_until' => $suppress_until
						])
					];
				}

				// Create tasks to unsuppress problems manually.
				if (($acknowledgement['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS) {
					$tasks[$k] = [
						'type' => ZBX_TM_TASK_DATA,
						'status' => ZBX_TM_STATUS_NEW,
						'clock' => $time
					];

					$task_suppress[$k] = [
						'taskid' => $id,
						'type' => ZBX_TM_DATA_TYPE_TEMP_SUPPRESSION,
						'data' => json_encode([
							'eventid' => strval($unsuppress_eventids[$k]),
							'action' => ZBX_PROTO_VALUE_SUPPRESSION_UNSUPPRESS,
							'userid' => $acknowledgement['userid']
						])
					];
				}
			}

			if ($tasks) {
				$taskids = DB::insertBatch('task', $tasks);
				$task_suppress = array_replace_recursive($task_suppress, zbx_toObject($taskids, 'taskid', true));
				DB::insertBatch('task_data', $task_suppress, false);
			}

			// Create tasks to perform server-side acknowledgement operations.
			$tasks = [];
			$tasks_ack = [];

			foreach ($acknowledgeids as $k => $id) {
				$acknowledgement = $acknowledges[$k];

				// Acknowledge task should be created for each acknowledge operation, regardless of it's action.
				$tasks[$k] = [
					'type' => ZBX_TM_TASK_ACKNOWLEDGE,
					'status' => ZBX_TM_STATUS_NEW,
					'clock' => $time
				];

				$tasks_ack[$k] = [
					'acknowledgeid' => $id
				];
			}

			if ($tasks) {
				$taskids = DB::insertBatch('task', $tasks);
				$tasks_ack = array_replace_recursive($tasks_ack, zbx_toObject($taskids, 'taskid', true));
				DB::insertBatch('task_acknowledge', $tasks_ack, false);
			}

			// Create tasks for event rank change actions - convert symptoms to cause.
			$tasks = [];
			$task_update_event_rank = [];

			foreach ($acknowledgeids as $k => $id) {
				$acknowledgement = $acknowledges[$k];

				if (($acknowledgement['action']
						& ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) {
					$tasks[$k] = [
						'type' => ZBX_TM_TASK_DATA,
						'status' => ZBX_TM_STATUS_NEW,
						'clock' => $time
					];

					$task_update_event_rank[$k] = [
						'taskid' => $id,
						'type' => ZBX_TM_DATA_TYPE_RANK_EVENT,
						'data' => json_encode([
							'acknowledgeid' => $id,
							'action' => $acknowledgement['action'],
							'eventid' => $tasks_update_event_rank_cause[$k]['eventid'],
							'userid' => $acknowledgement['userid']
						])
					];
				}
			}

			if ($tasks) {
				$taskids = DB::insertBatch('task', $tasks);
				$task_update_event_rank = array_replace_recursive($task_update_event_rank,
					zbx_toObject($taskids, 'taskid', true)
				);
				DB::insertBatch('task_data', $task_update_event_rank, false);

				$upd_acknowledges = [];

				foreach ($acknowledgeids as $k => $id) {
					$acknowledgement = $acknowledges[$k];

					if (($acknowledgement['action']
							& ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) {
						$upd_acknowledges[] = [
							'values' => ['taskid' => $taskids[$k]],
							'where' => ['acknowledgeid' => $id]
						];
					}
				}

				DB::update('acknowledges', $upd_acknowledges);
			}

			/*
			 * Create tasks for event rank change actions - convert cause to symptoms or update symptoms by changing
			 * cause to a different cause.
			 */
			$tasks = [];
			$task_update_event_rank = [];

			foreach ($acknowledgeids as $k => $id) {
				$acknowledgement = $acknowledges[$k];

				if (($acknowledgement['action']
						& ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
					$tasks[$k] = [
						'type' => ZBX_TM_TASK_DATA,
						'status' => ZBX_TM_STATUS_NEW,
						'clock' => $time
					];

					$task_update_event_rank[$k] = [
						'taskid' => $id,
						'type' => ZBX_TM_DATA_TYPE_RANK_EVENT,
						'data' => json_encode([
							'acknowledgeid' => $id,
							'action' => $acknowledgement['action'],
							'eventid' => $tasks_update_event_rank_symptom[$k]['eventid'],
							'cause_eventid' => $tasks_update_event_rank_symptom[$k]['cause_eventid'],
							'userid' => $acknowledgement['userid']
						])
					];
				}
			}

			if ($tasks) {
				$taskids = DB::insertBatch('task', $tasks);
				$task_update_event_rank = array_replace_recursive($task_update_event_rank,
					zbx_toObject($taskids, 'taskid', true)
				);
				DB::insertBatch('task_data', $task_update_event_rank, false);

				$upd_acknowledges = [];

				foreach ($acknowledgeids as $k => $id) {
					$acknowledgement = $acknowledges[$k];

					if (($acknowledgement['action']
							& ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
						$upd_acknowledges[] = [
							'values' => ['taskid' => $taskids[$k]],
							'where' => ['acknowledgeid' => $id]
						];
					}
				}

				DB::update('acknowledges', $upd_acknowledges);
			}
		}

		return ['eventids' => $data['eventids']];
	}

	/**
	 * Validates the input parameters for the acknowledge() method.
	 *
	 * @param array        $data                    And array of operation data.
	 *        string|array $data['eventids']        An event ID or an array of event IDs.
	 *        string       $data['cause_eventid']   Cause event ID. Used if $data['action'] yields 0x100.
	 *        string       $data['message']         Message if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 *        int          $data['severity']        New severity level if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 *        int          $data['suppress_until']  Suppress until time if ZBX_PROBLEM_UPDATE_SUPPRESS flag is passed.
	 *        int          $data['action']          Flags of performed operations combined:
	 *                                               - 0x01  - ZBX_PROBLEM_UPDATE_CLOSE
	 *                                               - 0x02  - ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
	 *                                               - 0x04  - ZBX_PROBLEM_UPDATE_MESSAGE
	 *                                               - 0x08  - ZBX_PROBLEM_UPDATE_SEVERITY
	 *                                               - 0x10  - ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE
	 *                                               - 0x20  - ZBX_PROBLEM_UPDATE_SUPPRESS
	 *                                               - 0x40  - ZBX_PROBLEM_UPDATE_UNSUPPRESS
	 *                                               - 0x80  - ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
	 *                                               - 0x100 - ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM
	 *
	 * @throws APIException
	 */
	protected function validateAcknowledge(array $data, int $time): void {
		$fields =  [
			'eventids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NORMALIZE],
			'action' =>			['type' => API_INT32, 'flags' => API_REQUIRED],
			'message' =>		['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'default' => DB::getDefault('acknowledges', 'message'), 'length' => DB::getFieldLength('acknowledges', 'message')],
			'severity' =>		['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => DB::getDefault('acknowledges', 'new_severity')],
			'suppress_until' =>	['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'cause_eventid' =>	['type' => API_MULTIPLE, 'rules' => [
				// "cause_eventid" should only be accessible if a cause event is converted to symptom event.
				['if' => static function ($data) { return ($data['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) != 0; }, 'type' => API_ID, 'flags' => API_REQUIRED],
				['else' => true, 'type' => API_UNEXPECTED]
			]]
		];

		if (!CApiInputValidator::validate(['type' => API_OBJECT, 'fields' => $fields], $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$action_mask = ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_ACKNOWLEDGE | ZBX_PROBLEM_UPDATE_MESSAGE
			| ZBX_PROBLEM_UPDATE_SEVERITY | ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE | ZBX_PROBLEM_UPDATE_SUPPRESS
			| ZBX_PROBLEM_UPDATE_UNSUPPRESS | ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE | ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM;

		// Check that at least one valid flag is set.
		if (($data['action'] & $action_mask) != $data['action']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('unexpected value "%1$s"', $data['action'])
			));
		}

		$has_close_action = ($data['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE;
		$has_ack_action = ($data['action'] & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;
		$has_message_action = ($data['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE;
		$has_severity_action = ($data['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY;
		$has_unack_action = ($data['action'] & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE;
		$has_suppress_action = ($data['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS;
		$has_unsuppress_action = ($data['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS;
		$has_rank_change_to_cause_action =
			($data['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE;
		$has_change_rank_to_symptom_action =
			($data['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM;

		// Check access rules.
		if ($has_close_action && !self::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_('no permissions to close problems')
			));
		}

		if ($has_message_action && !self::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_('no permissions to add problem comments')
			));
		}

		if ($has_severity_action && !self::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_('no permissions to change problem severity')
			));
		}

		if (($has_ack_action || $has_unack_action) && !self::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				$has_ack_action
					? _('no permissions to acknowledge problems')
					: _('no permissions to unacknowledge problems')
			));
		}

		if (($has_suppress_action || $has_unsuppress_action)
				&& !self::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				$has_suppress_action
					? _('no permissions to suppress problems')
					: _('no permissions to unsuppress problems')
			));
		}

		// Check permissions in user roles if user is allowed to change event rank.
		if (($has_rank_change_to_cause_action || $has_change_rank_to_symptom_action)
				&& !self::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				$has_rank_change_to_cause_action
					? _('no permissions to convert symptom problems to cause problems')
					: _('no permissions to convert cause problems to symptom problems')
			));
		}

		if ($has_ack_action && $has_unack_action) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('value must be one of %1$s', implode(', ', [ZBX_PROBLEM_UPDATE_ACKNOWLEDGE,
					ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE
				]))
			));
		}

		if ($has_suppress_action && $has_unsuppress_action) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('value must be one of %1$s', implode(', ', [ZBX_PROBLEM_UPDATE_SUPPRESS,
					ZBX_PROBLEM_UPDATE_UNSUPPRESS
				]))
			));
		}

		if ($has_close_action && ($has_suppress_action || $has_unsuppress_action)) {
			$action = $has_suppress_action ? ZBX_PROBLEM_UPDATE_SUPPRESS : ZBX_PROBLEM_UPDATE_UNSUPPRESS;
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('value must be one of %1$s', implode(', ', [ZBX_PROBLEM_UPDATE_CLOSE, $action]))
			));
		}

		if ($has_rank_change_to_cause_action && $has_change_rank_to_symptom_action) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('value must be one of %1$s', implode(', ', [ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE,
					ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM
				]))
			));
		}

		// Add the new cause ID to validate if the event exists and is still a problem.
		$eventids = array_fill_keys($data['eventids'], true);
		if ($has_change_rank_to_symptom_action && $data['cause_eventid'] !== null) {
			$eventids[$data['cause_eventid']] = true;
		}

		$events = $this->get([
			'output' => ['r_eventid'],
			'selectRelatedObject' => $has_close_action ? ['manual_close'] : null,
			'eventids' => array_keys($eventids),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE
		]);

		/*
		 * If at least one of following is given, API call should not be processed:
		 *   - eventid for OK event
		 *   - eventid with source, that is not trigger
		 *   - no read rights for related trigger
		 *   - nonexistent eventid
		 */
		if (count($eventids) != count($events)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$editable_events_count = $this->get([
			'countOutput' => true,
			'eventids' => array_keys($eventids),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'editable' => true
		]);

		if ($has_close_action) {
			$this->checkCanBeManuallyClosed($events, $editable_events_count);
		}

		if ($has_message_action && $data['message'] === '') {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'message',
				_('cannot be empty')
			));
		}

		if ($has_severity_action) {
			$this->checkCanChangeSeverity($events, $editable_events_count, $data['severity']);
		}

		if ($has_suppress_action) {
			$this->checkIfValidTime($data, $time);
		}
	}

	/**
	 * Checks if events can be closed manually.
	 *
	 * @param array $events                 Array of event objects.
	 * @param int   $editable_events_count  Count of editable events.
	 *
	 * @throws APIException                 Throws an exception:
	 *                                        - If at least one event is not editable;
	 *                                        - If any of given event can be closed manually according the triggers
	 *                                          configuration.
	 */
	protected function checkCanBeManuallyClosed(array $events, $editable_events_count): void {
		if (count($events) != $editable_events_count) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($events as $event) {
			if ($event['relatedObject']['manual_close'] != ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot close problem: %1$s.', _('trigger does not allow manual closing'))
				);
			}
		}
	}

	/**
	 * Checks if severity can be changed for all given events.
	 *
	 * @param array $events                 Array of event objects.
	 * @param int   $editable_events_count  Count of editable events.
	 * @param int   $severity               New severity.
	 *
	 * @throws APIException                 Throws an exception:
	 *                                        - If unknown severity is given;
	 *                                        - If at least one event is not editable.
	 */
	protected function checkCanChangeSeverity(array $events, $editable_events_count, $severity): void {
		if (count($events) != $editable_events_count) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$validator = new CLimitedSetValidator([
			'values' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING,
				TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER
			]
		]);

		if (!$validator->validate($severity)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'severity',
				_s('unexpected value "%1$s"', $severity)
			));
		}
	}

	/**
	 * Checks if time is valid future time.
	 *
	 * @param array $data                    Input data.
	 *        int   $data['suppress_until']  Suppress until Unix time. O for indefinite time.
	 * @param int   $time                    Current Unix time.
	 *
	 * @throws APIException
	 */
	protected function checkIfValidTime(array $data, int $time): void {
		if ($data['suppress_until'] <= $time && $data['suppress_until'] != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'suppress_until',
					_s('unexpected value "%1$s"', $data['suppress_until'])
				)
			);
		}
	}

	/**
	 * Checks if unsuppress action can be executed for given event.
	 *
	 * @param array  $event                                         Event object.
	 *        array  $event['suppression_data']                     List of problem suppression data.
	 *        string $event['suppression_data'][]['maintenanceid']  Problem maintenanceid.
	 *
	 * @return bool
	 */
	protected function isEventSuppressed(array $event): bool {
		foreach ($event['suppression_data'] as $suppression) {
			if ($suppression['maintenanceid'] == 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if event is closed.
	 *
	 * @param array  $event                              Event object.
	 *        string $event['r_eventid']                 OK event id. 0 if not resolved.
	 *        array  $event['acknowledges']              List of problem updates.
	 *        int    $event['acknowledges'][]['action']  Action performed in update.
	 *
	 * @return bool
	 */
	protected function isEventClosed(array $event): bool {
		if (bccomp($event['r_eventid'], '0') == 1) {
			return true;
		}

		foreach ($event['acknowledges'] as $acknowledge) {
			if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
				// If at least one manual close update was found, event is closing.
				return true;
			}
		}

		return false;
	}
}
