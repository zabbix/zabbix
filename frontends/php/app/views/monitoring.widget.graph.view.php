<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

if ($data['only_footer']) {
	$output = [
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
	];
}
elseif ($data['graph']['critical_error'] !== null) {
	$item = (new CTableInfo())->setNoDataMessage($data['graph']['critical_error']);

	$output = [
		'header' => $data['name'],
		'body' => $item->toString(),
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
	];
}
else {
	$item = (new CDiv())->setId($data['graph']['containerid']);

	$flickerfree_item = (new CDiv($item))
		->addClass('flickerfreescreen')
		->setAttribute('data-timestamp', $data['graph']['timestamp'])
		->setId('flickerfreescreen_'.$data['graph']['dataid']);

	$script = 'timeControl.addObject("'.$data['graph']['dataid'].'", '.CJs::encodeJson($data['timeline']).', '.
		CJs::encodeJson($data['time_control_data']).');'.
		'timeControl.processObjects();'.
		'window.flickerfreeScreen.add('.zbx_jsvalue($data['fs_data']).');';

if ($data['widget']['initial_load'] == 1) {
	$script .=
			'if (typeof(zbx_graph_widget_resize_end) !== typeof(Function)) {'.
				'function zbx_graph_widget_resize_end(img_id) {'.
					'var content = jQuery("#"+img_id).closest(".dashbrd-grid-widget-content"),'.
						'property_zone_height = timeControl.objectList[img_id]["objDims"]["graphPropertyZoneHeight"],'.
						'new_width = content.width(),'.
						'new_height = content.height() - 10,'.
						'src = jQuery("#"+img_id).attr("src");'.

					'if (typeof src === "undefined") return;'.
					'var img_url = new Curl(src);'.
					'img_url.setArgument("width", new_width);'.
					'img_url.setArgument("height", new_height);'.
					'jQuery("#"+img_id)'.
						'.load(img_url.getUrl(), function(response, status, xhr) {'.
							'timeControl.changeSBoxHeight(img_id, +xhr.getResponseHeader("X-ZBX-SBOX-HEIGHT"));'.
						'})'.
						'.attr("src", img_url.getUrl());'.

					'var tmpImg = jQuery("#"+img_id)'.
						'.load(img_url.getUrl(), function(response, status, xhr) {'.
							'timeControl.changeSBoxHeight(img_id, +xhr.getResponseHeader("X-ZBX-SBOX-HEIGHT"));'.
						'});'.

					'$("#"+img_id).replaceWith(tmpImg);'.
				'}'.
			'}'.

			'if (typeof(zbx_graph_widget_timer_refresh) !== typeof(Function)) {'.
				'function zbx_graph_widget_timer_refresh(img_id, grid) {'.
					'timeControl.refreshObject(img_id);'.

					'var url = new Curl("zabbix.php"),'.
						'widget = grid["widget"];'.
					'url.setArgument("action", "widget.'.WIDGET_GRAPH.'.view");'.
					'jQuery.ajax({'.
						'url: url.getUrl(),'.
						'method: "POST",'.
						'data: {'.
							'uniqueid: widget["uniqueid"],'.
							'only_footer: 1'.
						'},'.
						'dataType: "json",'.
						'success: function(resp) {'.
							'widget["content_footer"].html(resp.footer);'.
						'}'.
					'});'.
				'}'.
			'}'.

			'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onResizeEnd", '.
				'"zbx_graph_widget_resize_end", "'.$data['widget']['uniqueid'].'", {'.
					'parameters: ["'.$data['graph']['dataid'].'"],'.
					'trigger_name: "graph_widget_resize_end_'.$data['widget']['uniqueid'].'"'.
				'});'.

			'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "timer_refresh", '.
				'"zbx_graph_widget_timer_refresh", "'.$data['widget']['uniqueid'].'", {'.
					'parameters: ["'.$data['graph']['dataid'].'"],'.
					'grid: {widget: 1},'.
					'trigger_name: "graph_widget_timer_refresh_'.$data['widget']['uniqueid'].'"'.
				'});';
	}

	$script .=
		'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onContentUpdated", '.
			'"zbx_graph_widget_resize_end", "'.$data['widget']['uniqueid'].'", {'.
				'parameters: ["'.$data['graph']['dataid'].'"],'.
				'trigger_name: "graph_widget_content_update_end_'.$data['widget']['uniqueid'].'"'.
			'});';

	$output = [
		'header' => $data['name'],
		'body' => $flickerfree_item->toString(),
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
		'script_inline' => $script
	];
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
