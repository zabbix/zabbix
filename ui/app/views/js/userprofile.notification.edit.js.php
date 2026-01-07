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
			this.form_element = document.getElementById('userprofile-notification-form');
			this.form = new CForm(this.form_element, rules);

			this.#toggleFrontendNoticationSettingsEnabled();
			this.#initActions();
		}

		#toggleFrontendNoticationSettingsEnabled() {
			document
				.getElementById('notificationsTab')
				.querySelectorAll('input:not([name="messages[enabled]"]),button,z-select')
				.forEach((elem) => {
					elem.toggleAttribute('disabled', !document.getElementById('messages_enabled').checked);
				});
		}

		#initActions() {
			this.form_element.addEventListener('submit', (e) => {
				e.preventDefault();
				this.#submit();
			});

			document.getElementById('messages_enabled').addEventListener('click', () => {
				this.#toggleFrontendNoticationSettingsEnabled();
			});

			document.querySelector('#notificationsTab').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-test_sound')) {
					testUserSound(`messages_sounds.${e.target.dataset.message_sounds}`);
				}
				else if (e.target.classList.contains('js-audio_stop')) {
					AudioControl.stop();
				}
			});
		}

		#submit() {
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
					curl.setArgument('action', 'userprofile.notification.update');

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
