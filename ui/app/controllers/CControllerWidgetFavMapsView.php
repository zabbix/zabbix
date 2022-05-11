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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetFavMapsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_FAV_MAPS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$maps = [];
		$mapids = [];

		foreach (CFavorite::get('web.favorite.sysmapids') as $favourite) {
			$mapids[$favourite['value']] = true;
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
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes($this->getContext())[WIDGET_FAV_MAPS]),
			'maps' => $maps,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'allowed_ui_maps' => $this->checkAccess(CRoleHelper::UI_MONITORING_MAPS)
		]));
	}
}
