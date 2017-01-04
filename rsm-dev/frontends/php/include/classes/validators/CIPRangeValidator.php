<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class containing methods for IP range and network mask validation.
 */
class CIPRangeValidator extends CIPValidator {

	/**
	 * Maximum amount of IP addresses for each range (configuration parameter).
	 *
	 */
	public $ipRangeLimit = 0;

	/**
	 * Maximum amount of IP addresses.
	 *
	 * @var int
	 */
	private $maxIPCount;

	/**
	 * IP address range with maximum amount of IP addresses.
	 *
	 * @var string
	 */
	private $maxIPRange;

	/**
	 * Validate comma-separated IP address ranges.
	 *
	 * @param string $ranges
	 *
	 * @return bool
	 */
	public function validate($ranges) {
		if (!is_string($ranges)) {
			$this->setError(_s('Invalid IP address range "%1$s": must be a string.', $this->stringify($ranges)));

			return false;
		}

		if ($ranges === '') {
			$this->setError(_('IP address range cannot be empty.'));

			return false;
		}

		$this->maxIPCount = 0;
		$this->maxIPRange = '';

		foreach (explode(',', $ranges) as $range) {
			$range = trim($range, " \t\r\n");

			if (!$this->isValidMask($range) && !$this->isValidRange($range)) {
				$this->setError(_s('Invalid IP address range "%1$s".', $range));

				return false;
			}
		}

		if ($this->ipRangeLimit != 0 && bccomp($this->maxIPCount, $this->ipRangeLimit) > 0) {
			$this->setError(
				_s('IP range "%1$s" exceeds "%2$s" address limit.', $this->maxIPRange, $this->ipRangeLimit)
			);

			return false;
		}

		return true;
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

		if (!$this->isValidIPv4($parts[0])) {
			return false;
		}

		if (!preg_match('/^[0-9]{1,2}$/', $parts[1]) || $parts[1] > 30) {
			return false;
		}

		$ipCount = bcpow(2, 32 - $parts[1]);

		if ($this->maxIPCount < $ipCount) {
			$this->maxIPCount = $ipCount;
			$this->maxIPRange = $range;
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
		$parts = explode('/', $range);

		if (count($parts) != 2) {
			return false;
		}

		if (!$this->isValidIPv6($parts[0])) {
			return false;
		}

		if (!preg_match('/^[0-9]{1,3}$/', $parts[1]) || $parts[1] > 128) {
			return false;
		}

		$ipCount = bcpow(2, 128 - $parts[1]);

		if ($this->maxIPCount < $ipCount) {
			$this->maxIPCount = $ipCount;
			$this->maxIPRange = $range;
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

		$ipCount = 1;
		$ipParts = [];

		foreach ($parts as $part) {
			if (preg_match('/^([0-9]{1,3})-([0-9]{1,3})$/', $part, $matches)) {
				if ($matches[1] > $matches[2]) {
					return false;
				}

				$ipCount = bcmul($ipCount, $matches[2] - $matches[1] + 1);
				$ipParts[] = $matches[2];
			}
			else {
				$ipParts[] = $part;
			}
		}

		if (!$this->isValidIPv4(implode('.', $ipParts))) {
			return false;
		}

		if ($this->maxIPCount < $ipCount) {
			$this->maxIPCount = $ipCount;
			$this->maxIPRange = $range;
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
		$parts = explode(':', $range);

		$ipCount = 1;
		$ipParts = [];

		foreach ($parts as $part) {
			if (preg_match('/^([a-f0-9]{1,4})-([a-f0-9]{1,4})$/i', $part, $matches)) {
				sscanf($matches[1], '%x', $from);
				sscanf($matches[2], '%x', $to);

				if ($from > $to) {
					return false;
				}

				$ipCount = bcmul($ipCount, $to - $from + 1);
				$ipParts[] = $matches[1];
			}
			else {
				$ipParts[] = $part;
			}
		}

		if (!$this->isValidIPv6(implode(':', $ipParts))) {
			return false;
		}

		if ($this->maxIPCount < $ipCount) {
			$this->maxIPCount = $ipCount;
			$this->maxIPRange = $range;
		}

		return true;
	}
}
