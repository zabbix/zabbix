<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


if ($data['graph']['unavailable_object']) {
	$item = (new CTableInfo())->setNoDataMessage(_('No permissions to referred object or it does not exist!'));

	$output = [
		'header' => $data['name'],
		'body' => $item->toString()
	];
}
else {
	$flickerfree_item = (new CDiv())
		->addItem((new CLink(null, $data['item_graph_url']))
			->setId($data['graph']['containerid'])
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_GRAPH_LINK)
		)
		->addClass('flickerfreescreen')
		->setAttribute('data-timestamp', $data['graph']['timestamp'])
		->setId('flickerfreescreen_'.$data['graph']['dataid']);

	$script = 'timeControl.addObject("'.$data['graph']['dataid'].'", '.CJs::encodeJson($data['timeline']).', '.
			CJs::encodeJson($data['time_control_data']).
		');'.
		'timeControl.processObjects();'.
		'window.flickerfreeScreen.add('.zbx_jsvalue($data['fs_data']).');';

	if ($data['widget']['initial_load']) {
		$script .=
			'if (typeof zbx_graph_widget_resize_end !== typeof(Function)) {'.
				'function zbx_graph_widget_resize_end(img_id) {'.
					'var $img = jQuery("#" + img_id),'.
						'update = function($img) {'.
							'var img_src = $img.attr("src");'.
								'img_url = new Curl(img_src),'.
								'content = $img.closest(".dashbrd-grid-widget-content"),'.
								'content_width = Math.floor(content.width()),'.
								'content_height = Math.floor(content.height());'.

							'timeControl.objectList[img_id]["objDims"].width = content_width;'.
							'timeControl.objectList[img_id]["objDims"].graphHeight = content_height;'.

							'img_url.setArgument("width", content_width);'.
							'img_url.setArgument("height", content_height);'.
							'img_url.setArgument("_", (new Date).getTime().toString(34));'.
							'$img.attr("src", img_url.getUrl());'.
						'};'.

					'if ($img.attr("src") === undefined) {'.
						'$img.one("load", function() {'.
							'update($img);'.
						'});'.
					'}'.
					'else {'.
						'update($img);'.
					'}'.
				'}'.
			'}'.

			'if (typeof zbx_graph_widget_timer_refresh !== typeof(Function)) {'.
				'function zbx_graph_widget_timer_refresh(img_id) {'.
					'timeControl.refreshObject(img_id);'.
				'}'.
			'}'.

			'if (typeof zbx_graph_widget_delete !== typeof(Function)) {'.
				'function zbx_graph_widget_delete(timeControl_dataid, fs_data) {'.
					'timeControl.removeObject(timeControl_dataid);'.
					'window.flickerfreeScreen.remove(fs_data);'.
				'}'.
			'}'.

			'jQuery(".dashbrd-grid-container").dashboardGrid("addAction", "onResizeEnd", '.
				'"zbx_graph_widget_resize_end", "'.$data['widget']['uniqueid'].'", {'.
					'parameters: ["'.$data['graph']['dataid'].'"],'.
					'trigger_name: "graph_widget_resize_end_'.$data['widget']['uniqueid'].'"'.
				'});'.

			'jQuery(".dashbrd-grid-container").dashboardGrid("addAction", "timer_refresh", '.
				'"zbx_graph_widget_timer_refresh", "'.$data['widget']['uniqueid'].'", {'.
					'parameters: ["'.$data['graph']['dataid'].'"],'.
					'trigger_name: "graph_widget_timer_refresh_'.$data['widget']['uniqueid'].'"'.
				'});'.

			'jQuery(".dashbrd-grid-container").dashboardGrid("addAction", "onWidgetDelete", '.
				'"zbx_graph_widget_delete", "'.$data['widget']['uniqueid'].'", {'.
					'parameters: ["'.$data['graph']['dataid'].'",'.zbx_jsvalue($data['fs_data']).'],'.
					'trigger_name: "graph_widget_delete_'.$data['widget']['uniqueid'].'"'.
				'});';
	}

	$output = [
		'header' => $data['name'],
		'body' => $flickerfree_item->toString(),
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
