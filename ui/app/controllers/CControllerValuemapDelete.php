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


class CControllerValuemapDelete extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'valuemapids' => 'required | array_db valuemaps.valuemapid'
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

		$valuemaps = API::ValueMap()->get([
			'output' => [],
			'valuemapids' => $this->getInput('valuemapids')
		]);

		return (count($this->getInput('valuemapids')) == count($valuemaps));
	}

	protected function doAction() {
		/** @var array $valuemapids */
		$valuemapids = $this->getInput('valuemapids');
		$result = (bool) API::ValueMap()->delete($valuemapids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'valuemap.list')
			->setArgument('page', CPagerHelper::loadPage('valuemap.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Value map deleted', 'Value maps deleted', count($valuemapids)));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete value map', 'Cannot delete value maps', count($valuemapids)));
		}

		$this->setResponse($response);
	}
}
