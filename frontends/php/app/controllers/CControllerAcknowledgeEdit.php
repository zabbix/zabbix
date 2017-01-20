<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
			'acknowledge_type' =>	'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM.','.ZBX_ACKNOWLEDGE_ALL,
			'backurl' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$backurl = $this->getInput('backurl', 'tr_status.php');

			switch (parse_url($backurl, PHP_URL_PATH)) {
				case 'events.php':
				case 'overview.php':
				case 'screenedit.php':
				case 'screens.php':
				case 'slides.php':
				case 'tr_events.php':
				case 'tr_status.php':
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
			'acknowledge_type' => $this->getInput('acknowledge_type', ZBX_ACKNOWLEDGE_SELECTED),
			'backurl' => $this->getInput('backurl', 'tr_status.php'),
			'unack_problem_events_count' => 0,
			'unack_events_count' => 0
		];

		if (count($this->getInput('eventids')) == 1) {
			$events = API::Event()->get([
				'output' => [],
				'eventids' => $this->getInput('eventids'),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'select_acknowledges' => ['clock', 'message', 'alias', 'name', 'surname']
			]);

			if ($events) {
				$data['event'] = [
					'acknowledges' => $events[0]['acknowledges']
				];
				order_result($data['acknowledges'], 'clock', ZBX_SORT_DOWN);
			}
		}

		$events = API::Event()->get([
			'output' => ['objectid', 'acknowledged', 'value'],
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
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
		$response->setTitle(_('Alarm acknowledgements'));
		$this->setResponse($response);
	}
}

