<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


if ($data['only_footer']) {
	$output = [
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
		'period_string' => $data['period_string']
	];
}
elseif ($data['graph']['unavailable_object']) {
	$item = (new CTableInfo())->setNoDataMessage(_('No permissions to referred object or it does not exist!'));

	$output = [
		'header' => $data['name'],
		'body' => $item->toString(),
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
	];
}
else {
	$flickerfree_item = (new CDiv((new CLink(null, $data['item_graph_url']))->setId($data['graph']['containerid'])))
		->addClass('flickerfreescreen')
		->setAttribute('data-timestamp', $data['graph']['timestamp'])
		->setId('flickerfreescreen_'.$data['graph']['dataid']);

	$script = 'timeControl.addObject("'.$data['graph']['dataid'].'", '.CJs::encodeJson($data['timeline']).', '.
			CJs::encodeJson($data['time_control_data']).
		');'.
		'timeControl.processObjects();'.
		'window.flickerfreeScreen.add('.zbx_jsvalue($data['fs_data']).');';

if ($data['widget']['initial_load'] == 1) {
	$script .=
		'if (typeof(zbx_graph_widget_resize_end) !== typeof(Function)) {'.
			'function zbx_graph_widget_resize_end(img_id) {'.
				'var content = jQuery("#"+img_id).closest(".dashbrd-grid-widget-content"),'.
					'property_zone_height = timeControl.objectList[img_id]["objDims"]["graphPropertyZoneHeight"],'.
					'new_width = content.width(),'.
					'new_height = content.height() - 4,'.
					'src = jQuery("#"+img_id).attr("src");'.

				'if (typeof src === "undefined") {'.
					'return;'.
				'}'.

				'var img_url = new Curl(src);'.

				'img_url.setArgument("width", new_width);'.
				'img_url.setArgument("height", new_height);'.
				'jQuery("#"+img_id)'.
					'.attr("src", img_url.getUrl());'.
			'}'.
		'}'.

		'if (typeof(zbx_graph_widget_timer_refresh) !== typeof(Function)) {'.
			'function zbx_graph_widget_timer_refresh(img_id) {'.
				'timeControl.refreshObject(img_id);'.
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
				'trigger_name: "graph_widget_timer_refresh_'.$data['widget']['uniqueid'].'"'.
			'});';
	}

	$output = [
		'header' => $data['name'],
		'body' => $flickerfree_item->toString(),
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
		'script_inline' => $script
	];

	if (array_key_exists('period_string', $data)) {
		$output['period_string'] = $data['period_string'];
	}
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
