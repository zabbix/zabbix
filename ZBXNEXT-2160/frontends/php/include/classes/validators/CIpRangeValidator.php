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

class CIpRangeValidator extends CValidator {

	private $ipCountLimit = 65536;
	private $skipIpCountLimit = false;

	/**
	 * Validate the ranges for the provided comma-separated string of IP's
	 *
	 * @param string $ipRangeList
	 * @param bool   $skipIpCountLimit
	 * @return bool
	 */
	public function validate($ipRangeList, $skipIpCountLimit = false) {

		$this->skipIpCountLimit = $skipIpCountLimit;

		foreach (explode(',', $ipRangeList) as $ipRange) {
			if (strpos($ipRange, '/') !== false) {
				if (!$this->isValidMask($ipRange)) {
					return false;
				}
			}
			else {
				if (!$this->isValidRange($ipRange)) {
					return false;
				}
			}
		}
		return true;
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

		if (count($parts) != 2) {
			$this->setError(_s('Invalid mask format in "%1$s".', $ipMask));
			return false;
		}
		$ip = $parts[0];
		$bits = $parts[1];

		if (validate_ipv4($ip, $arr)) {
			if (!preg_match('/^\d{1,2}$/', $bits)) {
				$this->setError(_s('Invalid mask format in "%1$s".', $ipMask));
				return false;
			}
			if ($bits < 16) {
				$this->setError(_s('Provided network mask "%1$s" contains more than %2$s IPs.', $ipMask, $this->ipCountLimit));
				return false;
			}
			if ($bits > 30) {
				$this->setError(_s('Provided network mask "%1$s" is too small.', $ipMask));
				return false;
			}
		}
		elseif (ZBX_HAVE_IPV6 && validate_ipv6($ip, $arr)) {
			if (!preg_match('/^\d{1,2}$/', $bits)) {
				$this->setError(_s('Invalid mask format in "%1$s".', $ipMask));
				return false;
			}
			if ($bits < 112) {
				$this->setError(_s('Provided network mask "%1$s" contains more than %2$s IPs.', $ipMask, $this->ipCountLimit));
				return false;
			}
			if ($bits > 128) {
				$this->setError(_s('Provided network mask "%1$s" is too small.', $ipMask));
				return false;
			}
		}
		else {
			$this->setError(_s('Invalid IP format for mask "%1$s".', $ipMask));
			return false;
		}
		return true;
	}

	/**
	 * Validate the ranges in the IP
	 *
	 * @param string $ipRange
	 * @return bool
	 */
	protected function isValidRange($ipRange) {

		$ipCount = 0;

		if (strpos($ipRange, '.') !== false) {
			// IPv4
			$ipParts = explode('.', $ipRange);
			// To validate an IP, we use this array to construct the starting IP of the range and then validate it
			$ipPartsForValidation = array();

			foreach ($ipParts as $part) {
				// Check if we have the range in the part
				if (strpos($part, '-') !== false) {
					$rangeParts = explode('-', $part);
					// Check that we got only 2 parts - start and end
					if (count($rangeParts) != 2) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}

					// Check that IP part conforms to IPv4 definition format
					if (!preg_match('/^([0-9]{1,3}-[0-9]{1,3})$/', $part)) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}

					sscanf($rangeParts[0], "%d", $from_value);
					sscanf($rangeParts[1], "%d", $to_value);

					// Check that end IP is not bigger that 255 and start IP is smaller than end
					if (($to_value > 255) || ($from_value > $to_value)) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}
					$ipsInRange = $to_value - $from_value + 1;
					// Counting the amount of IP's in the range
					if ($ipCount === 0) {
						$ipCount = $ipsInRange;
					} else {
						$ipCount = $ipCount * $ipsInRange;
					}
					$ipPartsForValidation[] = $rangeParts[0];
				}
				else {
					$ipPartsForValidation[] = $part;
				}
			}

			if (!validate_ipv4(implode('.', $ipPartsForValidation), $arr)) {
				$this->setError(_s('Incorrect IPv4 format "%1$s".', $ipRange));
				return false;
			}
		}
		elseif (ZBX_HAVE_IPV6 && strlen($ipRange) > 0) {
			// IPv6
			$ipParts = explode(':', $ipRange);
			$ipPartsForValidation = array();

			foreach ($ipParts as $part) {
				// Check if we have the range in the part
				if (strpos($part, '-')) {
					// Check that we got only 2 parts - start and end
					$rangeParts = explode('-', $part);
					if (count($rangeParts) != 2) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}

					// Check that IP part conforms to IPv6 definition format
					if (!preg_match('/^([a-f0-9]{1,4}-[a-f0-9]{1,4})$/i', $part)) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}

					sscanf($rangeParts[0], "%x", $from_value);
					sscanf($rangeParts[1], "%x", $to_value);

					// Check that end IP is not bigger that 65535 and start IP is smaller than end
					if (($to_value > 65535) || ($from_value > $to_value)) {
						$this->setError(_s('Incorrect IP range format "%1$s".', $ipRange));
						return false;
					}
					$ipsInRange = $to_value - $from_value + 1;
					if ($ipCount === 0) {
						$ipCount = $ipsInRange;
					} else {
						$ipCount = $ipCount * $ipsInRange;
					}
					$ipPartsForValidation[] = $rangeParts[0];
				}
				else {
					$ipPartsForValidation[] = $part;
				}
			}

			if (!validate_ipv6(implode(':', $ipPartsForValidation))) {
				$this->setError(_s('Incorrect IPv6 format "%1$s".', $ipRange));
				return false;
			}
		}
		else {
			$this->setError(_s('Incorrect IPv4 format "%1$s".', $ipRange));
			return false;
		}

		// Check if IP count in the given range is bigger that the limit
		if (!$this->skipIpCountLimit && $ipCount > $this->ipCountLimit) {
			$this->setError(_s('IP count in range "%1$s" is above %2$s.', $ipRange, $this->ipCountLimit));
			return false;
		}

		return true;
	}
}
