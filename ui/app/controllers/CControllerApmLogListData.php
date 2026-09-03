<?php declare(strict_types = 0);
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


class CControllerApmLogListData extends CControllerDataTable {

	protected array $allowed_data_fields = ['timestamp', 'body', 'severity_text', 'severity_number'];

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_LOGS);
	}

	protected function getData(): array
	{
		$rows = [];

		$output = [
			'data_fields' => $this->getDataFields(),
			'rows' => $rows
		];

		$debug_mode = CWebUser::$data['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED;

		if ($debug_mode == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		return $output;
	}
}
