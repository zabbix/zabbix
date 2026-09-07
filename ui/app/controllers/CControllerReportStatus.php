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


class CControllerReportStatus extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SYSTEM_INFO);
	}

	protected function doAction() {
		$system_info = CSystemInfoHelper::getData();
		$export_data = CSystemInfoHelper::getExportData($system_info);

		$response = new CControllerResponseData([
			'system_info' => $system_info,
			'serverid' => $export_data['serverid']['value'],
			'export_file_name' => 'Zabbix-system-information-'.$export_data['report']['value'].'.json',
			'export_data' => json_encode($export_data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
			),
			'user_type' => CWebUser::getType()
		]);

		$response->setTitle(_('System information'));
		$this->setResponse($response);
	}
}
