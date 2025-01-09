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


namespace Widgets\SystemInfo\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CSystemInfoHelper,
	CWebUser;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'system_info' => CSystemInfoHelper::getData(),
			'info_type' => $this->fields_values['info_type'],
			'user_type' => CWebUser::getType(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($data['system_info']['is_software_update_check_enabled']) {
			$data['show_software_update_check_details'] = $this->fields_values['show_software_update_check_details'];
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
