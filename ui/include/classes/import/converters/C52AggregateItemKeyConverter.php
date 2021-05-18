<?php declare(strict_types=1);
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
 * Convert aggregate item key to calculated item function.
 */
class C52AggregateItemKeyConverter extends CConverter {

	/**
	 * Item key parser instance.
	 *
	 * @var CItemKey
	 */
	protected $item_key_parser;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'lldmacros' => false  Enable low-level discovery macros usage in time periods.
	 *
	 * @var array
	 */
	private $options = [
		'lldmacros' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->item_key_parser = new CItemKey();
	}

	/**
	 * Convert aggregate item key to calculated item syntax.
	 *
	 * @param string $value  Item key.
	 *
	 * @return string  Converted item key.
	 */
	public function convert($value) {
		$this->item_key = '';

		if ($this->item_key_parser->parse($value) != CParser::PARSE_SUCCESS) {
			return $value;
		}

		$params = $this->item_key_parser->getParamsRaw();
		$params = reset($params);

		if (!$params || count($params['parameters']) < 2) {
			return $value;
		}

		$params = $params['parameters'];
		$this->item_key = trim($this->item_key_parser->getParam(1));
		$func_foreach = $this->item_key_parser->getParam(2).'_foreach';
		$item_key = '/*/'.$this->item_key;
		$host_groups = [$params[0]['raw']];

		if ($params[0]['type'] == CItemKey::PARAM_ARRAY) {
			$host_groups = array_column($params[0]['parameters'], 'raw');
		}

		foreach ($host_groups as &$host_group) {
			if ($host_group[0] === '"') {
				$host_group = str_replace('\\"', '"', substr($host_group, 1, -1));
			}
		}
		unset($host_group);

		$host_groups = array_filter($host_groups, 'strlen');

		foreach ($host_groups as &$host_group) {
			$host_group = CFilterParser::quoteString($host_group);
		}
		unset($host_group);

		if ($host_groups) {
			$item_key .= '?[group='.implode(' or group=', $host_groups).']';
		}

		$timeperiod = $this->item_key_parser->getParam(3);
		$new_value = substr($this->item_key_parser->getKey(), 3).'('.$func_foreach.'('.$item_key;

		if ($timeperiod !== null) {
			$timeperiod = trim($timeperiod);

			if ($this->isQuotableTimeperiod($timeperiod)) {
				$timeperiod = CHistFunctionParser::quoteParam($timeperiod);
			}

			$new_value .= ','.$timeperiod;
		}

		return $new_value.'))';
	}

	private function isQuotableTimeperiod(string $timeperiod): bool {
		$number_parser = new CNumberParser(['with_minus' => false, 'with_suffix' => true]);

		if ($number_parser->parse($timeperiod) == CParser::PARSE_SUCCESS) {
			return false;
		}

		$user_macro_parser = new CUserMacroParser();

		if ($user_macro_parser->parse($timeperiod) == CParser::PARSE_SUCCESS) {
			return false;
		}

		if ($this->options['lldmacros']) {
			$lld_macro_parser = new CLLDMacroParser();

			if ($lld_macro_parser->parse($timeperiod) == CParser::PARSE_SUCCESS) {
				return false;
			}

			$lld_macro_function_parser = new CLLDMacroFunctionParser();

			if ($lld_macro_function_parser->parse($timeperiod) == CParser::PARSE_SUCCESS) {
				return false;
			}
		}

		return true;
	}
}
