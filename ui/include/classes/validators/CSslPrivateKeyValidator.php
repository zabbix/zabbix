<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CSslPrivateKeyValidator extends CValidator {

	/**
	 * Checks if provided value is PEM-encoded private key.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!extension_loaded('openssl')) {
			$this->setError(_('OpenSSL extension is not available'));

			return false;
		}

		if (!openssl_pkey_get_private($value)) {
			$this->setError(_('a PEM-encoded private key is expected'));

			return false;
		}

		return true;
	}
}
