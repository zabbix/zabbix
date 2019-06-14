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


$available_row = (new CCol(sprintf("%d %s", $data['hosts'][HOST_AVAILABLE_TRUE], _('Available'))))->addClass(ZBX_STYLE_HOST_AVAIL_TRUE);
$not_available_row = (new CCol(sprintf("%d %s", $data['hosts'][HOST_AVAILABLE_FALSE], _('Not available'))))->addClass(ZBX_STYLE_HOST_AVAIL_FALSE);
$unknown_row = (new CCol(sprintf("%d %s", $data['hosts'][HOST_AVAILABLE_UNKNOWN], _('Unknown'))))->addClass(ZBX_STYLE_HOST_AVAIL_UNKNOWN);
$total_row = (new CCol(sprintf("%d %s", $data['total'], _('Total'))))->addClass(ZBX_STYLE_HOST_AVAIL_TOTAL);

$table = new CTableInfo();

if ($data['layout'] == STYLE_HORIZONTAL) {
	$table->addRow([$available_row, $not_available_row, $unknown_row, $total_row]);
}
else {
	$table
		->addRow($available_row)
		->addRow($not_available_row)
		->addRow($unknown_row)
		->addRow($total_row);
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
