<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerIconMapEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'iconmapid'      => 'db icon_map.iconmapid',
			'iconmap'        => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		$this->inventory_list = [];
		$inventory_list = getHostInventories();
		foreach ($inventory_list as $field) {
			$this->inventory_list[$field['nr']] = $field['title'];
		}

		$this->images = [];
		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => IMAGE_TYPE_ICON]
		]);

		order_result($images, 'name');

		foreach ($images as $icon) {
			$this->images[$icon['imageid']] = $icon['name'];
		}

		reset($this->images);
		$this->default_imageid = key($this->images);

		if ($this->hasInput('iconmapid')) {
			$iconmaps = API::IconMap()->get([
				'output' => ['iconmapid', 'name', 'default_iconid'],
				'selectMappings' => ['inventory_link', 'expression', 'iconid', 'sortorder'],
				'iconmapids' => (array) $this->getInput('iconmapid')
			]);

			if (!$iconmaps) {
				return false;
			}

			$this->iconmap = $this->getInput('iconmap', []) + reset($iconmaps);
		}
		else {
			$this->iconmap = $this->getInput('iconmap', []) + [
				'name' => '',
				'default_iconid' => $this->default_imageid,
				'mappings' => []
			];
		}

		return true;
	}

	protected function doAction() {
		order_result($this->iconmap['mappings'], 'sortorder');

		$data = [
			'iconmapid' => $this->getInput('iconmapid', 0),
			'icon_list' => $this->images,
			'iconmap' => $this->iconmap,
			'inventory_list' => $this->inventory_list
		];

		$data['default_imageid'] = $this->default_imageid;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of icon mapping'));

		$this->setResponse($response);
	}
}
