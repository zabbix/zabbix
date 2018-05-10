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


class CScreenEvents extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$config = select_config();

		$table = (new CTableInfo())->setHeader([_('Time'), _('Recovery time'), _('Host'), _('Description'), _('Value'),
			_('Severity')
		]);

		$triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid', 'name'],
			'skipDependent' => true,
			'monitored' => true,
			'sortfield' => 'lastchange',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $this->screenitem['elements'],
			'preservekeys' => true
		]);

		$events = API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'objectid', 'clock', 'ns', 'name', 'acknowledged', 'severity'],
			'select_acknowledges' => ['action'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'objectids' => array_keys($triggers),
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $this->screenitem['elements']
		]);

		$sort_clock = [];
		$sort_event = [];

		foreach ($events as $key => $event) {
			if (!array_key_exists($event['objectid'], $triggers)) {
				continue;
			}

			$events[$key]['host'] = reset($triggers[$event['objectid']]['hosts']);
			$sort_clock[$key] = $event['clock'];
			$sort_event[$key] = $event['eventid'];
		}
		array_multisort($sort_clock, SORT_DESC, $sort_event, SORT_DESC, $events);

		if ($events) {
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
				if (array_key_exists($event['r_eventid'], $r_events)) {
					$event['r_clock'] = $r_events[$event['r_eventid']]['clock'];
				}
				else {
					$event['r_clock'] = 0;
				}
			}
			unset($event);
		}

		foreach ($events as $event) {
			$host = $event['host'];

			if ($event['r_eventid'] == 0) {
				$in_closing = false;

				foreach ($event['acknowledges'] as $acknowledge) {
					if ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) {
						$in_closing = true;
						break;
					}
				}

				$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
				$value_clock = $in_closing ? time() : $event['clock'];
			}
			else {
				$value = TRIGGER_VALUE_FALSE;
				$value_str = _('RESOLVED');
				$value_clock = $event['r_clock'];
			}

			$statusSpan = new CSpan($value_str);

			// Add colors span depending on configuration and trigger parameters.
			addTriggerValueStyle($statusSpan, $value, $event['clock'], $event['acknowledged'] == EVENT_ACKNOWLEDGED);

			$table->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				($event['r_eventid'] == 0) ? '' : zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['r_clock']),
				$host['name'],
				new CLink(
					$event['name'],
					'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
				),
				$statusSpan,
				getSeverityCell($event['severity'], $config)
			]);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput((new CUiWidget(uniqid(), [$table, $footer]))->setHeader(_('History of events')));
	}
}
