<?php declare(strict_types = 1);
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


class CControllerAuditSettingsUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'auditlog_enabled'	=> 'db config.auditlog_enabled|in 1',
			'hk_audit_mode'		=> 'db config.hk_audit_mode|in 1',
			'hk_audit'			=> 'db config.hk_audit|time_unit '.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))
							->setArgument('action', 'audit.settings.edit')
							->getUrl()
					);
					$response->setFormData($this->getInputAll() + [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '0'
					]);
					CMessageHelper::setErrorTitle(_('Cannot update configuration'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$housekeeping = [CHousekeepingHelper::HK_AUDIT_MODE => $this->getInput('hk_audit_mode', 0)];

		if ($housekeeping[CHousekeepingHelper::HK_AUDIT_MODE] == 1) {
			$housekeeping[CHousekeepingHelper::HK_AUDIT] = $this->getInput('hk_audit');
		}

		$settings = [CSettingsHelper::AUDITLOG_ENABLED => $this->getInput('auditlog_enabled', 0)];

		$result_housekeeping = API::Housekeeping()->update($housekeeping);
		$result_settings = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'audit.settings.edit')
		);

		if ($result_housekeeping && $result_settings) {
			CMessageHelper::setSuccessTitle(_('Configuration updated'));
		}
		else {
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));
		}

		$this->setResponse($response);
	}
}
