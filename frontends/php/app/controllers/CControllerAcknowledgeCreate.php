<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			'countOutput' => true,
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
		]);

		return ($events == count($this->getInput('eventids')));
	}

	protected function doAction() {
		$updated_events_count = 0;
		$result = false;

		$this->close_problems = ($this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_CLOSE);
		$this->change_severity = ($this->getInput('change_severity', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_SEVERITY);
		$this->acknowledge = ($this->getInput('acknowledge_problem', ZBX_PROBLEM_UPDATE_NONE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE);
		$this->new_severity = $this->getInput('severity', '');
		$this->message = $this->getInput('message', '');

		$eventids = $this->groupEventsByActionsAllowed($this->getInput('eventids'));

		while ($eventids['readable']) {
			// Repeate this as long as $eventids['readable'] is clean.
			$data = $this->getAcknowledgeOptions($eventids);

			// Acknowledge events.
			if ($data['action'] != ZBX_PROBLEM_UPDATE_NONE) {
				// Acknowledge directly selected events.
				if ($data['eventids']) {
					$result = API::Event()->acknowledge($data);
					$updated_events_count += count($data['eventids']);
				}

				// Acknowledge events that are created from the same trigger if ZBX_ACKNOWLEDGE_PROBLEM is selected.
				if ($this->getInput('scope', ZBX_ACKNOWLEDGE_SELECTED) == ZBX_ACKNOWLEDGE_PROBLEM) {
					// Get trigger IDs for selected events.
					$events = API::Event()->get([
						'output' => ['objectid'],
						'eventids' => $data['eventids'],
						'source' => EVENT_SOURCE_TRIGGERS,
						'object' => EVENT_OBJECT_TRIGGER
					]);

					$triggerids = zbx_objectValues($events, 'objectid');
					if ($triggerids) {
						// Select related events by trigger IDs. Submitted events were already updated.
						$related_problems = API::Problem()->get([
							'output' => [],
							'objectids' => array_keys(array_flip($triggerids)),
							'preservekeys' => true
						]);

						// Skip update for submitted events.
						foreach ($this->getInput('eventids') as $eventid) {
							unset($related_problems[$eventid]);
						}

						if ($related_problems) {
							$related_problems = array_chunk(array_keys($related_problems), ZBX_DB_MAX_INSERTS);

							foreach ($related_problems as $problems) {
								$problem_eventids = $this->groupEventsByActionsAllowed($problems);

								$test = 5;
								while ($problem_eventids['readable']) {
									$data = $this->getAcknowledgeOptions($problem_eventids);

									// Acknowledge events.
									if ($data['action'] != ZBX_PROBLEM_UPDATE_NONE && $data['eventids']) {
										$result = API::Event()->acknowledge($data);
										$updated_events_count += count($data['eventids']);
									}
									else {
										break;
									}
								}
							}
						}
					}
				}
			}
			else {
				break;
			}
		}

		if ($result) {
			$response = new CControllerResponseRedirect($this->getInput('backurl', 'zabbix.php?action=problem.view'));
			$response->setMessageOk(_n('Event updated', 'Events updated', $updated_events_count));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(($data['action'] == ZBX_PROBLEM_UPDATE_NONE)
				? _('At least one update operation is mandatory')
				: _n('Cannot update event', 'Cannot update events', $updated_events_count)
			);
		}
		$this->setResponse($response);
	}

	/**
	 * Function returns an array for event.acknowledge API method, containing a list of eventids and specific 'action'
	 * flag to perform for list of eventids returned. Function will also clean utilized eventids from $eventids array.
	 *
	 * @param array $eventids
	 * @param array $eventids['closable']            Event ids that user is allowed to close manually.
	 * @param array $eventids['editable']            Event ids that user is allowed to make changes.
	 * @param array $eventids['acknowledgeable']     Event ids that user is allowed to make acknowledgement.
	 * @param array $eventids['readable']            Event ids that user is allowed to read.
	 *
	 * @return array
	 */
	protected function getAcknowledgeOptions(array &$eventids) {
		$data = [
			'action' => ZBX_PROBLEM_UPDATE_NONE,
			'eventids' => []
		];

		if ($this->close_problems && $eventids['closable']) {
			$data['action'] |= ZBX_PROBLEM_UPDATE_CLOSE;
			$data['eventids'] = $eventids['closable'];
			$eventids['closable'] = [];
		}

		if ($this->change_severity && $eventids['editable']) {
			$selected_eventids = $data['eventids']
				? array_intersect($eventids['editable'], $data['eventids'])
				: $eventids['editable'];

			$data['action'] |= ZBX_PROBLEM_UPDATE_SEVERITY;
			$data['severity'] = $this->new_severity;
			$data['eventids'] = array_merge($data['eventids'], $selected_eventids);
			$eventids['editable'] = array_diff($eventids['editable'], $data['eventids']);
		}

		if ($this->acknowledge && $eventids['acknowledgeable']) {
			$selected_eventids = $data['eventids']
				? array_intersect($eventids['acknowledgeable'], $data['eventids'])
				: $eventids['acknowledgeable'];

			$data['action'] |= ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;
			$data['eventids'] = array_merge($data['eventids'], $selected_eventids);
			$eventids['acknowledgeable'] = array_diff($eventids['acknowledgeable'], $data['eventids']);
		}

		if ($this->message !== '' && $eventids['readable']) {
			$selected_eventids = $data['eventids']
				? array_intersect($eventids['readable'], $data['eventids'])
				: $eventids['readable'];

			$data['action'] |= ZBX_PROBLEM_UPDATE_MESSAGE;
			$data['message'] = $this->message;
			$data['eventids'] = array_merge($data['eventids'], $selected_eventids);
		}

		$eventids['readable'] = array_diff($eventids['readable'], $data['eventids']);
		$data['eventids'] = array_keys(array_flip($data['eventids']));

		return $data;
	}

	/**
	 * Function groups eventids according the actions user can perform for each of event.
	 * Following groups of eventids are made:
	 *  - closable events (events are writable + configured to be closed manually + not closed before);
	 *  - editable events (events are writable);
	 *  - acknowledgeable (events are not yet acknowledged);
	 *  - readable events (events that user has at least read permissions).
	 *
	 * @param array $eventids    Eventids to group.
	 *
	 * @param array
	 */
	protected function groupEventsByActionsAllowed(array $eventids = []) {
		$eventid_groups = [
			'closable' => [],
			'editable' => [],
			'acknowledgeable' => [],
			'readable' => []
		];

		$events = API::Event()->get([
			'output' => ['objectid', 'acknowledged', 'r_eventid'],
			'select_acknowledges' => $this->close_problems ? ['action'] : null,
			'selectRelatedObject' => $this->close_problems ? ['manual_close'] : null,
			'eventids' => $eventids,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'preservekeys' => true
		]);

		$editable_triggers = ($events && ($this->change_severity || $this->close_problems))
			? API::Trigger()->get([
				'output' => [],
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		foreach ($events as $eventid => $event) {
			if ($this->close_problems && $this->isEventClosable($event, $editable_triggers)) {
				$eventid_groups['closable'][] = $eventid;
			}

			if ($this->change_severity && array_key_exists($event['objectid'], $editable_triggers)) {
				$eventid_groups['editable'][] = $eventid;
			}

			if ($this->acknowledge && $event['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
				$eventid_groups['acknowledgeable'][] = $eventid;
			}

			$eventid_groups['readable'][] = $eventid;
		}

		return $eventid_groups;
	}

	/**
	 * Checks if events can be closed manually.
	 *
	 * @param array $event                                     Event object.
	 * @param array $event['r_eventid']                        OK event id. 0 if not resolved.
	 * @param array $event['acknowledges']                     List of problem updates.
	 * @param array $event['relatedObject']['manual_close']    Trigger's manual_close configuration.
	 * @param array $event['acknowledges'][]['action']         Action performed in update.
	 * @param array $editable_triggers                         List of editable triggers.
	 *
	 * @return bool
	 */
	protected function isEventClosable(array $event, array $editable_triggers) {
		if ($event['relatedObject']['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
				|| !array_key_exists($event['objectid'], $editable_triggers)
				|| bccomp($event['r_eventid'], '0') > 0) {
			return false;
		}
		else {
			foreach ($event['acknowledges'] as $acknowledge) {
				if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
					return false;
				}
			}
		}

		return true;
	}
}
