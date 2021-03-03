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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$ids = API::Dashboard()->get([
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true,
]);
$r = API::Dashboard()->delete(array_keys($ids));
$m = CMessageHelper::getMessages();



$pages = [];
for ($i = 1; $i < 10; $i++) {
	$pages[] = [
		'name' => 'Verza Page '.$i,
		'widgets' => [
			[
				'type' => 'clock',
				'width' => 4,
				'height' => 3,
				'x' => $i * 2 - 2,
			]
		],
	];
}

$r = API::Dashboard()->create([
	[
		'name' => 'Big Ben 123',
		'auto_start' => 0,
		'display_period' => 1800,
		'pages' => $pages,
	]
]);
var_dump(($m = CMessageHelper::getMessages()) ? $m : $r);
