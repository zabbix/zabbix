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

class CControllerWidgetFavGraphsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_FAV_GRAPHS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$graphs = [];
		$itemids = [];

		foreach (CFavorite::get('web.favorite.graphids') as $favourite) {
			$itemids[$favourite['value']] = true;
		}

		if ($itemids) {
			$db_items = API::Item()->get([
				'output' => ['itemid', 'name'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($itemids),
				'webitems' => true
			]);

			foreach ($db_items as $db_item) {
				$graphs[] = [
					'itemid' => $db_item['itemid'],
					'label' => $db_item['hosts'][0]['name'].NAME_DELIMITER.$db_item['name']
				];
			}
		}

		CArrayHelper::sort($graphs, ['label']);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'graphs' => $graphs,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'allowed_ui_hosts' => $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS),
			'allowed_ui_latest_data' => $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
		]));
	}
}
