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

window.media_edit_popup = new class {

	/**
	 * @var {Overlay}
	 */
	#overlay;

	/**
	 * @type {HTMLDivElement}
	 */
	#dialogue;

	/**
	 * @type {HTMLFormElement}
	 */
	#form_element;

	/**
	 * @type {CForm}
	 */
	#form

	/**
	 * @type {Object}
	 */
	#mediatypes;

	/**
	 * @type {HTMLElement}
	 */
	#media_type;

	init({rules, mediatypes, sendto_emails}) {
		this.#overlay = overlays_stack.getById('media-edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#mediatypes = mediatypes;
		this.#media_type = document.getElementById('mediatypeid');

		jQuery('#sendto_emails').dynamicRows({
			template: '#sendto-emails-row-tmpl',
			rows: sendto_emails.map(email => ({email})),
			allow_empty: true
		});

		this.#media_type.addEventListener('change', () => this.#updateForm());

		this.#updateForm();
		this.#form.discoverAllFields();

		this.#form_element.style.display = '';
	}

	#updateForm() {
		const mediatypeid = this.#media_type.value;
		if (mediatypeid in this.#mediatypes) {
			document.getElementById('mediatype_type').setAttribute('value', this.#mediatypes[mediatypeid].type);
		}

		const is_type_email = mediatypeid in this.#mediatypes
			&& this.#mediatypes[mediatypeid].type == <?= MEDIA_TYPE_EMAIL ?>;

		for (const field of this.#form_element.querySelectorAll('.js-field-sendto')) {
			field.style.display = is_type_email ? 'none' : '';
		}

		for (const field of this.#form_element.querySelectorAll('.js-field-sendto-emails')) {
			field.style.display = is_type_email ? '' : 'none';
		}

		if (mediatypeid in this.#mediatypes) {
			this.#media_type.querySelector('.focusable').classList.toggle('<?= ZBX_STYLE_COLOR_NEGATIVE ?>',
				this.#mediatypes[mediatypeid].status == <?= MEDIA_STATUS_DISABLED ?>
			);
		}
	}

	submit() {
		const fields = this.#form.getAllValues();
		this.#overlay.setLoading();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();
					return;
				}

				const url = new URL('zabbix.php', location.href);
				url.searchParams.set('action', 'popup.media.check');
				this.#post(url, fields);
			});
	}

	#post(url, data) {
		this.#overlay.setLoading();

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(exception => {
				for (const element of this.#form_element.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title, true, true)[0];

				this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
}
