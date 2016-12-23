<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


//$this->addJsFile('class.pmaster.js');

/*
 * Dashboard grid
 */
$dashboardGrid = [[], [], []];
$widgetRefreshParams = [];

$widgets = [
	WIDGET_FAVOURITE_GRAPHS => [
		'id' => 'favouriteGraphs',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteGraphs'],
		'data' => $data['favourite_graphs'],
		'header' => _('Favourite graphs'),
		'links' => [
			['name' => _('Graphs'), 'url' => 'charts.php']
		],
		'defaults' => ['col' => 0, 'row' => 0]
	],
	WIDGET_FAVOURITE_SCREENS => [
		'id' => 'favouriteScreens',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteScreens'],
		'data' => $data['favourite_screens'],
		'header' => _('Favourite screens'),
		'links' => [
			['name' => _('Screens'), 'url' => 'screens.php'],
			['name' => _('Slide shows'), 'url' => 'slides.php']
		],
		'defaults' => ['col' => 0, 'row' => 1]
	],
	WIDGET_FAVOURITE_MAPS => [
		'id' => 'favouriteMaps',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteMaps'],
		'data' => $data['favourite_maps'],
		'header' => _('Favourite maps'),
		'links' => [
			['name' => _('Maps'), 'url' => 'zabbix.php?action=map.view']
		],
		'defaults' => ['col' => 0, 'row' => 2]
	]
];

foreach ($widgets as $widgetid => $widget) {
	$icon = (new CButton(null))
		->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
		->setTitle(_('Action'))
		->setId($widget['id'])
		->setMenuPopup(call_user_func($widget['menu_popup']));

	$footer = new CList();
	foreach ($widget['links'] as $link) {
		$footer->addItem(new CLink($link['name'], $link['url']));
	}

	$col = CProfile::get('web.dashboard.widget.'.$widgetid.'.col', $widget['defaults']['col']);
	$row = CProfile::get('web.dashboard.widget.'.$widgetid.'.row', $widget['defaults']['row']);

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, $widget['data']))
		->setExpanded((bool) CProfile::get('web.dashboard.widget.'.$widgetid.'.state', true))
		->setHeader($widget['header'], [$icon], true, 'zabbix.php?action=dashboard.widget')
		->setFooter($footer);
}

$widgets = [
	WIDGET_SYSTEM_STATUS => [
		'action' => 'widget.system.view',
		'header' => _('System status'),
		'defaults' => ['col' => 1, 'row' => 1]
	],
	WIDGET_HOST_STATUS => [
		'action' => 'widget.hosts.view',
		'header' => _('Host status'),
		'defaults' => ['col' => 1, 'row' => 2]
	],
	WIDGET_LAST_ISSUES => [
		'action' => 'widget.issues.view',
		'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
		'defaults' => ['col' => 1, 'row' => 3]
	],
	WIDGET_WEB_OVERVIEW => [
		'action' => 'widget.web.view',
		'header' => _('Web monitoring'),
		'defaults' => ['col' => 1, 'row' => 4]
	],
];

if ($data['show_status_widget']) {
	$widgets[WIDGET_ZABBIX_STATUS] = [
		'action' => 'widget.status.view',
		'header' => _('Status of Zabbix'),
		'defaults' => ['col' => 1, 'row' => 0]
	];
}
if ($data['show_discovery_widget']) {
	$widgets[WIDGET_DISCOVERY_STATUS] = [
		'action' => 'widget.discovery.view',
		'header' => _('Discovery status'),
		'defaults' => ['col' => 1, 'row' => 5]
	];
}

foreach ($widgets as $widgetid => $widget) {
	$profile = 'web.dashboard.widget.'.$widgetid;

	$rate = CProfile::get($profile.'.rf_rate', 60);
	$expanded = (bool) CProfile::get($profile.'.state', true);
	$col = CProfile::get($profile.'.col', $widget['defaults']['col']);
	$row = CProfile::get($profile.'.row', $widget['defaults']['row']);

	$icon = (new CButton(null))
		->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
		->setTitle(_('Action'))
		->setMenuPopup(CMenuPopupHelper::getRefresh($widgetid, $rate));

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, (new CDiv())->addClass(ZBX_STYLE_PRELOADER)))
		->setExpanded($expanded)
		->setHeader($widget['header'], [$icon], true, 'zabbix.php?action=dashboard.widget')
		->setFooter((new CList())->setId($widgetid.'_footer'));

	$widgetRefreshParams[$widgetid] = [
		'frequency' => $rate,
		'url' => 'zabbix.php?action='.$widget['action'],
		'counter' => 0,
		'darken' => 0,
		'params' => ['widgetRefresh' => $widgetid]
	];
}

// sort dashboard grid
foreach ($dashboardGrid as $key => $val) {
	ksort($dashboardGrid[$key]);
}

(new CWidget())
	->setTitle(_('Dashboard'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			->addItem(get_icon('dashconf', ['enabled' => $data['filter_enabled']]))
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem(
		(new CDiv())->addClass('dashbrd-grid-container')
	)
	->addItem(
		(new CDiv(
			(new CDiv([
				(new CDiv($dashboardGrid[0]))->addClass(ZBX_STYLE_CELL),
				(new CDiv($dashboardGrid[1]))->addClass(ZBX_STYLE_CELL),
				(new CDiv($dashboardGrid[2]))->addClass(ZBX_STYLE_CELL)
			]))
				->addClass(ZBX_STYLE_ROW)
		))
			->addClass(ZBX_STYLE_TABLE)
			->addClass('widget-placeholder')
	)
	->show();

/*
 * Javascript
 */
// start refresh process
//$this->addPostJS('initPMaster("dashboard", '.CJs::encodeJson($widgetRefreshParams).');');

// activating blinking
//$this->addPostJS('jqBlink.blink();');

/*
	*/
?>

<style>
.dashbrd-grid-container {
	width: 100%;
	position: relative; }
	.dashbrd-grid-widget {
		background-color: #3b3b3b;
		position: absolute; }
		.dashbrd-grid-widget-dragging {
			opacity: 0.8;
			z-index: 1000 }
		.ui-resizable-handle {
			position: absolute; }
		.ui-resizable-n {
			cursor: n-resize;
			height: 7px;
			top: -5px;
			left: 2px;
		right: 2px; }
		.ui-resizable-e {
			cursor: e-resize;
			width: 7px;
			right: -5px;
			top: 2px;
		bottom: 2px; }
		.ui-resizable-s {
			cursor: s-resize;
			height: 7px;
			bottom: -5px;
			left: 2px;
		right: 2px; }
		.ui-resizable-w {
			cursor: w-resize;
			width: 7px;
			left: -5px;
			top: 2px;
		bottom: 2px; }
		.ui-resizable-ne {
			cursor: ne-resize;
			height: 7px;
			width: 7px;
			top: -5px;
			right: -5px; }
		.ui-resizable-nw {
			cursor: nw-resize;
			width: 7px;
			height: 7px;
			top: -5px;
			left: -5px; }
		.ui-resizable-se {
			cursor: se-resize;
			height: 7px;
			width: 7px;
			bottom: -5px;
			right: -5px; }
		.ui-resizable-sw {
			cursor: sw-resize;
			width: 7px;
			height: 7px;
			bottom: -5px;
			left: -5px; }
	.dashbrd-grid-placeholder {
		border: 1px dashed #505050;
		background-color: #1b1b1b;
		position: absolute;
		z-index: 999 }
</style>

<script type="text/javascript">
	(function($) {
		"use strict"

		function resizeDashboardGrid(obj, $data, min_rows) {
			$data['options']['rows'] = 0;

			$.each($data['widgets'], function() {
				if (this['row'] + this['height'] > $data['options']['rows']) {
					$data['options']['rows'] = this['row'] + this['height'];
				}
			});

			if (min_rows !== undefined && $data['options']['rows'] < min_rows) {
				$data['options']['rows'] = min_rows;
			}

			obj.css({
				'height': '' + ($data['options']['widget-height'] * $data['options']['rows']) + 'px'
			});
		}

		function getWidgetByTarget(widgets, target) {
			return widgets[$.data(target, 'widget-id')];
		}

		function getWidgetPosition(obj, $data, widget, target) {
			var	widget_pos = $(target).position(),
				widget_width_px = Math.floor(obj.width() / $data['options']['columns']),
				row = (widget_pos.top - (widget_pos.top % $data['options']['widget-height'])) / $data['options']['widget-height'],
				col = (widget_pos.left - (widget_pos.left % widget_width_px)) / widget_width_px;

			if (row < 0) {
				row = 0;
			}

			if (col < 0) {
				col = 0;
			}
			else if (col > $data['options']['columns'] - widget['width']) {
				col = $data['options']['columns'] - widget['width'];
			}

			return {'row': row, 'col': col};
		}

		var methods = {
			init: function(options) {
				options = $.extend({}, {columns: 12}, options);
				options['widget-height'] = 60;
				options['widget-width'] = 100 / options['columns'];
				options['rows'] = 0;

				return this.each(function() {
					var	$this = $(this),
						$placeholder = $('<div>', {'class': 'dashbrd-grid-placeholder'});

					$this.data('dashboardGrid', {
						options: options,
						widgets: [],
						placeholder: $placeholder
					});

					$this.append($placeholder.hide());
				});
			},

			addWidget: function(params) {
				params = $.extend({}, {'row': 0, 'col': 0, 'height': 1, 'width': 1}, params);

				return this.each(function() {
					var	$this = $(this),
						$data = $this.data('dashboardGrid'),
						$widget = $('<div>', {
							'class': 'dashbrd-grid-widget'
						})
							.data('widget-id', $data['widgets'].length)
							.css({
								'top': '' + ($data['options']['widget-height'] * params['row']) + 'px',
								'left': '' + ($data['options']['widget-width'] * params['col']) + '%',
								'height': '' + ($data['options']['widget-height'] * params['height']) + 'px',
								'width': '' + ($data['options']['widget-width'] * params['width']) + '%'
							});

					$data['widgets'].push(params);

					resizeDashboardGrid($this, $data);

					$this.append($widget);

					$widget.draggable({
						start: function(event, ui) {
							var	widget = getWidgetByTarget($data['widgets'], event.target);

							$data['placeholder']
								.css({
									'height': '' + ($data['options']['widget-height'] * widget['height']) + 'px',
									'width': '' + ($data['options']['widget-width'] * widget['width']) + '%'
								})
								.show();

							$(event.target).addClass('dashbrd-grid-widget-dragging');
						},
						drag: function(event, ui) {
							var	widget = getWidgetByTarget($data['widgets'], event.target),
								widget_pos = getWidgetPosition($this, $data, widget, event.target);

							$data['placeholder'].css({
								'top': '' + ($data['options']['widget-height'] * widget_pos.row) + 'px',
								'left': '' + ($data['options']['widget-width'] * widget_pos.col) + '%'
							});

							if ($data['options']['rows'] < widget_pos.row + widget.height) {
								resizeDashboardGrid($this, $data, widget_pos.row + widget.height);
							}
						},
						stop: function(event, ui) {
							var	widget = getWidgetByTarget($data['widgets'], event.target),
								widget_pos = getWidgetPosition($this, $data, widget, event.target);

							widget['row'] = widget_pos['row'];
							widget['col'] = widget_pos['col'];

							$(event.target).css({
								'top': '' + ($data['options']['widget-height'] * widget_pos.row) + 'px',
								'left': '' + ($data['options']['widget-width'] * widget_pos.col) + '%'
							});

							$data['placeholder'].hide();

							$(event.target).removeClass('dashbrd-grid-widget-dragging');

							resizeDashboardGrid($this, $data);
						}
					});

					$widget.resizable({
						handles: 'all',
						start: function(event, ui) {
							$(event.target).addClass('dashbrd-grid-widget-dragging');
						},
						resize: function(event, ui) {
						},
						stop: function(event, ui) {
							$(event.target).removeClass('dashbrd-grid-widget-dragging');
						}
					});
				});
			}
		}

		$.fn.dashboardGrid = function(method) {
			if (methods[method]) {
				return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
			} else if (typeof method === 'object' || !method) {
				return methods.init.apply(this, arguments);
			} else {
				$.error('Invalid method "' +  method + '".');
			}
		}

		$('.dashbrd-grid-container')
			.dashboardGrid()
			.dashboardGrid('addWidget', {'row': 1, 'col': 5, 'height': 1, 'width': 1})
			.dashboardGrid('addWidget', {'row': 2, 'col': 0, 'height': 2, 'width': 2})
			.dashboardGrid('addWidget', {'row': 2, 'col': 6, 'height': 3, 'width': 6})
			.dashboardGrid('addWidget', {'row': 1, 'col': 2, 'height': 2, 'width': 1});
		//	.addWidget(1, 1, 1, 1);
	}(jQuery));
</script>

<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var favourites = {graphid: 1, itemid: 1, screenid: 1, slideshowid: 1, sysmapid: 1};

		if (isset(list.object, favourites)) {
			var favouriteIds = [];

			for (var i = 0; i < list.values.length; i++) {
				favouriteIds.push(list.values[i][list.object]);
			}

			sendAjaxData('zabbix.php?action=dashboard.favourite&operation=create', {
				data: {
					object: list.object,
					'objectids[]': favouriteIds
				}
		});
		}
	}
</script>
