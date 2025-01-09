<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

window.connector_edit_popup = new class {

	init({connectorid, tags}) {
		this.connectorid = connectorid;

		this.overlay = overlays_stack.getById('connector.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'connector.list');
		this.overlay.backurl = backurl.getUrl();

		jQuery('#tags').dynamicRows({
			template: '#tag-row-tmpl',
			rows: tags,
			allow_empty: true
		});

		for (const id of ['data_type', 'tags', 'authtype', 'max_records_mode', 'max_attempts']) {
			document.getElementById(id).addEventListener('change', () => this._updateForm());
		}

		this._updateForm();

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));

		this.form.style.display = '';
		this.overlay.recoverFocus();
	}

	_updateForm() {
		const data_type = this.form.querySelector('[name="data_type"]:checked').value;

		for (const element of this.form.querySelectorAll('.js-field-item-value-types')) {
			element.style.display = data_type == <?= ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES ?> ? '' : 'none';
		}

		for (const tag_operator of document.getElementById('tags').querySelectorAll('.js-tag-operator')) {
			const tag_value = tag_operator.closest('.form_row').querySelector('.js-tag-value');

			tag_value.style.display = tag_operator.value == <?= CONDITION_OPERATOR_EXISTS ?>
				|| tag_operator.value == <?= CONDITION_OPERATOR_NOT_EXISTS ?> ? 'none' : '';
		}

		const authtype = document.getElementById('authtype').value;
		const use_username_password = authtype == <?= ZBX_HTTP_AUTH_BASIC ?> || authtype == <?= ZBX_HTTP_AUTH_NTLM ?>
			|| authtype == <?= ZBX_HTTP_AUTH_KERBEROS ?> || authtype == <?= ZBX_HTTP_AUTH_DIGEST ?>;
		const use_token = authtype == <?= ZBX_HTTP_AUTH_BEARER ?>;

		for (const field of this.form.querySelectorAll('.js-field-username, .js-field-password')) {
			field.style.display = use_username_password ? '' : 'none';
		}

		for (const field of this.form.querySelectorAll('.js-field-token')) {
			field.style.display = use_token ? '' : 'none';
		}

		const max_records_mode = this.form.querySelector('[name="max_records_mode"]:checked').value;
		document.getElementById('max_records').style.display = max_records_mode == 0 ? 'none' : '';

		document.getElementById('attempt_interval').disabled = document.getElementById('max_attempts').value <= 1;
	}

	clone({title, buttons}) {
		this.connectorid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'connector.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('connector')) ?>);

		this._post(curl.getUrl(), {connectorids: [this.connectorid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.connectorid != null) {
			fields.connectorid = this.connectorid;
		}

		const fields_to_trim = ['name', 'url', 'username', 'token', 'attempt_interval', 'timeout', 'http_proxy',
			'ssl_cert_file', 'ssl_key_file', 'description'
		];
		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		if ('tags' in fields) {
			for (const tag of Object.values(fields.tags)) {
				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.connectorid !== null ? 'connector.update' : 'connector.create');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
