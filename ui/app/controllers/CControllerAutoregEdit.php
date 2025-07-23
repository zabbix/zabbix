<?php
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


class CControllerAutoregEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$autoreg = API::Autoregistration()->get([
			'output' => ['tls_accept']
		]);

		$data = [
			'tls_in_none' => ($autoreg['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE,
			'tls_in_psk' => ($autoreg['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK,
			'js_validation_rules' => CControllerAutoregUpdate::getValidationRules()
		];

		$data['js_validation_rules'] = (new CFormValidator($data['js_validation_rules']))->getRules();

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Autoregistration'));
		$this->setResponse($response);
	}
}
