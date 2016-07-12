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
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'problem';

		$sort_field = $this->data['sort'];
		$sort_order = $this->data['sortorder'];

		$config = select_config();

		$db_problems = API::Problem()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'r_eventid', 'r_clock'],
			'selectAcknowledges' => $config['event_ack_enable'] ? ['userid', 'clock', 'message'] : null,
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
//			'sortfield' => $sort_field,
//			'sortorder' => $sort_order,
			'preservekeys' => true
		]);

		$triggerids = [];

		foreach ($db_problems as $db_problem) {
			$triggerids[$db_problem['objectid']] = true;
		}

		if ($triggerids) {
			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'priority', 'url', 'flags'],
				'selectHosts' => ['hostid', 'name', 'status'],
				'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
				'triggerids' => array_keys($triggerids),
				'preservekeys' => true
			]);
			$db_triggers = CMacrosResolverHelper::resolveTriggerUrls($db_triggers);

			$triggers_host_list = getTriggersHostList($db_triggers);
		}

		// create table
		$table = (new CTableInfo())
			->setHeader([
				_('Severity'),
				_('Time'),
				_('Recovery time'),
				_('Status'),
				_('Host'),
				_('Problem'),
				_('Duration'),
				$config['event_ack_enable'] ? _('Ack') : null,
				_('Actions'),
				_('Tags')
			]);

		// actions
		$actions = makeEventsActions(array_keys($db_problems));
		if ($config['event_ack_enable']) {
			$acknowledges = makeEventsAcknowledges($db_problems, 'zabbix.php?action=problem.view');
		}
		$tags = makeEventsTags($db_problems);

		foreach ($db_problems as $db_problem) {
			if (!array_key_exists($db_problem['objectid'], $db_triggers)) {
				continue;
			}

			$db_trigger = $db_triggers[$db_problem['objectid']];

			$status_cell = new CSpan($db_problem['r_eventid'] != 0 ? _('RESOLVED') : _('PROBLEM'));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle(
				$status_cell,
				$db_problem['r_eventid'] != 0 ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE,
				$db_problem['r_eventid'] != 0 ? $db_problem['r_clock'] : $db_problem['clock'],
				$config['event_ack_enable'] ? (bool) $db_problem['acknowledges'] : false
			);

			$table->addRow([
				getSeverityCell($db_trigger['priority'], $config, null, $db_problem['r_eventid'] != 0),
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $db_problem['clock']),
				$db_problem['r_eventid'] != 0 ? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $db_problem['r_clock']) : '',
				$status_cell,
				$triggers_host_list[$db_trigger['triggerid']],
				(new CSpan(CMacrosResolverHelper::resolveEventDescription($db_trigger + ['clock' => $db_problem['clock'], 'ns' => $db_problem['ns']])))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setMenuPopup(CMenuPopupHelper::getTrigger($db_trigger)),
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

		return $this->getOutput($table, true, $this->data);
	}
}
