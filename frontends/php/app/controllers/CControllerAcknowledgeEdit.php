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


class CControllerAcknowledgeEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'eventids' =>			'required|array_db acknowledges.eventid',
			'message' =>			'db acknowledges.message',
			'acknowledge_type' =>	'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM,
			'close_problem' =>		'db acknowledges.action|in '.
										ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_CLOSE,
			'backurl' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$backurl = $this->getInput('backurl', 'zabbix.php?action=problem.view');

			switch (parse_url($backurl, PHP_URL_PATH)) {
				case 'overview.php':
				case 'screenedit.php':
				case 'screens.php':
				case 'slides.php':
				case 'tr_events.php':
				case 'zabbix.php':
					break;

				default:
					$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		$events = API::Event()->get([
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'countOutput' => true
		]);

		return ($events == count($this->getInput('eventids')));
	}

	protected function doAction() {
		$data = [
			'sid' => $this->getUserSID(),
			'eventids' => $this->getInput('eventids'),
			'message' => $this->getInput('message', ''),
			'close_problem' => $this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE),
			'acknowledge_type' => $this->getInput('acknowledge_type', ZBX_ACKNOWLEDGE_SELECTED),
			'backurl' => $this->getInput('backurl', 'zabbix.php?action=problem.view'),
			'unack_problem_events_count' => 0,
			'unack_events_count' => 0
		];

		if (count($this->getInput('eventids')) == 1) {
			$events = API::Event()->get([
				'output' => [],
				'select_acknowledges' => ['clock', 'message', 'action', 'alias', 'name', 'surname'],
				'eventids' => $this->getInput('eventids'),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER
			]);

			if ($events) {
				$data['event'] = [
					'acknowledges' => $events[0]['acknowledges']
				];
				order_result($data['acknowledges'], 'clock', ZBX_SORT_DOWN);
			}
		}

		$events = API::Event()->get([
			'output' => ['eventid', 'objectid', 'acknowledged', 'value'],
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'preservekeys' => true
		]);

		$triggerids = [];

		foreach ($events as $event) {
			if ($event['acknowledged'] == EVENT_ACKNOWLEDGED) {
				$data['unack_problem_events_count']++;
				$data['unack_events_count']++;
			}
			elseif ($event['value'] == TRIGGER_VALUE_FALSE) {
				$data['unack_problem_events_count']++;
			}
			$triggerids[$event['objectid']] = true;
		}

		$triggerids = array_keys($triggerids);

		$event_cond = false;
		$data['close_problem_chbox'] = false;

		// At least one trigger should have RW permissions and should be allowed manual close.
		$trigger_cond = (bool) API::Trigger()->get([
			'output' => [],
			'triggerids' => $triggerids,
			'filter' => ['manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED],
			'editable' => true,
			'preservekeys' => true
		]);

		// Get events in problem state with acknowledges.
		$problems_events = API::Event()->get([
			'output' => ['r_eventid'],
			'select_acknowledges' => ['action'],
			'eventids' => array_keys($events),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'preservekeys' => true
		]);

		// At least one event should not be closed.
		foreach ($problems_events as $problem_event) {
			// Check if it was closed by event recovery.
			if ($problem_event['r_eventid'] != 0) {
				continue;
			}

			$event_closed = false;

			if ($problem_event['acknowledges']) {
				foreach ($problem_event['acknowledges'] as $acknowledge) {
					if ($acknowledge['action'] == ZBX_PROBLEM_UPDATE_CLOSE) {
						$event_closed = true;
						break;
					}
				}

				if (!$event_closed) {
					$event_cond = true;
					break;
				}
			}
			else {
				// No acknowledges yet, so event is still open.
				$event_cond = true;
				break;
			}
		}

		/*
		 * Show checkbox as enabled if trigger conditions (has permissions and allowed to close) and
		 * event conditions (problem state and not closed) are both set to true. Otherwise checkbox is disabled.
		 */
		if ($trigger_cond && $event_cond) {
			$data['close_problem_chbox'] = true;
		}

		$data['unack_problem_events_count'] += API::Event()->get([
			'countOutput' => true,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectids' => $triggerids,
			'filter' => [
				'acknowledged' => EVENT_NOT_ACKNOWLEDGED,
				'value' => TRIGGER_VALUE_TRUE
			]
		]);

		$data['unack_events_count'] += API::Event()->get([
			'countOutput' => true,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectids' => $triggerids,
			'filter' => [
				'acknowledged' => EVENT_NOT_ACKNOWLEDGED
			]
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event acknowledgements'));
		$this->setResponse($response);
	}
}

