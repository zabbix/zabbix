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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A class to display problems as a screen element.
 */
class CScreenProblem extends CScreenBase {

	/**
	 * Data
	 *
	 * @var array
	 */
	public $data;

	/**
	 * @var array
	 */
	private $config;

	/**
	 * Init screen data.
	 *
	 * @param array $options
	 * @param array $options['data']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);
		$this->data = array_key_exists('data', $options) ? $options['data'] : null;

		if ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL) {
			$this->data['filter']['period'] = $this->timeline['period'];
			$this->data['filter']['stime'] = zbxDateToTime($this->timeline['stime']);
		}

		$config = select_config();

		$this->config = [
			'event_ack_enable' => $config['event_ack_enable'],
			'search_limit' => $config['search_limit'],
			'severity_color_0' => $config['severity_color_0'],
			'severity_color_1' => $config['severity_color_1'],
			'severity_color_2' => $config['severity_color_2'],
			'severity_color_3' => $config['severity_color_3'],
			'severity_color_4' => $config['severity_color_4'],
			'severity_color_5' => $config['severity_color_5'],
			'severity_name_0' => $config['severity_name_0'],
			'severity_name_1' => $config['severity_name_1'],
			'severity_name_2' => $config['severity_name_2'],
			'severity_name_3' => $config['severity_name_3'],
			'severity_name_4' => $config['severity_name_4'],
			'severity_name_5' => $config['severity_name_5']
		];
	}

	/**
	 * Get problems from "events" table.
	 *
	 * @param array       $options
	 * @param array|null  $options['groupids']
	 * @param array|null  $options['hostids']
	 * @param array|null  $options['applicationids']
	 * @param array|null  $options['objectids']
	 * @param string|null $options['eventid_till']
	 * @param int|null    $options['time_from']
	 * @param int|null    $options['time_till']
	 * @param array       $options['severities']      (optional)
	 * @param bool        $options['acknowledged']    (optional)
	 * @param array       $options['tags']            (optional)
	 * @param int         $options['limit']
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getDataEvents(array $options) {
		return API::Event()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		] + $options);
	}

	/**
	 * Get problems from "problem" table.
	 *
	 * @param array       $options
	 * @param array|null  $options['groupids']
	 * @param array|null  $options['hostids']
	 * @param array|null  $options['applicationids']
	 * @param array|null  $options['objectids']
	 * @param string|null $options['eventid_till']
	 * @param bool        $options['recent']
	 * @param array       $options['severities']      (optional)
	 * @param bool        $options['acknowledged']    (optional)
	 * @param int         $options['time_from']       (optional)
	 * @param array       $options['tags']            (optional)
	 * @param int         $options['limit']
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getDataProblems(array $options) {
		return API::Problem()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		] + $options);
	}

	/**
	 * Get problems from "problem" table. Return:
	 * [
	 *     'problems' => [...],
	 *     'triggers' => [...]
	 * ]
	 *
	 * @param array  $filter
	 * @param array  $filter['groupids']              (optional)
	 * @param array  $filter['exclude_groupids']      (optional)
	 * @param array  $filter['hostids']               (optional)
	 * @param array  $filter['triggerids']            (optional)
	 * @param array  $filter['inventory']             (optional)
	 * @param string $filter['inventory'][]['field']
	 * @param string $filter['inventory'][]['value']
	 * @param string $filter['application']           (optional)
	 * @param string $filter['name']                  (optional)
	 * @param int    $filter['show']                  TRIGGERS_OPTION_*
	 * @param int    $filter['stime']                 (optional) usable together with 'period' and only for
	 *                                                           TRIGGERS_OPTION_ALL
	 * @param int    $filter['period']                (optional) usable together with 'stime' and only for
	 *                                                           TRIGGERS_OPTION_ALL
	 * @param int    $filter['age_state']             (optional) usable together with 'age' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 * @param int    $filter['age']                   (optional) usable together with 'age_state' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 * @param int    $filter['severity']              (optional)
	 * @param array  $filter['severities']            (optional)
	 * @param int    $filter['unacknowledged']        (optional)
	 * @param array  $filter['tags']                  (optional)
	 * @param string $filter['tags'][]['tag']
	 * @param string $filter['tags'][]['value']
	 * @param int    $filter['maintenance']           (optional)
	 * @param array  $config
	 * @param int    $config['search_limit']
	 * @param int    $config['event_ack_enable']
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getData(array $filter, array $config) {
		$filter_groupids = array_key_exists('groupids', $filter) && $filter['groupids'] ? $filter['groupids'] : null;
		$filter_hostids = array_key_exists('hostids', $filter) && $filter['hostids'] ? $filter['hostids'] : null;
		$filter_applicationids = null;
		$filter_triggerids = array_key_exists('triggerids', $filter) && $filter['triggerids']
			? $filter['triggerids']
			: null;

		if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
			if ($filter_hostids === null) {
				// get all groups if no selected groups defined
				if ($filter_groupids === null) {
					$filter_groupids = array_keys(API::HostGroup()->get([
						'output' => [],
						'preservekeys' => true
					]));
				}

				$filter_groupids = array_diff($filter_groupids, $filter['exclude_groupids']);

				// get available hosts
				$filter_hostids = array_keys(API::Host()->get([
					'output' => [],
					'groupids' => $filter_groupids,
					'preservekeys' => true
				]));
			}

			$exclude_hostids = array_keys(API::Host()->get([
				'output' => [],
				'groupids' => $filter['exclude_groupids'],
				'preservekeys' => true
			]));

			$filter_hostids = array_diff($filter_hostids, $exclude_hostids);
		}

		if (array_key_exists('inventory', $filter) && $filter['inventory']) {
			$options = [
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'preservekeys' => true
			];
			foreach ($filter['inventory'] as $field) {
				$options['searchInventory'][$field['field']][] = $field['value'];
			}

			$hostids = array_keys(API::Host()->get($options));

			$filter_hostids = ($filter_hostids !== null) ? array_intersect($filter_hostids, $hostids) : $hostids;
		}

		if (array_key_exists('application', $filter) && $filter['application'] !== '') {
			$filter_applicationids = array_keys(API::Application()->get([
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'search' => ['name' => $filter['application']],
				'preservekeys' => true
			]));
			$filter_groupids = null;
			$filter_hostids = null;
		}

		if (array_key_exists('name', $filter) && $filter['name'] !== '') {
			$filter_groupids = null;
			$filter_hostids = null;
			$filter_applicationids = null;
		}

		$data = [
			'problems' => [],
			'triggers' => []
		];

		$seen_triggerids = [];
		$eventid_till = null;

		do {
			$options = [
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'applicationids' => $filter_applicationids,
				'objectids' => $filter_triggerids,
				'eventid_till' => $eventid_till,
				'limit' => $config['search_limit'] + 1
			];

			if (array_key_exists('name', $filter) && $filter['name'] !== '') {
				$options['search']['name'] = $filter['name'];
			}

			if ($filter['show'] == TRIGGERS_OPTION_ALL) {
				if (array_key_exists('stime', $filter) && array_key_exists('period', $filter)) {
					$options['time_from'] = $filter['stime'];
					$options['time_till'] = $filter['stime'] + $filter['period'];
				}
			}
			else {
				$options['recent'] = ($filter['show'] == TRIGGERS_OPTION_RECENT_PROBLEM);
				if (array_key_exists('age_state', $filter) && array_key_exists('age', $filter)
						&& $filter['age_state'] == 1) {
					$options['time_from'] = time() - $filter['age'] * SEC_PER_DAY + 1;
				}
			}
			if (array_key_exists('severity', $filter) && $filter['severity'] != TRIGGER_SEVERITY_NOT_CLASSIFIED) {
				$options['severities'] = range($filter['severity'], TRIGGER_SEVERITY_COUNT - 1);
			}
			if (array_key_exists('severities', $filter)) {
				$filter_severities = implode(',', $filter['severities']);
				$all_severities = implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1));

				if ($filter_severities !== '' && $filter_severities !== $all_severities) {
					$options['severities'] = $filter['severities'];
				}
			}
			if (array_key_exists('unacknowledged', $filter) && $filter['unacknowledged']
					&& $config['event_ack_enable']) {
				$options['acknowledged'] = false;
			}
			if (array_key_exists('evaltype', $filter)) {
				$options['evaltype'] = $filter['evaltype'];
			}
			if (array_key_exists('tags', $filter) && $filter['tags']) {
				$options['tags'] = $filter['tags'];
			}

			$problems = ($filter['show'] == TRIGGERS_OPTION_ALL)
				? self::getDataEvents($options)
				: self::getDataProblems($options);

			$end_of_data = (count($problems) < $config['search_limit'] + 1);

			if ($problems) {
				$eventid_till = end($problems)['eventid'] - 1;
				$triggerids = [];

				foreach ($problems as $problem) {
					if (!array_key_exists($problem['objectid'], $seen_triggerids)) {
						$triggerids[$problem['objectid']] = true;
					}
				}

				if ($triggerids) {
					$seen_triggerids += $triggerids;

					$options = [
						'output' => ['priority', 'url', 'flags', 'expression', 'comments'],
						'selectHosts' => ['hostid', 'name', 'status'],
						'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
						'triggerids' => array_keys($triggerids),
						'monitored' => true,
						'skipDependent' => true,
						'preservekeys' => true
					];

					if (array_key_exists('details', $filter) && $filter['details'] == 1) {
						$options['output'] = array_merge($options['output'], ['recovery_mode', 'recovery_expression']);
					}
					if (array_key_exists('maintenance', $filter) && $filter['maintenance'] == 0) {
						$options['maintenance'] = false;
					}

					$data['triggers'] += API::Trigger()->get($options);
				}

				foreach ($problems as $eventid => $problem) {
					if (!array_key_exists($problem['objectid'], $data['triggers'])) {
						unset($problems[$eventid]);
					}
				}

				$data['problems'] += $problems;
			}
		}
		while (count($data['problems']) < $config['search_limit'] + 1 && !$end_of_data);

		$data['problems'] = array_slice($data['problems'], 0, $config['search_limit'] + 1, true);

		return $data;
	}

	/**
	 * @param array  $data
	 * @param array  $data['problems']
	 * @param array  $data['triggers']
	 * @param array  $config
	 * @param int    $config['search_limit']
	 * @param string $sort
	 * @param string $sortorder
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function sortData(array $data, array $config, $sort, $sortorder) {
		if (!$data['problems']) {
			return $data;
		}

		$last_problem = end($data['problems']);
		$data['problems'] = array_slice($data['problems'], 0, $config['search_limit'], true);

		switch ($sort) {
			case 'host':
				$triggers_hosts_list = [];
				foreach (getTriggersHostsList($data['triggers']) as $triggerid => $trigger_hosts) {
					$triggers_hosts_list[$triggerid] = implode(', ', zbx_objectValues($trigger_hosts, 'name'));
				}

				foreach ($data['problems'] as &$problem) {
					$problem['host'] = $triggers_hosts_list[$problem['objectid']];
				}
				unset($problem);

				$sort_fields = [
					['field' => 'host', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'priority':
				foreach ($data['problems'] as &$problem) {
					$problem['priority'] = $data['triggers'][$problem['objectid']]['priority'];
				}
				unset($problem);

				$sort_fields = [
					['field' => 'priority', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'name':
				$sort_fields = [
					['field' => 'name', 'order' => $sortorder],
					['field' => 'objectid', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			default:
				$sort_fields = [
					['field' => 'clock', 'order' => $sortorder],
					['field' => 'ns', 'order' => $sortorder]
				];
		}
		CArrayHelper::sort($data['problems'], $sort_fields);

		$data['problems'][$last_problem['eventid']] = $last_problem;

		return $data;
	}

	/**
	 * @param array $eventids
	 * @param array $config
	 * @param int   $config['event_ack_enable']
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getExDataEvents(array $eventids, array $config) {
		$options = [
			'output' => ['eventid', 'r_eventid'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids
		];
		if ($config['event_ack_enable']) {
			$options['select_acknowledges'] = ['userid', 'clock', 'message', 'action'];
		}

		$events = API::Event()->get($options);

		$r_eventids = [];

		foreach ($events as $event) {
			$r_eventids[$event['r_eventid']] = true;
		}
		unset($r_eventids[0]);

		$r_events = $r_eventids
			? API::Event()->get([
				'output' => ['clock', 'correlationid', 'userid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		foreach ($events as &$event) {
			if (array_key_exists($event['r_eventid'], $r_events)) {
				$event['r_clock'] = $r_events[$event['r_eventid']]['clock'];
				$event['correlationid'] = $r_events[$event['r_eventid']]['correlationid'];
				$event['userid'] = $r_events[$event['r_eventid']]['userid'];
			}
			else {
				$event['r_clock'] = 0;
				$event['correlationid'] = 0;
				$event['userid'] = 0;
			}
		}
		unset($event);

		return $events;
	}

	/**
	 * @param array $eventids
	 * @param array $config
	 * @param int   $config['event_ack_enable']
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getExDataProblems(array $eventids, array $config) {
		$options = [
			'output' => ['eventid', 'r_eventid', 'r_clock', 'correlationid', 'userid'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids,
			'recent' => true
		];
		if ($config['event_ack_enable']) {
			$options['selectAcknowledges'] = ['userid', 'clock', 'message', 'action'];
		}

		return API::Problem()->get($options);
	}

	/**
	 * @param array $data
	 * @param array $data['problems']
	 * @param array $data['triggers']
	 * @param array $filter
	 * @param int   $filter['details']
	 * @param int   $filter['show']
	 * @param array $config
	 * @param int   $config['event_ack_enable']
	 * @param bool  $resolve_comments
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function makeData(array $data, array $filter, array $config, $resolve_comments = false) {
		// unset unused triggers
		$triggerids = [];

		foreach ($data['problems'] as $problem) {
			$triggerids[$problem['objectid']] = true;
		}

		foreach ($data['triggers'] as $triggerid => $trigger) {
			if (!array_key_exists($triggerid, $triggerids)) {
				unset($data['triggers'][$triggerid]);
			}
		}

		if (!$data['problems']) {
			return $data;
		}

		// resolve macros
		if ($filter['details'] == 1) {
			foreach ($data['triggers'] as &$trigger) {
				$trigger['expression_html'] = $trigger['expression'];
				$trigger['recovery_expression_html'] = $trigger['recovery_expression'];
			}
			unset($trigger);

			$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
				'html' => true,
				'resolve_usermacros' => true,
				'resolve_macros' => true,
				'sources' => ['expression_html', 'recovery_expression_html']
			]);
		}
		$data['triggers'] = CMacrosResolverHelper::resolveTriggerUrls($data['triggers']);
		if ($resolve_comments) {
			$data['triggers'] = CMacrosResolverHelper::resolveTriggerDescriptions($data['triggers']);
		}

		// get additional data
		$eventids = array_keys($data['problems']);

		$problems_data = ($filter['show'] == TRIGGERS_OPTION_ALL)
			? self::getExDataEvents($eventids, $config)
			: self::getExDataProblems($eventids, $config);

		$correlationids = [];
		$userids = [];

		foreach ($problems_data as $problem_data) {
			$problem = &$data['problems'][$problem_data['eventid']];

			$problem['r_eventid'] = $problem_data['r_eventid'];
			$problem['r_clock'] = $problem_data['r_clock'];
			if ($config['event_ack_enable']) {
				$problem['acknowledges'] = $problem_data['acknowledges'];
			}
			$problem['tags'] = $problem_data['tags'];
			$problem['correlationid'] = $problem_data['correlationid'];
			$problem['userid'] = $problem_data['userid'];

			if ($problem['correlationid'] != 0) {
				$correlationids[$problem['correlationid']] = true;
			}
			if ($problem['userid'] != 0) {
				$userids[$problem['userid']] = true;
			}

			unset($problem);
		}

		$data['correlations'] = $correlationids
			? API::Correlation()->get([
				'output' => ['name'],
				'correlationids' => array_keys($correlationids),
				'preservekeys' => true
			])
			: [];

		$data['users'] = $userids
			? API::User()->get([
				'output' => ['alias', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];

		return $data;
	}

	/**
	 * Add timeline breakpoint to a table if needed.
	 *
	 * @param CTableInfo $table
	 * @param int        $last_clock  timestamp of the previous record
	 * @param int        $clock       timestamp of the current record
	 * @param string     $sortorder
	 *
	 * @static
	 */
	public static function addTimelineBreakpoint(CTableInfo $table, $last_clock, $clock, $sortorder) {
		if ($sortorder === ZBX_SORT_UP) {
			list($clock, $last_clock) = [$last_clock, $clock];
		}

		$breakpoint = null;
		$today = strtotime('today');
		$yesterday = strtotime('yesterday');
		$this_year = strtotime('first day of January '.date('Y', $today));

		if ($last_clock >= $today) {
			if ($clock < $today) {
				$breakpoint = _('Today');
			}
			elseif (strftime('%H', $last_clock) != strftime('%H', $clock)) {
				$breakpoint = strftime('%H:00', $last_clock);
			}
		}
		elseif ($last_clock >= $yesterday) {
			if ($clock < $yesterday) {
				$breakpoint = _('Yesterday');
			}
		}
		elseif ($last_clock >= $this_year && $clock < $this_year) {
			$breakpoint = strftime('%Y', $last_clock);
		}
		elseif (strftime('%Y%m', $last_clock) != strftime('%Y%m', $clock)) {
			$breakpoint = getMonthCaption(strftime('%m', $last_clock));
		}

		if ($breakpoint !== null) {
			$table->addRow((new CRow([
				(new CCol(new CTag('h4', true, $breakpoint)))->addClass(ZBX_STYLE_TIMELINE_DATE),
				(new CCol())
					->addClass(ZBX_STYLE_TIMELINE_AXIS)
					->addClass(ZBX_STYLE_TIMELINE_DOT_BIG),
				(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD),
				(new CCol())->setColSpan($table->getNumCols() - 3)
			]))->addClass(ZBX_STYLE_HOVER_NOBG));
		}
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'problem';

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('fullscreen', $this->data['fullscreen']);

		$data = self::getData($this->data['filter'], $this->config);
		$data = self::sortData($data, $this->config, $this->data['sort'], $this->data['sortorder']);

		$paging = getPagingLine($data['problems'], ZBX_SORT_UP, clone $url);

		$data = self::makeData($data, $this->data['filter'], $this->config);

		if ($data['triggers']) {
			$triggerids = array_keys($data['triggers']);

			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectDependencies' => ['triggerid'],
				'triggerids' => $triggerids,
				'preservekeys' => true
			]);

			foreach ($data['triggers'] as $triggerid => &$trigger) {
				$trigger['dependencies'] = array_key_exists($triggerid, $db_triggers)
					? $db_triggers[$triggerid]['dependencies']
					: [];
			}
			unset($trigger);

			$rw_triggers = API::Trigger()->get([
				'output' => [],
				'triggerids' => $triggerids,
				'editable' => true,
				'preservekeys' => true
			]);

			foreach ($data['triggers'] as $triggerid => &$trigger) {
				$trigger['editable'] = array_key_exists($triggerid, $rw_triggers);
			}
			unset($trigger);
		}

		if ($data['problems']) {
			$triggers_hosts = getTriggersHostsList($data['triggers']);
		}

		if ($this->data['action'] === 'problem.view') {
			$actions = makeEventsActions($data['problems'], true);
			$url_form = clone $url;

			$form = (new CForm('get', 'zabbix.php'))
				->setName('problem')
				->cleanItems()
				->addVar('backurl',
					$url_form
						->setArgument('uncheck', '1')
						->getUrl()
				);

			if ($this->config['event_ack_enable']) {
				$header_check_box = (new CColHeader(
					(new CCheckBox('all_eventids'))
						->onClick("checkAll('".$form->getName()."', 'all_eventids', 'eventids');")
				))->addClass(ZBX_STYLE_CELL_WIDTH);
			}
			else {
				$header_check_box = null;
			}

			$link = $url
				->setArgument('page', $this->data['page'])
				->getUrl();

			$show_timeline = ($this->data['sort'] === 'clock');

			$header_clock =
				make_sorting_header(_('Time'), 'clock', $this->data['sort'], $this->data['sortorder'], $link)
					->addClass(ZBX_STYLE_CELL_WIDTH);

			if ($show_timeline) {
				$header = [
					$header_clock->addClass(ZBX_STYLE_RIGHT),
					(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
					(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
				];
			}
			else {
				$header = [$header_clock];
			}

			// create table
			$table = (new CTableInfo())
				->setHeader(array_merge($header, [
					$header_check_box,
					make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder'], $link),
					(new CColHeader(_('Recovery time')))->addClass(ZBX_STYLE_CELL_WIDTH),
					_('Status'),
					_('Info'),
					make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder'], $link),
					make_sorting_header(_('Problem'), 'name', $this->data['sort'], $this->data['sortorder'], $link),
					_('Duration'),
					$this->config['event_ack_enable'] ? _('Ack') : null,
					_('Actions'),
					_('Tags')
				]));

			if ($this->config['event_ack_enable']) {
				$url->setArgument('uncheck', '1');
				$acknowledges = makeEventsAcknowledges($data['problems'], $url->getUrl());
			}
			$tags = makeEventsTags($data['problems']);
			if ($data['problems']) {
				$triggers_hosts = makeTriggersHostsList($triggers_hosts);
			}

			$last_clock = 0;
			$today = strtotime('today');

			// Make trigger dependencies.
			if ($data['triggers']) {
				$dependencies = makeTriggerDependencies($data['triggers']);
			}

			foreach ($data['problems'] as $eventid => $problem) {
				$trigger = $data['triggers'][$problem['objectid']];

				$cell_clock = ($problem['clock'] >= $today)
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
				$cell_clock = new CCol(new CLink($cell_clock,
					(new CUrl('tr_events.php'))
						->setArgument('triggerid', $problem['objectid'])
						->setArgument('eventid', $problem['eventid'])
				));
				if ($problem['r_eventid'] != 0) {
					$cell_r_clock = ($problem['r_clock'] >= $today)
						? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
						: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
					$cell_r_clock = (new CCol(new CLink($cell_r_clock,
						(new CUrl('tr_events.php'))
							->setArgument('triggerid', $problem['objectid'])
							->setArgument('eventid', $problem['eventid'])
					)))
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass(ZBX_STYLE_RIGHT);
				}
				else {
					$cell_r_clock = '';
				}

				if ($problem['r_eventid'] != 0) {
					$value = TRIGGER_VALUE_FALSE;
					$value_str = _('RESOLVED');
					$value_clock = $problem['r_clock'];
				}
				else {
					$in_closing = false;

					if ($this->config['event_ack_enable']) {
						foreach ($problem['acknowledges'] as $acknowledge) {
							if ($acknowledge['action'] == ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM) {
								$in_closing = true;
								break;
							}
						}
					}

					$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
					$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
					$value_clock = $in_closing ? time() : $problem['clock'];
				}

				$cell_status = new CSpan($value_str);

				// Add colors and blinking to span depending on configuration and trigger parameters.
				addTriggerValueStyle($cell_status, $value, $value_clock,
					$this->config['event_ack_enable'] ? (bool) $problem['acknowledges'] : false
				);

				// Info.
				$info_icons = [];
				if ($problem['r_eventid'] != 0) {
					if ($problem['correlationid'] != 0) {
						$info_icons[] = makeInformationIcon(
							array_key_exists($problem['correlationid'], $data['correlations'])
								? _s('Resolved by correlation rule "%1$s".',
									$data['correlations'][$problem['correlationid']]['name']
								)
								: _('Resolved by correlation rule.')
						);
					}
					elseif ($problem['userid'] != 0) {
						$info_icons[] = makeInformationIcon(
							array_key_exists($problem['userid'], $data['users'])
								? _s('Resolved by user "%1$s".', getUserFullname($data['users'][$problem['userid']]))
								: _('Resolved by user.')
						);
					}
				}

				$options = [
					'description_enabled' => ($trigger['comments'] !== ''
						|| ($trigger['editable'] && $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL))
				];

				$description = array_key_exists($trigger['triggerid'], $dependencies)
					? $dependencies[$trigger['triggerid']]
					: [];
				$description[] = (new CLinkAction($problem['name']))
					->setMenuPopup(CMenuPopupHelper::getTrigger($trigger, null, $options));

				if ($this->data['filter']['details'] == 1) {
					$description[] = BR();

					if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						$description[] = [_('Problem'), ': ', $trigger['expression_html'], BR()];
						$description[] = [_('Recovery'), ': ', $trigger['recovery_expression_html']];
					}
					else {
						$description[] = $trigger['expression_html'];
					}
				}

				if ($show_timeline) {
					if ($last_clock != 0) {
						self::addTimelineBreakpoint($table, $last_clock, $problem['clock'], $this->data['sortorder']);
					}
					$last_clock = $problem['clock'];

					$row = [
						$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
						(new CCol())
							->addClass(ZBX_STYLE_TIMELINE_AXIS)
							->addClass(ZBX_STYLE_TIMELINE_DOT),
						(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
					];
				}
				else {
					$row = [
						$cell_clock
							->addClass(ZBX_STYLE_NOWRAP)
							->addClass(ZBX_STYLE_RIGHT)
					];
				}

				$table->addRow(array_merge($row, [
					$this->config['event_ack_enable']
						? new CCheckBox('eventids['.$problem['eventid'].']', $problem['eventid'])
						: null,
					getSeverityCell($trigger['priority'], $this->config, null, $value == TRIGGER_VALUE_FALSE),
					$cell_r_clock,
					$cell_status,
					makeInformationList($info_icons),
					$triggers_hosts[$trigger['triggerid']],
					$description,
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock']),
					$this->config['event_ack_enable'] ? $acknowledges[$problem['eventid']] : null,
					array_key_exists($eventid, $actions)
						? (new CCol($actions[$eventid]))->addClass(ZBX_STYLE_NOWRAP)
						: '',
					$tags[$problem['eventid']]
				]));
			}

			$footer = null;
			if ($this->config['event_ack_enable']) {
				$footer = new CActionButtonList('action', 'eventids', [
					'acknowledge.edit' => ['name' => _('Bulk acknowledge')]
				], 'problem');
			}

			return $this->getOutput($form->addItem([$table, $paging, $footer]), true, $this->data);
		}
		else {
			$actions = makeEventsActions($data['problems'], true, false);
			$csv = [];

			$csv[] = [
				_('Severity'),
				_('Time'),
				_('Recovery time'),
				_('Status'),
				_('Host'),
				_('Problem'),
				_('Duration'),
				$this->config['event_ack_enable'] ? _('Ack') : null,
				_('Actions'),
				_('Tags')
			];

			$tags = makeEventsTags($data['problems'], false);

			foreach ($data['problems'] as $problem) {
				$trigger = $data['triggers'][$problem['objectid']];

				if ($problem['r_eventid'] != 0) {
					$value_str = _('RESOLVED');
				}
				else {
					$in_closing = false;

					if ($this->config['event_ack_enable']) {
						foreach ($problem['acknowledges'] as $acknowledge) {
							if ($acknowledge['action'] == ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM) {
								$in_closing = true;
								break;
							}
						}
					}

					$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
				}

				$hosts = [];
				foreach ($triggers_hosts[$trigger['triggerid']] as $trigger_host) {
					$hosts[] = $trigger_host['name'];
				}

				$csv[] = [
					getSeverityName($trigger['priority'], $this->config),
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']),
					($problem['r_eventid'] != 0)
						? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock'])
						: '',
					$value_str,
					implode(', ', $hosts),
					$problem['name'],
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock']),
					$this->config['event_ack_enable'] ? ($problem['acknowledges'] ? _('Yes') : _('No')) : null,
					array_key_exists($problem['eventid'], $actions) ? $actions[$problem['eventid']] : '',
					implode(', ', $tags[$problem['eventid']])
				];
			}

			return zbx_toCSV($csv);
		}
	}
}
