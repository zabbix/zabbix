<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\FavMaps\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CFavorite,
	CRoleHelper;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$maps = [];
		$mapids = [];

		foreach (CFavorite::get('web.favorite.sysmapids') as $favorite) {
			$mapids[$favorite['value']] = true;
		}

		if ($mapids) {
			$db_maps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => array_keys($mapids)
			]);

			foreach ($db_maps as $db_map) {
				$maps[] = [
					'sysmapid' => $db_map['sysmapid'],
					'label' => $db_map['name']
				];
			}
		}

		CArrayHelper::sort($maps, ['label']);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget !== null ? $this->widget->getDefaultName() : ''),
			'maps' => $maps,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'allowed_ui_maps' => $this->checkAccess(CRoleHelper::UI_MONITORING_MAPS)
		]));
	}
}
