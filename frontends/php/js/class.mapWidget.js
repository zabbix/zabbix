/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

if (typeof(zbx_sysmap_widget_trigger) !== typeof(Function)) {
	/**
	 * Call widget internal processes externally.
	 *
	 * @param {string} hook_name - trigger name.
	 * @param (array|null) data	 - data passed to method called.
	 */
	function zbx_sysmap_widget_trigger(hook_name, data) {
		var grid = Array.prototype.slice.call(arguments, -1),
			grid = grid.length ? grid[0] : null;

		switch (hook_name) {
			case 'onWidgetRefresh':
				var div_id = jQuery('[data-uniqueid="' + grid['widget']['uniqueid'] + '"]').attr('id');
				jQuery('#' + div_id).zbx_mapwidget('update', grid['widget']);
				break;
			case 'afterUpdateWidgetConfig':
				jQuery('.dashbrd-grid-widget-container').dashboardGrid('setWidgetStorageValue',
					grid['widget']['uniqueid'], 'current_sysmapid', grid['widget']['fields']['sysmapid']);
				break;
			case 'onDashboardReady':
				if (typeof grid['widget']['storage']['current_sysmapid'] === 'undefined') {
					grid['widget']['content_body'].html(data['html']);
				}
				break;
			case 'onEditStart':
				jQuery(".dashbrd-grid-widget-container").dashboardGrid('refreshWidget', grid['widget']['widgetid']);
				break;
		}
	}
}

if (typeof(navigateToSubmap) !== typeof(Function)) {
	/**
	 * Navigate to different map in map widget.
	 *
	 * @param {numeric} submapid		- id of map to navigate to.
	 * @param {string}  uniqueid		- uniqueid of map widget which must be navigated.
	 * @param (boolean) reset_previous	- erase a value from navigation history (in case of false) or store submapid
	 *									  value to history (in case of true). This changes when user navigates in deeper
	 *									  level or back to the top level.
	 */
	function navigateToSubmap(submapid, uniqueid, reset_previous) {
		var widget = jQuery('.dashbrd-grid-widget-container').dashboardGrid('getWidgetsBy', 'uniqueid', uniqueid),
			reset_previous = reset_previous || false,
			previous_maps = '';

		if (widget.length) {
			if (typeof widget[0]['storage']['current_sysmapid'] !== 'undefined'
					&& widget[0]['storage']['current_sysmapid'] !== '') {
				if (typeof widget[0]['storage']['previous_maps'] === 'undefined') {
					if (!reset_previous) {
						previous_maps = widget[0]['storage']['current_sysmapid'];
					}
				}
				else {
					if (reset_previous) {
						previous_maps = widget[0]['storage']['previous_maps'].toString().split(',').filter(Number);
						delete previous_maps[previous_maps.length-1];
						previous_maps = previous_maps.filter(Number).join(',');
					}
					else {
						previous_maps
							= widget[0]['storage']['previous_maps'] + ',' + widget[0]['storage']['current_sysmapid'];
					}
				}
			}

			jQuery('.dashbrd-grid-widget-container').dashboardGrid('setWidgetStorageValue', uniqueid,
				'current_sysmapid', submapid);
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('setWidgetStorageValue', uniqueid, 'previous_maps',
				previous_maps);
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('refreshWidget', uniqueid);
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('widgetDataShare', widget[0], 'current_sysmapid',
				{submapid: submapid, previous_maps: previous_maps, moving_upward: reset_previous ? 1 : 0});
			jQuery('.action-menu').menuPopup('close', null);
		}
	}
}

jQuery(function($) {
	/**
	 * Create Map Widget.
	 *
	 * @return object
	 */
	if (typeof($.fn.zbx_mapwidget) === 'undefined') {
		$.fn.zbx_mapwidget = function(input, widget) {
			var methods = {
				// Update map.
				update: function() {
					var $this = $(this);

					return this.each(function() {
						var widget_data = $this.data('widgetData');

						if (widget_data['is_refreshing'] === false) {
							widget_data['is_refreshing'] = true;

							var url = new Curl(widget_data['map_instance'].options.refresh);
							url.setArgument('curtime', new CDate().getTime());
							url.setArgument('uniqueid', widget['uniqueid']);
							url.setArgument('used_in_widget', 1);

							$.ajax({
								'url': url.getUrl()
							})
							.done(function(data) {
								widget_data['is_refreshing'] = false;
								if (+data.mapid > 0) {
									widget_data['map_instance'].update(data);
									widget['content_footer'].html(data.map_widget_footer);
								}
								else {
									jQuery('.dashbrd-grid-widget-container').dashboardGrid('refreshWidget',
										widget_data['uniqueid']);
								}
							});
						}
					});
				},

				// initialization of widget
				init: function(options) {
					var widget_data = $.extend({}, options);

					return this.each(function() {
						var $this = $(this);

						options['map_options']['canvas']['useViewBox'] = !IE;
						options['map_options']['show_timestamp'] = false;
						widget_data['map_instance'] = new SVGMap(options['map_options']);
						widget_data['is_refreshing'] = false;
						$this.data('widgetData', widget_data);
					});
				}
			};

			if (methods[input]) {
				return methods[input].apply(this, Array.prototype.slice.call(arguments, 1));
			}
			else if (typeof input === 'object') {
				return methods.init.apply(this, arguments);
			}
			else {
				return null;
			}
		}
	}
});
