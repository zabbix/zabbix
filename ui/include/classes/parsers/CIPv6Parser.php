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


/**
 * A parser for IPv6 address.
 */
class CIPv6Parser extends CParser {

	const STATE_NEW = 0;
	const STATE_AFTER_DIGITS = 1;
	const STATE_AFTER_COLON = 2;
	const STATE_AFTER_DBLCOLON = 3;

	/**
	 * @var CIPv4Parser
	 */
	private $ipv4_parser;

	public function __construct() {
		$this->ipv4_parser = new CIPv4Parser();
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$state = self::STATE_NEW;
		$colons = 0;
		$dbl_colons = 0;

		for ($p = $pos; isset($source[$p]); $p++) {
			switch ($state) {
				case self::STATE_NEW:
					if (self::parseDoubleColon($source, $p)) {
						if ($dbl_colons++ == 1) {
							return self::PARSE_FAIL;
						}
						$state = self::STATE_AFTER_DBLCOLON;
					}
					elseif (self::parseXDigits($source, $p)) {
						$state = self::STATE_AFTER_DIGITS;
					}
					else {
						return self::PARSE_FAIL;
					}
					break;

				case self::STATE_AFTER_COLON:
					if (self::parseXDigits($source, $p)) {
						$state = self::STATE_AFTER_DIGITS;
					}
					else {
						return self::PARSE_FAIL;
					}
					break;

				case self::STATE_AFTER_DBLCOLON:
					if (self::parseXDigits($source, $p)) {
						$state = self::STATE_AFTER_DIGITS;
					}
					else {
						break 2;
					}
					break;

				case self::STATE_AFTER_DIGITS:
					if (self::parseDoubleColon($source, $p)) {
						if ($dbl_colons++ == 1) {
							return self::PARSE_FAIL;
						}
						$state = self::STATE_AFTER_DBLCOLON;
					}
					elseif ($source[$p] == ':') {
						if ($colons++ == 7) {
							return self::PARSE_FAIL;
						}
						$state = self::STATE_AFTER_COLON;
					}
					else {
						break 2;
					}
					break;
			}
		}

		if ($state == self::STATE_AFTER_COLON || $state == self::STATE_NEW) {
			return self::PARSE_FAIL;
		}

		if (isset($source[$p]) && $source[$p] == '.' && $state == self::STATE_AFTER_DIGITS
				&& ($colons != 0 || $dbl_colons != 0)) {
			if (($dbl_colons == 0 && $colons != 6) || ($dbl_colons == 1 && $colons > 4)) {
				return self::PARSE_FAIL;
			}

			while ($source[$p - 1] != ':') {
				$p--;
			}

			if ($this->ipv4_parser->parse($source, $p) == self::PARSE_FAIL) {
				return self::PARSE_FAIL;
			}

			$p += $this->ipv4_parser->getLength();
		}
		elseif (($dbl_colons == 0 && $colons != 7) || ($dbl_colons == 1 && $colons > 5)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	private static function parseDoubleColon($source, &$pos) {
		$p = $pos;

		if ($source[$p] !== ':') {
			return false;
		}
		$p++;

		if (!isset($source[$p]) || $source[$p] !== ':') {
			return false;
		}

		$pos = $p;

		return true;
	}

	private static function parseXDigits($source, &$pos) {
		$p = $pos;

		while (isset($source[$p]) && ctype_xdigit($source[$p])) {
			$p++;
		}

		if ($p == $pos || $p - $pos > 4) {
			return false;
		}

		$pos = $p - 1;

		return true;
	}
}
