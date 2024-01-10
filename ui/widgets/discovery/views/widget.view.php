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
 * Discovery status widget view.
 *
 * @var CView $this
 * @var array $data
 */

if ($data['error'] !== null) {
	$table = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$table = (new CTableInfo())
		->setHeader([
			_x('Discovery rule', 'compact table header'),
			_x('Up', 'discovery results in dashboard'),
			_x('Down', 'discovery results in dashboard')
		])
		->setHeadingColumn(0);

	foreach ($data['drules'] as $drule) {
		$table->addRow([
			$data['allowed_ui_discovery']
				? new CLink($drule['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'discovery.view')
					->setArgument('filter_set', 1)
					->setArgument('filter_druleids', [$drule['druleid']])
				)
				: $drule['name'],
			$drule['up'] != 0 ? (new CSpan($drule['up']))->addClass(ZBX_STYLE_GREEN) : '',
			$drule['down'] != 0 ? (new CSpan($drule['down']))->addClass(ZBX_STYLE_RED) : ''
		]);
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();
