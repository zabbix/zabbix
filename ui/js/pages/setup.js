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


const ZBX_DB_MYSQL		= 'MYSQL';
const ZBX_DB_POSTGRESQL	= 'POSTGRESQL';

const DB_STORE_CREDS_VAULT_HASHICORP	= 1;
const DB_STORE_CREDS_VAULT_CYBERARK		= 2;

const STEP_WELCOME			= 1;
const STEP_DB_CONNECTION	= 3;
const STEP_SETTINGS			= 4;

const view = new class {
	init({step, hashicorp_endpoint_default, cyberark_endpoint_default}) {
		this._hashicorp_endpoint_default = hashicorp_endpoint_default;
		this._cyberark_endpoint_default = cyberark_endpoint_default;

		const form = document.getElementById('setup-form');

		switch (step) {
			case STEP_WELCOME:
				document.getElementById('default-lang').addEventListener('change', () => form.submit());
				break;

			case STEP_DB_CONNECTION:
				for (const id of ['type', 'server', 'tls_encryption', 'verify_certificate', 'creds_storage',
						'vault_certificates_toggle', 'vault_url']) {
					document.getElementById(id).addEventListener('change', () => this._update());
				}

				form.addEventListener('submit', () => {
					const input_ids = ['server', 'database', 'schema', 'vault_url', 'vault_prefix_hashicorp',
						'vault_prefix_cyberark', 'vault_db_path', 'vault_query_string', 'vault_token',
						'vault_cert_file', 'vault_key_file', 'ca_file', 'key_file', 'cert_file'
					];

					for (const id of input_ids) {
						const input = document.getElementById(id);

						input.value = input.value.trim();
					}
				});

				this._update();
				break;

			case STEP_SETTINGS:
				document.getElementById('default-theme').addEventListener('change', () => form.submit())
				break;
		}
	}

	_update() {
		const verify_host = document.getElementById('verify_host');
		const tls_encryption = document.getElementById('tls_encryption');
		const tls_encryption_hint = document.getElementById('tls_encryption_hint');
		const vault_url = document.getElementById('vault_url');
		const db_warning = document.getElementById('db_warning');

		const db_type = document.querySelector('[name=type]').value;
		const host = document.querySelector('[name=server]').value;

		const encryption_enabled = tls_encryption.checked;
		const encryption_supported = (db_type === ZBX_DB_MYSQL || db_type === ZBX_DB_POSTGRESQL);
		const encryption_allowed = (host !== ''
			&& ((db_type === ZBX_DB_MYSQL && host !== 'localhost')
				|| (db_type === ZBX_DB_POSTGRESQL && !host.startsWith('/'))));

		const encryption_customizable = (encryption_supported && encryption_allowed && encryption_enabled
			&& document.getElementById('verify_certificate').checked);

		const vault_selected = parseInt(document.querySelector('input[name="creds_storage"]:checked').value);
		const vault_enabled = [DB_STORE_CREDS_VAULT_HASHICORP, DB_STORE_CREDS_VAULT_CYBERARK].includes(vault_selected);
		const vault_certificates_enabled = document.getElementById('vault_certificates_toggle').checked;

		const rows = {
			'db_schema_row': db_type === ZBX_DB_POSTGRESQL,
			'db_encryption_row': encryption_supported,
			'db_verify_host': encryption_supported && encryption_allowed && encryption_enabled,
			'db_keyfile_row': encryption_customizable,
			'db_certfile_row': encryption_customizable,
			'db_cafile_row': encryption_customizable,
			'db_verify_host_row': encryption_customizable,
			'db_cipher_row': encryption_customizable && db_type === ZBX_DB_MYSQL,
			'vault_url_row': vault_enabled,
			'vault_prefix_hashicorp_row': vault_selected == DB_STORE_CREDS_VAULT_HASHICORP,
			'vault_db_path_row': vault_selected == DB_STORE_CREDS_VAULT_HASHICORP,
			'vault_token_row': vault_selected == DB_STORE_CREDS_VAULT_HASHICORP,
			'db_user': !vault_enabled,
			'db_password': !vault_enabled,
			'vault_prefix_cyberark_row': vault_selected == DB_STORE_CREDS_VAULT_CYBERARK,
			'vault_query_string_row': vault_selected == DB_STORE_CREDS_VAULT_CYBERARK,
			'vault_certificates_row': vault_selected == DB_STORE_CREDS_VAULT_CYBERARK,
			'vault_cert_file_row': vault_selected == DB_STORE_CREDS_VAULT_CYBERARK && vault_certificates_enabled,
			'vault_key_file_row': vault_selected == DB_STORE_CREDS_VAULT_CYBERARK && vault_certificates_enabled
		};

		for (let id in rows) {
			const element = document.getElementById(id);

			element.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !rows[id]);

			for (const input of element.querySelectorAll('input')) {
				if (!rows[id]) {
					input.setAttribute('disabled', 'disabled');
				}
				else {
					input.removeAttribute('disabled');
				}
			}
		}

		if (vault_enabled
				&& [this._hashicorp_endpoint_default, this._cyberark_endpoint_default].includes(vault_url.value)) {
			vault_url.value = vault_selected == DB_STORE_CREDS_VAULT_CYBERARK
				? this._cyberark_endpoint_default
				: this._hashicorp_endpoint_default;
		}

		// TLS encryption checkbox and secure connection hint message.
		if (encryption_supported) {
			if (!encryption_allowed) {
				document.querySelector('input + [for=tls_encryption]').classList.add(ZBX_STYLE_DISPLAY_NONE);
				tls_encryption.setAttribute('disabled', 'disabled');
				tls_encryption_hint.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			}
			else {
				document.querySelector('input + [for=tls_encryption]').classList.remove(ZBX_STYLE_DISPLAY_NONE);
				tls_encryption.removeAttribute('disabled');
				tls_encryption_hint.classList.add(ZBX_STYLE_DISPLAY_NONE);
			}
		}

		// Verify host checkbox availability.
		if (db_type === ZBX_DB_MYSQL) {
			verify_host.checked = true;
			verify_host.setAttribute('checked', true);
			verify_host.setAttribute('disabled', 'disabled');
		}
		else if (encryption_customizable) {
			verify_host.removeAttribute('disabled');
		}
	}
};
