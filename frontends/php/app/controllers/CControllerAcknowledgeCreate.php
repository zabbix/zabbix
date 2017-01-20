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


class CControllerAcknowledgeCreate extends CController {

	protected function checkInput() {
		$fields = [
			'eventids' =>			'required|array_db acknowledges.eventid',
			'message' =>			'db acknowledges.message',
			'acknowledge_type' =>	'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM.','.ZBX_ACKNOWLEDGE_ALL,
			'backurl' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot acknowledge event'));
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
		$acknowledge_type = $this->getInput('acknowledge_type');

		$result = true;

		if ($acknowledge_type == ZBX_ACKNOWLEDGE_PROBLEM || $acknowledge_type == ZBX_ACKNOWLEDGE_ALL) {
			$events = API::Event()->get([
				'output' => ['objectid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => $eventids
			]);

			$triggerids = zbx_objectValues($events, 'objectid');

			$filter = [
				'acknowledged' => EVENT_NOT_ACKNOWLEDGED
			];

			if ($acknowledge_type == ZBX_ACKNOWLEDGE_PROBLEM) {
				$filter['value'] = TRIGGER_VALUE_TRUE;
			}

			while ($result) {
				$events = API::Event()->get([
					'output' => [],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectids' => $triggerids,
					'filter' => $filter,
					'preservekeys' => true,
					'limit' => ZBX_DB_MAX_INSERTS
				]);

				if ($events) {
					foreach ($eventids as $i => $eventid) {
						if (array_key_exists($eventid, $events)) {
							unset($eventids[$i]);
						}
					}

					$result = API::Event()->acknowledge([
						'eventids' => array_keys($events),
						'message' => $this->getInput('message', '')
					]);
				}
				else {
					break;
				}
			}
		}

		if ($result && $eventids) {
			$result = API::Event()->acknowledge([
				'eventids' => $eventids,
				'message' => $this->getInput('message', '')
			]);
		}

		if ($result) {
			$response = new CControllerResponseRedirect($this->getInput('backurl', 'tr_status.php'));
			$response->setMessageOk(_n('Event acknowledged', 'Events acknowledged', count($eventids)));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=acknowledge.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_n('Cannot acknowledge event', 'Cannot acknowledge events', count($eventids)));
		}
		$this->setResponse($response);
	}
}

