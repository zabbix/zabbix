<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupServiceTimeValidate extends CController {

	private $ts_from;
	private $ts_to;

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>		'required|int32',
			'type' =>			'required|in '.implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME]),
			'note' =>			'string',
			'from' =>			'string',
			'till' =>			'string',
			'from_week' =>		'in '.implode(',', range(0, 6)),
			'from_hour' =>		'string',
			'from_minute' =>	'string',
			'till_week' =>		'in '.implode(',', range(0, 6)),
			'till_hour' =>		'string',
			'till_minute' =>	'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('type')) {
				case SERVICE_TIME_TYPE_UPTIME:
				case SERVICE_TIME_TYPE_DOWNTIME:
					$this->ts_from = dowHrMinToSec($this->getInput('from_week', ''), $this->getInput('from_hour', ''),
						$this->getInput('from_minute', '')
					);

					if ($this->ts_from === false) {
						error(_('Incorrect service start time.'));
						$ret = false;
					}

					$this->ts_to = dowHrMinToSec($this->getInput('till_week', ''), $this->getInput('till_hour', ''),
						$this->getInput('till_minute', '')
					);

					if ($this->ts_to === false) {
						error(_('Incorrect service end time.'));
						$ret = false;
					}

					break;

				case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
					$parser = new CAbsoluteTimeParser();

					if ($this->hasInput('from') && $parser->parse($this->getInput('from')) == CParser::PARSE_SUCCESS) {
						$this->ts_from = $parser->getDateTime(true)->getTimestamp();

						if (!validateUnixTime($this->ts_from)) {
							error(_('Incorrect service start time.'));
							$ret = false;
						}
					}
					else {
						error(_('Incorrect service start time.'));
						$ret = false;
					}

					if ($this->hasInput('till') && $parser->parse($this->getInput('till')) == CParser::PARSE_SUCCESS) {
						$this->ts_to = $parser->getDateTime(true)->getTimestamp();

						if (!validateUnixTime($this->ts_to)) {
							error(_('Incorrect service end time.'));
							$ret = false;
						}
					}
					else {
						error(_('Incorrect service end time.'));
						$ret = false;
					}

					break;
			}
		}

		if ($ret && $this->ts_from >= $this->ts_to) {
			error(_('Service start time must be less than end time.'));
			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES);
	}

	protected function doAction(): void {
		$data = [
			'row_index' => $this->getInput('row_index'),
			'form' => [
				'type' => $this->getInput('type'),
				'ts_from' => $this->ts_from,
				'ts_to' => $this->ts_to,
				'note' => $this->getInput('note', '')
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
