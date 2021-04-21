<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


/**
 * Class to store function period parser results.
 */
class CPeriodParserResult extends CFunctionParameterResult {

	/**
	 * <sec|#num> parameter
	 *
	 * @var string
	 */
	public $sec_num;

	/**
	 * <time_shift> parameter
	 *
	 * @var string
	 */
	public $time_shift;

	/**
	 * Indicates if time_shift contains macros.
	 *
	 * @var bool
	 */
	public $sec_num_contains_macros = false;

	/**
	 * Indicates if time_shift_contains_macros contains macros.
	 *
	 * @var bool
	 */
	public $time_shift_contains_macros = false;

	/**
	 * Token type.
	 *
	 * @var int
	 */
	public $type;

	public function __construct(array $data = []) {
		$data = array_intersect_key($data, array_flip(['sec_num', 'time_shift', 'sec_num_contains_macros',
			'time_shift_contains_macros', 'match', 'pos', 'length'
		]));

		$data += [
			'type' => CTriggerExprParserResult::TOKEN_TYPE_PERIOD,
			'sec_num' => '',
			'time_shift' => '',
			'sec_num_contains_macros' => false,
			'time_shift_contains_macros' => false,
			'match' => '',
			'pos' => 0,
			'length' => 0
		];

		foreach ($data as $property => $value) {
			$this->$property = $value;
		}
	}
}
