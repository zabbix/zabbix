<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Gauge widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Gauge\Widget;

if ($data['error'] !== '') {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$body = new CTable();

	// Debug and testing raw data.
	$body->addRow(['description text : ', $data['data']['description']['text']]);
	$body->addRow(['description font_size: ', $data['data']['description']['font_size']]);
	$body->addRow(['description pos: ', $data['data']['description']['pos']]);
	$body->addRow(['description bold: ', $data['data']['description']['is_bold']]);
	$body->addRow(['description color: ', $data['data']['description']['color']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['value type: ', $data['data']['value']['type']]);
	$body->addRow(['value text: ', $data['data']['value']['text']]);
	$body->addRow(['value font_size: ', $data['data']['value']['font_size']]);
	$body->addRow(['value bold: ', $data['data']['value']['is_bold']]);
	$body->addRow(['value color: ', $data['data']['value']['color']]);
	$body->addRow(['value show_arc: ', $data['data']['value']['show_arc']]);
	$body->addRow(['value arc_size: ', $data['data']['value']['arc_size']]);
	$body->addRow(['value prev_value: ', $data['data']['value']['prev_value']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['units text: ', $data['data']['units']['text']]);
	$body->addRow(['units pos: ', $data['data']['units']['pos']]);
	$body->addRow(['units show: ', $data['data']['units']['show']]);
	$body->addRow(['units font_size: ', $data['data']['units']['font_size']]);
	$body->addRow(['units bold: ', $data['data']['units']['is_bold']]);
	$body->addRow(['units color: ', $data['data']['units']['color']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['needle show: ', $data['data']['needle']['show']]);
	$body->addRow(['needle color: ', $data['data']['needle']['color']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['min raw: ', $data['data']['minmax']['min']['raw']]);
	$body->addRow(['min text: ', $data['data']['minmax']['min']['text']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['max raw: ', $data['data']['minmax']['max']['raw']]);
	$body->addRow(['max text: ', $data['data']['minmax']['max']['text']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['minmax show: ', $data['data']['minmax']['show']]);
	$body->addRow(['minmax size: ', $data['data']['minmax']['size']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	$body->addRow(['empty_color: ', $data['data']['empty_color']]);
	$body->addRow(['bg_color: ', $data['data']['bg_color']]);
	$body->addRow(['&nbsp;', '&nbsp;']);

	foreach ($data['data']['thresholds']['data'] as $th_data) {
		foreach ($th_data as $k => $v) {
			$body->addRow(['thresholds data ['.$k.'] : ', $v]);
		}
	}
	$body->addRow(['&nbsp;', '&nbsp;']);
	$body->addRow(['thresholds show_arc : ', $data['data']['thresholds']['show_arc']]);
	$body->addRow(['thresholds arc_size : ', $data['data']['thresholds']['arc_size']]);
	$body->addRow(['thresholds show_labels : ', $data['data']['thresholds']['show_labels']]);
}

(new CWidgetView($data))
	->addItem($body)
	->show();
