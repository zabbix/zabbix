<?php declare(strict_types = 0);
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


/**
 * Update controller for "Geographical maps" administration screen.
 */
class CControllerGeomapsUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'geomaps_tile_provider' => ['setting geomaps_tile_provider', 'required'],
			'geomaps_tile_url' => ['setting geomaps_tile_url', 'required', 'not_empty'],
			'geomaps_max_zoom' => ['setting geomaps_max_zoom', 'required', 'min' => 1, 'max' => ZBX_GEOMAP_MAX_ZOOM],
			'geomaps_attribution' => ['setting geomaps_attribution']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$settings = [
			CSettingsHelper::GEOMAPS_TILE_PROVIDER => $this->getInput('geomaps_tile_provider'),
			CSettingsHelper::GEOMAPS_TILE_URL => $this->getInput('geomaps_tile_url'),
			CSettingsHelper::GEOMAPS_MAX_ZOOM => $this->getInput('geomaps_max_zoom'),
			CSettingsHelper::GEOMAPS_ATTRIBUTION => $this->getInput('geomaps_attribution', '')
		];

		$result = API::Settings()->update($settings);

		$output = [];

		if ($result) {
			$success = ['title' => _('Configuration updated')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
