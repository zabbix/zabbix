<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @var CPartial $this
 * @var array $data
 */

$table = (new CTableInfo())->setHeadingColumn(0);

$headings[] = _('Items');
foreach ($data['hosts'] as $host) {
	$headings[] = (new CSpan($host['name']))
		->addClass(ZBX_STYLE_TEXT_VERTICAL)
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
