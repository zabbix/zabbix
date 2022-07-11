<?php declare(strict_types = 0);
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


class CControllerAuditSettingsEdit extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'auditlog_enabled'	=> 'db config.auditlog_enabled',
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$data = [
			'auditlog_enabled' => $this->getInput('auditlog_enabled',
				CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED)
			),
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
