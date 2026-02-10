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

<script type="text/javascript">
	const view = new class {
		init({rules}) {
			this.form_element = document.getElementById('userprofile-form');
			this.form = new CForm(this.form_element, rules);

			this.form_element.addEventListener('submit', (e) => {
				e.preventDefault();
				this.#submit();
			});

			document.getElementById('change-password-button')?.addEventListener('click', () => {
				document.getElementById('change_password').setAttribute('value', 1);
				this.#displayPasswordChange(true);
			});

			this.#displayPasswordChange(false);

			this.#changeVisibilityAutoLoginLogout();
			this.#autologoutHandler();
		}

		#confirmSubmit() {
			const elem_current = document.getElementById('current_password');
			const elem_password1 = document.getElementById('password1');
			const elem_password2 = document.getElementById('password2');

			if (elem_current && elem_password1 && elem_password2) {
				const current_password = elem_current.value;
				const password1 = elem_password1.value;
				const password2 = elem_password2.value;

				if (password1 !== '' && password2 !== '' && current_password !== '') {
					const warning_msg = <?= json_encode(
						_('In case of successful password change user will be logged out of all active sessions. Continue?')
					) ?>;

					return confirm(warning_msg);
				}
			}

			return true;
		}

		#changeVisibilityAutoLoginLogout() {
			const autologin_cbx = document.querySelector('#autologin');
			const autologout_cbx = document.querySelector('#autologout_visible');

			autologin_cbx.addEventListener('click', (e) => {
				if (e.target.checked) {
					autologout_cbx.checked = false;
				}
				this.#autologoutHandler();
			});

			autologout_cbx.addEventListener('click', (e) => {
				if (e.target.checked) {
					autologin_cbx.checked = false;
				}
				this.#autologoutHandler();
			});
		}

		#autologoutHandler() {
			const autologout_visible = document.querySelector('#autologout_visible');
			const disabled = !autologout_visible.checked;
			const autologout = document.querySelector('#autologout');
			const hidden = autologout.parentElement.
				querySelector(`input[type=hidden][name=${autologout.getAttribute('name')}]`);

			if (disabled) {
				autologout.setAttribute('disabled', '')
			}
			else {
				autologout.removeAttribute('disabled')
			}

			if (!hidden) {
				const hidden_input = document.createElement('input');

				hidden_input.type = 'hidden';
				hidden_input.name = autologout.getAttribute('name');
				hidden_input.value = '0';

				autologout.parentElement.insertBefore(hidden_input, autologout);
			}
			else if (!disabled) {
				hidden.remove();
			}
		}

		#displayPasswordChange(visible = true) {
			if (visible) {
				document.getElementById('current_password')?.removeAttribute('disabled');
				document.getElementById('password1')?.removeAttribute('disabled');
				document.getElementById('password2')?.removeAttribute('disabled');

				this.form_element.querySelectorAll('.password-change-active').forEach(elem => {
					elem.style.display = '';
				})

				this.form_element.querySelectorAll('.password-change-inactive').forEach(elem => {
					elem.style.display = 'none';
				})
			}
			else {
				document.getElementById('current_password')?.setAttribute('disabled', 'disabled');
				document.getElementById('password1')?.setAttribute('disabled', 'disabled');
				document.getElementById('password2')?.setAttribute('disabled', 'disabled');

				this.form_element.querySelectorAll('.password-change-active').forEach(elem => {
					elem.style.display = 'none';
				})

				this.form_element.querySelectorAll('.password-change-inactive').forEach(elem => {
					elem.style.display = '';
				})
			}
		}

		#submit() {
			if (!this.#confirmSubmit()) {
				return;
			}

			this.#setLoadingStatus(['update'])
			clearMessages();
			const fields = this.form.getAllValues();

			this.form.validateSubmit(fields)
				.then((result) => {
					if (!result) {
						this.#unsetLoadingStatus();
						return;
					}

					var curl = new Curl('zabbix.php');
					curl.setArgument('action', 'userprofile.update');

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

								location.href = new URL(response.success.redirect, location.href).href;
							}
						})
						.catch((exception) => this.#ajaxExceptionHandler(exception))
						.finally(() => this.#unsetLoadingStatus())
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

		#setLoadingStatus(loading_ids) {
			this.form_element.classList.add('is-loading', 'is-loading-fadein');

			[
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.setAttribute('disabled', 'disabled');

					if (loading_ids.includes(button.id)) {
						button.classList.add('is-loading');
					}
				}
			});
		}

		#unsetLoadingStatus() {
			[
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.classList.remove('is-loading');
					button.removeAttribute('disabled');
				}
			});

			this.form_element.classList.remove('is-loading', 'is-loading-fadein');
		}
	};
</script>
