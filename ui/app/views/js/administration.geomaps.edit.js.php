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

<script>
	const view = new class {
		/**
		 * @type {HTMLFormElement}
		 */
		form_element;

		/**
		 * @type {CForm}
		 */
		form;

		/**
		 * @type {Object}
		 */
		tile_providers = {};

		/**
		 * @type {Object}
		 */
		defaults = {};

		init({rules, tile_providers}) {
			this.form_element = document.getElementById('geomaps-form');
			this.form = new CForm(this.form_element, rules);

			this.tile_providers = tile_providers;
			this.defaults = {
				geomaps_tile_url: '',
				geomaps_max_zoom: ''
			};

			document.querySelector('[name="geomaps_tile_provider"]')
				.addEventListener('change', (e) => this.#tileProviderChange(e));

			this.form_element.addEventListener('submit', (e) => this.#submit(e));
		}

		#tileProviderChange(e) {
			const title_url_field = this.form_element.querySelector('[name=geomaps_tile_url]');
			const attribution_field = this.form_element.querySelector('[name=geomaps_attribution]');
			const attribution_label = attribution_field.parentElement.previousSibling;
			const max_zoom_field = this.form_element.querySelector('[name=geomaps_max_zoom]');

			if (e.target.value !== '') {
				title_url_field.readOnly = true;
				max_zoom_field.readOnly = true;
				title_url_field.tabIndex = -1;
				max_zoom_field.tabIndex = -1;

				attribution_field.parentElement.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				attribution_label.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}
			else {
				title_url_field.readOnly = false;
				max_zoom_field.readOnly = false;
				title_url_field.removeAttribute('tabIndex');
				max_zoom_field.removeAttribute('tabIndex');

				attribution_field.parentElement.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				attribution_label.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}

			const data = this.tile_providers[e.target.value] || this.defaults;
			title_url_field.value = data.geomaps_tile_url;
			max_zoom_field.value = data.geomaps_max_zoom;

			attribution_field.value = '';

			this.form.validateChanges(['geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution']);
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

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'geomaps.update');

					this.#post(url.href, fields);
				});
		}

		#post(url, data) {
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
