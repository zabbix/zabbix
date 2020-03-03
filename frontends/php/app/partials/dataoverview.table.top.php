<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
foreach ($data['db_hosts'] as $host) {
	$headings[] = (new CColHeader($host['name']))
		->addClass('vertical_rotation')
		->setTitle($host['name']);
}

$table->setHeader($headings);

foreach ($data['items_by_name'] as $name => $hostid_to_itemid) {
	$row = [(new CColHeader($name))->addClass(ZBX_STYLE_NOWRAP)];

	foreach ($data['db_hosts'] as $hostid => $host) {
		if (!array_key_exists($host['hostid'], $hostid_to_itemid)) {
			$row[] = new CCol();
		}
		else {
			$itemid = $hostid_to_itemid[$host['hostid']];
			$item = $data['visible_items'][$itemid];
			$row[] = getItemDataOverviewCell($item, $item['trigger']);
		}
	}

	$table->addRow($row);
}

if ($data['has_hidden_data']) {
	$table->setFooter([
		(new CCol(_('Not all results are displayed. Please provide more specific search criteria.')))
			->setColSpan($table->getNumCols())
			->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
	]);
}

echo $table;
