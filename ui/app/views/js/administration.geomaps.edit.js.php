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

<script>
	const view = {
		tile_url: null,
		attribution: null,
		max_zoom: null,
		tile_providers: {},
		defaults: {},

		init({rules, tile_providers}) {
			this.form_element = document.getElementById('geomaps-form');
			this.form = new CForm(this.form_element, rules);
			this.tile_url = document.getElementById('geomaps_tile_url');
			this.attribution = document.getElementById('geomaps_attribution');
			this.max_zoom = document.getElementById('geomaps_max_zoom');

			this.tile_providers = tile_providers;
			this.defaults = {
				geomaps_tile_url: '',
				geomaps_max_zoom: ''
			};

			document.querySelector('[name="geomaps_tile_provider"]')
				.addEventListener('change', this.events.tileProviderChange);

			document.getElementById('geomaps-form').addEventListener('submit', (e) => {
				e.preventDefault();

				const fields = this.form.getAllValues(),
					curl = new Curl(this.form_element.getAttribute('action'));

				this.form.validateSubmit(fields)
					.then((result) => {
						if (!result) {
							return;
						}

						this.post(curl.getUrl(), fields);
					});
			});
		},

		events: {
			tileProviderChange(e) {
				const attribution_field = view.attribution.parentNode;
				const attribution_label = view.attribution.parentNode.previousElementSibling;

				if (e.target.value !== '') {
					view.tile_url.readOnly = true;
					view.max_zoom.readOnly = true;
					view.tile_url.tabIndex = -1;
					view.max_zoom.tabIndex = -1;

					attribution_field.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
					attribution_label.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				}
				else {
					view.tile_url.readOnly = false;
					view.max_zoom.readOnly = false;
					view.tile_url.removeAttribute('tabIndex');
					view.max_zoom.removeAttribute('tabIndex');

					attribution_field.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
					attribution_label.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				}

				const data = view.tile_providers[e.target.value] || view.defaults;
				view.tile_url.value = data.geomaps_tile_url;
				view.max_zoom.value = data.geomaps_max_zoom;
				view.attribution.value = '';

				view.form.validateChanges(['geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution']);
			}
		},

		post(url, data) {
			fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('form_errors' in response) {
						this.form.setErrors(response.form_errors, true, true);
						this.form.renderErrors();
					}
					else if ('error' in response) {
						throw {error: response.error};
					}
					else {
						postMessageOk(response.success.title);
						location.href = location.href;
					}
				})
				.catch((exception) => {
					for (const element of this.form_element.parentNode.children) {
						if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
							element.parentNode.removeChild(element);
						}
					}

					let title;
					let messages;

					if (typeof exception === 'object' && 'error' in exception) {
						title = exception.error.title;
						messages = exception.error.messages;
					}
					else {
						messages = [<?= json_encode(_('Unexpected server error.')) ?>];
					}

					const message_box = makeMessageBox('bad', messages, title)[0];

					this.form_element.parentNode.insertBefore(message_box, this.form_element);
				});
		}
	};
</script>
