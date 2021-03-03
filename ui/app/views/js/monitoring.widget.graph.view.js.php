<?php
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

(widget, dashboard_page, {graph_url, time_control_data, flickerfreescreen_data}) => {

	const uniqueid = getUniqueId();

	const flickerfreescreen = widget.content_body[0].firstElementChild;
	const flickerfreescreen_container = flickerfreescreen.firstElementChild;

	const events = {
		widgetActivate: () => {
			const time_selector = ZABBIX.Dashboard.getTimeSelector();

			if (graph_url !== null) {
				const curl = new Curl(graph_url, false);

				curl.setArgument('from', time_selector.from);
				curl.setArgument('to', time_selector.to);

				flickerfreescreen_container.href = curl.getUrl();
			}

			const curl = new Curl(time_control_data.src, false);

			curl.setArgument('from', time_selector.from);
			curl.setArgument('to', time_selector.to);

			time_control_data.src = curl.getUrl();

			timeControl.addObject('graph_' + uniqueid, time_selector, time_control_data);
			timeControl.processObjects();

			flickerfreescreen_data.timeline = time_selector;

			window.flickerfreeScreen.add(flickerfreescreen_data);
		},

		widgetDeactivate: () => {
			timeControl.removeObject('graph_' + uniqueid);
			window.flickerfreeScreen.remove(flickerfreescreen_data);
		},

		widgetResize: () => {
			const image = document.getElementById('graph_' + uniqueid);

			if (image.src === '') {
				image.addEventListener('load', events.widgetResize, {once: true});

				return;
			}

			const content = widget.content_body[0];
			const content_style = getComputedStyle(content);
			const content_width = Math.floor(content.clientWidth - parseFloat(content_style.paddingLeft)
				- parseFloat(content_style.paddingRight)
			);
			const content_height = Math.floor(content.clientHeight - parseFloat(content_style.paddingTop)
				- parseFloat(content_style.paddingBottom)
			);

			timeControl.objectList['graph_' + uniqueid].objDims.width = content_width;
			timeControl.objectList['graph_' + uniqueid].objDims.graphHeight = content_height;

			const image_url = new Curl(image.src, false);

			image_url.setArgument('width', content_width);
			image_url.setArgument('height', content_height);
			image_url.setArgument('_', (new Date).getTime().toString(34));
			image.src = image_url.getUrl();
		},

		widgetRefresh: (e) => {
			timeControl.refreshObject('graph_' + uniqueid);

			e.preventDefault();
		},

		widgetDelete: () => {
			events.widgetDeactivate();
		},

		dashboardPageEdit: () => {
			if (graph_url !== null) {
				flickerfreescreen_container.href = 'javascript:void(0)';
				flickerfreescreen_container.setAttribute('role', 'button');
			}
		}
	};

	time_control_data.id = 'graph_' + uniqueid;
	time_control_data.containerid = 'graph_container_' + uniqueid;
	flickerfreescreen_data.id = 'graph_' + uniqueid;

	flickerfreescreen.id = 'flickerfreescreen_graph_' + uniqueid;
	flickerfreescreen_container.id = 'graph_container_' + uniqueid;

	widget
		.on(WIDGET_EVENT_ACTIVATE, events.widgetActivate)
		.on(WIDGET_EVENT_DEACTIVATE, events.widgetDeactivate)
		.on('onResizeEnd', events.widgetResize)
		.on(WIDGET_EVENT_REFRESH, events.widgetRefresh, {passive: false})
		.on(WIDGET_EVENT_DELETE, events.widgetDelete);

	dashboard_page.on(DASHBOARD_PAGE_EVENT_EDIT, events.dashboardPageEdit);

	events.widgetActivate();
}
