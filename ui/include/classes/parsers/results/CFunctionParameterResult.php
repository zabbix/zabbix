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
 * Class to store unspecified function parameter.
 */
class CFunctionParameterResult extends CParserResult {

	/**
	 * Parameter type.
	 *
	 * @var type
	 */
	public $type;

	public function __construct(array $data = []) {
		$data = array_intersect_key($data, array_flip(['type', 'match', 'pos', 'length']));
		$data += [
			'type' => -1,
			'match' => '',
			'pos' => 0,
			'length' => 0
		];

		foreach ($data as $property => $value) {
			$this->$property = $value;
		}
	}

	/**
	 * Get value of parsed parameter.
	 *
	 * @param bool $keep_unquoted  Keep parameters of type CFunctionParser::PARAM_QUOTED unqioted.
	 *
	 * @return string
	 */
	public function getValue(bool $keep_unquoted = false): string {
		$value = $this->match;

		if ($this instanceof CQueryParserResult) {
			return $value;
		}
		elseif (!$keep_unquoted && $this->type == CFunctionParser::PARAM_QUOTED) {
			$unquoted = '';
			for ($p = 1; isset($value[$p]); $p++) {
				if ($value[$p] === '\\' && $value[$p + 1] === '"') {
					continue;
				}

				$unquoted .= $value[$p];
			}

			$value = substr($unquoted, 0, -1);
		}

		return $value;
	}
}
