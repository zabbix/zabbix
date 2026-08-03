<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerAcknowledgeRankChange extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventids' =>		'required|array_db acknowledges.eventid',
			'cause_eventid' =>	'db acknowledges.eventid',
			'change_rank' =>	'db acknowledges.action|in '.ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE.','.ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('change_rank') == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM
				&& !$this->hasInput('cause_eventid')) {
			error(_s('Field "%1$s" is mandatory.', 'cause_eventid'));
			$ret = false;
		}

		if (!$ret) {
			$error_title = $this->hasInput('eventids')
				? _n('Cannot update event', 'Cannot update events', count($this->getInput('eventids', [])))
				: _('Cannot update events');

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => $error_title,
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)) {
			return false;
		}

		$eventids = array_flip($this->getInput('eventids'));

		if ($this->hasInput('cause_eventid')) {
			$eventids[$this->getInput('cause_eventid')] = true;
		}

		$events = API::Event()->get([
			'countOutput' => true,
			'eventids' => array_keys($eventids),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
		]);

		return $events == count($eventids);
	}

	protected function doAction(): void {
		$updated_events_count = 0;
		$result = false;

		$eventid_chunks = array_chunk($this->getInput('eventids'), ZBX_DB_MAX_INSERTS);
		$data['action'] = $this->getInput('change_rank');

		if ($data['action'] == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
			$data['cause_eventid'] = $this->getInput('cause_eventid');
		}

		foreach ($eventid_chunks as $eventid_chunk) {
			$data['eventids'] = $eventid_chunk;
			$result = API::Event()->acknowledge($data);

			// Do not continue if event.acknowledge validation fails.
			if (!$result) {
				break;
			}

			$updated_events_count += count($data['eventids']);
		}

		$output = [];

		if ($result) {
			$success = ['title' => _n('Event updated', 'Events updated', $updated_events_count)];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot update event', 'Cannot update events', count($this->getInput('eventids'))),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
