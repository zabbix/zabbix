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


class CControllerAuditSettingsEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'auditlog_enabled'	=> 'db config.auditlog_enabled',
			'auditlog_mode'		=> 'db config.auditlog_mode',
			'hk_audit_mode'		=> 'db config.hk_audit_mode',
			'hk_audit'			=> 'db config.hk_audit'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG);
	}

	protected function doAction(): void {
		$data = [
			'auditlog_enabled' => $this->getInput('auditlog_enabled',
				CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED)
			),
			'auditlog_mode' => $this->getInput('auditlog_mode', CSettingsHelper::get(CSettingsHelper::AUDITLOG_MODE)),
			'hk_audit_mode' => $this->getInput('hk_audit_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_AUDIT_MODE
			)),
			'hk_audit' => $this->getInput('hk_audit', CHousekeepingHelper::get(CHousekeepingHelper::HK_AUDIT))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of audit log'));
		$this->setResponse($response);
	}
}
