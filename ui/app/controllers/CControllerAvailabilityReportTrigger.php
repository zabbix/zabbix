<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CControllerAvailabilityReportTrigger extends CController {

	/**
	 * @var array
	 */
	private $trigger = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'triggerid' => 'required|array_db triggers.triggerid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)) {
			return false;
		}

		$this->trigger = API::Trigger()->get([
			'output' => ['triggerid', 'description'],
			'triggerids' => $this->getInput('triggerid'),
			'selectHosts' => ['name'],
			'expandDescription' => true
		]);

		if (!$this->trigger) {
			return false;
		}

		$this->trigger = reset($this->trigger);

		return true;
	}

	protected function doAction(): void {
		$data = [
			'trigger' => $this->trigger,
			'host_name' => $this->trigger['hosts'][0]['name']
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Availability report graph'));
		$this->setResponse($response);
	}
}
