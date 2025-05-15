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

	public function parse($source, $pos = 0) {
		$this->result = [];

		if ($source === '') {
			return self::PARSE_SUCCESS;
		}

		foreach (self::split(',', $source) as $rdn) {
			foreach (self::split('\+', $rdn) as $attribute) {
				if (!preg_match('#(?<!\\\\)=#', $attribute)) {
					$this->result = [];

					return self::PARSE_FAIL;
				}

				[$name, $value] = self::split('=', $attribute);

				$this->result[] = [
					'name' => self::unescape(self::normalize($name)),
					'value' => self::unescape(self::normalize($value))
				];
			}
		}

		return self::PARSE_SUCCESS;
	}

	private static function split(string $char, string $string) {
		return preg_split("#(?<!\\\\)$char#", $string);
	}

	private static function normalize(string $string): string {
		$string = ltrim($string, ' ');
		$string = preg_replace('#((?<!\\\\) )+$#', '', $string);

		return $string;
	}

	private static function unescape(string $string): string {
		$chars = "\"\\\\=,+';<> ";
		$string = preg_replace("#\\\\([$chars])#", '$1', $string);
		$string = preg_replace('/^\\\\#/', '#', $string);

		return $string;
	}
}
