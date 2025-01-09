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

		init({tile_providers}) {
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

			document.getElementById('geomaps-form').addEventListener('submit', this.events.submit);
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
			},

			submit() {
				view.tile_url.value = view.tile_url.value.trim();
				view.attribution.value = view.attribution.value.trim();
			}
		}
	};
</script>
