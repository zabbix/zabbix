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
			'from'		=> ['problem' => 'problem p'],
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

			'editable'					=> null,
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
			'tags'						=> null,
			'recent'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectAcknowledges'		=> null,
			'selectTags'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		$this->validateGet($options);

		// source and object
		$sqlParts['where'][] = 'p.source='.zbx_dbstr($options['source']);
		$sqlParts['where'][] = 'p.object='.zbx_dbstr($options['object']);

		$sub_sql_parts = [];

		// triggers
		if ($options['object'] == EVENT_OBJECT_TRIGGER) {
			// specific triggers
			if ($options['objectids'] !== null) {
				$triggers = API::Trigger()->get([
					'output' => [],
					'triggerids' => $options['objectids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				]);
				$options['objectids'] = array_keys($triggers);
			}
			// all triggers
			elseif ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
				$sub_sql_parts['from']['t'] = 'triggers t';
				$sub_sql_parts['where']['p-t'] = 'p.objectid=t.triggerid';
			}
			else {
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i,hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId($userid)).
					' WHERE p.objectid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
					' GROUP BY f.triggerid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
					')';
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
			elseif ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
				$sub_sql_parts['from']['i'] = 'items i';
				$sub_sql_parts['where']['p-i'] = 'p.objectid=i.itemid';
			}
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
				$sub_sql_parts['from']['f'] = 'functions f';
				$sub_sql_parts['from']['i'] = 'items i';
				$sub_sql_parts['from']['hg'] = 'hosts_groups hg';
				$sub_sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sub_sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sub_sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sub_sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sub_sql_parts['from']['i'] = 'items i';
				$sub_sql_parts['from']['hg'] = 'hosts_groups hg';
				$sub_sql_parts['where']['p-i'] = 'p.objectid=i.itemid';
				$sub_sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sub_sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sub_sql_parts['from']['f'] = 'functions f';
				$sub_sql_parts['from']['i'] = 'items i';
				$sub_sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sub_sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sub_sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sub_sql_parts['from']['i'] = 'items i';
				$sub_sql_parts['where']['p-i'] = 'p.objectid=i.itemid';
				$sub_sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
		}

		// applicationids
		if ($options['applicationids'] !== null) {
			zbx_value2array($options['applicationids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sub_sql_parts['from']['f'] = 'functions f';
				$sub_sql_parts['from']['ia'] = 'items_applications ia';
				$sub_sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sub_sql_parts['where']['f-ia'] = 'f.itemid=ia.itemid';
				$sub_sql_parts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// items
			elseif ($options['object'] == EVENT_OBJECT_ITEM) {
				$sub_sql_parts['from']['ia'] = 'items_applications ia';
				$sub_sql_parts['where']['p-ia'] = 'p.objectid=ia.itemid';
				$sub_sql_parts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// ignore this filter for lld rules
		}

		// severities
		if ($options['severities'] !== null) {
			zbx_value2array($options['severities']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sub_sql_parts['from']['t'] = 'triggers t';
				$sub_sql_parts['where']['p-t'] = 'p.objectid=t.triggerid';
				$sub_sql_parts['where']['t'] = dbConditionInt('t.priority', $options['severities']);
			}
			// ignore this filter for items and lld rules
		}

		if ($sub_sql_parts) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.implode(',', $sub_sql_parts['from']).
				' WHERE '.implode(' AND ', array_unique($sub_sql_parts['where'])).
			')';
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
			foreach ($options['tags'] as $tag) {
				if ($tag['value'] !== '') {
					$tag['value'] = str_replace('!', '!!', $tag['value']);
					$tag['value'] = str_replace('%', '!%', $tag['value']);
					$tag['value'] = str_replace('_', '!_', $tag['value']);
					$tag['value'] = '%'.mb_strtoupper($tag['value']).'%';
					$tag['value'] = ' AND UPPER(pt.value) LIKE'.zbx_dbstr($tag['value'])." ESCAPE '!'";
				}

				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM problem_tag pt'.
					' WHERE p.eventid=pt.eventid'.
						' AND pt.tag='.zbx_dbstr($tag['tag']).
						$tag['value'].
				')';
			}
		}

		// recent
		if ($options['recent'] !== null && $options['recent']) {
			$config = select_config();
			$ok_events_from = time() - $config['ok_period'];

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
			$sqlParts['where'][] = 'e.eventid>='.zbx_dbstr($options['eventid_from']);
		}

		// eventid_till
		if ($options['eventid_till'] !== null) {
			$sqlParts['where'][] = 'e.eventid<='.zbx_dbstr($options['eventid_till']);
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
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($event = DBfetch($res)) {
			if ($options['countOutput'] !== null) {
				$result = $event['rowscount'];
			}
			else {
				$result[$event['eventid']] = $event;
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['object', 'objectid'], $options['output']);
		}

		// removing keys (hash -> array)
		if ($options['preservekeys'] === null) {
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
			'values' => array_keys([EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL])
		]);
		if (!$sourceValidator->validate($options['source'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect source value.'));
		}

		$objectValidator = new CLimitedSetValidator([
			'values' => array_keys([EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE])
		]);
		if (!$objectValidator->validate($options['object'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect object value.'));
		}

		$sourceObjectValidator = new CEventSourceObjectValidator();
		if (!$sourceObjectValidator->validate(['source' => $options['source'], 'object' => $options['object']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
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
			$tags = API::getApiService()->select('problem_tag', [
				'output' => $this->outputExtend($options['selectTags'], ['eventid']),
				'filter' => ['eventid' => $eventids],
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($tags, 'eventid', 'problemtagid');
			$tags = $this->unsetExtraFields($tags, ['problemtagid', 'eventid'], []);
			$result = $relationMap->mapMany($result, $tags, 'tags');
		}

		return $result;
	}
}
