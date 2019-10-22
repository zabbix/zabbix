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


class CControllerIconMapDelete extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'iconmapid' => 'required | db icon_map.iconmapid'
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

		return (bool) API::IconMap()->get([
			'output' => [],
			'iconmapids' => (array) $this->getInput('iconmapid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$result = (bool) API::IconMap()->delete((array) $this->getInput('iconmapid'));

		$url = (new CUrl('zabbix.php'));
		if ($result) {
			$url->setArgument('action', 'iconmap.list');
			$response = new CControllerResponseRedirect($url);
			$response->setMessageOk(_('Icon map deleted'));
		}
		else {
			$url->setArgument('action', 'iconmap.edit');
			$url->setArgument('iconmapid', $this->getInput('iconmapid'));
			$response = new CControllerResponseRedirect($url);
			$response->setMessageError(_('Cannot delete icon map'));
		}

		$this->setResponse($response);
	}
}
