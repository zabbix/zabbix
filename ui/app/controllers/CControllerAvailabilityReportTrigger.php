<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerAvailabilityReportTrigger extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'triggerid' => 'required|array_db triggers.triggerid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot display availability report.'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)) {
			return false;
		}

		$trigger = API::Trigger()->get([
			'countOutput' => true,
			'triggerids' => $this->getInput('triggerid')
		]);

		return (bool) $trigger;
	}

	protected function doAction(): void {
		$trigger = API::Trigger()->get([
			'triggerids' => $this->getInput('triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'expandDescription' => true
		]);

		$data = $trigger
		? [
			'trigger' => $trigger[0],
			'host' => $trigger[0]['hosts'][0]
		]
		: [];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Availability report graph'));
		$this->setResponse($response);
	}
}
