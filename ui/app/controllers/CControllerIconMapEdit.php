<?php
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


class CControllerIconMapEdit extends CController {

	/**
	 * @var array
	 */
	private $iconmap = [];

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'iconmapid' => 'db icon_map.iconmapid',
			'iconmap'   => 'array'
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

		if ($this->hasInput('iconmapid')) {
			$iconmaps = API::IconMap()->get([
				'output' => ['iconmapid', 'name', 'default_iconid'],
				'selectMappings' => ['inventory_link', 'expression', 'iconid', 'sortorder'],
				'iconmapids' => $this->getInput('iconmapid')
			]);

			if (!$iconmaps) {
				return false;
			}

			$this->iconmap = $this->getInput('iconmap', []) + $iconmaps[0];
		}
		else {
			$this->iconmap = $this->getInput('iconmap', []) + [
				'name' => '',
				'default_iconid' => 0,
				'mappings' => []
			];
		}

		return true;
	}

	protected function doAction() {
		order_result($this->iconmap['mappings'], 'sortorder');

		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => IMAGE_TYPE_ICON]
		]);

		order_result($images, 'name');
		$images = array_column($images, 'name', 'imageid');

		$default_imageid = key($images);

		if (!$this->hasInput('iconmapid')) {
			$this->iconmap['default_iconid'] = $default_imageid;
		}

		$data = [
			'iconmapid' => $this->getInput('iconmapid', 0),
			'icon_list' => $images,
			'iconmap' => $this->iconmap,
			'inventory_list' => array_column(getHostInventories(), 'title', 'nr'),
			'default_imageid' => $default_imageid
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of icon mapping'));

		$this->setResponse($response);
	}
}
