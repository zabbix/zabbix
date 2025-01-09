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


class CControllerImageEdit extends CController {

	/**
	 * @var array
	 */
	private $image = [];

	protected function init() {
		$this->disableCsrfValidation();
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
