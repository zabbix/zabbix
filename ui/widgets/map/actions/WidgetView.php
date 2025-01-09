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


namespace Widgets\Map\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMapHelper;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1',
			'current_sysmapid' => 'db sysmaps.sysmapid',
			'unique_id' => 'string',
			'previous_maps' => 'array'
		]);
	}

	protected function doAction(): void {
		$previous_map = null;
		$sysmapid = null;
		$error = null;

		// Get previous map.
		if ($this->hasInput('previous_maps')) {
			$previous_maps = array_filter($this->getInput('previous_maps'), 'is_numeric');

			if ($previous_maps) {
				$previous_map = API::Map()->get([
					'output' => ['sysmapid', 'name'],
					'sysmapids' => [array_pop($previous_maps)]
				]);

				$previous_map = reset($previous_map);
			}
		}

		if ($this->hasInput('current_sysmapid')) {
			$sysmapid = $this->getInput('current_sysmapid');
		}
		elseif (array_key_exists('sysmapid', $this->fields_values) && $this->fields_values['sysmapid']) {
			$sysmapid = $this->fields_values['sysmapid'][0];
		}

		$sysmap_data = CMapHelper::get($sysmapid === null ? [] : [$sysmapid],
			['unique_id' => $this->getInput('unique_id')]
		);

		if ($sysmapid === null || $sysmap_data['id'] < 0) {
			$error = _('No permissions to referred object or it does not exist!');
		}

		// Pass variables to view.
		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'sysmap_data' => $sysmap_data ?: [],
			'widget_settings' => [
				'current_sysmapid' => $sysmapid,
				'previous_map' => $previous_map,
				'initial_load' => $this->getInput('initial_load', 1),
				'error' => $error
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
