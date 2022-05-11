<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerAutoregEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'tls_accept' =>				'in 0,'.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK),
			'tls_psk_identity' =>		'db config_autoreg_tls.tls_psk_identity',
			'tls_psk' =>				'db config_autoreg_tls.tls_psk',
			'change_psk' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		// get values from the database
		$autoreg = API::Autoregistration()->get([
			'output' => ['tls_accept']
		]);

		$data = [
			'tls_accept' => $autoreg['tls_accept'],
			'tls_psk_identity' => '',
			'tls_psk' => '',
			'change_psk' => !($autoreg['tls_accept'] & HOST_ENCRYPTION_PSK) || $this->hasInput('change_psk')
				|| $this->hasInput('tls_psk_identity')
		];

		// overwrite with input variables
		$this->getInputs($data, ['tls_accept', 'tls_psk_identity', 'tls_psk']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Autoregistration'));
		$this->setResponse($response);
	}
}
