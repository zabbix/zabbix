<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * @var CMacroParser
	 */
	private $macro_parser;

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
		foreach (['v6', 'dns', 'ranges', 'max_ipv4_cidr', 'usermacros', 'macros'] as $option) {
			if (array_key_exists($option, $options)) {
				$this->options[$option] = $options[$option];
			}
		}

		$this->ipv4_parser = new CIPv4Parser();
		if ($this->options['v6']) {
			$this->ipv6_parser = new CIPv6Parser();
		}
		if ($this->options['dns']) {
			$this->dns_parser = new CDnsParser();
		}
		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['macros']) {
			$this->macro_parser = new CMacroParser(['macros' => $this->options['macros']]);
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

		foreach (explode(',', $ranges) as $range) {
			$range = trim($range, " \t\r\n");

			if (!$this->isValidMask($range) && !$this->isValidRange($range) && !$this->isValidDns($range)
					&& !$this->isValidUserMacro($range) && !$this->isValidMacro($range)) {
				$this->error = _s('invalid address range "%1$s"', $range);
				$this->max_ip_count = '0';
				$this->max_ip_range = '';

				return false;
			}
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
	 *
	 * @return bool
	 */
	protected function isValidMask($range) {
		return ($this->isValidMaskIPv4($range) || $this->isValidMaskIPv6($range));
	}

	/**
	 * Validate an IPv4 mask.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidMaskIPv4($range) {
		$parts = explode('/', $range);

		if (count($parts) != 2) {
			return false;
		}

		if ($this->ipv4_parser->parse($parts[0]) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (!preg_match('/^[0-9]{1,2}$/', $parts[1]) || $parts[1] > $this->options['max_ipv4_cidr']) {
			return false;
		}

		$ip_count = bcpow(2, 32 - $parts[1], 0);

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = $range;
		}

		return true;
	}

	/**
	 * Validate an IPv6 mask.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidMaskIPv6($range) {
		if (!$this->options['v6']) {
			return false;
		}

		$parts = explode('/', $range);

		if (count($parts) != 2) {
			return false;
		}

		if ($this->ipv6_parser->parse($parts[0]) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (!preg_match('/^[0-9]{1,3}$/', $parts[1]) || $parts[1] > 128) {
			return false;
		}

		$ip_count = bcpow(2, 128 - $parts[1], 0);

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = $range;
		}

		return true;
	}

	/**
	 * Validate an IP address range.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidRange($range) {
		return ($this->isValidRangeIPv4($range) || $this->isValidRangeIPv6($range));
	}

	/**
	 * Validate an IPv4 address range.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidRangeIPv4($range) {
		$parts = explode('.', $range);

		$ip_count = '1';
		$ip_parts = [];

		foreach ($parts as $part) {
			if (preg_match('/^([0-9]{1,3})-([0-9]{1,3})$/', $part, $matches)) {
				if (!$this->options['ranges'] || $matches[1] > $matches[2]) {
					return false;
				}

				$ip_count = bcmul($ip_count, $matches[2] - $matches[1] + 1, 0);
				$ip_parts[] = $matches[2];
			}
			else {
				$ip_parts[] = $part;
			}
		}

		if ($this->ipv4_parser->parse(implode('.', $ip_parts)) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = $range;
		}

		return true;
	}

	/**
	 * Validate an IPv6 address range.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidRangeIPv6($range) {
		if (!$this->options['v6']) {
			return false;
		}

		$parts = explode(':', $range);

		$ip_count = '1';
		$ip_parts = [];

		foreach ($parts as $part) {
			if (preg_match('/^([a-f0-9]{1,4})-([a-f0-9]{1,4})$/i', $part, $matches)) {
				sscanf($matches[1], '%x', $from);
				sscanf($matches[2], '%x', $to);

				if (!$this->options['ranges'] || $from > $to) {
					return false;
				}

				$ip_count = bcmul($ip_count, $to - $from + 1, 0);
				$ip_parts[] = $matches[1];
			}
			else {
				$ip_parts[] = $part;
			}
		}

		if ($this->ipv6_parser->parse(implode(':', $ip_parts)) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (bccomp($this->max_ip_count, $ip_count) < 0) {
			$this->max_ip_count = $ip_count;
			$this->max_ip_range = $range;
		}

		return true;
	}

	/**
	 * Validate a DNS name.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidDns($range) {
		if (!$this->options['dns']) {
			return false;
		}

		if ($this->dns_parser->parse($range) != CParser::PARSE_SUCCESS) {
			return false;
		}

		if (bccomp($this->max_ip_count, 1) < 0) {
			$this->max_ip_count = '1';
			$this->max_ip_range = $range;
		}

		return true;
	}

	/**
	 * Validate a user macros syntax.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidUserMacro($range) {
		if (!$this->options['usermacros']) {
			return false;
		}

		return ($this->user_macro_parser->parse($range) == CParser::PARSE_SUCCESS);
	}

	/**
	 * Validate a host macros syntax.
	 * Example: {HOST.IP}, {HOST.CONN} etc.
	 *
	 * @param string $range
	 *
	 * @return bool
	 */
	protected function isValidMacro($range) {
		if (!$this->options['macros']) {
			return false;
		}

		return ($this->macro_parser->parse($range) == CParser::PARSE_SUCCESS);
	}
}
