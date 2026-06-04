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
		form = null;
		form_element = null;
		rules = null;

		init({rules}) {
			this.form_element = document.getElementById('image-form');
			this.form = new CForm(this.form_element, rules);
			this.rules = rules;
			this.#initEvents();
		}

		#initEvents() {
			this.form_element.addEventListener('submit', (e) => this.submit(e));

			this.form_element.querySelector('.table-forms .tfoot-buttons .js-delete')
				?.addEventListener('click', () => this.#delete());
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

		submit(e) {
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

					const action = document.getElementById('imageid') !== null
						? 'image.update'
						: 'image.create';

					curl.setArgument('action', action);

					fetch(curl.getUrl(), {
						method: 'POST',
						body: objectToFormData(fields)
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
						.finally(() => this.#unsetLoadingStatus());
				});
		}

		#delete() {
			if (window.confirm('<?=_('Delete selected image?') ?>')) {
				this.#setLoadingStatus('js-delete');
				const fields = this.form.getAllValues();

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'image.delete');
				curl.setArgument('imageid', fields.imageid);
				curl.setArgument('imagetype', fields.imagetype);
				curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('image')) ?>);

				redirect(curl.getUrl(), 'post', 'action', undefined, true);
			}
		}

		#setLoadingStatus(loading_btn_class) {
			this.form_element.classList.add('is-loading', 'is-loading-fadein');

			this.form_element.querySelectorAll('.table-forms .tfoot-buttons button:not(.js-cancel)')
				.forEach(button => {
					button.disabled = true;

					if (button.classList.contains(loading_btn_class)) {
						button.classList.add('is-loading');
					}
				});
		}

		#unsetLoadingStatus() {
			this.form_element.querySelectorAll('.table-forms .tfoot-buttons button:not(.js-cancel)')
				.forEach(button => {
					button.classList.remove('is-loading');
					button.disabled = false;
				});

			this.form_element.classList.remove('is-loading', 'is-loading-fadein');
		}
	};
</script>
