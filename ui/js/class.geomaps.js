/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	},

	elmntCounter: (function() {
		let static = 0;
		return function() {
			return ++static;
		}
	})()
});

/**
 * Leaflet extension to provide severity filter in "Geomap" widget.
 *
 * @type {L.Control}
 */
L.Control.severityFilterFilterControl = L.Control.extend({

	_severity_levels: null,
	_filter_checked: [],

	initialize: function({checked, severity_levels, disabled}) {
		this._filter_checked = checked;
		this._severity_levels = severity_levels;
		this._disabled = disabled;
	},

	onAdd: function(map) {
		const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
		const btn = L.DomUtil.create('a', 'geomap-filter-button', div);
		this.bar = L.DomUtil.create('ul', 'checkbox-list geomap-filter', div);

		btn.ariaLabel = t('Severity filter');
		btn.title = t('Severity filter');
		btn.role = 'button';
		btn.href = '#';

		if (!this._disabled) {
			for (const [severity, prop] of this._severity_levels) {
				const li = L.DomUtil.create('li', '', this.bar);
				const chbox = L.DomUtil.create('input', '', li);
				const label = L.DomUtil.create('label', '', li);
				const span = L.DomUtil.create('span', '');
				const chBoxId = 'filter_severity_' + map.elmntCounter();

				label.append(span, document.createTextNode(prop.name));
				chbox.checked = this._filter_checked.includes(severity.toString(10));
				chbox.classList.add('checkbox-radio');
				chbox.type = 'checkbox';
				chbox.value = severity;
				chbox.id = chBoxId;
				label.htmlFor = chBoxId;
			}

			L.DomEvent.on(btn, 'click', () => {this.bar.classList.toggle('collapsed')});
			L.DomEvent.on(this.bar, 'dblclick', (e) => {L.DomEvent.stopPropagation(e)});
			L.DomEvent.on(div, 'change', () => {
				map.updateFilter([...this.bar.querySelectorAll('input[type="checkbox"]:checked')].map(n => n.value));
			});
		}
		else {
			div.classList.add('disabled');
		}

		L.DomEvent.on(btn, 'dblclick', (e) => {L.DomEvent.stopPropagation(e)});

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
 * Leaflet extension to provide "Navigate home" button in "Geomap" widget.
 *
 * @type L.Control
 */
L.Control.navigateHomeControl = L.Control.extend({

	_div: null,

	onAdd: function(map) {
		this._div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
		this._btn = L.DomUtil.create('a', 'navigate-home-button', this._div);

		this._btn.role = 'button';
		this._btn.href = '#';

		L.DomEvent.on(this._btn, 'click', () => {map.setView(map._center.latLng, map._center.zoom)});
		L.DomEvent.on(this._btn, 'dblclick', (e) => {L.DomEvent.stopPropagation(e)});

		this._div.style.visibility = 'hidden';

		return this._div;
	},

	setTitle: function(title) {
		this._btn.ariaLabel = title;
		this._btn.title = title;
	},

	show: function() {
		this._div.style.visibility = 'visible';
	},

	hide: function() {
		this._div.style.visibility = 'hidden';
	}
});

/**
 * Factory function for L.Control.navigateHomeControl.
 *
 * @param {object} opts
 *
 * @return {L.control.navigateHomeControl}
 */
L.control.navigateHomeBtn = function(opts) {
	return new L.Control.navigateHomeControl(opts);
};
