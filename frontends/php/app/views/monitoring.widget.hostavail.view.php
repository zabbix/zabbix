<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$table = (new CTag('table', true))->addClass(ZBX_STYLE_HOST_AVAIL_WIDGET);

$available_row = (new CCol([
	(new CSpan($data['hosts'][HOST_AVAILABLE_TRUE]))->addClass(ZBX_STYLE_HOST_AVAIL_COUNT), _('Available')
]))->addClass(ZBX_STYLE_HOST_AVAIL_TRUE);

$not_available_row = (new CCol([
	(new CSpan($data['hosts'][HOST_AVAILABLE_FALSE]))->addClass(ZBX_STYLE_HOST_AVAIL_COUNT), _('Not available')
]))->addClass(ZBX_STYLE_HOST_AVAIL_FALSE);

$unknown_row = (new CCol([
	(new CSpan($data['hosts'][HOST_AVAILABLE_UNKNOWN]))->addClass(ZBX_STYLE_HOST_AVAIL_COUNT), _('Unknown')
]))->addClass(ZBX_STYLE_HOST_AVAIL_UNKNOWN);

$total_row = (new CCol([
	(new CSpan($data['total']))->addClass(ZBX_STYLE_HOST_AVAIL_COUNT), _('Total')
]))->addClass(ZBX_STYLE_HOST_AVAIL_TOTAL);

if ($data['layout'] == STYLE_HORIZONTAL) {
	$table
		->addItem([$available_row, $not_available_row, $unknown_row, $total_row])
		->addClass(ZBX_STYLE_HOST_AVAIL_LAYOUT_HORIZONTAL);
}
else {
	$table
		->addItem(new CRow($available_row))
		->addItem(new CRow($not_available_row))
		->addItem(new CRow($unknown_row))
		->addItem(new CRow($total_row))
		->addClass(ZBX_STYLE_HOST_AVAIL_LAYOUT_VERTICAL);
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
