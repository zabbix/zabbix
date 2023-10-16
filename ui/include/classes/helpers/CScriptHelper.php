<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CScriptHelper {

	public static function validateManualInput(array &$script): bool {
		if ($script['manualinput_validator_type'] == SCRIPT_MANUALINPUT_TYPE_LIST) {
			// Check if values are unique.
			$user_input_values = array_map('trim', explode(",", $script['manualinput_validator']));

			if (array_unique($user_input_values) !== $user_input_values) {
				error(_('Dropdown options must be unique.'));
			}

			$script['manualinput_validator'] = implode(',', $user_input_values);

			// Check if provided manualinput value is one of dropdown values when executing the script.
			if (array_key_exists('provided_manualinput', $script)
					&& !in_array($script['provided_manualinput'], $user_input_values, true)) {
				error(
					_s('Incorrect value for field "%1$s": %2$s.', 'manualinput',
						_s('value must be one of: %1$s', implode(', ', explode(",", $script['manualinput_validator'])))
					)
				);
			}
		}
		else {
			$default_input = trim($script['manualinput_default_value']);
			$input_validation = $script['manualinput_validator'];
			$regular_expression = '/' . str_replace('/', '\/', $input_validation) . '/';
			$regex_validator = new CRegexValidator();

			if (!$regex_validator->validate($input_validation)) {
				error(
					_s('Incorrect value for field "%1$s": %2$s.', _('input_validation'),
						_('invalid regular expression')
					)
				);
			}
			elseif (!preg_match($regular_expression, $default_input)) {
				error(
					_s('Incorrect value for field "%1$s": %2$s.', 'manualinput_default_value',
						_s('input does not match the provided pattern: %1$s', $input_validation)
					)
				);
			}

			$script['manualinput_default_value'] = $default_input;
			$script['manualinput_validator'] = $input_validation;
		}

		return !hasErrorMessages();
	}
}
