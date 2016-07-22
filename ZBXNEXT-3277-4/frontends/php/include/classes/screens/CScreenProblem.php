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
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param array		$options['data']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);

		$this->data = array_key_exists('data', $options) ? $options['data'] : null;

		if (!array_key_exists('groupids', $this->data['filter'])) {
			$this->data['filter']['groupids'] = [];
		}
		if (!array_key_exists('groups', $this->data['filter'])) {
			$this->data['filter']['groups'] = [];
		}
		if (!array_key_exists('hostids', $this->data['filter'])) {
			$this->data['filter']['hostids'] = [];
		}
		if (!array_key_exists('hosts', $this->data['filter'])) {
			$this->data['filter']['hosts'] = [];
		}
		if (!array_key_exists('inventory', $this->data['filter'])) {
			$this->data['filter']['inventory'] = [];
		}
		if (!array_key_exists('tags', $this->data['filter'])) {
			$this->data['filter']['tags'] = [];
		}
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'problem';

		$config = select_config();
		$now = time();

		$db_problems = API::Problem()->get(
			['output' => ['eventid', 'objectid', 'clock', 'ns', 'r_eventid', 'r_clock']]
			+ ($config['event_ack_enable'] ? ['selectAcknowledges' => ['userid', 'clock', 'message']] : [])
			+ ['selectTags' => ['tag', 'value']]
			+ ['source' => EVENT_SOURCE_TRIGGERS]
			+ ['object' => EVENT_OBJECT_TRIGGER]
			+ ($this->data['filter']['groupids'] ? ['groupids' => $this->data['filter']['groupids']] : [])
			+ ($this->data['filter']['hostids'] ? ['hostids' => $this->data['filter']['hostids']] : [])
			+ ($this->data['filter']['age_state'] == 1
				? ['time_from' => $now - $this->data['filter']['age'] * SEC_PER_DAY + 1]
				: []
			)
			+ ['preservekeys' => true]
		);

		$triggerids = [];
		$ok_events_from = $now - $config['ok_period'];

		foreach ($db_problems as $eventid => $db_problem) {
			if ($db_problem['r_eventid'] != 0 && ($db_problem['r_clock'] < $ok_events_from
					|| $this->data['filter']['show'] == TRIGGERS_OPTION_IN_PROBLEM)) {
				unset($db_problems[$eventid]);
				continue;
			}

			if ($this->data['filter']['unacknowledged'] && $db_problem['acknowledges']) {
				unset($db_problems[$eventid]);
				continue;
			}

			if ($this->data['filter']['tags']) {
				$match = 0;
				foreach ($this->data['filter']['tags'] as $filter_tag) {
					foreach ($db_problem['tags'] as $problem_tag) {
						if ($problem_tag['tag'] === $filter_tag['tag'] && ($filter_tag['value'] === '' ||
								mb_stripos($problem_tag['value'], $filter_tag['value']) !== false)) {
							$match++;
							break;
						}
					}
				}
				if ($match != count($this->data['filter']['tags'])) {
					unset($db_problems[$eventid]);
					continue;
				}
			}

			$triggerids[$db_problem['objectid']] = true;
		}

		if ($triggerids) {
			if ($this->data['filter']['application'] !== '') {
				$db_applications = API::Application()->get(
					['output' => []]
					+ ($this->data['filter']['groupids'] ? ['groupids' => $this->data['filter']['groupids']] : [])
					+ ($this->data['filter']['hostids'] ? ['hostids' => $this->data['filter']['hostids']] : [])
					+ ['search' => ['name' => $this->data['filter']['application']]]
					+ ['preservekeys' => true]
				);
			}

			if ($this->data['filter']['inventory']) {
				$inventory = [];
				foreach ($this->data['filter']['inventory'] as $field) {
					$inventory[$field['field']][] = $field['value'];
				}

				$db_hosts = API::Host()->get(
					['output' => []]
					+ ($this->data['filter']['groupids'] ? ['groupids' => $this->data['filter']['groupids']] : [])
					+ ($this->data['filter']['hostids'] ? ['hostids' => $this->data['filter']['hostids']] : [])
					+ ['searchInventory' => $inventory]
					+ ['preservekeys' => true]
				);
			}

			$db_triggers = API::Trigger()->get(
				['output' => ['triggerid', 'description', 'expression', 'priority', 'url', 'flags']]
				+ ['selectHosts' => ['hostid', 'name', 'status']]
				+ ['selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type']]
				+ ['triggerids' => array_keys($triggerids)]
				+ ($this->data['filter']['inventory'] ? ['hostids' => array_keys($db_hosts)] : [])
				+ ['monitored' => true]
				+ ['skipDependent' => true]
				+ ($this->data['filter']['problem'] !== ''
					? ['search' => ['description' => $this->data['filter']['problem']]]
					: []
				)
				+ ($this->data['filter']['application'] !== ''
					? ['applicationids' => array_keys($db_applications)]
					: []
				)
				+ ($this->data['filter']['severity'] != TRIGGER_SEVERITY_NOT_CLASSIFIED
					? ['min_severity' => $this->data['filter']['severity']]
					: []
				)
				+ ($this->data['filter']['maintenance'] == 0 ? ['maintenance' => false] : [])
				+ ['preservekeys' => true]
			);
			$db_triggers = CMacrosResolverHelper::resolveTriggerUrls($db_triggers);

			foreach ($db_problems as $eventid => $db_problem) {
				if (!array_key_exists($db_problem['objectid'], $db_triggers)) {
					unset($db_problems[$eventid]);
				}
			}

			$triggers_hosts = getTriggersHostsList($db_triggers);
		}

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('fullscreen', $this->data['fullscreen']);

		switch ($this->data['sort']) {
			case 'host':
				$triggers_hosts_list = [];
				foreach ($triggers_hosts as $triggerid => $trigger_hosts) {
					$triggers_hosts_list[$triggerid] = implode(', ', zbx_objectValues($trigger_hosts, 'name'));
				}

				foreach ($db_problems as &$db_problem) {
					$db_problem['host'] = $triggers_hosts_list[$db_problem['objectid']];
				}
				unset($db_problem);

				$sort_fields = [
					['field' => 'host', 'order' => $this->data['sortorder']],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'priority':
				foreach ($db_problems as &$db_problem) {
					$db_problem['priority'] = $db_triggers[$db_problem['objectid']]['priority'];
				}
				unset($db_problem);

				$sort_fields = [
					['field' => 'priority', 'order' => $this->data['sortorder']],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'problem':
				foreach ($db_problems as &$db_problem) {
					$db_problem['description'] = $db_triggers[$db_problem['objectid']]['description'];
				}
				unset($db_problem);

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

		$db_problems = array_slice($db_problems, 0, $config['search_limit'] + 1);

		CArrayHelper::sort($db_problems, $sort_fields);

		$paging = getPagingLine($db_problems, $this->data['sortorder'], clone $url);

		// create table
		$table = (new CTableInfo())
			->setHeader([
				make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
				make_sorting_header(_('Time'), 'clock', $this->data['sort'], $this->data['sortorder'])
					->addClass(ZBX_STYLE_CELL_WIDTH),
				(new CColHeader(_('Recovery time')))->addClass(ZBX_STYLE_CELL_WIDTH),
				_('Status'),
				make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder']),
				make_sorting_header(_('Problem'), 'problem', $this->data['sort'], $this->data['sortorder']),
				_('Duration'),
				$config['event_ack_enable'] ? _('Ack') : null,
				_('Actions'),
				_('Tags')
			]);

		// actions
		$actions = makeEventsActions(array_keys($db_problems));
		if ($config['event_ack_enable']) {
			$url->setArgument('page', $this->data['page']);
			$acknowledges = makeEventsAcknowledges($db_problems, $url->getUrl());
		}
		$tags = makeEventsTags($db_problems);
		if ($db_problems) {
			$triggers_hosts = makeTriggersHostsList($triggers_hosts);
		}

		foreach ($db_problems as $db_problem) {
			$db_trigger = $db_triggers[$db_problem['objectid']];

			$cell_clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $db_problem['clock']),
					'tr_events.php?triggerid='.$db_problem['objectid'].'&eventid='.$db_problem['eventid']);
			$cell_r_clock = $db_problem['r_eventid'] != 0
				? new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $db_problem['r_clock']),
					'tr_events.php?triggerid='.$db_problem['objectid'].'&eventid='.$db_problem['r_eventid'])
				: '';
			$cell_status = new CSpan($db_problem['r_eventid'] != 0 ? _('RESOLVED') : _('PROBLEM'));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle(
				$cell_status,
				$db_problem['r_eventid'] != 0 ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE,
				$db_problem['r_eventid'] != 0 ? $db_problem['r_clock'] : $db_problem['clock'],
				$config['event_ack_enable'] ? (bool) $db_problem['acknowledges'] : false
			);

			$description = CMacrosResolverHelper::resolveEventDescription(
				$db_trigger + ['clock' => $db_problem['clock'], 'ns' => $db_problem['ns']]
			);

			$table->addRow([
				getSeverityCell($db_trigger['priority'], $config, null, $db_problem['r_eventid'] != 0),
				(new CCol($cell_clock))->addClass(ZBX_STYLE_NOWRAP),
				(new CCol($cell_r_clock))->addClass(ZBX_STYLE_NOWRAP),
				$cell_status,
				$triggers_hosts[$db_trigger['triggerid']],
				(new CSpan($description))
					->setMenuPopup(CMenuPopupHelper::getTrigger($db_trigger))
					->addClass(ZBX_STYLE_LINK_ACTION),
				$db_problem['r_eventid'] != 0
					? zbx_date2age($db_problem['clock'], $db_problem['r_clock'])
					: zbx_date2age($db_problem['clock']),
				$config['event_ack_enable'] ? $acknowledges[$db_problem['eventid']] : null,
				array_key_exists($db_problem['eventid'], $actions)
					? (new CCol($actions[$db_problem['eventid']]))->addClass(ZBX_STYLE_NOWRAP)
					: '',
				$tags[$db_problem['eventid']]
			]);
		}

		return $this->getOutput([$table, $paging], true, $this->data);
	}
}
