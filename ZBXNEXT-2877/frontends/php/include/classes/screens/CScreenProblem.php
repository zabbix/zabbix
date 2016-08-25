<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

		if (!array_key_exists('groupids', $this->data['filter'])) {
			$this->data['filter']['groupids'] = [];
		}
		if (!array_key_exists('hostids', $this->data['filter'])) {
			$this->data['filter']['hostids'] = [];
		}
		if (!array_key_exists('triggerids', $this->data['filter'])) {
			$this->data['filter']['triggerids'] = [];
		}
		if (!array_key_exists('inventory', $this->data['filter'])) {
			$this->data['filter']['inventory'] = [];
		}
		if (!array_key_exists('tags', $this->data['filter'])) {
			$this->data['filter']['tags'] = [];
		}

		if ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL) {
			CProfile::update('web.problem.timeline.period', $this->data['filter']['period'], PROFILE_TYPE_INT);
			CProfile::update('web.problem.timeline.stime', $this->data['filter']['stime'], PROFILE_TYPE_STR);

			$time = time();

			$this->data['filter']['stime'] = zbxDateToTime($this->data['filter']['stime']);
			if ($this->data['filter']['stime'] > $time - $this->data['filter']['period']) {
				$this->data['filter']['stime'] = $time - $this->data['filter']['period'];
			}
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
	 * @param array  $filter_groupids
	 * @param array  $filter_hostids
	 * @param array  $filter_applicationids
	 * @param string $eventid_till
	 *
	 * @return array
	 */
	private function getDataEvents(array $filter_groupids = null, array $filter_hostids = null,
			array $filter_applicationids = null, array $filter_triggerids = null, $eventid_till = null) {
		$options = [
			'output' => ['eventid', 'objectid', 'clock', 'ns'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'time_from' => $this->data['filter']['stime'],
			'time_till' => $this->data['filter']['stime'] + $this->data['filter']['period'],
			'value' => TRIGGER_VALUE_TRUE,
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'applicationids' => $filter_applicationids,
			'objectids' => $filter_triggerids,
			'eventid_till' => $eventid_till,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $this->config['search_limit'] + 1,
			'preservekeys' => true
		];
		if ($this->data['filter']['severity'] != TRIGGER_SEVERITY_NOT_CLASSIFIED) {
			$options['severities'] = range($this->data['filter']['severity'], TRIGGER_SEVERITY_COUNT - 1);
		}
		if ($this->data['filter']['unacknowledged'] && $this->config['event_ack_enable']) {
			$options['acknowledged'] = false;
		}
		if ($this->data['filter']['tags']) {
			$options['tags'] = $this->data['filter']['tags'];
		}

		return API::Event()->get($options);
	}

	/**
	 * Get problems from "problem" table.
	 *
	 * @param array  $filter_groupids
	 * @param array  $filter_hostids
	 * @param array  $filter_applicationids
	 * @param string $eventid_till
	 *
	 * @return array
	 */
	private function getDataProblems(array $filter_groupids = null, array $filter_hostids = null,
			array $filter_applicationids = null, array $filter_triggerids = null, $eventid_till = null) {
		$options = [
			'output' => ['eventid', 'objectid', 'clock', 'ns'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'recent' => ($this->data['filter']['show'] == TRIGGERS_OPTION_RECENT_PROBLEM),
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'applicationids' => $filter_applicationids,
			'objectids' => $filter_triggerids,
			'eventid_till' => $eventid_till,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $this->config['search_limit'] + 1,
			'preservekeys' => true
		];
		if ($this->data['filter']['severity'] != TRIGGER_SEVERITY_NOT_CLASSIFIED) {
			$options['severities'] = range($this->data['filter']['severity'], TRIGGER_SEVERITY_COUNT - 1);
		}
		if ($this->data['filter']['unacknowledged'] && $this->config['event_ack_enable']) {
			$options['acknowledged'] = false;
		}
		if ($this->data['filter']['age_state'] == 1) {
			$options['time_from'] = time() - $this->data['filter']['age'] * SEC_PER_DAY + 1;
		}
		if ($this->data['filter']['tags']) {
			$options['tags'] = $this->data['filter']['tags'];
		}

		return API::Problem()->get($options);
	}

	/**
	 * Get problems from "problem" table. Return:
	 * [
	 *     'problems' => [...],
	 *     'triggers' => [...]
	 * ]
	 *
	 * @return array
	 */
	private function getData() {
		$filter_groupids = $this->data['filter']['groupids'] ? $this->data['filter']['groupids'] : null;
		$filter_hostids = $this->data['filter']['hostids'] ? $this->data['filter']['hostids'] : null;
		$filter_applicationids = null;
		$filter_triggerids = $this->data['filter']['triggerids'] ? $this->data['filter']['triggerids'] : null;

		if ($this->data['filter']['inventory']) {
			$options = [
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'preservekeys' => true
			];
			foreach ($this->data['filter']['inventory'] as $field) {
				$options['searchInventory'][$field['field']][] = $field['value'];
			}

			$hostids = array_keys(API::Host()->get($options));

			$filter_hostids = ($filter_hostids !== null) ? array_intersect($filter_hostids, $hostids) : $hostids;
		}

		if ($this->data['filter']['application'] !== '') {
			$filter_applicationids = array_keys(API::Application()->get([
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'search' => ['name' => $this->data['filter']['application']],
				'preservekeys' => true
			]));
			$filter_groupids = null;
			$filter_hostids = null;
		}

		if ($this->data['filter']['problem'] !== '') {
			$triggerids = array_keys(API::Trigger()->get([
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'applicationids' => $filter_applicationids,
				'search' => ['description' => $this->data['filter']['problem']],
				'preservekeys' => true
			]));

			$filter_triggerids = ($filter_triggerids !== null)
				? array_intersect($filter_triggerids, $triggerids)
				: $triggerids;
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
			$problems = ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL)
				? $this->getDataEvents($filter_groupids, $filter_hostids, $filter_applicationids, $filter_triggerids,
					$eventid_till
				)
				: $this->getDataProblems($filter_groupids, $filter_hostids, $filter_applicationids, $filter_triggerids,
					$eventid_till
				);

			$end_of_data = (count($problems) < $this->config['search_limit'] + 1);

			if ($problems) {
				$eventid_till = end($problems)['eventid'] - 1;
				$triggerids = [];

				foreach ($problems as $eventid => $problem) {
					if (!array_key_exists($problem['objectid'], $seen_triggerids)) {
						$triggerids[$problem['objectid']] = true;
					}
				}

				if ($triggerids) {
					$seen_triggerids += $triggerids;

					$options = [
						'output' => ['triggerid', 'description', 'expression', 'priority', 'url', 'flags'],
						'selectHosts' => ['hostid', 'name', 'status'],
						'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
						'triggerids' => array_keys($triggerids),
						'monitored' => true,
						'skipDependent' => true,
						'preservekeys' => true
					];
					if ($this->data['filter']['maintenance'] == 0) {
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
		while (count($data['problems']) < $this->config['search_limit'] + 1 && !$end_of_data);

		$data['problems'] = array_slice($data['problems'], 0, $this->config['search_limit'] + 1, true);

		return $data;
	}

	/**
	 * @param array $data
	 * @param array $data['problems']
	 * @param array $data['triggers']
	 *
	 * @return array
	 */
	private function sortData(array $data) {
		if (!$data['problems']) {
			return $data;
		}

		$last_problem = end($data['problems']);
		$data['problems'] = array_slice($data['problems'], 0, $this->config['search_limit'], true);

		switch ($this->data['sort']) {
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
					['field' => 'host', 'order' => $this->data['sortorder']],
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
					['field' => 'priority', 'order' => $this->data['sortorder']],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'problem':
				foreach ($data['problems'] as &$problem) {
					$problem['description'] = $data['triggers'][$problem['objectid']]['description'];
				}
				unset($problem);

				$sort_fields = [
					['field' => 'description', 'order' => $this->data['sortorder']],
					['field' => 'objectid', 'order' => $this->data['sortorder']],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			default:
				$sort_fields = [
					['field' => 'clock', 'order' => $this->data['sortorder']],
					['field' => 'ns', 'order' => $this->data['sortorder']]
				];
		}
		CArrayHelper::sort($data['problems'], $sort_fields);

		$data['problems'][$last_problem['eventid']] = $last_problem;

		return $data;
	}

	/**
	 * @param array $eventids
	 *
	 * @return array
	 */
	private function getExDataEvents(array $eventids) {
		$options = [
			'output' => ['eventid', 'r_eventid'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids
		];
		if ($this->config['event_ack_enable']) {
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
				'output' => ['clock'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		foreach ($events as &$event) {
			$event['r_clock'] = array_key_exists($event['r_eventid'], $r_events)
				? $r_events[$event['r_eventid']]['clock']
				: 0;
		}
		unset($event);

		return $events;
	}

	/**
	 * @param array $eventids
	 *
	 * @return array
	 */
	private function getExDataProblems(array $eventids) {
		$options = [
			'output' => ['eventid', 'r_eventid', 'r_clock'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids,
			'recent' => true
		];
		if ($this->config['event_ack_enable']) {
			$options['selectAcknowledges'] = ['userid', 'clock', 'message', 'action'];
		}

		return API::Problem()->get($options);
	}

	/**
	 * @param array $data
	 * @param array $data['problems']
	 * @param array $data['triggers']
	 *
	 * @return array
	 */
	private function makeData(array $data) {
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
		$data['triggers'] = CMacrosResolverHelper::resolveTriggerUrls($data['triggers']);

		// get additional data
		$eventids = array_keys($data['problems']);

		$problems_data = ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL)
			? $this->getExDataEvents($eventids)
			: $this->getExDataProblems($eventids);

		foreach ($problems_data as $problem_data) {
			$problem = &$data['problems'][$problem_data['eventid']];

			$problem['r_eventid'] = $problem_data['r_eventid'];
			$problem['r_clock'] = $problem_data['r_clock'];
			if ($this->config['event_ack_enable']) {
				$problem['acknowledges'] = $problem_data['acknowledges'];
			}
			$problem['tags'] = $problem_data['tags'];
			unset($problem);
		}

		return $data;
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

		$data = $this->getData();
		$data = $this->sortData($data);

		$paging = getPagingLine($data['problems'], ZBX_SORT_UP, clone $url);

		$data = $this->makeData($data);

		$actions = makeEventsActions(array_keys($data['problems']));
		if ($data['problems']) {
			$triggers_hosts = getTriggersHostsList($data['triggers']);
		}

		if ($this->data['action'] === 'problem.view') {
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
						->onClick("checkAll('".$form->GetName()."', 'all_eventids', 'eventids');")
				))->addClass(ZBX_STYLE_CELL_WIDTH);
			}
			else {
				$header_check_box = null;
			}

			$link = $url
				->setArgument('page', $this->data['page'])
				->getUrl();

			// create table
			$table = (new CTableInfo())
				->setHeader([
					$header_check_box,
					make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder'], $link),
					make_sorting_header(_('Time'), 'clock', $this->data['sort'], $this->data['sortorder'], $link)
						->addClass(ZBX_STYLE_CELL_WIDTH),
					(new CColHeader(_('Recovery time')))->addClass(ZBX_STYLE_CELL_WIDTH),
					_('Status'),
					make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder'], $link),
					make_sorting_header(_('Problem'), 'problem', $this->data['sort'], $this->data['sortorder'], $link),
					_('Duration'),
					$this->config['event_ack_enable'] ? _('Ack') : null,
					_('Actions'),
					_('Tags')
				]);

			if ($this->config['event_ack_enable']) {
				$url->setArgument('uncheck', '1');
				$acknowledges = makeEventsAcknowledges($data['problems'], $url->getUrl());
			}
			$tags = makeEventsTags($data['problems']);
			if ($data['problems']) {
				$triggers_hosts = makeTriggersHostsList($triggers_hosts);
			}

			foreach ($data['problems'] as $problem) {
				$trigger = $data['triggers'][$problem['objectid']];

				$cell_clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']),
						'tr_events.php?triggerid='.$problem['objectid'].'&eventid='.$problem['eventid']);
				$cell_r_clock = $problem['r_eventid'] != 0
					? new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']),
						'tr_events.php?triggerid='.$problem['objectid'].'&eventid='.$problem['r_eventid'])
					: '';

				if ($problem['r_eventid'] != 0) {
					$value = TRIGGER_VALUE_FALSE;
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

					$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
					$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
				}

				$cell_status = new CSpan($value_str);

				// Add colors and blinking to span depending on configuration and trigger parameters.
				addTriggerValueStyle($cell_status, $value,
					($problem['r_eventid'] != 0) ? $problem['r_clock'] : $problem['clock'],
					$this->config['event_ack_enable'] ? (bool) $problem['acknowledges'] : false
				);

				$description = CMacrosResolverHelper::resolveEventDescription(
					$trigger + ['clock' => $problem['clock'], 'ns' => $problem['ns']]
				);

				$table->addRow([
					$this->config['event_ack_enable']
						? new CCheckBox('eventids['.$problem['eventid'].']', $problem['eventid'])
						: null,
					getSeverityCell($trigger['priority'], $this->config, null, $value == TRIGGER_VALUE_FALSE),
					(new CCol($cell_clock))->addClass(ZBX_STYLE_NOWRAP),
					(new CCol($cell_r_clock))->addClass(ZBX_STYLE_NOWRAP),
					$cell_status,
					$triggers_hosts[$trigger['triggerid']],
					(new CSpan($description))
						->setMenuPopup(CMenuPopupHelper::getTrigger($trigger))
						->addClass(ZBX_STYLE_LINK_ACTION),
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock']),
					$this->config['event_ack_enable'] ? $acknowledges[$problem['eventid']] : null,
					array_key_exists($problem['eventid'], $actions)
						? (new CCol($actions[$problem['eventid']]))->addClass(ZBX_STYLE_NOWRAP)
						: '',
					$tags[$problem['eventid']]
				]);
			}

			$footer = null;
			if ($this->config['event_ack_enable']) {
				$footer = new CActionButtonList('action', 'eventids', [
					'acknowledge.edit' => ['name' => _('Bulk acknowledge')]
				]);
			}

			return $this->getOutput($form->addItem([$table, $paging, $footer]), true, $this->data);
		}
		else {
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
					CMacrosResolverHelper::resolveEventDescription(
						$trigger + ['clock' => $problem['clock'], 'ns' => $problem['ns']]
					),
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock']),
					$this->config['event_ack_enable'] ? ($problem['acknowledges'] ? _('Yes') : _('No')) : null,
					array_key_exists($problem['eventid'], $actions)
						? strip_tags($actions[$problem['eventid']])
						: '',
					implode(', ', $tags[$problem['eventid']])
				];
			}

			return zbx_toCSV($csv);
		}
	}
}
