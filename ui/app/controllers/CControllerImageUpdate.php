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


class CControllerImageUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_FORM);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['image.get', ['name' => '{name}'], 'imageid']
		];
		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'imageid' => ['required', 'db images.imageid'],
			'imagetype' => ['required', 'db images.imagetype', 'in '.IMAGE_TYPE_ICON.','.IMAGE_TYPE_BACKGROUND],
			'name' => ['required', 'db images.name', 'not_empty'],
			'image' => ['image '.ZBX_MAX_IMAGE_SIZE,
				'messages' => ['file' => _s('Image size must be less than %1$s.', convertUnits(['value' =>
					ZBX_MAX_IMAGE_SIZE, 'units' => 'B']))]
			]
		]];
	}

	protected function checkInput() {

		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update image'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
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

		return true;
	}

	/**
	 * @param $error string
	 *
	 * @return string|null
	 */
	protected function uploadImage(&$error) {
		try {
			if ($this->hasInput('image')) {
				$file = $this->getInput('image');

				if ($file->wasUploaded()) {
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
			$output['error'] = [
				'title' => _('Cannot update image'),
				'messages' => $error
			];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));

			return;
		}

		if ($this->hasInput('imageid')) {
			$options = [
				'imageid' => $this->getInput('imageid'),
				'name' => $this->getInput('name')
			];

			if ($image !== null) {
				$options['image'] = $image;
			}

			$result = API::Image()->update($options);
		}
		else {
			$options = [
				'imagetype' => $this->getInput('imagetype'),
				'name' => $this->getInput('name')
			];

			if ($image !== null) {
				$options['image'] = $image;
			}

			$result = API::Image()->create($options);
		}

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Image updated');
			$output['success']['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'image.list')
				->setArgument('imagetype', $this->getInput('imagetype'))
				->getUrl();

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update image'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
