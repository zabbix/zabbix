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


class CWidgetGeoMap extends CWidget {
	static SEVERITY_NO_PROBLEMS = -1;
	static SEVERITY_NOT_CLASSIFIED = 0;
	static SEVERITY_INFORMATION = 1;
	static SEVERITY_WARNING = 2;
	static SEVERITY_AVERAGE = 3;
	static SEVERITY_HIGH = 4;
	static SEVERITY_DISASTER = 5;

	_init() {
		super._init();

		this._map = null;
		this._icons = {};
		this._initial_load = true;
		this._home_coords = {};
		this._severity_levels = new Map();
	}

	_getUpdateRequestData() {
		return {
			...super._getUpdateRequestData(),
			initial_load: this._initial_load ? 1 : 0,
			unique_id: this._unique_id
		};
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if ('geomap' in response) {
			if ('config' in response.geomap) {
				this._initMap(response.geomap.config);
			}

			this._addMarkers(response.geomap.hosts);
		}

		this._initial_load = false;
	}

	updateProperties({name, view_mode, fields, configuration}) {
		this._initial_load = true;

		super.updateProperties({name, view_mode, fields, configuration});
	}

	_setContents({name, body, messages, info, debug}) {
		this._setHeaderName(name);

		if (this._initial_load) {
			this._content_body.innerHTML = '';
		}

		if (messages !== undefined) {
			this._content_body.insertAdjacentHTML('beforeend', messages);
		}

		if (this._initial_load) {
			this._content_body.insertAdjacentHTML('beforeend', body);
		}

		if (debug !== undefined) {
			this._content_body.insertAdjacentHTML('beforeend', debug);
		}
	}

	_addMarkers(hosts) {
		this._markers.clearLayers();
		this._clusters.clearLayers();

		this._markers.addData(hosts);
		this._clusters.addLayer(this._markers);
	}

	_initMap(config) {
		const latLng = new L.latLng([config.center.latitude, config.center.longitude]);

		this._home_coords = config.home_coords;

		// Initialize map and load tile layer.
		this._map = L.map(this._unique_id).setView(latLng, config.center.zoom);
		L.tileLayer(config.tile_url, {
			tap: false,
			minZoom: 0,
			maxZoom: parseInt(config.max_zoom, 10),
			minNativeZoom: 1,
			maxNativeZoom: parseInt(config.max_zoom, 10),
			attribution: config.attribution
		}).addTo(this._map);

		this.initSeverities(config.colors);

		// Create cluster layer.
		this._clusters = this._createClusterLayer();
		this._map.addLayer(this._clusters);

		// Create markers layer.
		this._markers = L.geoJSON([], {
			pointToLayer: function (point, ll) {
				return L.marker(ll, {
					icon: this._icons[point.properties.severity]
				});
			}.bind(this)
		});

		this._map.setDefaultView(latLng, config.center.zoom);

		// Severity filter.
		this._map.severityFilterControl = L.control.severityFilter({
			position: 'topright',
			checked: config.filter.severity,
			severity_levels: this._severity_levels,
			disabled: !this._widgetid
		}).addTo(this._map);

		// Navigate home btn.
		this._map.navigateHomeControl = L.control.navigateHomeBtn({position: 'topleft'}).addTo(this._map);
		if (Object.keys(this._home_coords).length > 0) {
			const home_btn_title = ('default' in this._home_coords)
				? t('Navigate to default view')
				: t('Navigate to initial view');

			this._map.navigateHomeControl.setTitle(home_btn_title);
			this._map.navigateHomeControl.show();
		}

		// Workaround to prevent dashboard jumping to make map completely visible.
		this._map.getContainer().focus = () => {};

		// Add event listeners.
		this._map.getContainer().addEventListener('click', (e) => {
			if (e.target.classList.contains('leaflet-container')) {
				this._map.severityFilterControl.close();
			}
		}, false);

		this._map.getContainer().addEventListener('filter', (e) => {
			this.removeHintBoxes();
			this.updateFilter(e.detail.join(','));
		}, false);

		this._map.getContainer().addEventListener('cluster.click', (e) => {
			const cluster = e.detail;
			const node = cluster.originalEvent.srcElement.classList.contains('marker-cluster')
				? cluster.originalEvent.srcElement
				: cluster.originalEvent.srcElement.closest('.marker-cluster');

			if ('hintBoxItem' in node) {
				return;
			}

			const container = this._map._container;
			const style = 'left: 0px; top: 0px;';

			const content = document.createElement('div');
			content.style.overflow = 'auto';
			content.style.maxHeight = (cluster.originalEvent.clientY-60)+'px';
			content.style.display = 'block';
			content.appendChild(this.makePopupContent(cluster.layer.getAllChildMarkers().map(o => o.feature)));

			node.hintBoxItem = hintBox.createBox(e, node, content, '', true, style, container.parentNode);

			const cluster_bounds = cluster.originalEvent.target.getBoundingClientRect();
			const hintbox_bounds = this._target.getBoundingClientRect();

			let x = cluster_bounds.left + cluster_bounds.width / 2 - hintbox_bounds.left;
			let y = cluster_bounds.top - hintbox_bounds.top - 10;

			node.hintBoxItem.position({
				of: node.hintBoxItem,
				my: 'center bottom',
				at: `left+${x}px top+${y}px`,
				collision: 'fit'
			});

			Overlay.prototype.recoverFocus.call({'$dialogue': node.hintBoxItem});
			Overlay.prototype.containFocus.call({'$dialogue': node.hintBoxItem});
		});

		this._markers.on('click keypress', (e) => {
			const node = e.originalEvent.srcElement;
			if ('hintBoxItem' in node) {
				return;
			}

			if (e.type === 'keypress') {
				if (e.originalEvent.key !== ' ' && e.originalEvent.key !== 'Enter') {
					return;
				}
				e.originalEvent.preventDefault();
			}

			const container = this._map._container;
			const content = this.makePopupContent([e.layer.feature]);
			const style = 'left: 0px; top: 0px;';

			node.hintBoxItem = hintBox.createBox(e, node, content, '', true, style, container.parentNode);

			const marker_bounds = e.originalEvent.target.getBoundingClientRect();
			const hintbox_bounds = this._target.getBoundingClientRect();

			let x = marker_bounds.left + marker_bounds.width / 2 - hintbox_bounds.left;
			let y = marker_bounds.top - hintbox_bounds.top - 10;

			node.hintBoxItem.position({
				of: node.hintBoxItem,
				my: 'center bottom',
				at: `left+${x}px top+${y}px`,
				collision: 'fit'
			});

			Overlay.prototype.recoverFocus.call({'$dialogue': node.hintBoxItem});
			Overlay.prototype.containFocus.call({'$dialogue': node.hintBoxItem});
		});

		this._map.getContainer().addEventListener('cluster.dblclick', (e) => {
			e.detail.layer.zoomToBounds({padding: [20, 20]});
		});

		this._map.getContainer().addEventListener('contextmenu', (e) => {
			if (e.target.classList.contains('leaflet-container')) {
				const $obj = $(e.target);
				const menu = [{
					label: t('Actions'),
					items: [{
						label: t('Set this view as default'),
						clickCallback: this.updateDefaultView.bind(this),
						disabled: !this._widgetid
					}, {
						label: t('Reset to initial view'),
						clickCallback: this.unsetDefaultView.bind(this),
						disabled: !('default' in this._home_coords)
					}]
				}];

				$obj.menuPopup(menu, e, {
					position: {
						of: $obj,
						my: 'left top',
						at: 'left+'+e.layerX+' top+'+e.layerY,
						collision: 'fit'
					}
				});
			}

			e.preventDefault();
		});

		// Close opened hintboxes when moving/zooming/resizing widget.
		this._map.on('zoomstart movestart resize', () => {
			this.removeHintBoxes();
		});
	}

	/**
	 * Function to create cluster layer.
	 *
	 * @returns {CWidgetGeoMap._createClusterLayer.clusters|L.MarkerClusterGroup}
	 */
	_createClusterLayer() {
		const clusters = L.markerClusterGroup({
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			removeOutsideVisibleBounds: true,
			spiderfyOnMaxZoom: false,
			iconCreateFunction: (cluster) => {
				const max_severity = Math.max(...cluster.getAllChildMarkers().map(p => p.feature.properties.severity));
				const color = this._severity_levels.get(max_severity).color;

				return new L.DivIcon({
					html: `
						<div style="background-color: ${color};">
							<span>${cluster.getChildCount()}</span>
						</div>`,
					className: 'marker-cluster',
					iconSize: new L.Point(40, 40)
				});
			}
		});

		// Transform 'clusterclick' event as 'cluster.click' and 'cluster.dblclick' events.
		clusters.on('clusterclick clusterkeypress', (c) => {
			if (c.type === 'clusterkeypress') {
				if (c.originalEvent.key !== ' ' && c.originalEvent.key !== 'Enter') {
					return;
				}
				c.originalEvent.preventDefault();
			}

			if ('event_click' in clusters) {
				clearTimeout(clusters.event_click);
				delete clusters.event_click;
				this._map.getContainer().dispatchEvent(
					new CustomEvent('cluster.dblclick', {detail: c})
				);
			}
			else {
				clusters.event_click = setTimeout(() => {
					delete clusters.event_click;
					this._map.getContainer().dispatchEvent(
						new CustomEvent('cluster.click', {detail: c})
					);
				}, 300);
			}
		});

		return clusters;
	}

	/**
	 * Save severity filter values in user profile and update widget.
	 *
	 * @param {string} filter
	 */
	updateFilter(filter) {
		updateUserProfile('web.dashboard.widget.geomap.severity_filter', filter, [this._widgetid], PROFILE_TYPE_STR)
			.always(() => {
				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			});
	}

	/**
	 * Save default view.
	 *
	 * @param {string} filter
	 */
	updateDefaultView() {
		const ll = this._map.getCenter();
		const zoom = this._map.getZoom();
		const view = `${ll.lat},${ll.lng},${zoom}`;

		updateUserProfile('web.dashboard.widget.geomap.default_view', view, [this._widgetid], PROFILE_TYPE_STR);
		this._map.setDefaultView(ll, zoom);
		this._home_coords['default'] = true;
		this._map.navigateHomeControl.show();
		this._map.navigateHomeControl.setTitle(t('Navigate to default view'));
	}

	/**
	 * Unset default view.
	 *
	 * @returns {undefined}
	 */
	unsetDefaultView() {
		updateUserProfile('web.dashboard.widget.geomap.default_view', '', [this._widgetid], PROFILE_TYPE_STR)
			.always(() => {
				delete this._home_coords.default;
			});

		if ('initial' in this._home_coords) {
			const latLng = new L.latLng([this._home_coords.initial.latitude, this._home_coords.initial.longitude]);
			this._map.setDefaultView(latLng, this._home_coords.initial.zoom);
			this._map.navigateHomeControl.setTitle(t('Navigate to initial view'));
			this._map.setView(latLng, this._home_coords.initial.zoom);
		}
		else {
			this._map.navigateHomeControl.hide();
		}
	}

	/**
	 * Function to delete all opened hintboxes.
	 */
	removeHintBoxes() {
		const markers = this._map._container.parentNode.querySelectorAll('.marker-cluster, .leaflet-marker-icon');
		[...markers].forEach((m) => {
			if ('hintboxid' in m) {
				hintBox.deleteHint(m);
			}
		});
	}

	/**
	 * Create host popup content.
	 *
	 * @param {array} hosts
	 *
	 * @return {string}
	 */
	makePopupContent(hosts) {
		const makeHostBtn = (host) => {
			const {name, hostid} = host.properties;
			const data_menu_popup = JSON.stringify({type: 'host', data: {hostid: hostid}});
			const btn = document.createElement('a');
			btn.ariaExpanded = false;
			btn.ariaHaspopup = true;
			btn.role = 'button';
			btn.setAttribute('data-menu-popup', data_menu_popup);
			btn.classList.add('link-action');
			btn.href = 'javascript:void(0)';
			btn.textContent = name;

			return btn;
		};

		const makeDataCell = (host, severity) => {
			if (severity in host.properties.problems) {
				const style = this._severity_levels.get(severity).class;
				const problems = host.properties.problems[severity];
				return `<td class="${style}">${problems}</td>`;
			}
			else {
				return `<td></td>`;
			}
		};

		const makeTableRows = () => {
			hosts.sort((a, b) => {
				if (a.properties.name < b.properties.name) {
					return -1;
				}
				if (a.properties.name > b.properties.name) {
					return 1;
				}
				return 0;
			});

			let rows = ``;
			hosts.forEach(host => {
				rows += `
					<tr>
						<td class="nowrap">${makeHostBtn(host).outerHTML}</td>
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_DISASTER)}
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_HIGH)}
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_AVERAGE)}
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_WARNING)}
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_INFORMATION)}
						${makeDataCell(host, CWidgetGeoMap.SEVERITY_NOT_CLASSIFIED)}
					</tr>`;
			});

			return rows;
		};

		const html = `
			<table class="list-table">
			<thead>
			<tr>
				<th>${t('Host')}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_DISASTER).abbr}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_HIGH).abbr}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_AVERAGE).abbr}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_WARNING).abbr}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_INFORMATION).abbr}</th>
				<th>${this._severity_levels.get(CWidgetGeoMap.SEVERITY_NOT_CLASSIFIED).abbr}</th>
			</th>
			</thead>
			<tbody>${makeTableRows()}</tbody>
			</table>`;

		// Make DOM.
		const dom = document.createElement('template');
		dom.innerHTML = html;

		return dom.content;
	}

	/**
	 * Function creates marker icons and severity-related options.
	 *
	 * @param {object} severity_colors
	 */
	initSeverities(severity_colors) {
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_NO_PROBLEMS, {
			name: t('No problems'),
			color: severity_colors[CWidgetGeoMap.SEVERITY_NO_PROBLEMS]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_NOT_CLASSIFIED, {
			name: t('Not classified'),
			abbr: t('N'),
			class: 'na-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_NOT_CLASSIFIED]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_INFORMATION, {
			name: t('Information'),
			abbr: t('I'),
			class: 'info-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_INFORMATION]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_WARNING, {
			name: t('Warning'),
			abbr: t('W'),
			class: 'warning-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_WARNING]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_AVERAGE, {
			name: t('Average'),
			abbr: t('A'),
			class: 'average-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_AVERAGE]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_HIGH, {
			name: t('High'),
			abbr: t('H'),
			class: 'high-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_HIGH]
		});
		this._severity_levels.set(CWidgetGeoMap.SEVERITY_DISASTER, {
			name: t('Disaster'),
			abbr: t('D'),
			class: 'disaster-bg',
			color: severity_colors[CWidgetGeoMap.SEVERITY_DISASTER]
		});

		for (const severity in severity_colors) {
			const color = severity_colors[severity];
			const tmpl = `
				<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="40px" height="40px" version="1.1" style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd" viewBox="0 0 500 500" xmlns:xlink="http://www.w3.org/1999/xlink">
				<defs>
					<style type="text/css"><![CDATA[
						.outline {fill: #000; fill-rule: nonzero; fill-opacity: .2}
					]]></style>
				</defs>
				<g>
					<path fill="${color}" d="M254 13c81,0 146,65 146,146 0,40 -16,77 -43,103l-97 233 -11 3 -98 -236c-27,-26 -43,-63 -43,-103 0,-81 65,-146 146,-146zm0 82c84,0 84,127 0,127 -84,0 -84,-127 0,-127z"/>
					<path class="outline" d="M254 6c109,0 182,111 141,211 -8,18 -19,35 -32,49l-98 235 -19 5 -100 -240c-18,-27 -44,-46 -44,-107 0,-84 68,-153 152,-153zm99 54c-132,-132 -327,70 -197,198l97 233 3 -1 97 -232c54,-54 55,-143 0,-198zm-99 29c92,0 92,140 0,140 -92,0 -92,-140 0,-140zm40 29c-53,-53 -134,28 -80,81 53,53 134,-27 80,-81z"/>
				</g>
				</svg>`;

			this._icons[severity] = L.icon({
				iconUrl: 'data:image/svg+xml;base64,' + btoa(tmpl),
				shadowUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACkAAAApCAQAAAACach9AAACMUlEQVR4Ae3ShY7jQBAE0Aoz/f9/HTMzhg1zrdKUrJbdx+Kd2nD8VNudfsL/Th/'+'/'+'/dyQN2TH6f3y/BGpC379rV+S+qqetBOxImNQXL8JCAr2V4iMQXHGNJxeCfZXhSRBcQMfvkOWUdtfzlLgAENmZDcmo2TVmt8OSM2eXxBp3DjHSMFutqS7SbmemzBiR+xpKCNUIRkdkkYxhAkyGoBvyQFEJEefwSmmvBfJuJ6aKqKWnAkvGZOaZXTUgFqYULWNSHUckZuR1HIIimUExutRxwzOLROIG4vKmCKQt364mIlhSyzAf1m9lHZHJZrlAOMMztRRiKimp/rpdJDc9Awry5xTZCte7FHtuS8wJgeYGrex28xNTd086Dik7vUMscQOa8y4DoGtCCSkAKlNwpgNtphjrC6MIHUkR6YWxxs6Sc5xqn222mmCRFzIt8lEdKx+ikCtg91qS2WpwVfBelJCiQJwvzixfI9cxZQWgiSJelKnwBElKYtDOb2MFbhmUigbReQBV0Cg4+qMXSxXSyGUn4UbF8l+7qdSGnTC0XLCmahIgUHLhLOhpVCtw4CzYXvLQWQbJNmxoCsOKAxSgBJno75avolkRw8iIAFcsdc02e9iyCd8tHwmeSSoKTowIgvscSGZUOA7PuCN5b2BX9mQM7S0wYhMNU74zgsPBj3HU7wguAfnxxjFQGBE6pwN+GjME9zHY7zGp8wVxMShYX9NXvEWD3HbwJf4giO4CFIQxXScH1/TM+04kkBiAAAAAElFTkSuQmCC',
				iconSize: [40, 40],
				iconAnchor: [20, 40],
				shadowSize: [40, 40],
				shadowAnchor: [13, 40]
			});
		}
	}
}
