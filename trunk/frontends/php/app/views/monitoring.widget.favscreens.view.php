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


$table = (new CTableInfo())->setNoDataMessage(_('No screens added.'));

foreach ($data['screens'] as $screen) {
	$url = $screen['slideshow']
		? (new CUrl('slides.php'))->setArgument('elementid', $screen['slideshowid'])
		: (new CUrl('screens.php'))->setArgument('elementid', $screen['screenid']);
	$on_click = $screen['slideshow']
		? "rm4favorites('slideshowid','".$screen['slideshowid']."')"
		: "rm4favorites('screenid','".$screen['screenid']."')";

	$table->addRow([
		new CLink($screen['label'], $url),
		(new CButton())
			->onClick($on_click)
			->addClass(ZBX_STYLE_REMOVE_BTN)
	]);
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList([
		_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))
	]))->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
