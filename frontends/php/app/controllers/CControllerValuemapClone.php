<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerValuemapClone extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'valuemapid'   => 'required | db valuemaps.valuemapid',
			'name'         => 'string | db valuemaps.name',
			'mappings'     => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$valuemaps = API::ValueMap()->get([
			'output' => [],
			'valuemapids' => (array) $this->getInput('valuemapid')
		]);

		return (bool) $valuemaps;
	}

	protected function doAction() {
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'valuemap.edit'));

		$form_data = $this->getInputAll();
		unset($form_data['valuemapid']);
		$form_data['form_refresh'] = 1;

		$response->setFormData($form_data);
		$this->setResponse($response);
	}
}
