/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

if (typeof(navigateToSubmap) !== typeof(Function)) {
	function navigateToSubmap(submapid, uniqueid, reset_previous){
		var widget = jQuery('.dashbrd-grid-widget-container').dashboardGrid('getWidgetsBy', 'uniqueid', uniqueid),
			reset_previous = reset_previous || false,
			previous_maps = '';

		if (widget.length) {
			if (typeof widget[0]['fields']['sysmapid'] !== 'undefined' && widget[0]['fields']['sysmapid'] !== '') {
				if (typeof widget[0]['fields']['previous_maps'] === 'undefined') {
					if (!reset_previous) {
						previous_maps = widget[0]['fields']['sysmapid'];
					}
				}
				else {
					if (reset_previous) {
						previous_maps = widget[0]['fields']['previous_maps'].split(',').filter(Number);
						delete previous_maps[previous_maps.length-1];
						previous_maps = previous_maps.filter(Number).join(',');
					}
					else {
						previous_maps = widget[0]['fields']['previous_maps']+','+widget[0]['fields']['sysmapid'];
					}
				}
			}

			jQuery('.dashbrd-grid-widget-container').dashboardGrid('setWidgetFieldValue', uniqueid, 'sysmapid',
				submapid);
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('setWidgetFieldValue', uniqueid, 'previous_maps',
				previous_maps);
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('refreshWidget', uniqueid);
			jQuery('.action-menu').fadeOut(100);
		}
	}
}
