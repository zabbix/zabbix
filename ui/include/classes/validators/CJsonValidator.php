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


class CJsonValidator extends CValidator {

	protected bool $usermacros = false;
	protected bool $lldmacros = false;
	protected array $macros_n = [];

	public function __construct(array $options = []) {
		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = (bool) $options['lldmacros'];
		}

		if (array_key_exists('usermacros', $options)) {
			$this->usermacros = (bool) $options['usermacros'];
		}

		if (array_key_exists('macros_n', $options)) {
			$this->macros_n = (array) $options['macros_n'];
		}
	}

	public function validate($value) {
		$types = [];

		if ($this->usermacros) {
			$types['usermacros'] = true;
		}

		if ($this->lldmacros) {
			$types['lldmacros'] = true;
		}

		if ($this->macros_n) {
			$types['macros_n'] = $this->macros_n;
		}

		if ($types) {
			$matches = CMacrosResolverGeneral::getMacroPositions($value, $types);
			$shift = 0;

			foreach ($matches as $pos => $substr) {
				$value = substr_replace($value, '1', $pos + $shift, strlen($substr));
				$shift = $shift + 1 - strlen($substr);
			}
		}

		json_decode($value);

		if (json_last_error() != JSON_ERROR_NONE) {
			$this->setError(_('JSON is expected'));

			return false;
		}

		return true;
	}
}
