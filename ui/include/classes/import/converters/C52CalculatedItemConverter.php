<?php
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


class C52CalculatedItemConverter extends CConverter {

	protected $time_functions = ['date', 'dayofmonth', 'dayofweek', 'time', 'now'];

	protected $group_functions = ['grpavg', 'grpmax', 'grpmin', 'grpsum'];

	public function __construct() {
		$this->parser = new C10TriggerExpression([
			'calculated' => true
		]);
	}

	/**
	 * Convert calculated item formula to 5.2 syntax.
	 */
	public function convert($formula) {
		$result = $this->parser->parse($formula);

		if ($result === false) {
			return $formula;
		}

		$functions = $result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION);
		$offset = 0;

		foreach ($functions as $function) {
			$func_name = $function['data']['functionName'];

			if (in_array($func_name, $this->time_functions)) {
				continue;
			}

			$params = $function['data']['functionParams'];
			$pos = $offset + $function['pos'] + strlen($func_name) + 1;
			$length = strlen($function['value']) - strlen($func_name) - 2;
			$host = '';
			$key = $params[0];
			$host_delimiter_pos = strpos($key, ':');

			if ($host_delimiter_pos !== false && $host_delimiter_pos < strpos($key, '[')) {
				list($host, $key) = explode(':', $key, 2);
			}

			$params[0] = '/'.$host.'/'.$key;
			$replace = implode(',', $params);
			$formula = substr_replace($formula, $replace, $pos, $length);
			$offset += strlen($replace) - $length;
		}

		return $formula;
	}
}
