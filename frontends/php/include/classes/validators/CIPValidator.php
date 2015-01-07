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
	 * Checks if the given IP address is a valid IPv4 or IPv6 address. If IP address is invalid, sets an error
	 * and returns false. Otherwise returns true.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function validate($ip) {
		if (!is_string($ip)) {
			$this->setError(_s('Invalid IP address "%1$s": must be a string.', $this->stringify($ip)));

			return false;
		}

		if ($ip === '') {
			$this->setError(_('IP address cannot be empty.'));

			return false;
		}

		if (!$this->isValidIPv4($ip) && !$this->isValidIPv6($ip)) {
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
			return false;
		}

		for ($i = 1; $i <= 4; $i++) {
			if ($matches[$i] > 255) {
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
			return false;
		}

		return true;
	}
}
