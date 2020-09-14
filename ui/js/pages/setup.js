/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


const ZBX_DB_MYSQL = 'MYSQL';
const ZBX_DB_POSTGRESQL = 'POSTGRESQL';
const ZBX_STYLE_DISPLAY_NONE = 'display-none';

function updateElementsAvailability() {
	let db_type = document.querySelector('[name=type]').value,
		host = document.querySelector('[name=server]').value,
		encryption_supported = (db_type === ZBX_DB_MYSQL || db_type === ZBX_DB_POSTGRESQL),
		encryption_allowed = (host !== '' && ((db_type === 'MYSQL' && host !== 'localhost')
			|| (db_type === ZBX_DB_POSTGRESQL && host.substr(0,1) !== '/')
		)),
		encryption_enabled = document.querySelector('#tls_encryption').checked,
		encryption_customizable = (encryption_supported && encryption_allowed && encryption_enabled
			&& document.querySelector('#verify_certificate').checked),
		rows = {
			'#db_schema_row': (db_type === ZBX_DB_POSTGRESQL),
			'#db_encryption_row': encryption_supported,
			'#db_verify_host': (encryption_supported && encryption_allowed && encryption_enabled),
			'#db_keyfile_row': encryption_customizable,
			'#db_certfile_row': encryption_customizable,
			'#db_cafile_row': encryption_customizable,
			'#db_verify_host_row': encryption_customizable,
			'#db_cipher_row': (encryption_customizable && (db_type === ZBX_DB_MYSQL))
		};

	for (let selector in rows) {
		document.querySelector(selector).classList.toggle(ZBX_STYLE_DISPLAY_NONE, !rows[selector]);
		let inputs = document.querySelector(selector).querySelectorAll('input');

		for (let input of inputs) {
			if (rows[selector]) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}
	}

	// TLS encryption checkbox and secure connection hint message.
	if (encryption_supported && !encryption_allowed) {
		document.querySelector('#tls_encryption').setAttribute('disabled', 'disabled');
		document.querySelector('input + [for=tls_encryption]').classList.add(ZBX_STYLE_DISPLAY_NONE);
		document.querySelector('#tls_encryption_hint').classList.remove(ZBX_STYLE_DISPLAY_NONE);
	}
	else {
		document.querySelector('#tls_encryption').removeAttribute('disabled');
		document.querySelector('input + [for=tls_encryption]').classList.remove(ZBX_STYLE_DISPLAY_NONE);
		document.querySelector('#tls_encryption_hint').classList.add(ZBX_STYLE_DISPLAY_NONE);
	}

	// Verify host checkbox availability.
	if (db_type === ZBX_DB_MYSQL) {
		document.querySelector('#verify_host').checked = true;
		document.querySelector('#verify_host').setAttribute('checked', true);
		document.querySelector('#verify_host').setAttribute('disabled', 'disabled');
	}
	else if (encryption_customizable) {
		document.querySelector('#verify_host').removeAttribute('disabled');
	}
}

window.onload = function () {
	// Stage 2, database configuration.
	if (document.querySelector('[name=type]')) {
		document.querySelectorAll('#type,#server,#tls_encryption,#verify_certificate').forEach(
			elm => elm.addEventListener('change', updateElementsAvailability)
		);

		updateElementsAvailability();
	}
}
