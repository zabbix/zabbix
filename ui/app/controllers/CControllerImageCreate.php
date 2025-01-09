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


class CControllerImageCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name'      => 'required|not_empty|db images.name',
			'imagetype' => 'required|fatal|db images.imagetype'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$url = (new CUrl('zabbix.php'))
						->setArgument('action', 'image.edit')
						->setArgument('imagetype', $this->getInput('imagetype'));

					$response = new CControllerResponseRedirect($url);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot add image'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		return true;
	}

	/**
	 * @param $error string
	 *
	 * @return string|null
	 */
	protected function uploadImage(&$error) {
		try {
			if (array_key_exists('image', $_FILES)) {
				$file = new CUploadFile($_FILES['image']);

				if ($file->wasUploaded()) {
					$file->validateImageSize();
					return base64_encode($file->getContent());
				}

				return null;
			}
			else {
				return null;
			}
		}
		catch (Exception $e) {
			$error = $e->getMessage();
		}

		return null;
	}

	protected function doAction() {
		$image = $this->uploadImage($error);

		if ($error) {
			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'image.edit')
				->setArgument('imagetype', $this->getInput('imagetype'));

			$response = new CControllerResponseRedirect($url);
			error($error);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add image'));

			$this->setResponse($response);
			return;
		}

		$options = [
			'imagetype' => $this->getInput('imagetype'),
			'name' => $this->getInput('name')
		];

		if ($image != null) {
			$options['image'] = $image;
		}

		$result = API::Image()->create($options);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'image.list')
					->setArgument('imagetype', $this->getInput('imagetype'))
			);
			CMessageHelper::setSuccessTitle(_('Image added'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'image.edit')
					->setArgument('imagetype', $this->getInput('imagetype'))
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add image'));
		}

		$this->setResponse($response);
	}
}
