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


/**
 * A parser for distinguished names.
 *
 * Supports multi-value RDN, LDAP compatible.
 */
class CDNParser extends CParser {

	/**
	 * All RDN attribute objects.
	 *
	 * @var array<array<string, string>>
	 */
	public array $result = [];

	public function parse($source, $pos = 0): int {
		$this->result = [];

		if ($source === '') {
			return self::PARSE_SUCCESS;
		}

		$source = preg_replace('#(?<!\\\\);(?=(?:[^"]*"[^"]*")*[^"]*$)#', ',', $source);

		foreach (self::split(',', $source) as $rdn) {
			foreach (self::split('\\+', $rdn) as $attribute) {
				if ($attribute && $attribute[0] === '=') {
					$this->result = [];

					return self::PARSE_FAIL;
				}

				if (!preg_match('#(?<!\\\\)=#', $attribute)) {
					$this->result = [];

					return self::PARSE_FAIL;
				}

				[$name, $value] = self::split('=', $attribute);

				$name = self::normalize($name);
				$value = self::normalize($value);

				if ($value !== '' && $value[0] === '"') {
					$value = self::unquote($value);
				}

				$this->result[] = [
					'name'  => self::unescape($name),
					'value' => self::unescape($value)
				];
			}
		}

		return self::PARSE_SUCCESS;
	}

	private static function split(string $char, string $string): array {
		$delimiter = substr($char, -1);
		$parts = [];
		$current = '';
		$bs_count = 0;
		$in_quotes = false;
		$len = strlen($string);

		for ($i = 0; $i < $len; $i++) {
			$ch = $string[$i];

			if ($ch === '\\') {
				$bs_count++;
				$current .= $ch;

				continue;
			}

			if ($ch === '"' && ($bs_count % 2) === 0) {
				$in_quotes = !$in_quotes;
				$current .= $ch;
				$bs_count = 0;

				continue;
			}

			if ($ch === $delimiter && ($bs_count % 2) === 0 && !$in_quotes) {
				$parts[] = $current;
				$current = '';
				$bs_count = 0;

				continue;
			}

			$current .= $ch;
			$bs_count = 0;
		}

		$parts[] = $current;

		if ($delimiter === '=' && count($parts) > 2) {
			for ($i = 2, $iMax = count($parts); $i < $iMax; $i++) {
				$parts[1] .= '='.$parts[$i];
			}
		}

		return $parts;
	}

	private static function normalize(string $string): string {
		return preg_replace('#((?<!\\\\) )+$#', '', ltrim($string, ' '));
	}

	private static function unquote(string $quoted): string {
		return str_replace(['\\\\"', '\\\\\\'], ['"', '\\'], substr($quoted, 1, -1));
	}

	private static function unescape(string $string): string {
		$chars = "\"\\\\=,+';<> ";
		$string = preg_replace("#\\\\([$chars])#", '$1', $string);

		return preg_replace('/^\\\\#/', '#', $string);
	}
}
