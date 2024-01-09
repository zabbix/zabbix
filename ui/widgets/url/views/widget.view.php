<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * URL widget form view.
 *
 * @var CView $this
 * @var array $data
 */

if ($data['url']['error'] !== null) {
	$item = (new CTableInfo())->setNoDataMessage($data['url']['error']);
}
else {
	$item = (new CIFrame($data['url']['url'], '100%', '100%', 'auto'))->addClass(ZBX_STYLE_WIDGET_URL);

	if ($data['config']['iframe_sandboxing_enabled'] == 1) {
		$item->setAttribute('sandbox', $data['config']['iframe_sandboxing_exceptions']);
	}
}

(new CWidgetView($data))
	->addItem($item)
	->show();
