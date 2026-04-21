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


/**
 * @var CView $this
 */
?>

<script>
const view = new class {
	init({rules, default_values}) {
		this.form_element = document.getElementById('audit-settings');
		this.form = new CForm(this.form_element, rules);
		this.rules = rules;
		this.default_values = default_values;

		this.#initEvents();
	}

	#initEvents() {
		this.form_element.addEventListener('submit', (e) => this.#submit(e));

		this.form_element.querySelector('.table-forms .tfoot-buttons .js-reset-defaults')
			.addEventListener('click', (e) => this.#resetDefaults(e.target));

		document.getElementById('auditlog_enabled').addEventListener('change', (e) => {
			document.getElementById('auditlog_mode').disabled = !e.target.checked;
		});

		document.getElementById('hk_audit_mode').addEventListener('change', (e) => {
			document.getElementById('hk_audit').disabled = !e.target.checked;
		});
	}

	#resetDefaults(reset_button) {
		overlayDialogue({
			title: <?= json_encode(_('Reset confirmation')) ?>,
			content: document.createElement('span').innerText = <?= json_encode(
				_('Reset all fields to default values?')
			) ?>,
			buttons: [
				{
					title: <?= json_encode(_('Cancel')) ?>,
					cancel: true,
					class: '<?= ZBX_STYLE_BTN_ALT ?>',
					action: () => {}
				},
				{
					title: <?= json_encode(_('Reset defaults')) ?>,
					focused: true,
					action: () => {
						clearMessages();

						Object.entries(this.default_values).forEach(([key, value]) => {
							const input = document.getElementById(key);
							if (input) {
								if (input.getAttribute('type') === 'checkbox') {
									input.checked = value;
									input.dispatchEvent(new Event('change'));
								}
								else {
									input.value = value;
								}
							}
						});

						this.form.reload(this.rules);
					}
				}
			]
		}, {
			position: Overlay.prototype.POSITION_CENTER,
			trigger_element: reset_button
		});
	}

	#submit(e) {
		e.preventDefault();
		this.#setLoadingStatus('js-submit');
		clearMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#unsetLoadingStatus();
					return;
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'audit.settings.update');

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
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

						if ('success' in response) {
							postMessageOk(response.success.title);

							if ('messages' in response.success) {
								postMessageDetails('success', response.success.messages);
							}

							location.href = location.href;
						}
					})
					.catch((exception) => this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetLoadingStatus());
			});
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

		addMessage(makeMessageBox('bad', messages, title)[0]);
	}

	#setLoadingStatus(loading_btn_class) {
		this.form_element.classList.add('is-loading', 'is-loading-fadein');

		this.form_element.querySelectorAll('.table-forms .tfoot-buttons button').forEach(button => {
			button.disabled = true;

			if (button.classList.contains(loading_btn_class)) {
				button.classList.add('is-loading');
			}
		});
	}

	#unsetLoadingStatus() {
		this.form_element.querySelectorAll('.table-forms .tfoot-buttons button').forEach(button => {
			button.classList.remove('is-loading');
			button.disabled = false;
		});

		this.form_element.classList.remove('is-loading', 'is-loading-fadein');
	}
};
</script>
