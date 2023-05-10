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
 * Data overview widget view.
 *
 * @var CView $this
 * @var array $data
 */

if ($data['error'] !== null) {
	$table = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$table = $data['style'] == STYLE_TOP
		? (new CPartial('table.top', $data))->getOutput()
		: (new CPartial('table.left', $data))->getOutput();
}

(new CWidgetView($data))
	->addItem($table)
	->show();
