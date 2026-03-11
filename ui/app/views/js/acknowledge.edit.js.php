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

window.update_problem_popup = new class {
	init({rules}) {
		this.overlay = overlays_stack.getById('acknowledge.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.problem_suppressible = !document.getElementById('suppress_problem').disabled;
		this.problem_unsuppressible = !document.getElementById('unsuppress_problem').disabled;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'problem.view');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initEvents();
	}

	#initEvents () {
		this.form_element.querySelectorAll('.js-operation-checkbox').forEach((input) => {
			input.addEventListener('change', (e) => {
				if (e.target.name === 'change_severity') {
					this.form_element.querySelectorAll('[name="severity"]').forEach(el => {
						el.disabled = !e.target.checked;
					});
				}

				this.#update();
			});
		});

		document.getElementById('suppress_time_option').addEventListener('change', () =>
			this.#update_suppress_time_options()
		);

		document.getElementById('message').addEventListener('input', () => this.#validateOperations());
		document.getElementById('message').addEventListener('blur', () => this.#validateOperations());

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());
	}

	#update() {
		const suppress_checked = document.getElementById('suppress_problem').checked;
		const unsuppress_checked = document.getElementById('unsuppress_problem').checked;
		const close_problem_checked = document.getElementById('close_problem').checked;

		this.#update_suppress_problem_state(close_problem_checked || unsuppress_checked);
		this.#update_unsuppress_problem_state(close_problem_checked || suppress_checked);
		this.#update_suppress_time_options();

		this.#validateOperations();
	}

	#update_suppress_problem_state(state) {
		if (this.problem_suppressible) {
			document.getElementById('suppress_problem').disabled = state;

			if (state) {
				document.getElementById('suppress_problem').checked = false;
			}
		}
	}

	#update_unsuppress_problem_state(state) {
		if (this.problem_unsuppressible) {
			document.getElementById('unsuppress_problem').disabled = state;

			if (state) {
				document.getElementById('unsuppress_problem').checked = false;
			}
		}
	}

	#update_suppress_time_options() {
		for (const element of document.querySelectorAll('#suppress_time_option input[type="radio"]')) {
			element.disabled = !document.getElementById('suppress_problem').checked;

			document.getElementById('suppress_until_problem').disabled = element.disabled;
			document.getElementById('suppress_until_problem_calendar').disabled = element.disabled;
		}

		const time_option_checked = document.querySelector('#suppress_time_option input:checked').value;

		if (time_option_checked == <?= ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE ?>) {
			document.getElementById('suppress_until_problem').disabled = true;
			document.getElementById('suppress_until_problem_calendar').disabled = true;
		}
	}

	#validateOperations() {
		const checked_operations = this.form_element.querySelectorAll('.js-operation-checkbox:checked').length;

		document.getElementById('operation_count').value = checked_operations +
			this.form.findFieldByName('message').getValue()?.length ? 1 : 0

		this.form.validateChanges(['operation_count'], true);
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
				curl.setArgument('action', 'popup.acknowledge.create');

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

						overlayDialogueDestroy(this.overlay.dialogueid);
						this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
						$.publish('acknowledge.create', [response, this.overlay]);
					})
					.catch((exception) => this.#ajaxExceptionHandler(exception))
					.finally(() => this.overlay.unsetLoading());
			});
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
