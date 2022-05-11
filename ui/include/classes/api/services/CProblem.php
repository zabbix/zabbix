<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class containing methods for operations with problems.
 */
class CProblem extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'problem';
	protected $tableAlias = 'p';
	protected $sortColumns = ['eventid'];

	/**
	 * Get problem data.
	 *
	 * @param array $options
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];

		$sqlParts = [
			'select'	=> [$this->fieldId('eventid')],
			'from'		=> ['p' => 'problem p'],
			'where'		=> [],
			'order'		=> [],
			'group'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'eventids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'objectids'					=> null,

			'editable'					=> false,
			'source'					=> EVENT_SOURCE_TRIGGERS,
			'object'					=> EVENT_OBJECT_TRIGGER,
			'severities'				=> null,
			'nopermissions'				=> null,
			// filter
			'time_from'					=> null,
			'time_till'					=> null,
			'eventid_from'				=> null,
			'eventid_till'				=> null,
			'acknowledged'				=> null,
			'suppressed'				=> null,
			'recent'					=> null,
			'any'						=> null,	// (internal) true if need not filtered by r_eventid
			'evaltype'					=> TAG_EVAL_TYPE_AND_OR,
			'tags'						=> null,
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectAcknowledges'		=> null,
			'selectSuppressionData'		=> null,
			'selectTags'				=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		$this->validateGet($options);

		// source and object
		$sqlParts['where'][] = 'p.source='.zbx_dbstr($options['source']);
		$sqlParts['where'][] = 'p.object='.zbx_dbstr($options['object']);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$user_groups = getUserGroupsByUserId(self::$userData['userid']);

				// specific triggers
				if ($options['objectids'] !== null) {
					$options['objectids'] = array_keys(API::Trigger()->get([
						'output' => [],
						'triggerids' => $options['objectids'],
						'editable' => $options['editable'],
						'preservekeys' => true
					]));
				}
				// all triggers
				else {
					$sqlParts['where'][] = 'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM functions f,items i,hosts_groups hgg'.
							' LEFT JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $user_groups).
						' WHERE p.objectid=f.triggerid'.
							' AND f.itemid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY i.hostid'.
						' HAVING MAX(permission)<'.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
							' OR MIN(permission) IS NULL'.
							' OR MIN(permission)='.PERM_DENY.
					')';
				}

				if ($options['source'] == EVENT_SOURCE_TRIGGERS) {
					$sqlParts = self::addTagFilterSqlParts($user_groups, $sqlParts);
				}
			}
			elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
				// specific items or lld rules
				if ($options['objectids'] !== null) {
					if ($options['object'] == EVENT_OBJECT_ITEM) {
						$items = API::Item()->get([
							'output' => [],
							'itemids' => $options['objectids'],
							'editable' => $options['editable'],
							'preservekeys' => true
						]);
						$options['objectids'] = array_keys($items);
					}
					elseif ($options['object'] == EVENT_OBJECT_LLDRULE) {
						$items = API::DiscoveryRule()->get([
							'output' => [],
							'itemids' => $options['objectids'],
							'editable' => $options['editable'],
							'preservekeys' => true
						]);
						$options['objectids'] = array_keys($items);
					}
				}
				// all items or lld rules
				else {
					$user_groups = getUserGroupsByUserId(self::$userData['userid']);

					$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM items i,hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $user_groups).
						' WHERE p.objectid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
					')';
				}
			}
		}

		// eventids
		if ($options['eventids'] !== null) {
			zbx_value2array($options['eventids']);
			$sqlParts['where'][] = dbConditionInt('p.eventid', $options['eventids']);
		}

		// objectids
		if ($options['objectids'] !== null) {
			zbx_value2array($options['objectids']);
			$sqlParts['where'][] = dbConditionInt('p.objectid', $options['objectids']);
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sqlParts['from']['f'] = 'functions f';
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['from']['hg'] = 'hosts_groups hg';
				$sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
				$sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sqlParts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['from']['hg'] = 'hosts_groups hg';
				$sqlParts['where']['p-i'] = 'p.objectid=i.itemid';
				$sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sqlParts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sqlParts['from']['f'] = 'functions f';
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
				$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['where']['p-i'] = 'p.objectid=i.itemid';
				$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
		}

		// severities
		if ($options['severities'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['object'] == EVENT_OBJECT_SERVICE) {
				zbx_value2array($options['severities']);
				$sqlParts['where'][] = dbConditionInt('p.severity', $options['severities']);
			}
			// ignore this filter for items and lld rules
		}

		// acknowledged
		if ($options['acknowledged'] !== null) {
			$acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
			$sqlParts['where'][] = 'p.acknowledged='.$acknowledged;
		}

		// suppressed
		if ($options['suppressed'] !== null) {
			$sqlParts['where'][] = (!$options['suppressed'] ? 'NOT ' : '').
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_suppress es'.
						' WHERE es.eventid=p.eventid'.
					')';
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'p',
				'problem_tag', 'eventid'
			);
		}

		// recent
		if ($options['recent'] !== null && $options['recent']) {
			$ok_events_from = time() - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD));

			$sqlParts['where'][] = '(p.r_eventid IS NULL OR p.r_clock>'.$ok_events_from.')';
		}
		else {
			$sqlParts['where'][] = 'p.r_eventid IS NULL';
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sqlParts['where'][] = 'p.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sqlParts['where'][] = 'p.clock<='.zbx_dbstr($options['time_till']);
		}

		// eventid_from
		if ($options['eventid_from'] !== null) {
			$sqlParts['where'][] = 'p.eventid>='.zbx_dbstr($options['eventid_from']);
		}

		// eventid_till
		if ($options['eventid_till'] !== null) {
			$sqlParts['where'][] = 'p.eventid<='.zbx_dbstr($options['eventid_till']);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('problem p', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('problem p', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($event = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $event['rowscount'];
			}
			else {
				$result[$event['eventid']] = $event;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['object', 'objectid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the get() method.
	 *
	 * @throws APIException  if the input is invalid
	 *
	 * @param array $options
	 */
	protected function validateGet(array $options) {
		$sourceValidator = new CLimitedSetValidator([
			'values' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]
		]);
		if (!$sourceValidator->validate($options['source'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect source value.'));
		}

		$objectValidator = new CLimitedSetValidator([
			'values' => [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE]
		]);
		if (!$objectValidator->validate($options['object'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect object value.'));
		}

		$sourceObjectValidator = new CEventSourceObjectValidator();
		if (!$sourceObjectValidator->validate(['source' => $options['source'], 'object' => $options['object']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
		}

		$evaltype_validator = new CLimitedSetValidator([
			'values' => [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]
		]);
		if (!$evaltype_validator->validate($options['evaltype'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect evaltype value.'));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$eventids = array_keys($result);

		// Adding operational data.
		if ($this->outputIsRequested('opdata', $options['output'])) {
			$problems = DBFetchArrayAssoc(DBselect(
				'SELECT p.eventid,p.clock,p.ns,t.triggerid,t.expression,t.opdata'.
				' FROM problem p'.
				' JOIN triggers t ON t.triggerid=p.objectid'.
				' WHERE '.dbConditionInt('p.eventid', $eventids)
			), 'eventid');

			foreach ($result as $eventid => $problem) {
				$result[$eventid]['opdata'] =
					(array_key_exists($eventid, $problems) && $problems[$eventid]['opdata'] !== '')
						? CMacrosResolverHelper::resolveTriggerOpdata($problems[$eventid], ['events' => true])
						: '';
			}
		}

		// adding acknowledges
		if ($options['selectAcknowledges'] !== null) {
			if ($options['selectAcknowledges'] != API_OUTPUT_COUNT) {
				// create the base query
				$acknowledges = API::getApiService()->select('acknowledges', [
					'output' => $this->outputExtend($options['selectAcknowledges'],
						['acknowledgeid', 'eventid']
					),
					'filter' => ['eventid' => $eventids],
					'preservekeys' => true
				]);

				$relationMap = $this->createRelationMap($acknowledges, 'eventid', 'acknowledgeid');
				$acknowledges = $this->unsetExtraFields($acknowledges, ['eventid', 'acknowledgeid'],
					$options['selectAcknowledges']
				);
				$result = $relationMap->mapMany($result, $acknowledges, 'acknowledges');
			}
			else {
				$acknowledges = DBFetchArrayAssoc(DBselect(
					'SELECT a.eventid,COUNT(a.acknowledgeid) AS rowscount'.
						' FROM acknowledges a'.
						' WHERE '.dbConditionInt('a.eventid', $eventids).
						' GROUP BY a.eventid'
				), 'eventid');

				foreach ($result as $eventid => $event) {
					$result[$eventid]['acknowledges'] = array_key_exists($eventid, $acknowledges)
						? $acknowledges[$eventid]['rowscount']
						: '0';
				}
			}
		}

		// Adding suppression data.
		if ($options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT) {
			$suppression_data = API::getApiService()->select('event_suppress', [
				'output' => $this->outputExtend($options['selectSuppressionData'], ['eventid', 'maintenanceid']),
				'filter' => ['eventid' => $eventids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($suppression_data, 'eventid', 'event_suppressid');
			$suppression_data = $this->unsetExtraFields($suppression_data, ['event_suppressid', 'eventid'], []);
			$result = $relation_map->mapMany($result, $suppression_data, 'suppression_data');
		}

		// Adding suppressed value.
		if ($this->outputIsRequested('suppressed', $options['output'])) {
			$suppressed_eventids = [];
			foreach ($result as &$problem) {
				if (array_key_exists('suppression_data', $problem)) {
					$problem['suppressed'] = $problem['suppression_data']
						? (string) ZBX_PROBLEM_SUPPRESSED_TRUE
						: (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
				}
				else {
					$suppressed_eventids[] = $problem['eventid'];
				}
			}
			unset($problem);

			if ($suppressed_eventids) {
				$suppressed_events = API::getApiService()->select('event_suppress', [
					'output' => ['eventid'],
					'filter' => ['eventid' => $suppressed_eventids]
				]);
				$suppressed_eventids = array_flip(zbx_objectValues($suppressed_events, 'eventid'));
				foreach ($result as &$problem) {
					$problem['suppressed'] = array_key_exists($problem['eventid'], $suppressed_eventids)
						? (string) ZBX_PROBLEM_SUPPRESSED_TRUE
						: (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
				}
				unset($problem);
			}
		}

		// Remove "maintenanceid" field if it's not requested.
		if ($options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT
				&& !$this->outputIsRequested('maintenanceid', $options['selectSuppressionData'])) {
			foreach ($result as &$row) {
				$row['suppression_data'] = $this->unsetExtraFields($row['suppression_data'], ['maintenanceid'], []);
			}
			unset($row);
		}

		// Resolve webhook urls.
		if ($this->outputIsRequested('urls', $options['output'])) {
			$tags_options = [
				'output' => ['eventid', 'tag', 'value'],
				'filter' => ['eventid' => $eventids]
			];
			$tags = DBselect(DB::makeSql('problem_tag', $tags_options));

			$events = [];

			foreach ($result as $event) {
				$events[$event['eventid']]['tags'] = [];
			}

			while ($tag = DBfetch($tags)) {
				$events[$tag['eventid']]['tags'][] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
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

		// Adding event tags.
		if ($options['selectTags'] !== null && $options['selectTags'] != API_OUTPUT_COUNT) {
			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$options['selectTags'] = ['tag', 'value'];
			}

			$tags_options = [
				'output' => $this->outputExtend($options['selectTags'], ['eventid']),
				'filter' => ['eventid' => $eventids]
			];
			$tags = DBselect(DB::makeSql('problem_tag', $tags_options));

			foreach ($result as &$event) {
				$event['tags'] = [];
			}
			unset($event);

			while ($tag = DBfetch($tags)) {
				$event = &$result[$tag['eventid']];

				unset($tag['problemtagid'], $tag['eventid']);
				$event['tags'][] = $tag;
			}
			unset($event);
		}

		return $result;
	}

	/**
	 * Add sql parts related to tag-based permissions.
	 *
	 * @param array $usrgrpids
	 * @param array $sqlParts
	 *
	 * @return array
	 */
	protected static function addTagFilterSqlParts(array $usrgrpids, array $sqlParts) {
		$tag_filters = CEvent::getTagFilters($usrgrpids);

		if (!$tag_filters) {
			return $sqlParts;
		}

		$sqlParts['from']['f'] = 'functions f';
		$sqlParts['from']['i'] = 'items i';
		$sqlParts['from']['hg'] = 'hosts_groups hg';
		$sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
		$sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
		$sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';

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
				elseif ($filter['value'] === '') {
					$tags[] = $filter['tag'];
				}
				else {
					$tag_values[$filter['tag']][] = $filter['value'];
				}
			}

			$conditions = [];

			if ($tags) {
				$conditions[] = dbConditionString('pt.tag', $tags);
			}
			$parenthesis = $tags || count($tag_values) > 1;

			foreach ($tag_values as $tag => $values) {
				$condition = 'pt.tag='.zbx_dbstr($tag).' AND '.dbConditionString('pt.value', $values);
				$conditions[] = $parenthesis ? '('.$condition.')' : $condition;
			}

			$conditions = (count($conditions) > 1) ? '('.implode(' OR ', $conditions).')' : $conditions[0];

			$tag_conditions[] = 'hg.groupid='.zbx_dbstr($groupid).' AND '.$conditions;
		}

		if ($tag_conditions) {
			$sqlParts['from']['pt'] = 'problem_tag pt';
			$sqlParts['where']['p-pt'] = 'p.eventid=pt.eventid';

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

		$sqlParts['where'][] = (count($tag_conditions) > 1)
			? '('.implode(' OR ', $tag_conditions).')'
			: $tag_conditions[0];

		return $sqlParts;
	}
}
