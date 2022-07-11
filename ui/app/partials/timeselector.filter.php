<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CPartial $this
 */

$time_ranges = [
	[
		['now-2d', 'now'],
		['now-7d', 'now'],
		['now-30d', 'now'],
		['now-3M', 'now'],
		['now-6M', 'now'],
		['now-1y', 'now'],
		['now-2y', 'now']
	],
	[
		['now-1d/d', 'now-1d/d'],
		['now-2d/d', 'now-2d/d'],
		['now-1w/d', 'now-1w/d'],
		['now-1w/w', 'now-1w/w'],
		['now-1M/M', 'now-1M/M'],
		['now-1y/y', 'now-1y/y']
	],
	[
		['now/d', 'now/d'],
		['now/d', 'now'],
		['now/w', 'now/w'],
		['now/w', 'now'],
		['now/M', 'now/M'],
		['now/M', 'now'],
		['now/y', 'now/y'],
		['now/y', 'now']
	],
	[
		['now-5m', 'now'],
		['now-15m', 'now'],
		['now-30m', 'now'],
		['now-1h', 'now'],
		['now-3h', 'now'],
		['now-6h', 'now'],
		['now-12h', 'now'],
		['now-24h', 'now']
	]
];
$predefined_ranges = [];

foreach ($time_ranges as $column_ranges) {
	$column = (new CList())->addClass(ZBX_STYLE_TIME_QUICK);

	foreach ($column_ranges as $range) {
		$label = relativeDateToText($range[0], $range[1]);
		$is_selected = ($data['label'] === $label);

		$column->addItem((new CLink($label))
			->setAttribute('data-from', $range[0])
			->setAttribute('data-to', $range[1])
			->setAttribute('data-label', $label)
			->addClass($is_selected ? ZBX_STYLE_SELECTED : null)
		);
	}

	$predefined_ranges[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
}

(new CDiv([
	(new CDiv([
		new CList([
			new CLabel(_('From'), 'from'),
			(new CDateSelector('from', $data['from']))->setDateFormat($data['format'])
		]),
		(new CList([(new CListItem(''))->addClass(ZBX_STYLE_RED)]))
			->setAttribute('data-error-for', 'from')
			->addClass(ZBX_STYLE_TIME_INPUT_ERROR)
			->addStyle('display: none'),
		new CList([
			new CLabel(_('To'), 'to'),
			(new CDateSelector('to', $data['to']))->setDateFormat($data['format'])
		]),
		(new CList([(new CListItem(''))->addClass(ZBX_STYLE_RED)]))
			->setAttribute('data-error-for', 'to')
			->addClass(ZBX_STYLE_TIME_INPUT_ERROR)
			->addStyle('display: none'),
		new CList([
			new CButton('apply', _('Apply'))
		])
	]))->addClass(ZBX_STYLE_TIME_INPUT),
	(new CDiv($predefined_ranges))->addClass(ZBX_STYLE_TIME_QUICK_RANGE)
]))
	->addClass(ZBX_STYLE_FILTER_CONTAINER)
	->addClass(ZBX_STYLE_TIME_SELECTION_CONTAINER)
	->show();
