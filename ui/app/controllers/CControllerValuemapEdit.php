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


class CControllerValuemapEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'valuemapid'   => 'db valuemaps.valuemapid',
			'name'         => 'string | db valuemaps.name',
			'mappings'     => 'array',
			'form_refresh' => '',
			'page'         => 'ge 1'
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

		if ($this->hasInput('valuemapid')) {
			$valuemaps = API::ValueMap()->get([
				'output'         => ['valuemapid', 'name'],
				'valuemapids'    => (array) $this->getInput('valuemapid'),
				'selectMappings' => ['value', 'newvalue']
			]);

			if (!$valuemaps) {
				return false;
			}

			if ($this->getInput('form_refresh', 0) != 0) {
				$this->valuemap = [
					'mappings'       => $this->getInput('mappings', []),
					'name'           => $this->getInput('name', ''),
					'valuemapid'     => $this->getInput('valuemapid'),
					'valuemap_count' => 0
				];
			}
			else {
				$this->valuemap = reset($valuemaps);
				order_result($this->valuemap['mappings'], 'value');
			}
		}
		else {
			$this->valuemap = [
				'name'       => '',
				'mappings'   => [],
				'valuemapid' => 0
			];
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'mappings'       => $this->getInput('mappings', $this->valuemap['mappings']),
			'name'           => $this->getInput('name', $this->valuemap['name']),
			'valuemapid'     => $this->getInput('valuemapid', $this->valuemap['valuemapid']),
			'valuemap_count' => 0
		];

		if ($data['valuemapid'] != 0) {
			$data['valuemap_count'] += API::Item()->get([
				'countOutput' => true,
				'webitems'    => true,
				'filter'      => ['valuemapid' => $data['valuemapid']]
			]);

			$data['valuemap_count'] += API::ItemPrototype()->get([
				'countOutput' => true,
				'filter'      => ['valuemapid' => $data['valuemapid']]
			]);
		}

		if (!$data['mappings']) {
			$data['mappings'][] = ['value' => '', 'newvalue' => ''];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of value mapping'));
		$this->setResponse($response);
	}
}
