<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * Specifies the maximum amount of allowed IP addresses. If set to 0, it is possible to select all IP range
	 * 0-255.0-255.0-255.0-255 and 0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff.
	 *
	 * @var int
	 */
	public $ipMaxCount = 0;

	/**
	 * Validate ranges for the provided comma-separated string of IP's.
	 *
	 * @param string $ipRangeList
	 *
	 * @return bool
	 */
	public function validate($ipRangeList) {
		if (!is_string($ipRangeList)) {
			$this->setError(_s('Invalid IP range "%1$s": must be a string.', $this->stringify($ipRangeList)));

			return false;
		}

		if ($ipRangeList === '') {
			$this->setError(_('IP range cannot be empty.'));

			return false;
		}

		$isRangeValid = true;

		$this->ipMaxCount = (string) $this->ipMaxCount;

		foreach (explode(',', $ipRangeList) as $ipRange) {
			if (strpos($ipRange, '/') !== false) {
				$isRangeValid &= $this->isValidMask($ipRange);
			}
			else {
				$isRangeValid &= $this->isValidRange($ipRange);
			}
		}

		return (bool) $isRangeValid;
	}

	/**
	 * Validate IP mask. IP/bits.
	 * bits range for IPv4: 16 - 30
	 * bits range for IPv6: 112 - 128
	 *
	 * @param string $ipMask
	 *
	 * @return bool
	 */
	protected function isValidMask($ipMask) {
		$parts = explode('/', $ipMask);

		if (count($parts) > 2) {
			$this->setError(_s('Invalid IP address range "%1$s".', $ipMask));

			return false;
		}

		$ip = $parts[0];
		$bits = $parts[1];

		if ($this->isValidIPv4($ip)) {
			$isMaskNumber = preg_match('/^[0-9]{1,2}$/', $bits);
			$bitsLeft = 32 - $bits;
		}
		elseif ($this->isValidIPv6($ip)) {
			$isMaskNumber = preg_match('/^[0-9]{1,3}$/', $bits);
			$bitsLeft = 128 - $bits;
		}
		else {
			$this->setError(_s('Invalid IP address "%1$s".', $ipMask));

			return false;
		}

		if (!$isMaskNumber || $bitsLeft < 0) {
			$this->setError(_s('Invalid network mask "%1$s".', $ipMask));

			return false;
		}

		if ($this->ipMaxCount != 0) {
			$ipCount = bcpow('2', (string) $bitsLeft);

			if (bccomp($ipCount, $this->ipMaxCount) > 0) {
				$this->setError(_s('Invalid network mask "%1$s".', $ipMask));

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate ranges in the IP.
	 *
	 * @param string $ipRange
	 *
	 * @return bool
	 */
	protected function isValidRange($ipRange) {
		if (strpos($ipRange, '.') !== false) {
			return $this->isValidRangeIPv4($ipRange);
		}
		elseif (ZBX_HAVE_IPV6 && strpos($ipRange, ':') !== false) {
			return $this->isValidRangeIPv6($ipRange);
		}
		else {
			$this->setError(_s('Invalid IP address "%1$s".', $ipRange));

			return false;
		}
	}

	/**
	 * Validate an IP range in IPv4.
	 *
	 * @param string $ipRange
	 *
	 * @return bool
	 */
	protected function isValidRangeIPv4($ipRange) {
		$ipCount = 0;

		// To validate an IP, use this array to construct the starting IP of the range and then validate it.
		$ipPartsForValidation = array();

		$ipParts = explode('.', $ipRange);

		foreach ($ipParts as $part) {
			// Check if we have the range in the part.
			if (strpos($part, '-') !== false) {
				$rangeParts = explode('-', $part);

				// Check that we got only 2 parts and if IP part conforms to IPv4 definition format.
				if (count($rangeParts) != 2 || !preg_match('/^([0-9]{1,3}-[0-9]{1,3})$/', $part)) {
					$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

					return false;
				}

				sscanf($rangeParts[0], "%d", $fromValue);
				sscanf($rangeParts[1], "%d", $toValue);

				// Check that end IP is not bigger that 255 and start IP is smaller than end.
				if ($toValue > 255 || $fromValue > $toValue) {
					$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

					return false;
				}

				$ipsInRange = $toValue - $fromValue + 1;

				// Counting the amount of IP's in the range.
				if ($ipCount == 0) {
					$ipCount = $ipsInRange;
				}
				else {
					$ipCount = bcmul((string) $ipCount, (string) $ipsInRange);
				}

				$ipPartsForValidation[] = $rangeParts[0];
			}
			else {
				$ipPartsForValidation[] = $part;
			}
		}

		$ip = implode('.', $ipPartsForValidation);

		if (!$this->isValidIPv4($ip)) {
			$this->setError(_s('Invalid IP address "%1$s".', $ipRange));

			return false;
		}

		// Check if IP count in the given range is bigger that the limit.
		if ($this->ipMaxCount != 0 && bccomp((string) $ipCount, $this->ipMaxCount) > 0) {
			$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

			return false;
		}

		return true;
	}

	/**
	 * Validate an IP range in IPv6.
	 *
	 * @param string $ipRange
	 *
	 * @return bool
	 */
	protected function isValidRangeIPv6($ipRange) {
		$ipCount = 0;

		// To validate an IP, use this array to construct the starting IP of the range and then validate it.
		$ipPartsForValidation = array();

		$ipParts = explode(':', $ipRange);

		foreach ($ipParts as $part) {
			// Check if we have the range in the part.
			if (strpos($part, '-')) {
				$rangeParts = explode('-', $part);

				// Check that we got only 2 parts and if IP part conforms to IPv6 definition format.
				if (count($rangeParts) != 2 || !preg_match('/^([a-f0-9]{1,4}-[a-f0-9]{1,4})$/i', $part)) {
					$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

					return false;
				}

				sscanf($rangeParts[0], "%x", $fromValue);
				sscanf($rangeParts[1], "%x", $toValue);

				// Check that end IP is not bigger that 65535 and start IP is smaller than end.
				if ($toValue > 65535 || $fromValue > $toValue) {
					$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

					return false;
				}

				$ipsInRange = $toValue - $fromValue + 1;

				// Counting the amount of IP's in the range.
				if ($ipCount == 0) {
					$ipCount = $ipsInRange;
				}
				else {
					$ipCount = bcmul((string) $ipCount, (string) $ipsInRange);
				}

				$ipPartsForValidation[] = $rangeParts[0];
			}
			else {
				$ipPartsForValidation[] = $part;
			}
		}

		$ip = implode(':', $ipPartsForValidation);

		if (!$this->isValidIPv6($ip)) {
			$this->setError(_s('Invalid IP address "%1$s".', $ipRange));

			return false;
		}

		// Check if IP count in the given range is bigger that the limit.
		if ($this->ipMaxCount != 0 && bccomp((string) $ipCount, $this->ipMaxCount) > 0) {
			$this->setError(_s('Invalid IP address range "%1$s".', $ipRange));

			return false;
		}

		return true;
	}
}
