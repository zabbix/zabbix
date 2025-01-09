<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
