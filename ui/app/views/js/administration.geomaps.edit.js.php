<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
				geomaps_attribution: '',
				geomaps_max_zoom: ''
			};

			document.querySelector('[name="geomaps_tile_provider"]')
				.addEventListener('change', this.events.tileProviderChange.bind(this));
		},

		events: {
			tileProviderChange(e) {
				if (e.target.value !== '') {
					this.tile_url.readOnly = true;
					this.attribution.readOnly = true;
					this.max_zoom.readOnly = true;
					this.tile_url.tabIndex = -1;
					this.attribution.tabIndex = -1;
					this.max_zoom.tabIndex = -1;
				}
				else {
					this.tile_url.readOnly = false;
					this.attribution.readOnly = false;
					this.max_zoom.readOnly = false;
					this.tile_url.removeAttribute('tabIndex');
					this.attribution.removeAttribute('tabIndex');
					this.max_zoom.removeAttribute('tabIndex');
				}

				const data = this.tile_providers[e.target.value] || this.defaults;
				this.tile_url.value = data.geomaps_tile_url;
				this.attribution.value = data.geomaps_attribution;
				this.max_zoom.value = data.geomaps_max_zoom;
			}
		}
	};
</script>
