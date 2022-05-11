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

$header = [_('Hosts')];
foreach ($data['items'] as $item_name => $item_data) {
	foreach ($item_data as $columns_data) {
		$header[] = (new CColHeader($item_name))
			->addClass('vertical_rotation')
			->setTitle($item_name);
	}
}
$table->setHeader($header);

foreach ($data['hosts'] as $hostid => $host) {
	$name = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostid));
	$row = [(new CColHeader($name))->addClass(ZBX_STYLE_NOWRAP)];

	foreach ($data['items'] as $item_name => $columns_data) {
		foreach ($columns_data as $column_data) {
			if (array_key_exists($host['name'], $column_data)) {
				$item = $column_data[$host['name']];
				$row[] = getItemDataOverviewCell($item, $item['trigger']);
			}
			else {
				$row[] = new CCol();
			}
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
