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


class CColorValidator extends CStringValidator {

	/**
	 * Hex color code regex.
	 *
	 * @var string
	 */
	public $regex = '/^[0-9a-f]{6}$/i';

	public function __construct(array $options = []) {
		$this->messageRegex = _('Color "%1$s" is not correct: expecting hexadecimal color code (6 symbols).');
		$this->messageEmpty = _('Empty color.');

		parent::__construct($options);
	}
}
