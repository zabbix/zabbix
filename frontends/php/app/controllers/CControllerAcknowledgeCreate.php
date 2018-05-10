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


class CControllerAcknowledgeCreate extends CController {

	protected function checkInput() {
		$fields = [
			'eventids' =>				'required|array_db acknowledges.eventid',
			'message' =>				'db acknowledges.message |flags '.P_CRLF,
			'scope' =>					'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM,
			'change_severity' =>		'db acknowledges.action|in '.
											ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_SEVERITY,
			'severity' =>				'ge '.TRIGGER_SEVERITY_NOT_CLASSIFIED.'|le '.TRIGGER_SEVERITY_COUNT,
			'acknowledge_problem' =>	'db acknowledges.action|in '.
												ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_ACKNOWLEDGE,
			'close_problem' =>			'db acknowledges.action|in '.
											ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_CLOSE,
			'backurl' =>				'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update event'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
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
		$eventids = $this->getInput('eventids');
		$message = $this->getInput('message', '');
		$result = false;

		$data = [
			'action' => ZBX_PROBLEM_UPDATE_NONE
		];

		// Close event(s).
		if ($this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_CLOSE) {
			$data['action'] += ZBX_PROBLEM_UPDATE_CLOSE;
		}

		// Acknowledge event(s).
		if ($this->getInput('acknowledge_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) {
			$data['action'] += ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;
		}

		// Add message.
		if ($message !== '') {
			$data['action'] += ZBX_PROBLEM_UPDATE_MESSAGE;
			$data['message'] = $message;
		}

		// Change severity.
		if ($this->getInput('change_severity', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_SEVERITY) {
			$data['action'] += ZBX_PROBLEM_UPDATE_SEVERITY;
			$data['severity'] = $this->getInput('severity', '');
		}

		// Acknowledge events.
		if ($data['action'] != ZBX_PROBLEM_UPDATE_NONE) {
			// Aacknowledge directly selected events.
			if ($eventids) {
				$data['eventids'] = $eventids;
				$result = API::Event()->acknowledge($data);
			}

			// Acknowledge events that are created from the same trigger if ZBX_ACKNOWLEDGE_PROBLEM has sekected.
			if ($this->getInput('scope', ZBX_ACKNOWLEDGE_SELECTED) == ZBX_ACKNOWLEDGE_PROBLEM) {
				// Get trigger IDs for selected events.
				$events = API::Event()->get([
					'output' => ['eventid', 'objectid'],
					'eventids' => $eventids,
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'preservekeys' => true
				]);
				$triggerids = zbx_objectValues($events, 'objectid');

				while ($result) {
					// Filter unacknowledged events by trigger IDs. Selected events were already acknowledged (and closed).
					$events = API::Event()->get([
						'output' => [],
						'source' => EVENT_SOURCE_TRIGGERS,
						'object' => EVENT_OBJECT_TRIGGER,
						'objectids' => $triggerids,
						'filter' => [
							'acknowledged' => EVENT_NOT_ACKNOWLEDGED,
							'value' => TRIGGER_VALUE_TRUE
						],
						'preservekeys' => true,
						'limit' => ZBX_DB_MAX_INSERTS
					]);

					if ($events) {
						// Remove perviously updated events.
						foreach ($eventids as $i => $eventid) {
							if (array_key_exists($eventid, $events)) {
								unset($eventids[$i]);
							}
						}

						$data['eventids'] = array_keys($events);
						$result = API::Event()->acknowledge($data);
					}
					else {
						break;
					}
				}
			}
		}

		if ($result) {
			$response = new CControllerResponseRedirect($this->getInput('backurl', 'zabbix.php?action=problem.view'));
			$response->setMessageOk(_n('Event updated', 'Events updated', count($eventids)));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_n('Cannot update event', 'Cannot update events', count($eventids)));
		}
		$this->setResponse($response);
	}
}

