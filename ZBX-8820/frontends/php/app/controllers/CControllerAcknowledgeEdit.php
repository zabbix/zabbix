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


class CControllerAcknowledgeEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'eventids' =>	'required|array_db acknowledges.eventid',
			'message' =>	'db acknowledges.message',
			'backurl' =>	'required|string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			// TODO "backurl" validation
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		$events = API::Event()->get([
			'eventids' => $this->getInput('eventids'),
			'countOutput' => true
		]);

		return ($events == count($this->getInput('eventids')));
	}

	protected function doAction() {
		$data = [
			'sid' => $this->getUserSID(),
			'eventids' => $this->getInput('eventids'),
			'message' => $this->getInput('message', ''),
			'backurl' => $this->getInput('backurl')
		];

		if (count($this->getInput('eventids')) == 1) {
			$events = API::Event()->get([
				'output' => [],
				'eventids' => $this->getInput('eventids'),
				'select_acknowledges' => ['clock', 'message', 'alias', 'name', 'surname']
			]);

			if ($events) {
				$data['event'] = [
					'acknowledges' => $events[0]['acknowledges']
				];
				order_result($data['acknowledges'], 'clock', ZBX_SORT_DOWN);
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Alarm acknowledgements'));
		$this->setResponse($response);
	}
}

