<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerIconMapList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$data = [
			'icon_list' => [],
			'inventory_list' => []
		];

		$icon_list = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => IMAGE_TYPE_ICON]
		]);

		foreach ($icon_list as $icon) {
			$data['icon_list'][$icon['imageid']] = $icon['name'];
		}

		$inventory_fields = getHostInventories();
		foreach ($inventory_fields as $field) {
			$data['inventory_list'][$field['nr']] = $field['title'];
		}

		$data['iconmaps'] = API::IconMap()->get([
			'output' => ['mappings', 'name', 'iconmapid'],
			'selectMappings' => ['inventory_link', 'expression', 'iconid'],
			'editable' => true
		]);
		order_result($data['iconmaps'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of icon mapping'));
		$this->setResponse($response);
	}
}
