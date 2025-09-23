<?php declare(strict_types = 0);
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


class CRegexHelper {

	public static function expression_type2str(?int $type = null) {
		$types = [
			EXPRESSION_TYPE_INCLUDED => _('Character string included'),
			EXPRESSION_TYPE_ANY_INCLUDED => _('Any character string included'),
			EXPRESSION_TYPE_NOT_INCLUDED => _('Character string not included'),
			EXPRESSION_TYPE_TRUE => _('Result is TRUE'),
			EXPRESSION_TYPE_FALSE => _('Result is FALSE')
		];

		if ($type === null) {
			return $types;
		}

		return array_key_exists($type, $types) ? $types[$type] : _('Unknown');
	}

	public static function expressionDelimiters(): array {
		return [
			',' => ',',
			'.' => '.',
			'/' => '/'
		];
	}

	public static function preparePattern(string $expression): string {
		$escaped_expression = '';
		$prev_symbol = '';
		$length = strlen($expression);

		// Escape delimiter symbols within character class sequences: [(] => [\(].
		for ($i = 0; $i < $length; $i++) {
			$current_symbol = $expression[$i];
			$escaped_expression .= $current_symbol;

			if ($prev_symbol === '\\' || $current_symbol !== '[') {
				$prev_symbol = $current_symbol;

				continue;
			}

			$prev_symbol = $current_symbol;

			while (++$i < $length) {
				$current_symbol = $expression[$i];

				if ($prev_symbol !== '\\' && strpos('()', $current_symbol) !== false) {
					$escaped_expression .= '\\';
				}

				$escaped_expression .= $current_symbol;
				$prev_symbol = $current_symbol;

				if ($current_symbol === ']') {
					break;
				}
			}
		}

		return '('.$escaped_expression.')';
	}
}

