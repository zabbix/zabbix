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


class CControllerMacrosUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	static function getValidationRules(): array {
		return ['object', 'fields' => [
			'macros' => ['objects',
				'uniq' => [['macro']],
				'fields' => [
					'globalmacroid' => ['db globalmacro.globalmacroid'],
					'type' => ['db globalmacro.type', 'required',
						'in' => [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]
					],
					'value' => [
						['db globalmacro.value'],
						['db globalmacro.value', 'required', 'not_empty',
							'use' => [CVaultSecretParser::class,
								['provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)]
							],
							'when' => ['type', 'in' => [ZBX_MACRO_TYPE_VAULT]]
						]
					],
					'description' => ['db globalmacro.description'],
					'macro' => [
						['db globalmacro.macro',
							'use' => [CUserMacroParser::class, []],
							'messages' => ['use' => _('Expected user macro format is "{$MACRO}".')]
						],
						['db globalmacro.macro', 'required', 'not_empty', 'when' => ['value', 'not_empty']],
						['db globalmacro.macro', 'required', 'not_empty', 'when' => ['description', 'not_empty']]
					]
				],
				'messages' => [
					'uniq' => _('Macro name is not unique.')
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();

			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update macros'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS);
	}

	protected function doAction() {
		/** @var array $macros */
		$macros = $this->getInput('macros', []);
		foreach ($macros as &$macro) {
			$macro['macro'] = trim($macro['macro']);

			if (array_key_exists('value', $macro)) {
				$macro['value'] = trim($macro['value']);
			}

			$macro['description'] = trim($macro['description']);
		}
		unset($macro);

		foreach ($macros as $idx => $macro) {
			if (!array_key_exists('globalmacroid', $macro) && $macro['macro'] === ''
					&& (!array_key_exists('value', $macro) || $macro['value'] === '') && $macro['description'] === '') {
				unset($macros[$idx]);
			}
		}

		$db_macros = API::UserMacro()->get([
			'output' => ['globalmacroid', 'macro', 'value', 'type', 'description'],
			'globalmacro' => true,
			'preservekeys' => true
		]);

		$macros_to_update = [];
		foreach ($macros as $idx => $macro) {
			if (array_key_exists('globalmacroid', $macro) && array_key_exists($macro['globalmacroid'], $db_macros)) {
				$dbMacro = $db_macros[$macro['globalmacroid']];

				// Remove item from new macros array.
				unset($macros[$idx], $db_macros[$macro['globalmacroid']]);

				// If the macro is unchanged - skip it.
				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
					if (!array_key_exists('value', $macro)) {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}
				else {
					if ($dbMacro['type'] == ZBX_MACRO_TYPE_SECRET) {
						if ($dbMacro['macro'] === $macro['macro']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
					else {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['value'] === $macro['value']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}

				$macros_to_update[] = $macro;
			}
		}

		$result = true;

		if ($macros_to_update || $db_macros || $macros) {
			DBstart();

			if ($macros_to_update) {
				$result = (bool) API::UserMacro()->updateGlobal($macros_to_update);
			}

			if ($db_macros) {
				$result = $result && (bool) API::UserMacro()->deleteGlobal(array_keys($db_macros));
			}

			if ($macros) {
				$result = $result && (bool) API::UserMacro()->createGlobal(array_values($macros));
			}

			$result = DBend($result);
		}

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Macros updated');
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update macros'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
