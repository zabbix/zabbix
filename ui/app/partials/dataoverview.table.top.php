<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CPartial $this
 */
$table = (new CTableInfo())
	->makeVerticalRotation()
	->setHeadingColumn(0);

$headings[] = _('Items');
foreach ($data['hosts'] as $host) {
	$headings[] = (new CColHeader($host['name']))
		->addClass('vertical_rotation')
		->setTitle($host['name']);
}

$table->setHeader($headings);

foreach ($data['items'] as $item_name => $item_data) {
	foreach ($item_data as $items) {
		$row = [(new CColHeader($item_name))->addClass(ZBX_STYLE_NOWRAP)];
		foreach ($data['hosts'] as $host) {
			if (array_key_exists($host['name'], $items)) {
				$item = $items[$host['name']];
				$row[] = getItemDataOverviewCell($item, $item['trigger']);
			}
			else {
				$row[] = new CCol();
			}
		}

		$table->addRow($row);
	}
}

if ($data['has_hidden_data']) {
	$table->setFooter([
		(new CCol(_('Not all results are displayed. Please provide more specific search criteria.')))
			->setColSpan($table->getNumCols())
			->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
	]);
}

echo $table;
