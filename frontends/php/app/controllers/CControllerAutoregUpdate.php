<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerAutoregUpdate extends CController {

	/**
	 * @var CControllerResponseRedirect
	 */
	private $response;

	protected function init() {
		$this->response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'autoreg.edit')
			->getUrl()
		);

		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'tls_accept' =>				'in '.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK),
			'tls_psk_identity' =>		'db config_autoreg_tls.tls_psk_identity',
			'tls_psk' =>				'db config_autoreg_tls.tls_psk',
			'enable_confirmation' =>	'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$this->response->setFormData($this->getInputAll());
					$this->response->setMessageError(_('Cannot update configuration'));
					$this->setResponse($this->response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$autoreg = ['tls_accept' => $this->getInput('tls_accept', HOST_ENCRYPTION_NONE)];

		if ($this->getInput('tls_psk_identity', '') !== '') {
			$autoreg['tls_psk_identity'] = $this->getInput('tls_psk_identity');
		}

		if ($this->getInput('tls_psk', '') !== '') {
			$autoreg['tls_psk'] = $this->getInput('tls_psk');
		}

		$result = (bool) API::Autoregistration()->update($autoreg);

		if ($result) {
			$this->response->setMessageOk(_('Configuration updated'));
		}
		else {
			$this->response->setFormData($this->getInputAll());
			$this->response->setMessageError(_('Cannot update configuration'));
		}

		$this->setResponse($this->response);
	}
}
