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


class CControllerImageList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'imagetype' => 'db images.imagetype|in '.IMAGE_TYPE_ICON.','.IMAGE_TYPE_BACKGROUND
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$data = [];
		$data['imagetype'] = $this->getInput('imagetype', IMAGE_TYPE_ICON);

		$data['images'] = API::Image()->get([
			'output' => ['imageid', 'imagetype', 'name'],
			'filter' => ['imagetype' => $data['imagetype']]
		]);
		order_result($data['images'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of images'));
		$this->setResponse($response);
	}
}
