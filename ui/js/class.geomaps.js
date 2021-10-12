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
 * Extend Leaflet Map with functions necessary for Zabbix "Geomap" widget.
 *
 * @type {L.Map}
 */
L.Map.include({

	_center: null,

	navigateHomeControl: null,
	severityFilterControl: null,

	setDefaultView: function(latLng, zoom) {
		this._center = {
			latLng: latLng,
			zoom: zoom
		};
	},

	updateFilter: function(filter_data) {
		this.getContainer().dispatchEvent(new CustomEvent('filter', {detail: filter_data}));
	}
});

/**
 * Leaflet extension to provide severity filter in "Geomap" widget.
 *
 * @type {L.Control}
 */
L.Control.severityFilterFilterControl = L.Control.extend({

	_severity_levels: null,
	_filter_checked: [],

	initialize: function({checked, severity_levels}) {
		this._filter_checked = checked;
		this._severity_levels = severity_levels;
	},

	onAdd: function(map) {
		const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
		const btn = L.DomUtil.create('a', 'geomap-filter-button', div);
		this.bar = L.DomUtil.create('ul', 'checkbox-list geomap-filter', div);

		btn.ariaLabel = t('Severity filter');
		btn.title = t('Severity filter');
		btn.role = 'button';
		btn.href = '#';

		for (const [severity, prop] of this._severity_levels) {
			const li = L.DomUtil.create('li', '', this.bar);
			const chbox = L.DomUtil.create('input', '', li);
			const label = L.DomUtil.create('label', '', li);
			const span = L.DomUtil.create('span', '', label);
			const caption = L.DomUtil.create('label', '', li);

			caption.innerHTML = prop.name;
			chbox.checked = this._filter_checked.includes(severity.toString(10));
			chbox.classList.add('checkbox-radio');
			chbox.type = 'checkbox';
			chbox.value = severity;
		}

		L.DomEvent.on(btn, 'click', () => {this.bar.classList.toggle('collapsed')});
		L.DomEvent.on(btn, 'dblclick', (e) => {L.DomEvent.stopPropagation(e)});
		L.DomEvent.on(div, 'change', () => {
			map.updateFilter([...this.bar.querySelectorAll('input[type="checkbox"]:checked')].map(n => n.value));
		});

		return div;
	},

	close: function() {
		this.bar.classList.remove('collapsed');
	}
});

/**
 * Factory function for L.Control.severityFilterFilterControl.
 *
 * @param {object} opts  Filter options.
 *
 * @return {L.control.severityFilter}
 */
L.control.severityFilter = function(opts) {
	return new L.Control.severityFilterFilterControl(opts);
};


/**
 * Leaflet extension to provide "Navigate to default view" button in "Geomap" widget.
 *
 * @type L.Control
 */
L.Control.navigateToDefaultViewControl = L.Control.extend({

	_div: null,

	onAdd: function(map) {
		this._div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
		const btn = L.DomUtil.create('a', 'navigate-home-button', this._div);

		btn.ariaLabel = t('Navigate to default view');
		btn.title = t('Navigate to default view');
		btn.role = 'button';
		btn.href = '#';

		L.DomEvent.on(btn, 'click', () => {map.setView(map._center.latLng, map._center.zoom)});
		L.DomEvent.on(btn, 'dblclick', (e) => {L.DomEvent.stopPropagation(e)});

		this._div.style.visibility = 'hidden';

		return this._div;
	},

	show: function() {
		this._div.style.visibility = 'visible';
	}
});

/**
 * Factory function for L.Control.navigateToDefaultViewControl.
 *
 * @param {object} opts
 *
 * @return {L.control.navigateToDefaultViewControl}
 */
L.control.navigateHomeBtn = function(opts) {
	return new L.Control.navigateToDefaultViewControl(opts);
};
