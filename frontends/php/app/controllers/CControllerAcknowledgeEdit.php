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
			'eventids' =>					'required|array_db acknowledges.eventid',
			'message' =>					'db acknowledges.message',
			'scope' =>						'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM,
			'change_severity' =>			'db acknowledges.action|in '.
												ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_SEVERITY,
			'severity' =>					'ge '.TRIGGER_SEVERITY_NOT_CLASSIFIED.'|le '.TRIGGER_SEVERITY_COUNT,
			'acknowledge_problem' =>		'db acknowledges.action|in '.
												ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_ACKNOWLEDGE,
			'close_problem' =>				'db acknowledges.action|in '.
												ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_CLOSE,
			'backurl' =>					'string'
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
			'scope' => (int) $this->getInput('scope', ZBX_ACKNOWLEDGE_SELECTED),
			'backurl' => $this->getInput('backurl', 'zabbix.php?action=problem.view'),
			'change_severity' => $this->getInput('change_severity', ZBX_PROBLEM_UPDATE_NONE),
			'severity' => $this->hasInput('severity') ? (int) $this->getInput('severity') : null,
			'acknowledge_problem' => $this->getInput('acknowledge_problem', ZBX_PROBLEM_UPDATE_NONE),
			'close_problem' => $this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE),
			'related_problems_count' => 0,
			'problem_can_be_closed' => false,
			'problem_can_be_acknowledged' => false
		];

		// Select events:
		$events = API::Event()->get([
			'output' => ['eventid', 'objectid', 'acknowledged', 'value', 'r_eventid'],
			'select_acknowledges' => API_OUTPUT_EXTEND, // TODO VM: should be replaced with an array of specific values.
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'preservekeys' => true
		]);

		// Show action list if only one event is requested.
		if (count($events) == 1) {
			$data['event'] = reset($events);
		}

		// Loop through events to figure out what operations should be allowed.
		$close_triggerids = []; // List of triggers with possibly closable problems. To check, if they can be closed.
		$event_triggerids = []; // List of all triggers, to count related_problems_count

		foreach ($events as $event) {
			$event_closed = false;
			$event_triggerids[$event['objectid']] = true;

			// Find if event is in PROBLEM state; Then look if no ZBX_PROBLEM_UPDATE_CLOSE action flag.
			// We can't close events, that already were resolved. We can't close "resolve" events.
			if ($event['r_eventid'] != 0 || $event['value'] == TRIGGER_VALUE_FALSE) {
				$event_closed = true;
				$data['related_problems_count']++; // count selected but closed events.
			}
			elseif ($event['acknowledges']) {
				foreach ($event['acknowledges'] as $acknowledge) {
					if ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) {
						$event_closed = true;
						break;
					}
				}
			}

			if (!$event_closed) {
				$data['problem_can_be_closed'] = true;
				$close_triggerids[$event['objectid']] = true;

				// If event is not acknowledged and is not closed.
				if ($event['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
					$data['problem_can_be_acknowledged'] = true;
				}
			}

			// TODO VM: This check was removed, because we need triggerids for all closable events.
		}

		/**
		 * If there are open problems, check if they can be closed manually.
		 *
		 * At least one of triggers in PROBLEM state should have read-write permissions and should have 'manual_close'
		 * flag set to ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED.
		 *
		 * Even if it is found that problem is not closable due 'manual_close', it won't change the
		 * problem_can_be_acknowledged anymore.
		 *
		 * Additional API request is needed because through Event.get we cannot see which trigger is editable.
		 */
		if ($data['problem_can_be_closed']) {
			$can_be_closed = (bool) API::Trigger()->get([
				'output' => [],
				'triggerids' => array_keys($close_triggerids),
				'filter' => ['manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED],
				'editable' => true,
				'preservekeys' => true
			]);

			if (!$can_be_closed) {
				$data['problem_can_be_closed'] = false;
			}
		}

		// Add number of selected and related problem events to count of selected resolved events.
		$data['related_problems_count'] += API::Problem()->get([
			'countOutput' => true,
			'objectids' => array_keys($event_triggerids)
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Update problem'));
		$this->setResponse($response);
	}
}

