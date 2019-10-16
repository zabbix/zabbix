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


class CControllerIconMapUpdate extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'iconmapid' => 'db icon_map.iconmapid',
			'iconmap'   => 'required | array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doUpdateAction() {
		$iconmap = (array) $this->getInput('iconmap') + ['mappings' => []];
		$iconmap['iconmapid'] = $this->getInput('iconmapid');

		$result = (bool) API::IconMap()->update($iconmap);

		$url = new CUrl('zabbix.php');

		if ($result) {
			$response = new CControllerResponseRedirect($url->setArgument('action', 'iconmap.list'));
			$response->setMessageOk(_('Icon map updated'));
		}
		else {
			$url->setArgument('iconmapid', $iconmap['iconmapid']);
			$response = new CControllerResponseRedirect($url->setArgument('action', 'iconmap.edit'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update icon map'));
		}

		$this->setResponse($response);
	}

	protected function doCreateAction() {
		$result = (bool) API::IconMap()->create((array) $this->getInput('iconmap'));

		$url = new CUrl('zabbix.php');
		if ($result) {
			$response = new CControllerResponseRedirect($url->setArgument('action', 'iconmap.list'));
			$response->setMessageOk(_('Icon map created'));
		}
		else {
			$response = new CControllerResponseRedirect($url->setArgument('action', 'iconmap.edit'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot create icon map'));
		}

		$this->setResponse($response);
	}

	protected function doAction() {
		if ($this->hasInput('iconmapid')) {
			$this->doUpdateAction();
		}
		else {
			$this->doCreateAction();
		}
	}
}
