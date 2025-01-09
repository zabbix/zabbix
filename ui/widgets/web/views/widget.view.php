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
 * Web monitoring widget view.
 *
 * @var CView $this
 * @var array $data
 */

$table = new CTableInfo();

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	// indicator of sort field
	$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

	$table
		->setHeader([
			[_x('Host group', 'compact table header'), $sort_div],
			_x('Ok', 'compact table header'),
			_x('Failed', 'compact table header'),
			_x('Unknown', 'compact table header')
		])
		->setHeadingColumn(0);

	$url = $data['allowed_ui_hosts']
		? (new CUrl('zabbix.php'))
			->setArgument('action', 'web.view')
			->setArgument('filter_set', '1')
		: null;

	foreach ($data['groups'] as $group) {
		if ($url !== null) {
			$url->setArgument('filter_groupids', [$group['groupid']]);
			$group_name = new CLink($group['name'], $url->getUrl());
		}
		else {
			$group_name = $group['name'];
		}

		$table->addRow(
			(new CRow([
				$group_name,
				$group['ok'] != 0 ? (new CSpan($group['ok']))->addClass(ZBX_STYLE_GREEN) : '',
				$group['failed'] != 0 ? (new CSpan($group['failed']))->addClass(ZBX_STYLE_RED) : '',
				$group['unknown'] != 0 ? (new CSpan($group['unknown']))->addClass(ZBX_STYLE_GREY) : ''
			]))->setAttribute('data-hostgroupid', $group['groupid'])
		);
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();
