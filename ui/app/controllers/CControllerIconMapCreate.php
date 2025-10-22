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


class CControllerIconMapCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules() {
		$api_uniq = [
			['iconmap.get', ['name' => '{name}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db icon_map.name', 'required', 'not_empty'],
			'mappings' => ['objects', 'required', 'not_empty', 'uniq' => [['inventory_link', 'expression']], 'fields' => [
				'inventory_link' => ['db icon_mapping.inventory_link', 'required'],
				'expression' => ['db icon_mapping.expression', 'required', 'not_empty',
					'use' => [CRegexValidator::class, []]],
				'iconid' => ['db icon_mapping.iconid', 'required'],
				'sortorder' => ['integer']
			]],
			'default_iconid' => ['db icon_map.default_iconid']
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$this->setResponse(new CControllerResponseFatal());

			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot create icon map'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$this->getInputs($iconmap, ['name', 'mappings', 'default_iconid']);

		CArrayHelper::sort($iconmap['mappings'], ['sortorder']);
		$iconmap['mappings'] = array_map(static function (array $mapping): array {
			return array_intersect_key($mapping, array_flip(['inventory_link', 'expression', 'iconid']));
		}, array_values($iconmap['mappings']));

		$result = (bool) API::IconMap()->create($iconmap);
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Icon map created');
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create icon map'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
