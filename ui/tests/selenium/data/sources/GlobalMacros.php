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


class GlobalMacros {

	/**
	 * Create Global Macros for macros related tests.
	 */
	public static function load() {
		CDataHelper::call('usermacro.createglobal', [
			[
				'macro' => '{$DEFAULT_DELAY}',
				'value' => '30'
			],
			[
				'macro' => '{$LOCALIP}',
				'value' => '127.0.0.1',
				'description' => 'Test description 2'
			],
			[
				'macro' => '{$DEFAULT_LINUX_IF}',
				'value' => 'eth0'
			],
			[
				'macro' => '{$0123456789012345678901234567890123456789012345678901234567890}',
				'value' => STRING_255
			],
			[
				'macro' => '{$A}',
				'value' => 'Some text'
			],
			[
				'macro' => '{$1}',
				'value' => 'Numeric macro',
				'description' => 'Test description 1'
			],
			[
				'macro' => '{$_}',
				'value' => 'Underscore'
			],
			[
				'macro' => '{$WORKING_HOURS}',
				'value' => '1-5,09:00-18:00',
				'description' => 'Test description 3'
			],
			[
				'macro' => '{$X_SECRET_2_SECRET}',
				'value' => 'This text should stay secret',
				'description' => 'This text should stay secret',
				'type' => ZBX_MACRO_TYPE_SECRET
			],
			[
				'macro' => '{$X_TEXT_2_SECRET}',
				'value' => 'This text should become secret',
				'description' => 'This text should become secret'
			],
			[
				'macro' => '{$X_SECRET_2_TEXT}',
				'value' => 'This text should become visible',
				'description' => 'This text should become visible',
				'type' => ZBX_MACRO_TYPE_SECRET
			],
			[
				'macro' => '{$Y_SECRET_MACRO_REVERT}',
				'value' => 'Changes value and revert' ,
				'type' => ZBX_MACRO_TYPE_SECRET
			],
			[
				'macro' => '{$Y_SECRET_MACRO_2_TEXT_REVERT}',
				'value' => 'Change value and type and revert' ,
				'type' => ZBX_MACRO_TYPE_SECRET
			],
			[
				'macro' => '{$Z_GLOBAL_MACRO_2_RESOLVE}',
				'value' => 'Value 2 B resolved'
			]
		]);
	}
}
