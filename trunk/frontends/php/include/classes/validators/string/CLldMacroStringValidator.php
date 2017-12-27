<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CLldMacroStringValidator extends CStringValidator {

	/**
	 * Error message if a string doesn't contain LLD macros.
	 *
	 * @var string
	 */
	public $messageMacro;

	/**
	 * Validates the given string and checks if it contains LLD macros.
	 */
	public function validate($value)
	{
		if (!parent::validate($value)) {
			return false;
		}

		// check if a string contains an LLD macro
		if (!zbx_empty($value) && !preg_match('/(\{#'.ZBX_PREG_MACRO_NAME_LLD.'\})+/', $value)) {
			$this->error($this->messageMacro);

			return false;
		}

		return true;
	}

}
