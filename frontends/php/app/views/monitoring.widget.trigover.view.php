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


/**
 * @var CView $this
 */

if ($data['style'] == STYLE_TOP) {
	$table = (new CPartial('trigoverview.table.top', $data))->getOutput();
}
else {
	$table = (new CPartial('trigoverview.table.left', $data))->getOutput();
}

$output = [
	'header' => $data['name'],
	'body' => $table
];

if ($data['initial_load']) {
	$output['script_inline'] =
		'if (typeof refreshTrigOverViewWidget !== typeof(Function)) {'.
			'function refreshTrigOverViewWidget(event, response, overlay) {'.
				'var element = overlays_stack.length ? overlays_stack.end().element : overlay.element;'.
				'if (element) {'.
					'element = (element instanceof jQuery) ? element[0] : element;'.
					'var widgets = $(".dashbrd-grid-container").dashboardGrid("getWidgetsBy", "type", "trigover");'.
					'widgets.forEach(widget => {'.
						'if ($.contains(widget.container[0], element)) {'.
							'clearMessages();'.
							'addMessage(makeMessageBox("good", response.message, null, true));'.
							'$(".dashbrd-grid-container").dashboardGrid("refreshWidget", widget.uniqueid);'.
						'}'.
					'});'.
				'}'.
			'}'.

			'$.subscribe("acknowledge.create", refreshTrigOverViewWidget);'.
		'}';
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
