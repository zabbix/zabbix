<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
		$userid = self::$userData['userid'];

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
			'applicationids'			=> null,
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
			'evaltype'					=> TAG_EVAL_TYPE_AND,
			'tags'						=> null,
			'recent'					=> null,
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectAcknowledges'		=> null,
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
				// specific triggers
				$userGroups = getUserGroupsByUserId(self::$userData['userid']);

				if ($options['objectids'] !== null) {
					$triggers = API::Trigger()->get([
						'output' => ['triggerid'],
						'selectGroups' => ['groupid'],
						'triggerids' => $options['objectids'],
						'editable' => $options['editable']
					]);

					$group_triggers = [];

					foreach ($triggers as $trigger) {
						foreach ($trigger['groups'] as $group) {
							$group_triggers[$group['groupid']][$trigger['triggerid']] = $trigger['triggerid'];
						}
					}

					// Get rights.
					$db_rights = DBselect(
						'SELECT r.groupid,r.id'.
						' FROM rights r'.
						' WHERE '.dbConditionInt('r.groupid', $userGroups).
							' AND '.dbConditionInt('r.id', array_keys($group_triggers))
					);

					$rights = [];

					while ($db_right = DBfetch($db_rights)) {
						$rights[$db_right['groupid']][$db_right['id']] = true;
					}

					// Get tag filter.
					$db_tag_filters = DBselect(
						'SELECT tf.groupid,tf.tag,tf.value,tf.usrgrpid'.
						' FROM tag_filter tf'.
						' WHERE '.dbConditionInt('tf.usrgrpid', $userGroups)
					);

					$tag_filters_tmp = [];
					$allowed_triggers = [];

					while ($db_tag_filter = DBfetch($db_tag_filters)) {
						if ($db_tag_filter['tag'] === '' && $db_tag_filter['value'] === ''
								&& array_key_exists($db_tag_filter['groupid'], $group_triggers)) {
							$allowed_triggers = array_merge($allowed_triggers,
								$group_triggers[$db_tag_filter['groupid']]
							);
						}
						else {
							$tag_filters_tmp[$db_tag_filter['usrgrpid']][$db_tag_filter['groupid']][] = [
								'tag' => $db_tag_filter['tag'],
								'value' => $db_tag_filter['value']
							];
						}
					}

					$tag_filters = [];

					foreach ($userGroups as $usrgrpid) {
						if (array_key_exists($usrgrpid, $tag_filters_tmp)) {
							foreach ($tag_filters_tmp[$usrgrpid] as $groupid => $tag_filter) {
								if (array_key_exists($usrgrpid, $rights)
										&& array_key_exists($groupid, $rights[$usrgrpid])) {
									unset($rights[$usrgrpid][$groupid]);
								}
								else {
									unset($rights[$usrgrpid]);
								}

								if (array_key_exists($groupid, $tag_filters)) {
									$tag_filters[$groupid] = array_merge($tag_filters[$groupid], $tag_filter);
								}
								else {
									$tag_filters[$groupid] = $tag_filter;
								}
							}
						}
					}

					foreach ($rights as $usrgrpid => $right) {
						foreach ($right as $groupid => $value) {
							if (!array_key_exists($usrgrpid, $tag_filters_tmp)
									|| array_key_exists($groupid, $tag_filters_tmp[$usrgrpid])) {
								if (array_key_exists($groupid, $group_triggers)) {
									$allowed_triggers = array_merge($allowed_triggers, $group_triggers[$groupid]);
									unset($group_triggers[$groupid]);
								}
							}
						}
					}

					$fillter_condition = [];

					if ($allowed_triggers) {
						$fillter_condition[] = dbConditionInt('p.objectid', $allowed_triggers);
					}

					foreach ($tag_filters as $groupid => $tag_filter) {
						foreach ($tag_filter as $values) {
							if (array_key_exists($groupid, $group_triggers)) {
								$tag_value = '';
								if ($values['value'] !== '') {
									$tag_value = ' AND pt.value = '.zbx_dbstr($values['value']);
								}

								$fillter_condition[] = 'EXISTS ('.
									'SELECT NULL'.
									' FROM problem_tag pt'.
									' WHERE pt.eventid = p.eventid'.
										' AND '.dbConditionInt('p.objectid', $group_triggers[$groupid]).
										' AND pt.tag = '.zbx_dbstr($values['tag']).
										$tag_value.
								')';
							}
						}
					}

					if ($fillter_condition) {
						$sqlParts['where'][] = '('.implode(' OR ', $fillter_condition).')';
					}
					else {
						$options['objectids'] = [];
					}
				}
				// all triggers
				else {
					// Get all visible groups.
					$host_groups = API::HostGroup()->get([
						'output' => ['groupid'],
						'preservekeys' => true
					]);

					// Get rights.
					$db_rights = DBselect(
						'SELECT r.groupid,r.id'.
						' FROM rights r'.
						' WHERE '.dbConditionInt('r.groupid', $userGroups).
							' AND '.dbConditionInt('r.id', array_keys($host_groups))
					);

					$rights = [];

					while ($db_right = DBfetch($db_rights)) {
						$rights[$db_right['groupid']][$db_right['id']] = true;
					}

					// Get tag filter.
					$db_tag_filters = DBselect(
						'SELECT tf.groupid,tf.tag,tf.value,tf.usrgrpid'.
						' FROM tag_filter tf'.
						' WHERE '.dbConditionInt('tf.usrgrpid', $userGroups).
							' AND '.dbConditionInt('tf.groupid', array_keys($host_groups))
					);

					$tag_filters_tmp = [];

					$skip_groups = [];
					while ($db_tag_filter = DBfetch($db_tag_filters)) {
						if ($db_tag_filter['tag'] === '' && $db_tag_filter['value'] === '') {
							$skip_groups[$db_tag_filter['groupid']] = true;
						}
						else {
							$tag_filters_tmp[$db_tag_filter['usrgrpid']][$db_tag_filter['groupid']][] = [
								'tag' => $db_tag_filter['tag'],
								'value' => $db_tag_filter['value']
							];
						}
					}

					$tag_filters = [];
					$allowed_triggers = [];

					foreach ($userGroups as $usrgrpid) {
						if (array_key_exists($usrgrpid, $tag_filters_tmp)) {
							foreach ($tag_filters_tmp[$usrgrpid] as $groupid => $tag_filter) {
								if (array_key_exists($groupid, $rights[$usrgrpid])) {
									unset($rights[$usrgrpid][$groupid]);
								}

								if (array_key_exists($groupid, $tag_filters)) {
									$tag_filters[$groupid] = array_merge($tag_filters[$groupid], $tag_filter);
								}
								else {
									$tag_filters[$groupid] = $tag_filter;
								}
							}
						}
						else {
							if (array_key_exists($usrgrpid, $rights)) {
								$skip_groups += $rights[$usrgrpid];
								unset($rights[$usrgrpid]);
							}
						}
					}

					foreach ($rights as $usrgrpid => $right) {
						foreach ($right as $groupid => $value) {
							if (array_key_exists($groupid, $host_groups)) {
								$allowed_triggers = array_merge($allowed_triggers, $host_groups[$groupid]);
							}
						}
					}

					$fillter_condition = [];

					if ($host_groups) {
						$triggers = API::Trigger()->get([
							'output' => ['triggerid'],
							'selectGroups' => ['groupid'],
							'groupids' => array_keys($host_groups)
						]);

						$group_triggers = [];

						foreach ($triggers as $trigger) {
							foreach ($trigger['groups'] as $group) {
								$group_triggers[$group['groupid']][$trigger['triggerid']] = $trigger['triggerid'];
							}
						}

						if ($allowed_triggers) {
							$fillter_condition[] = dbConditionInt('p.objectid', $allowed_triggers);
						}

						foreach ($tag_filters as $groupid => $tag_filter) {
							foreach ($tag_filter as $values) {
								if (array_key_exists($groupid, $group_triggers)) {
									$tag_value = '';
									if ($values['value'] !== '') {
										$tag_value = ' AND pt.value = '.zbx_dbstr($values['value']);
									}

									$fillter_condition[] = 'EXISTS ('.
										'SELECT NULL'.
										' FROM problem_tag pt'.
										' WHERE pt.eventid = p.eventid'.
											' AND '.dbConditionInt('p.objectid', $group_triggers[$groupid]).
											' AND pt.tag = '.zbx_dbstr($values['tag']).
											$tag_value.
									')';
								}
							}
						}
					}

					if ($skip_groups) {
						$fillter_condition[] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM functions f,items i,hosts_groups hgg'.
						' WHERE p.objectid=f.triggerid'.
							' AND f.itemid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
							' AND '.dbConditionInt('hgg.groupid', array_keys($skip_groups)).
						')';
					}

					if ($fillter_condition) {
						$sqlParts['where'][] = '('.implode(' OR ', $fillter_condition).')';
					}
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
					$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM items i,hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId($userid)).
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

		// applicationids
		if ($options['applicationids'] !== null) {
			zbx_value2array($options['applicationids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sqlParts['from']['f'] = 'functions f';
				$sqlParts['from']['ia'] = 'items_applications ia';
				$sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sqlParts['where']['f-ia'] = 'f.itemid=ia.itemid';
				$sqlParts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// items
			elseif ($options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['ia'] = 'items_applications ia';
				$sqlParts['where']['p-ia'] = 'p.objectid=ia.itemid';
				$sqlParts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// ignore this filter for lld rules
		}

		// severities
		if ($options['severities'] !== null) {
			zbx_value2array($options['severities']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sqlParts['from']['t'] = 'triggers t';
				$sqlParts['where']['p-t'] = 'p.objectid=t.triggerid';
				$sqlParts['where']['t'] = dbConditionInt('t.priority', $options['severities']);
			}
			// ignore this filter for items and lld rules
		}

		// acknowledged
		if ($options['acknowledged'] !== null) {
			$sqlParts['where'][] = ($options['acknowledged'] ? '' : 'NOT ').'EXISTS ('.
				'SELECT NULL'.
				' FROM acknowledges a'.
				' WHERE p.eventid=a.eventid'.
			')';
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$where = '';
			$cnt = count($options['tags']);

			foreach ($options['tags'] as $tag) {
				if (!array_key_exists('value', $tag)) {
					$tag['value'] = '';
				}

				if ($tag['value'] !== '') {
					if (!array_key_exists('operator', $tag)) {
						$tag['operator'] = TAG_OPERATOR_LIKE;
					}

					switch ($tag['operator']) {
						case TAG_OPERATOR_EQUAL:
							$tag['value'] = ' AND pt.value='.zbx_dbstr($tag['value']);
							break;

						case TAG_OPERATOR_LIKE:
						default:
							$tag['value'] = str_replace('!', '!!', $tag['value']);
							$tag['value'] = str_replace('%', '!%', $tag['value']);
							$tag['value'] = str_replace('_', '!_', $tag['value']);
							$tag['value'] = '%'.mb_strtoupper($tag['value']).'%';
							$tag['value'] = ' AND UPPER(pt.value) LIKE'.zbx_dbstr($tag['value'])." ESCAPE '!'";
					}
				}
				elseif ($tag['operator'] == TAG_OPERATOR_EQUAL) {
					$tag['value'] = ' AND pt.value='.zbx_dbstr($tag['value']);
				}

				if ($where !== '')  {
					$where .= ($options['evaltype'] == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ';
				}

				$where .= 'EXISTS ('.
					'SELECT NULL'.
					' FROM problem_tag pt'.
					' WHERE p.eventid=pt.eventid'.
						' AND pt.tag='.zbx_dbstr($tag['tag']).$tag['value'].
				')';
			}

			// Add closing parenthesis if there are more than one OR statements.
			if ($options['evaltype'] == TAG_EVAL_TYPE_OR && $cnt > 1) {
				$where = '('.$where.')';
			}

			$sqlParts['where'][] = $where;
		}

		// recent
		if ($options['recent'] !== null && $options['recent']) {
			$config = select_config();
			$ok_events_from = time() - timeUnitToSeconds($config['ok_period']);

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

		if ($options['output'] !== API_OUTPUT_EXTEND && in_array('r_clock', $options['output'])
				&& !in_array('r_eventid', $options['output'])) {
			$options['output'][] = 'r_eventid';
			$unset_extra_field = true;
		}
		else {
			$unset_extra_field = false;
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
			$result = $this->hideInaccessibleData($result, $options['output'], $unset_extra_field);
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
			'values' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL]
		]);
		if (!$sourceValidator->validate($options['source'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect source value.'));
		}

		$objectValidator = new CLimitedSetValidator([
			'values' => [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE]
		]);
		if (!$objectValidator->validate($options['object'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect object value.'));
		}

		$sourceObjectValidator = new CEventSourceObjectValidator();
		if (!$sourceObjectValidator->validate(['source' => $options['source'], 'object' => $options['object']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
		}

		$evaltype_validator = new CLimitedSetValidator([
			'values' => [TAG_EVAL_TYPE_AND, TAG_EVAL_TYPE_OR]
		]);
		if (!$evaltype_validator->validate($options['evaltype'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect evaltype value.'));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$eventids = array_keys($result);

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

				foreach ($result as &$event) {
					$event['acknowledges'] = array_key_exists($event['eventid'], $acknowledges)
						? $acknowledges[$event['eventid']]['rowscount']
						: 0;
				}
				unset($event);
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
	 * If user don't have permission to see recovery event, r_clock should be hidden.
	 *
	 * @param array $result
	 * @param mixed $output
	 * @param bool  $unset_extra_field
	 *
	 * @return array
	 */
	protected function hideInaccessibleData(array $result, $output, $unset_extra_field) {
		if ($output === API_OUTPUT_EXTEND || in_array('r_clock', $output)) {
			$r_eventids = (array_flip((zbx_objectValues($result, 'r_eventid'))));
			unset($r_eventids[0]);

			$events = API::Event()->get([
				'output' => ['eventid'],
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			]);

			foreach ($result as &$problem) {
				if ($problem['r_eventid'] && !array_key_exists($problem['r_eventid'], $events)) {
					$problem['r_clock'] = 0;
				}

				if ($unset_extra_field) {
					unset($problem['r_eventid']);
				}
			}
			unset($problem);
		}

		return $result;
	}
}
