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
 * Class containing operations for updating/creating a host wizard.
 */
abstract class CControllerHostWizardUpdateGeneral extends CControllerHostUpdateGeneral {

	private array $template;

	protected function checkPermissions(): bool {
		$templates = API::Template()->get([
			'output' => [],
			'selectMacros' => ['macro', 'config'],
			'templateids' => $this->getInput('templateid'),
			'filter' => ['wizard_ready' => ZBX_WIZARD_READY]
		]);

		if (!$templates) {
			return false;
		}

		$this->template = $templates[0];

		return true;
	}

	/**
	 * Check that all macros are following macro config rules.
	 *
	 * @return bool
	 */
	protected function validateMacrosByConfig(): bool {
		$macros = $this->getInput('macros', []);
		$indexed_macros = [];

		foreach ($macros as $index => $macro) {
			$indexed_macros[$macro['macro']] = ['index' => $index] + $macro;
		}

		foreach ($this->template['macros'] as $tempalte_macro) {
			if ($tempalte_macro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
				continue;
			}

			$config = $tempalte_macro['config'];

			// Check if mandatory macros are present.
			if (!array_key_exists($tempalte_macro['macro'], $indexed_macros)) {
				error(_s('Macro "%1$s" is missing.', $config['label']));

				return false;
			}

			$macro = $indexed_macros[$tempalte_macro['macro']];

			// Macro with type secret can be without value.
			if ($macro['type'] == ZBX_MACRO_TYPE_SECRET && !array_key_exists('value', $macro)) {
				unset($indexed_macros[$tempalte_macro['macro']]);
				continue;
			}

			$macro_value = trim($macro['value']);

			if ($macro_value === ''
					&& $config['type'] == ZBX_WIZARD_FIELD_TEXT
					&& $config['required'] == ZBX_WIZARD_FIELD_REQUIRED) {
				error(_s('Incorrect value for macro "%1$s": %2$s.', $config['label'], _('cannot be empty')));

				return false;
			}

			if ($macro_value !== '' && $config['regex'] !== '' && !preg_match('/'.$config['regex'].'/', $macro_value)) {
				error(_s('Incorrect value for macro "%1$s": %2$s.', $config['label'],
					_s('input does not match the pattern: %1$s', '/'.$config['regex'].'/')
				));

				return false;
			}

			$allowed_values = [];
			if ($config['type'] == ZBX_WIZARD_FIELD_LIST) {
				$allowed_values = array_column($config['options'], 'value');

				if ($config['required'] === ZBX_WIZARD_FIELD_NOT_REQUIRED) {
					$allowed_values[] = '';
				}
			}
			elseif ($config['type'] == ZBX_WIZARD_FIELD_CHECKBOX) {
				$allowed_values = array_values($config['options'][0]);
			}

			if ($allowed_values && !in_array($macro_value, $allowed_values)) {
				error(_s('Incorrect value for macro "%1$s": %2$s.', $config['label'],
					_s('value must be one of %1$s', implode(', ', $allowed_values))
				));

				return false;
			}

			unset($indexed_macros[$tempalte_macro['macro']]);
		}

		// Check that only configurable macros were passed.
		if ($indexed_macros) {
			error(_n('Unexpected macro "%1$s"', 'Unexpected macros "%1$s"', implode(', ', array_keys($indexed_macros)),
				count($indexed_macros)
			));

			return false;
		}

		return true;
	}
}
