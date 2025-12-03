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


class CSslCertificateValidator extends CValidator {

	/**
	 * Cheks if provided value is PEM-encoded certificate.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!openssl_x509_parse($value)) {
			$this->setError(_('a PEM-encoded certificate is expected'));

			return false;
		}
		return true;
	}
}
