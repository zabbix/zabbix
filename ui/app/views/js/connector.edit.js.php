<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

window.connector_edit_popup = new class {

	init({rules, clone_rules, tags}) {
		this.overlay = overlays_stack.getById('connector.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.clone_rules = clone_rules;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'connector.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		jQuery('#tags').dynamicRows({
			template: '#tag-row-tmpl',
			rows: tags,
			allow_empty: true
		});

		this.#initEvents();
		this.#updateForm();

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));

		this.form_element.style.display = '';
		this.overlay.recoverFocus();
	}

	#initEvents() {
		for (const id of ['data_type', 'tags', 'authtype', 'max_records_mode']) {
			document.getElementById(id).addEventListener('input', () => this.#updateForm());
		}

		document.getElementById('max_attempts').addEventListener('input', () => {
			this.#updateForm();
			const field = this.form.findFieldByName('attempt_interval');

			if (field.isDisabled()) {
				field.unsetErrors();
				field.showErrors();
			}
			else {
				this.form.validateChanges(['attempt_interval']);
			}
		});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
	}

	#updateForm() {
		const data_type = this.form_element.querySelector('[name="data_type"]:checked').value;

		for (const element of this.form_element.querySelectorAll('.js-field-item-value-types')) {
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

		for (const field of this.form_element.querySelectorAll('.js-field-username, .js-field-password')) {
			field.style.display = use_username_password ? '' : 'none';
		}

		for (const field of this.form_element.querySelectorAll('.js-field-token')) {
			field.style.display = use_token ? '' : 'none';
		}

		const max_records_mode = this.form_element.querySelector('[name="max_records_mode"]:checked').value;
		document.getElementById('max_records').style.display = max_records_mode == 0 ? 'none' : '';

		document.getElementById('attempt_interval').disabled = document.getElementById('max_attempts').value <= 1;
	}

	#clone() {
		document.getElementById('connectorid').remove();

		const title = <?= json_encode(_('New connector')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: ZBX_STYLE_BTN_ALT,
				cancel: true,
				action: ''
			}
		];

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());

		this.overlay.recoverFocus();
		this.overlay.containFocus();
		this.form.reload(this.clone_rules);
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete selected connector?')) ?>)) {
			this.#removePopupMessages();
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'connector.delete');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('connector')) ?>);

			this.#post(curl.getUrl(), {connectorids: [document.getElementById('connectorid').value]}, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				const curl = new Curl('zabbix.php');
				const action = document.getElementById('connectorid') !== null
					? 'connector.update'
					: 'connector.create';

				curl.setArgument('action', action);

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
			});
	}

	#post(url, data, success_callback) {
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
};
