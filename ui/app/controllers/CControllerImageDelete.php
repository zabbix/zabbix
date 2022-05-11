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


class CControllerImageDelete extends CController {

	protected function checkInput() {
		$fields = [
			'imageid'   => 'required|db images.imageid',
			'imagetype' => 'required|db images.imagetype'
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

		$images = API::Image()->get(['imageids' => $this->getInput('imageid')]);
		if (!$images) {
			return false;
		}

		$this->image = $images[0];

		return true;
	}

	protected function doAction() {
		$result = API::Image()->delete([$this->image['imageid']]);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'image.list')
					->setArgument('imagetype', $this->getInput('imagetype'))
			);
			CMessageHelper::setSuccessTitle(_('Image deleted'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'image.edit')
					->setArgument('imageid', $this->getInput('imageid'))
			);
			CMessageHelper::setErrorTitle(_('Cannot delete image'));
		}

		$this->setResponse($response);
	}
}
