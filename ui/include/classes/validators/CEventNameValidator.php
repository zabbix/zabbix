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


/**
 * Validate only trigger event name field expression macros, other macros will be ignored.
 */
class CEventNameValidator extends CValidator {

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$p = 0;
		$expr_macro = new CExpressionMacroParser([
			'usermacros' => true,
			'lldmacros' => true,
			'host_macro_n' => true,
			'empty_host' => true,
			'macros_n' => ['{FUNCTION.VALUE}', '{FUNCTION.RECOVERY.VALUE}']
		]);
		$expr_func_macro = new CExpressionMacroFunctionParser([
			'usermacros' => true,
			'lldmacros' => true,
			'host_macro_n' => true,
			'empty_host' => true,
			'macros_n' => ['{FUNCTION.VALUE}', '{FUNCTION.RECOVERY.VALUE}']
		]);

		while (isset($value[$p])) {
			if (substr($value, $p, 3) === '{{?') {
				if ($expr_func_macro->parse($value, $p) != CParser::PARSE_FAIL) {
					$p += $expr_func_macro->getLength();

					continue;
				}
				$p++;
			}

			if (substr($value, $p, 2) === '{?') {
				if ($expr_macro->parse($value, $p) == CParser::PARSE_FAIL) {
					$this->setError($expr_macro->getError());

					return false;
				}

				$p += $expr_macro->getLength();

				continue;
			}

			$p++;
		}

		return true;
	}
}
