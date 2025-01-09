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
 * Class containing methods for IP range and network mask parsing.
 */
class CIPRangeParser {

	/**
	 * An error message if IP range is not valid.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Maximum amount of IP addresses.
	 *
	 * @var string
	 */
	private $max_ip_count;

	/**
	 * IP address range with maximum amount of IP addresses.
	 *
	 * @var string
	 */
	private $max_ip_range;

	/**
	 * @var CIPv4Parser
	 */
	private $ipv4_parser;

	/**
	 * @var CIPv6Parser
	 */
	private $ipv6_parser;

	/**
	 * @var CDnsParser
	 */
	private $dns_parser;

	/**
	 * @var array
	 */
	private $macro_parsers = [];

	/**
	 * Supported options:
	 *   v6             enabled support of IPv6 addresses
	 *   dns            enabled support of DNS names
	 *   ranges         enabled support of IP ranges like 192.168.3.1-255
	 *   max_ipv4_cidr  maximum value for IPv4 CIDR subnet mask notations
	 *   usermacros     allow usermacros syntax
	 *   macros         allow macros syntax like {HOST.HOST}, {HOST.NAME}, ...
	 *
	 * @var array
	 */
	private $options = [
		'v6' => true,
		'dns' => true,
		'ranges' => true,
		'max_ipv4_cidr' => 32,
		'usermacros' => false,
		'macros' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->ipv4_parser = new CIPv4Parser();
		if ($this->options['v6']) {
			$this->ipv6_parser = new CIPv6Parser();
		}
		if ($this->options['dns']) {
			$this->dns_parser = new CDnsParser();
		}
		if ($this->options['usermacros']) {
			array_push($this->macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}
		if ($this->options['macros']) {
			array_push($this->macro_parsers,
				new CMacroParser(['macros' => $this->options['macros']]),
				new CMacroFunctionParser(['macros' => $this->options['macros']])
			);
		}
	}

	/**
	 * Validate comma-separated IP address ranges.
	 *
	 * @param string $ranges
	 *
	 * @return bool
	 */
	public function parse($ranges) {
		$this->error = '';
		$this->max_ip_count = '0';
		$this->max_ip_range = '';

		$p = 0;

		do {
			while (isset($ranges[$p]) && strpos(" \t\r\n", $ranges[$p]) !== false) {
				$p++;
			}

			if (isset($ranges[$p]) && !$this->parseMask($ranges, $p) && !$this->parseRange($ranges, $p)
					&& !$this->parseDns($ranges, $p) && !$this->parseMacro($ranges, $p)) {
				break;
			}

			while (isset($ranges[$p]) && strpos(" \t\r\n", $ranges[$p]) !== false) {
				$p++;
			}

			if (!isset($ranges[$p]) || $ranges[$p] !== ',') {
				break;
			}
			$p++;
		}
		while (isset($ranges[$p]));

		if (isset($ranges[$p])) {
			$this->error = _s('incorrect address starting from "%1$s"', substr($ranges, $p));
			$this->max_ip_count = '0';
			$this->max_ip_range = '';

			return false;
		}

		return true;
	}

	/**
	 * Get first validation error.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Get maximum number of IP addresses.
	 *
	 * @return string
	 */
	public function getMaxIPCount() {
		return $this->max_ip_count;
	}

	/**
	 * Get range with maximum number of IP addresses.
	 *
	 * @return string
	 */
	public function getMaxIPRange() {
		return $this->max_ip_range;
	}

	/**
	 * Validate an IP mask.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseMask(string $range, int &$pos): bool {
		return $this->parseMaskIPv4($range, $pos) || $this->parseMaskIPv6($range, $pos);
	}

	/**
	 * Validate an IPv4 mask.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseMaskIPv4(string $range, int &$pos): bool {
		$p = $pos;

		if ($this->ipv4_parser->parse($range, $p) == CParser::PARSE_FAIL) {
			return false;
		}
		$p += $this->ipv4_parser->getLength();

		if (!isset($range[$p]) || $range[$p] !== '/') {
			return false;
		}
		$p++;

		if (!preg_match('/^[0-9]+/', substr($range, $p), $matches) || strlen($matches[0]) > 2
				|| $matches[0] > $this->options['max_ipv4_cidr']) {
			return false;
		}
		$p += strlen($matches[0]);

		$ip_count = bcpow(2, 32 - $matches[0], 0);

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = substr($range, $pos, $p - $pos);
		}

		$pos = $p;

		return true;
	}

	/**
	 * Validate an IPv6 mask.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseMaskIPv6(string $range, int &$pos): bool {
		if (!$this->options['v6']) {
			return false;
		}

		$p = $pos;

		if ($this->ipv6_parser->parse($range, $p) == CParser::PARSE_FAIL) {
			return false;
		}
		$p += $this->ipv6_parser->getLength();

		if (!isset($range[$p]) || $range[$p] !== '/') {
			return false;
		}
		$p++;

		if (!preg_match('/^[0-9]+/', substr($range, $p), $matches) || strlen($matches[0]) > 3 || $matches[0] > 128) {
			return false;
		}
		$p += strlen($matches[0]);

		$ip_count = bcpow(2, 128 - $matches[0], 0);

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = substr($range, $pos, $p - $pos);
		}

		$pos = $p;

		return true;
	}

	/**
	 * Validate an IP address range.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseRange(string $range, int &$pos): bool {
		return $this->parseRangeIPv4($range, $pos) || $this->parseRangeIPv6($range, $pos);
	}

	/**
	 * Validate an IPv4 address range.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseRangeIPv4(string $range, int &$pos): bool {
		$p = $pos;
		$ip_count = '1';
		$ip = '';

		while (isset($range[$p])) {
			if (preg_match('/^([0-9]{1,3})-([0-9]{1,3})/', substr($range, $p), $matches)) {
				if (!$this->options['ranges'] || $matches[1] > $matches[2]) {
					return false;
				}

				$ip_count = bcmul($ip_count, $matches[2] - $matches[1] + 1, 0);
				$ip .= $matches[2];
				$p += strlen($matches[0]);
			}
			elseif (preg_match('/^[0-9]{1,3}/', substr($range, $p), $matches)) {
				$ip .= $matches[0];
				$p += strlen($matches[0]);
			}
			else {
				return false;
			}

			if (!isset($range[$p]) || $range[$p] !== '.') {
				break;
			}
			$ip .= $range[$p++];
		}

		if ($this->ipv4_parser->parse($ip) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = substr($range, $pos, $p - $pos);
		}

		$pos = $p;

		return true;
	}

	/**
	 * Validate an IPv6 address range.
	 *
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseRangeIPv6(string $range, int &$pos): bool {
		if (!$this->options['v6']) {
			return false;
		}

		$p = $pos;
		$ip_count = '1';
		$ip = '';

		while (isset($range[$p])) {
			if (preg_match('/^([a-f0-9]{1,4})-([a-f0-9]{1,4})/i', substr($range, $p), $matches)) {
				sscanf($matches[1], '%x', $from);
				sscanf($matches[2], '%x', $to);

				if (!$this->options['ranges'] || $from > $to) {
					return false;
				}

				$ip_count = bcmul($ip_count, $to - $from + 1, 0);
				$ip .= $matches[1];
				$p += strlen($matches[0]);
			}
			elseif (preg_match('/^[a-f0-9]{0,4}/i', substr($range, $p), $matches)) {
				$ip .= $matches[0];
				$p += strlen($matches[0]);
			}
			else {
				return false;
			}

			if (!isset($range[$p]) || $range[$p] !== ':') {
				break;
			}
			$ip .= $range[$p++];
		}

		if ($this->ipv6_parser->parse($ip) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = substr($range, $pos, $p - $pos);
		}

		$pos = $p;

		return true;
	}

	/**
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseDns(string $range, int &$pos) {
		if (!$this->options['dns']) {
			return false;
		}

		$p = $pos;

		if ($this->dns_parser->parse($range, $p) == CParser::PARSE_FAIL) {
			return false;
		}
		$p += $this->dns_parser->getLength();

		if (bccomp($this->max_ip_count, 1) < 0) {
			$this->max_ip_count = '1';
			$this->max_ip_range = substr($range, $pos, $p - $pos);
		}

		$pos = $p;

		return true;
	}

	/**
	 * @param string $range
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseMacro(string $range, int &$pos) {
		foreach ($this->macro_parsers as $macro_parser) {
			if ($macro_parser->parse($range, $pos) != CParser::PARSE_FAIL) {
				$pos += $macro_parser->getLength();
				return true;
			}
		}

		return false;
	}
}
