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


class CControllerImageEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'imageid'   => 'db images.imageid',
			'imagetype' => 'db images.imagetype|in '.IMAGE_TYPE_ICON.','.IMAGE_TYPE_BACKGROUND,
			'name'      => 'db images.name'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}
		elseif (!$this->hasInput('imageid') && !$this->hasInput('imagetype')) {
			$this->setResponse(new CControllerResponseFatal());
			$ret = false;
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		if (!$this->hasInput('imageid')) {
			$this->image = [
				'imageid'   => 0,
				'imagetype' => $this->getInput('imagetype'),
				'name'      => $this->getInput('name', '')
			];

			return true;
		}

		$images = API::Image()->get(['imageids' => $this->getInput('imageid')]);
		if (!$images) {
			return false;
		}

		$this->image = $images[0];

		return true;
	}

	protected function doAction() {
		$response = new CControllerResponseData($this->getInputAll() + $this->image);
		$response->setTitle(_('Configuration of images'));
		$this->setResponse($response);
	}
}
