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
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$ret = $this->validateInput(['object', 'fields' => [
			'iconmapid' => [],
			'iconmap' => []
		]]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		$this->iconmap = $this->getInput('iconmap', []) + [
			'name' => '',
			'default_iconid' => 0,
			'mappings' => []
		];

		if ($this->hasInput('iconmapid')) {
			$iconmaps = API::IconMap()->get([
				'output' => ['iconmapid', 'name', 'default_iconid'],
				'selectMappings' => ['inventory_link', 'expression', 'iconid', 'sortorder'],
				'iconmapids' => $this->getInput('iconmapid')
			]);

			if (!$iconmaps) {
				return false;
			}

			$this->iconmap = $iconmaps[0];
		}

		return true;
	}

	protected function doAction() {
		order_result($this->iconmap['mappings'], 'sortorder');
		$this->iconmap['mappings'] = array_values($this->iconmap['mappings']);

		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => IMAGE_TYPE_ICON]
		]);

		order_result($images, 'name');
		$images = array_column($images, 'name', 'imageid');

		$default_imageid = key($images);
		$inventory_list = array_column(getHostInventories(), 'title', 'nr');

		if (!$this->hasInput('iconmapid')) {
			if ($this->iconmap['default_iconid'] == 0) {
				$this->iconmap['default_iconid'] = $default_imageid;
			}

			if (!$this->iconmap['mappings']) {
				$this->iconmap['mappings'] = [[
					'inventory_link' => key($inventory_list),
					'expression' => "",
					'iconid' => $default_imageid,
					'sortorder' => 0
				]];
			}
		}

		$rules = $this->hasInput('iconmapid')
			? (new CFormValidator(CControllerIconMapUpdate::getValidationRules()))->getRules()
			: (new CFormValidator(CControllerIconMapCreate::getValidationRules()))->getRules();

		$data = [
			'iconmapid' => $this->getInput('iconmapid', 0),
			'icon_list' => $images,
			'iconmap' => $this->iconmap,
			'inventory_list' => $inventory_list,
			'default_imageid' => $default_imageid,
			'js_validation_rules' => $rules
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of icon mapping'));

		$this->setResponse($response);
	}
}
