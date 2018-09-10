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

if ($data['clock']['critical_error'] !== null) {
	$item = (new CTableInfo())->setNoDataMessage($data['clock']['critical_error']);

	$output = [
		'header' => $data['name'],
		'body' => $item->toString(),
		'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
	];
}
else {
	$item = (new CClock());

	if ($data['clock']['error'] !== null) {
		$item->setError($data['clock']['error']);
	}

	if ($data['clock']['time'] !== null) {
		$item->setTime($data['clock']['time']);
	}

	if ($data['clock']['time_zone_offset'] !== null) {
		$item->setTimeZoneOffset($data['clock']['time_zone_offset']);
	}

	if ($data['clock']['time_zone_string'] !== null) {
		$item->setTimeZoneString($data['clock']['time_zone_string']);
	}

	$output = [
		'header' => $data['name'],
		'body' => $item->toString(),
		'footer' => (new CList([$item->getTimeDiv(), _s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
		'script_file' => $item->getScriptFile(),
		'script_inline' => $item->getScriptRun()
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
