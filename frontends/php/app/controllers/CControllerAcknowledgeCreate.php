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
		$updated_events_count = 0;
		$result = false;

		$data = [
			'action' => ZBX_PROBLEM_UPDATE_NONE
		];

		// Close event(s).
		if ($this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_CLOSE) {
			$data['action'] |= ZBX_PROBLEM_UPDATE_CLOSE;
		}

		// Acknowledge event(s).
		if ($this->getInput('acknowledge_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) {
			$data['action'] |= ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;
		}

		// Add message.
		if ($message !== '') {
			$data['action'] |= ZBX_PROBLEM_UPDATE_MESSAGE;
			$data['message'] = $message;
		}

		// Change severity.
		if ($this->getInput('change_severity', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_SEVERITY) {
			$data['action'] |= ZBX_PROBLEM_UPDATE_SEVERITY;
			$data['severity'] = $this->getInput('severity', '');
		}

		// Acknowledge events.
		if ($data['action'] != ZBX_PROBLEM_UPDATE_NONE) {
			// Acknowledge directly selected events.
			if ($eventids) {
				$data['eventids'] = $eventids;
				$result = API::Event()->acknowledge($data);
				$updated_events_count += count($eventids); // TODO VM: maybe we should count events in $result, if this may be different from $eventids.
			}

			// Acknowledge events that are created from the same trigger if ZBX_ACKNOWLEDGE_PROBLEM is selected.
			if ($this->getInput('scope', ZBX_ACKNOWLEDGE_SELECTED) == ZBX_ACKNOWLEDGE_PROBLEM) {
				// Get trigger IDs for selected events.
				$events = API::Event()->get([
					'output' => ['eventid', 'objectid'],
					'eventids' => $eventids,
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'preservekeys' => true
				]);
				$triggerids = array_unique(zbx_objectValues($events, 'objectid'));

				$last_eventid = '-1'; // keeps eventid of last updated problem in previous update bulk

				while ($result) {
					// Update related events by trigger IDs. Selected events were already updated.
					$problems = API::Problem()->get([
						'output' => ['eventid'],
						'objectids' => $triggerids,
						'preservekeys' => true,
						'order' => 'eventids',
						'eventid_from' => bcadd($last_eventid, 1, 0),
						'limit' => ZBX_DB_MAX_INSERTS
					]);

					// Skip update for selected events
					foreach($eventids as $id => $eventid){
						unset($problems[$eventid]);
					}

					if ($problems) {
						$data['eventids'] = array_keys($problems);
						$result = API::Event()->acknowledge($data);
						$updated_events_count += count($problems);
						// Get last processed eventid, for next iteration to start from it.
						$last_eventid = end($data['eventids']);
					}
					else {
						break;
					}
				}
			}
		}

		if ($result) {
			$response = new CControllerResponseRedirect($this->getInput('backurl', 'zabbix.php?action=problem.view'));
			$response->setMessageOk(_n('Event updated', 'Events updated', $updated_events_count));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(($data['action'] === ZBX_PROBLEM_UPDATE_NONE)
				? _('At least one update operation is mandatory')
				: _n('Cannot update event', 'Cannot update events', $updated_events_count)
			);
		}
		$this->setResponse($response);
	}
}

