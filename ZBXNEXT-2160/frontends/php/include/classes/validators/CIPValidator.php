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
 * Class containing methods for IP address validation.
 */
class CIPValidator extends CValidator {

	/**
	 * If set to false, the string cannot be empty.
	 *
	 * @var bool
	 */
	public $empty = false;

	/**
	 * If set to false, only IPv4 and IPv6 is allowed. If set to true, macros are allowed instead of IP address.
	 *
	 * @var bool
	 */
	public $allowMacros = false;

	/**
	 * Checks if the given IP address not empty and is a valid IPv4 or IPv6 address. If IP address is
	 * invalid, sets an error and returns false. Otherwise returns true.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function validate($ip) {
		if (!$this->empty && zbx_empty($ip)) {
			$this->setError(_('IP address cannot be empty.'));

			return false;
		}
		elseif (!is_string($ip)) {
			$this->setError(_s('Invalid IP address "%1$s": must be a string.', $this->stringify($ip)));

			return false;
		}

		$isValidIp = ($this->isValidIPv4($ip) || $this->isValidIPv6($ip));

		/*
		 * If macros are not allowed, return true or false depeding on whether IP address is valid. If macros are
		 * allowed and IP address is invalid, check if string contains a valid macro.
		 */
		if (!$this->allowMacros) {
			return $isValidIp;
		}
		elseif (!$isValidIp && !preg_match('/^'.ZBX_PREG_MACRO_NAME_FORMAT.'$/i', $ip)
				&& !preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $ip)) {
			$this->setError(_s('Invalid IP address "%1$s".', $ip));

			return false;
		}

		return true;
	}

	/**
	 * Validate IPv4 address.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	protected function isValidIPv4($ip) {
		if (!preg_match('/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/', $ip, $matches)) {
			$this->setError(_s('Invalid IP address "%1$s".', $ip));

			return false;
		}

		for ($i = 1; $i <= 4; $i++) {
			if (!is_numeric($matches[$i]) || $matches[$i] > 255 || $matches[$i] < 0 ) {
				$this->setError(_s('Invalid IP address "%1$s".', $ip));

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate IPv6 address.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	protected function isValidIPv6($ip) {
		if (!ZBX_HAVE_IPV6) {
			$this->setError(_s('Invalid IP address "%1$s".', $ip));

			return false;
		}

		$patterns = array(
			'([a-f0-9]{1,4}:){7}[a-f0-9]{1,4}',
			':(:[a-f0-9]{1,4}){1,7}',
			'[a-f0-9]{1,4}::([a-f0-9]{1,4}:){0,5}[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){2}:([a-f0-9]{1,4}:){0,4}[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){3}:([a-f0-9]{1,4}:){0,3}[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){4}:([a-f0-9]{1,4}:){0,2}[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){5}:([a-f0-9]{1,4}:){0,1}[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){6}:[a-f0-9]{1,4}',
			'([a-f0-9]{1,4}:){1,7}:',
			'::'
		);

		$pattern = '/^('.implode(')$|^(', $patterns).')$/i';

		if (!preg_match($pattern, $ip)) {
			$this->setError(_s('Invalid IP address "%1$s".', $ip));

			return false;
		}

		return true;
	}

	/**
	 * Check if given string has a dot, it is considered as a type of IPv4 address.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	protected function isIPv4($ip) {
		return (strpos($ip, '.') !== false);
	}

	/**
	 * Check if IPv6 is enabled and given string has a colon. If so it is considered as a type of IPv6 address.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	protected function isIPv6($ip) {
		return (ZBX_HAVE_IPV6 && strpos($ip, ':') !== false);
	}
}
