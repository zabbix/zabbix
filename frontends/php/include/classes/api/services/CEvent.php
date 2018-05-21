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
 * Class containing methods for operations with events.
 */
class CEvent extends CApiService {

	protected $tableName = 'events';
	protected $tableAlias = 'e';
	protected $sortColumns = ['eventid', 'objectid', 'clock'];

	/**
	 * Array of supported objects where keys are object IDs and values are translated object names.
	 *
	 * @var array
	 */
	protected $objects = [];

	/**
	 * Array of supported sources where keys are source IDs and values are translated source names.
	 *
	 * @var array
	 */
	protected $sources = [];

	public function __construct() {
		parent::__construct();

		$this->sources = eventSource();
		$this->objects = eventObject();
	}

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
	 * @param bool  $options['editable']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$defOptions = [
			'eventids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'applicationids'			=> null,
			'objectids'					=> null,

			'editable'					=> false,
			'object'					=> EVENT_OBJECT_TRIGGER,
			'source'					=> EVENT_SOURCE_TRIGGERS,
			'severities'				=> null,
			'nopermissions'				=> null,
			// filter
			'value'						=> null,
			'time_from'					=> null,
			'time_till'					=> null,
			'eventid_from'				=> null,
			'eventid_till'				=> null,
			'acknowledged'				=> null,
			'evaltype'					=> TAG_EVAL_TYPE_AND,
			'tags'						=> null,
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHosts'				=> null,
			'selectRelatedObject'		=> null,
			'select_alerts'				=> null,
			'select_acknowledges'		=> null,
			'selectTags'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		$this->validateGet($options);

		if ($options['value'] !== null) {
			zbx_value2array($options['value']);
		}

		if ($options['source'] == EVENT_SOURCE_TRIGGERS && $options['object'] == EVENT_OBJECT_TRIGGER) {
			if ($options['value'] === null) {
				$options['value'] = [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE];
			}

			$problems = in_array(TRIGGER_VALUE_TRUE, $options['value'])
				? $this->getEvents(['value' => [TRIGGER_VALUE_TRUE]] + $options)
				: [];
			$recovery = in_array(TRIGGER_VALUE_FALSE, $options['value'])
				? $this->getEvents(['value' => [TRIGGER_VALUE_FALSE]] + $options)
				: [];
			if ($options['countOutput']) {
				$problems = ($problems === []) ? 0 : $problems;
				$recovery = ($recovery === []) ? 0 : $recovery;

				if ($options['groupCount']) {
					$problems = zbx_toHash($problems, 'objectid');
					$recovery = zbx_toHash($recovery, 'objectid');

					foreach ($problems as $objectid => &$problem) {
						if (array_key_exists($objectid, $recovery)) {
							$problem['rowscount'] += $recovery['rowscount'];
							unset($recovery[$objectid]);
						}
					}
					unset($problem);

					$result = array_values($problems + $recovery);
				}
				else {
					$result = $problems + $recovery;
				}
			}
			else {
				$result = self::sortResult($problems + $recovery, $options['sortfield'], $options['sortorder']);

				if ($options['limit'] !== null) {
					$result = array_slice($result, 0, $options['limit'], true);
				}
			}
		}
		else {
			$result = $this->getEvents($options);
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
	 * Returns the list of events.
	 *
	 * @param array     $options
	 */
	private function getEvents(array $options) {
		$sqlParts = [
			'select'	=> [$this->fieldId('eventid')],
			'from'		=> ['e' => 'events e'],
			'where'		=> [],
			'order'		=> [],
			'group'		=> [],
			'limit'		=> null
		];

		// source and object
		$sqlParts['where'][] = 'e.source='.zbx_dbstr($options['source']);
		$sqlParts['where'][] = 'e.object='.zbx_dbstr($options['object']);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
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
						' WHERE e.objectid=f.triggerid'.
							' AND f.itemid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY i.hostid'.
						' HAVING MAX(permission)<'.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
							' OR MIN(permission) IS NULL'.
							' OR MIN(permission)='.PERM_DENY.
					')';
				}

				if ($options['source'] == EVENT_SOURCE_TRIGGERS) {
					$sqlParts = self::addTagFilterSqlParts($user_groups, $sqlParts, $options['value'][0]);
				}
			}
			// items and LLD rules
			elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
				// specific items or LLD rules
				if ($options['objectids'] !== null) {
					if ($options['object'] == EVENT_OBJECT_ITEM) {
						$items = API::Item()->get([
							'output' => ['itemid'],
							'itemids' => $options['objectids'],
							'editable' => $options['editable']
						]);
						$options['objectids'] = zbx_objectValues($items, 'itemid');
					}
					elseif ($options['object'] == EVENT_OBJECT_LLDRULE) {
						$items = API::DiscoveryRule()->get([
							'output' => ['itemid'],
							'itemids' => $options['objectids'],
							'editable' => $options['editable']
						]);
						$options['objectids'] = zbx_objectValues($items, 'itemid');
					}
				}
				// all items and LLD rules
				else {
					$user_groups = getUserGroupsByUserId(self::$userData['userid']);

					$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM items i,hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $user_groups).
						' WHERE e.objectid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
					')';
				}
			}
		}

		// eventids
		if (!is_null($options['eventids'])) {
			zbx_value2array($options['eventids']);
			$sqlParts['where'][] = dbConditionInt('e.eventid', $options['eventids']);
		}

		// objectids
		if ($options['objectids'] !== null
				&& in_array($options['object'], [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE])) {

			zbx_value2array($options['objectids']);
			$sqlParts['where'][] = dbConditionInt('e.objectid', $options['objectids']);

			if ($options['groupCount']) {
				$sqlParts['group']['objectid'] = 'e.objectid';
			}
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sqlParts['from']['f'] = 'functions f';
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['from']['hg'] = 'hosts_groups hg';
				$sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
				$sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sqlParts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['from']['hg'] = 'hosts_groups hg';
				$sqlParts['where']['e-i'] = 'e.objectid=i.itemid';
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
				$sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
				$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['i'] = 'items i';
				$sqlParts['where']['e-i'] = 'e.objectid=i.itemid';
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
				$sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
				$sqlParts['where']['f-ia'] = 'f.itemid=ia.itemid';
				$sqlParts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// items
			elseif ($options['object'] == EVENT_OBJECT_ITEM) {
				$sqlParts['from']['ia'] = 'items_applications ia';
				$sqlParts['where']['e-ia'] = 'e.objectid=ia.itemid';
				$sqlParts['where']['ia'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			}
			// ignore this filter for lld rules
		}

		// severities
		if ($options['severities'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				zbx_value2array($options['severities']);
				$sqlParts['where'][] = dbConditionInt('e.severity', $options['severities']);
			}
			// ignore this filter for items and lld rules
		}

		// acknowledged
		if (!is_null($options['acknowledged'])) {
			$acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
			$sqlParts['where'][] = 'e.acknowledged='.$acknowledged;
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
							$tag['value'] = ' AND et.value='.zbx_dbstr($tag['value']);
							break;

						case TAG_OPERATOR_LIKE:
						default:
							$tag['value'] = str_replace('!', '!!', $tag['value']);
							$tag['value'] = str_replace('%', '!%', $tag['value']);
							$tag['value'] = str_replace('_', '!_', $tag['value']);
							$tag['value'] = '%'.mb_strtoupper($tag['value']).'%';
							$tag['value'] = ' AND UPPER(et.value) LIKE'.zbx_dbstr($tag['value'])." ESCAPE '!'";
					}
				}
				elseif ($tag['operator'] == TAG_OPERATOR_EQUAL) {
					$tag['value'] = ' AND et.value='.zbx_dbstr($tag['value']);
				}

				if ($where !== '') {
					$where .= ($options['evaltype'] == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ';
				}

				$where .= 'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag et'.
					' WHERE e.eventid=et.eventid'.
						' AND et.tag='.zbx_dbstr($tag['tag']).$tag['value'].
				')';
			}

			// Add closing parenthesis if there are more than one OR statements.
			if ($options['evaltype'] == TAG_EVAL_TYPE_OR && $cnt > 1) {
				$where = '('.$where.')';
			}

			$sqlParts['where'][] = $where;
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sqlParts['where'][] = 'e.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sqlParts['where'][] = 'e.clock<='.zbx_dbstr($options['time_till']);
		}

		// eventid_from
		if ($options['eventid_from'] !== null) {
			$sqlParts['where'][] = 'e.eventid>='.zbx_dbstr($options['eventid_from']);
		}

		// eventid_till
		if ($options['eventid_till'] !== null) {
			$sqlParts['where'][] = 'e.eventid<='.zbx_dbstr($options['eventid_till']);
		}

		// value
		if ($options['value'] !== null) {
			$sqlParts['where'][] = dbConditionInt('e.value', $options['value']);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('events e', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('events e', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$result = [];

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($event = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $event;
				}
				else {
					$result = $event['rowscount'];
				}
			}
			else {
				$result[$event['eventid']] = $event;
			}
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
		if (!$sourceValidator->validate($options['source'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect source value.'));
		}

		$objectValidator = new CLimitedSetValidator([
			'values' => array_keys(eventObject())
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

	/**
	 * Acknowledges the given events and closes them if necessary.
	 *
	 * @param array  $data                  And array of operation data.
	 * @param mixed  $data['eventids']      An event ID or an array of event IDs.
	 * @param string $data['message']       Message if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 * @param string $data['severity']      New severity level if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 * @param int    $data['action']        Flags of performed operations combined:
	 *                                       - 0x01 - ZBX_PROBLEM_UPDATE_CLOSE
	 *                                       - 0x02 - ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
	 *                                       - 0x04 - ZBX_PROBLEM_UPDATE_MESSAGE
	 *                                       - 0x08 - ZBX_PROBLEM_UPDATE_SEVERITY
	 *
	 * @return array
	 */
	public function acknowledge(array $data) {
		$this->validateAcknowledge($data);

		$data['eventids'] = zbx_toArray($data['eventids']);
		$data['eventids'] = array_keys(array_flip($data['eventids']));

		$has_close_action = (($data['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE);

		$events = $this->get([
			'output' => ['objectid', 'acknowledged', 'severity', 'r_eventid'],
			'select_acknowledges' => $has_close_action? ['action'] : null,
			'eventids' => $data['eventids'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'preservekeys' => true
		]);

		$ack_eventids = [];
		$sev_change_eventids = [];
		$acknowledges = [];
		$time = time();

		foreach ($events as $eventid => $event) {
			$action = ZBX_PROBLEM_UPDATE_NONE;
			$old_severity = 0;
			$new_severity = 0;
			$message = '';

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

			// Perform ZBX_PROBLEM_UPDATE_MESSAGE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
				$action |= ZBX_PROBLEM_UPDATE_MESSAGE;
				$message = $data['message'];
			}

			// Perform ZBX_PROBLEM_UPDATE_MESSAGE action flag.
			if (($data['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY
					&& $data['severity'] != $event['severity']) {
				$action |= ZBX_PROBLEM_UPDATE_SEVERITY;
				$old_severity = $event['severity'];
				$new_severity = $data['severity'];
				$sev_change_eventids[] = $eventid;
			}

			// For some of selected events action might not pe performed, as event is already with given change.
			if ($action != ZBX_PROBLEM_UPDATE_NONE) {
				$acknowledges[] = [
					'userid' => self::$userData['userid'],
					'eventid' => $eventid,
					'clock' => $time,
					'message' => $message,
					'action' => $action,
					'old_severity' => $old_severity,
					'new_severity' => $new_severity
				];
			}
		}

		// Make changes in problem and events tables.
		if ($acknowledges) {
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

				if (($acknowledgement['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE){
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
		}

		return ['eventids' => $data['eventids']];
	}

	/**
	 * Validates the input parameters for the acknowledge() method.
	 *
	 * @param array         $data              And array of operation data.
	 * @param string|array  $data['eventids']  An event ID or an array of event IDs.
	 * @param string        $data['message']   Message if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 * @param string        $data['severity']  New severity level if ZBX_PROBLEM_UPDATE_SEVERITY flag is passed.
	 * @param int           $data['action']    Flags of performed operations combined:
	 *                                           - 0x01 - ZBX_PROBLEM_UPDATE_CLOSE
	 *                                           - 0x02 - ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
	 *                                           - 0x04 - ZBX_PROBLEM_UPDATE_MESSAGE
	 *                                           - 0x08 - ZBX_PROBLEM_UPDATE_SEVERITY
	 *
	 * @throws APIException                    If the input is invalid.
	 */
	protected function validateAcknowledge(array $data) {
		$db_fields = [
			'eventids' => null,
			'action' => null,
			'message' => '',
			'severity' => ''
		];

		if (!check_db_fields($db_fields, $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}

		$data['eventids'] = zbx_toArray($data['eventids']);
		$data['eventids'] = array_keys(array_flip($data['eventids']));

		// Chack that at least one valid flag is set.
		$action_mask = ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_ACKNOWLEDGE | ZBX_PROBLEM_UPDATE_MESSAGE
				| ZBX_PROBLEM_UPDATE_SEVERITY;

		if (($data['action'] & $action_mask) != $data['action']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'action',
				_s('unexpected value "%1$s"', $data['action'])
			));
		}

		$has_close_action = (($data['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE);
		$has_message_action = (($data['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE);
		$has_severity_action = (($data['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY);

		$events = $this->get([
			'output' => [],
			'selectRelatedObject' => $has_close_action ? ['manual_close'] : null,
			'eventids' => $data['eventids'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE
		]);

		/*
		 * If at least one of following is given, API call should not be processed:
		 *   - eventid for OK event
		 *   - eventid with source, that is not trigger
		 *   - no read rights for related trigger
		 *   - unexisting eventid
		 */
		if (count($data['eventids']) != count($events)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$editable_events_count = $this->get([
			'countOutput' => true,
			'eventids' => $data['eventids'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'editable' => true
		]);

		if ($has_close_action) {
			$this->checkCanBeManuallyClosed($events, $editable_events_count);
		}

		if ($has_message_action && $data['message'] === '') {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'message', _('cannot be empty'))
			);
		}

		if ($has_severity_action) {
			$this->checkCanChangeSeverity($data['eventids'], $editable_events_count, $data['severity']);
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
	protected function checkCanBeManuallyClosed(array $events, $editable_events_count) {
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
	protected function checkCanChangeSeverity(array $events, $editable_events_count, $severity) {
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
	 * Checks if events are closed.
	 *
	 * @param array $event                              Event object.
	 * @param array $event['r_eventid']                 OK event id. 0 if not resolved.
	 * @param array $event['acknowledges']              List of problem updates.
	 * @param array $event['acknowledges'][]['action']  Action performed in update.
	 *
	 * @return bool
	 */
	protected function isEventClosed(array $event) {
		if (bccomp($event['r_eventid'], '0') == 1) {
			return true;
		}
		else {
			foreach ($event['acknowledges'] as $acknowledge) {
				if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
					// If at least one manual close update was found, event is closing.
					return true;
				}
			}
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('r_eventid', $options['output'])) {
				// Select fields from event_recovery table using LEFT JOIN.

				$sqlParts['select']['r_eventid'] = 'er1.r_eventid';
				$sqlParts['left_join'][] = ['from' => 'event_recovery er1', 'on' => 'er1.eventid=e.eventid'];
				$sqlParts['left_table'] = 'e';
			}

			if ($this->outputIsRequested('c_eventid', $options['output'])
					|| $this->outputIsRequested('correlationid', $options['output'])
					|| $this->outputIsRequested('userid', $options['output'])) {
				// Select fields from event_recovery table using LEFT JOIN.

				if ($this->outputIsRequested('c_eventid', $options['output'])) {
					$sqlParts['select']['c_eventid'] = 'er2.c_eventid';
				}
				if ($this->outputIsRequested('correlationid', $options['output'])) {
					$sqlParts['select']['correlationid'] = 'er2.correlationid';
				}
				if ($this->outputIsRequested('userid', $options['output'])) {
					$sqlParts['select']['userid'] = 'er2.userid';
				}

				$sqlParts['left_join'][] = ['from' => 'event_recovery er2', 'on' => 'er2.r_eventid=e.eventid'];
				$sqlParts['left_table'] = 'e';
			}

			if ($options['selectRelatedObject'] !== null || $options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('e.object', $sqlParts);
				$sqlParts = $this->addQuerySelect('e.objectid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$eventIds = array_keys($result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			// trigger events
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$query = DBselect(
					'SELECT e.eventid,i.hostid'.
						' FROM events e,functions f,items i'.
						' WHERE '.dbConditionInt('e.eventid', $eventIds).
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
						' WHERE '.dbConditionInt('e.eventid', $eventIds).
						' AND e.objectid=i.itemid'.
						' AND e.object='.zbx_dbstr($options['object']).
						' AND e.source='.zbx_dbstr($options['source'])
				);
			}

			$relationMap = new CRelationMap();
			while ($relation = DBfetch($query)) {
				$relationMap->addRelation($relation['eventid'], $relation['hostid']);
			}

			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding the related object
		if ($options['selectRelatedObject'] !== null && $options['selectRelatedObject'] != API_OUTPUT_COUNT
				&& $options['object'] != EVENT_OBJECT_AUTOREGHOST) {

			$relationMap = new CRelationMap();
			foreach ($result as $event) {
				$relationMap->addRelation($event['eventid'], $event['objectid']);
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
			}

			$objects = $api->get([
				'output' => $options['selectRelatedObject'],
				$api->pkOption() => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $objects, 'relatedObject');
		}

		// adding alerts
		if ($options['select_alerts'] !== null && $options['select_alerts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'eventid', 'alertid', 'alerts');
			$alerts = API::Alert()->get([
				'output' => $options['select_alerts'],
				'selectMediatypes' => API_OUTPUT_EXTEND,
				'alertids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN
			]);
			$result = $relationMap->mapMany($result, $alerts, 'alerts');
		}

		// adding acknowledges
		if ($options['select_acknowledges'] !== null) {
			if ($options['select_acknowledges'] != API_OUTPUT_COUNT) {
				// create the base query
				$sqlParts = API::getApiService()->createSelectQueryParts('acknowledges', 'a', [
					'output' => $this->outputExtend($options['select_acknowledges'],
						['acknowledgeid', 'eventid', 'clock', 'userid']
					),
					'filter' => ['eventid' => $eventIds]
				]);
				$sqlParts['order'][] = 'a.clock DESC';

				$acknowledges = DBFetchArrayAssoc(DBselect($this->createSelectQueryFromParts($sqlParts)), 'acknowledgeid');

				// if the user data is requested via extended output or specified fields, join the users table
				$userFields = ['alias', 'name', 'surname'];
				$requestUserData = [];
				foreach ($userFields as $userField) {
					if ($this->outputIsRequested($userField, $options['select_acknowledges'])) {
						$requestUserData[] = $userField;
					}
				}

				if ($requestUserData) {
					$users = API::User()->get([
						'output' => $requestUserData,
						'userids' => zbx_objectValues($acknowledges, 'userid'),
						'preservekeys' => true
					]);

					foreach ($acknowledges as &$acknowledge) {
						if (array_key_exists($acknowledge['userid'], $users)) {
							$acknowledge = array_merge($acknowledge, $users[$acknowledge['userid']]);
						}
					}
					unset($acknowledge);
				}

				$relationMap = $this->createRelationMap($acknowledges, 'eventid', 'acknowledgeid');
				$acknowledges = $this->unsetExtraFields($acknowledges, ['eventid', 'acknowledgeid', 'clock', 'userid'],
					$options['select_acknowledges']
				);
				$result = $relationMap->mapMany($result, $acknowledges, 'acknowledges');
			}
			else {
				$acknowledges = DBFetchArrayAssoc(DBselect(
					'SELECT COUNT(a.acknowledgeid) AS rowscount,a.eventid'.
						' FROM acknowledges a'.
						' WHERE '.dbConditionInt('a.eventid', $eventIds).
						' GROUP BY a.eventid'
				), 'eventid');
				foreach ($result as &$event) {
					if ((isset($acknowledges[$event['eventid']]))) {
						$event['acknowledges'] = $acknowledges[$event['eventid']]['rowscount'];
					}
					else {
						$event['acknowledges'] = 0;
					}
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
				'filter' => ['eventid' => $eventIds]
			];
			$tags = DBselect(DB::makeSql('event_tag', $tags_options));

			foreach ($result as &$event) {
				$event['tags'] = [];
			}
			unset($event);

			while ($tag = DBfetch($tags)) {
				$event = &$result[$tag['eventid']];

				unset($tag['eventtagid'], $tag['eventid']);
				$event['tags'][] = $tag;
			}
			unset($event);
		}

		return $result;
	}

	/**
	 * Returns the list of unique tag filters.
	 *
	 * @param array $usrgrpids
	 *
	 * @return array
	 */
	public static function getTagFilters(array $usrgrpids) {
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
	 * Add sql parts related to tag-based permissions.
	 *
	 * @param array $usrgrpids
	 * @param array $sqlParts
	 * @param int   $value
	 *
	 * @return string
	 */
	protected static function addTagFilterSqlParts(array $usrgrpids, array $sqlParts, $value) {
		$tag_filters = self::getTagFilters($usrgrpids);

		if (!$tag_filters) {
			return $sqlParts;
		}

		$sqlParts['from']['f'] = 'functions f';
		$sqlParts['from']['i'] = 'items i';
		$sqlParts['from']['hg'] = 'hosts_groups hg';
		$sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
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
				$conditions[] = dbConditionString('et.tag', $tags);
			}
			$parenthesis = $tags || count($tag_values) > 1;

			foreach ($tag_values as $tag => $values) {
				$condition = 'et.tag='.zbx_dbstr($tag).' AND '.dbConditionString('et.value', $values);
				$conditions[] = $parenthesis ? '('.$condition.')' : $condition;
			}

			$conditions = (count($conditions) > 1) ? '('.implode(' OR ', $conditions).')' : $conditions[0];

			$tag_conditions[] = 'hg.groupid='.zbx_dbstr($groupid).' AND '.$conditions;
		}

		if ($tag_conditions) {
			if ($value == TRIGGER_VALUE_TRUE) {
				$sqlParts['from']['et'] = 'event_tag et';
				$sqlParts['where']['e-et'] = 'e.eventid=et.eventid';
			}
			else {
				$sqlParts['from']['er'] = 'event_recovery er';
				$sqlParts['from']['et'] = 'event_tag et';
				$sqlParts['where']['e-er'] = 'e.eventid=er.r_eventid';
				$sqlParts['where']['er-et'] = 'er.eventid=et.eventid';
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

		$sqlParts['where'][] = (count($tag_conditions) > 1)
			? '('.implode(' OR ', $tag_conditions).')'
			: $tag_conditions[0];

		return $sqlParts;
	}

	/**
	 * Returns sorted array of events.
	 *
	 * @param array        $events
	 * @param string|array $sortfield
	 * @param string|array $sortorder
	 *
	 * @return array
	 */
	private static function sortResult(array $result, $sortfield, $sortorder) {
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
}
