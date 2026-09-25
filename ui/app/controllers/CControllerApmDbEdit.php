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


class CControllerApmDbEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_DATA_SOURCE);
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function doAction(): void {
		$settings = API::Settings()->get(['output' => ['apm_global_db']]);

		$response = new CControllerResponseData([
			'values' => $settings['apm_global_db'],
			'js_validation_rules' => (new CFormValidator(CControllerApmDbUpdate::getValidationRules()))
				->getRules()
		]);
		$response->setTitle(_('APM data source'));
		$this->setResponse($response);
	}
}
