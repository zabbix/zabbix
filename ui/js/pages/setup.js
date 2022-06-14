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


const ZBX_DB_MYSQL = 'MYSQL';
const ZBX_DB_POSTGRESQL = 'POSTGRESQL';
const ZBX_STYLE_DISPLAY_NONE = 'display-none';

function updateElementsAvailability() {
	const db_type = document.querySelector('[name=type]').value;
	const host = document.querySelector('[name=server]').value;
	const encryption_enabled = document.querySelector('#tls_encryption').checked;
	const encryption_supported = (db_type === ZBX_DB_MYSQL || db_type === ZBX_DB_POSTGRESQL);
	const encryption_allowed = (host !== '' && ((db_type === ZBX_DB_MYSQL && host !== 'localhost')
		|| (db_type === ZBX_DB_POSTGRESQL && !host.startsWith('/'))));
	const encryption_customizable = (encryption_supported && encryption_allowed && encryption_enabled
		&& document.querySelector('#verify_certificate').checked);
	const vault_enabled = (document.querySelector('#creds_storage_0:checked, #creds_storage_1:checked').value == 1);
	const rows = {
			'#db_schema_row': (db_type === ZBX_DB_POSTGRESQL),
			'#db_encryption_row': encryption_supported,
			'#db_verify_host': (encryption_supported && encryption_allowed && encryption_enabled),
			'#db_keyfile_row': encryption_customizable,
			'#db_certfile_row': encryption_customizable,
			'#db_cafile_row': encryption_customizable,
			'#db_verify_host_row': encryption_customizable,
			'#db_cipher_row': (encryption_customizable && (db_type === ZBX_DB_MYSQL)),
			'#vault_url_row': vault_enabled,
			'#vault_db_path_row': vault_enabled,
			'#vault_token_row': vault_enabled,
			'#db_user': !vault_enabled,
			'#db_password': !vault_enabled
		};

	for (let selector in rows) {
		const elem = document.querySelector(selector);

		elem
			.classList
			.toggle(ZBX_STYLE_DISPLAY_NONE, !rows[selector]);

		for (let input of elem.querySelectorAll('input')) {
			if (rows[selector]) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}
	}

	// TLS encryption checkbox and secure connection hint message.
	if (encryption_supported) {
		if (!encryption_allowed) {
			document
				.querySelector('#tls_encryption')
				.setAttribute('disabled', 'disabled');
			document
				.querySelector('input + [for=tls_encryption]')
				.classList
				.add(ZBX_STYLE_DISPLAY_NONE);
			document
				.querySelector('#tls_encryption_hint')
				.classList
				.remove(ZBX_STYLE_DISPLAY_NONE);
		}
		else {
			document
				.querySelector('#tls_encryption')
				.removeAttribute('disabled');
			document
				.querySelector('input + [for=tls_encryption]')
				.classList
				.remove(ZBX_STYLE_DISPLAY_NONE);
			document
				.querySelector('#tls_encryption_hint')
				.classList
				.add(ZBX_STYLE_DISPLAY_NONE);
		}
	}

	// Verify host checkbox availability.
	if (db_type === ZBX_DB_MYSQL) {
		document
			.querySelector('#verify_host')
			.checked = true;
		document
			.querySelector('#verify_host')
			.setAttribute('checked', true);
		document
			.querySelector('#verify_host')
			.setAttribute('disabled', 'disabled');
	}
	else if (encryption_customizable) {
		document
			.querySelector('#verify_host')
			.removeAttribute('disabled');
	}
}

document.addEventListener('DOMContentLoaded', () => {
	// Stage 1, language selection.
	const lang = document.getElementById('default-lang');
	if (lang) {
		lang.addEventListener('change', () => document.forms['setup-form'].submit());
	}

	// Stage 2, database configuration.
	if (document.querySelector('[name=type]')) {
		document.querySelectorAll('#type, #server, #tls_encryption, #verify_certificate, #creds_storage').forEach(
			(elem) => elem.addEventListener('change', updateElementsAvailability)
		);

		updateElementsAvailability();
	}

	// Stage 4, GUI settings.
	const theme = document.getElementById('default-theme');
	if (theme) {
		theme.addEventListener('change', () => document.forms['setup-form'].submit());
	}
});
