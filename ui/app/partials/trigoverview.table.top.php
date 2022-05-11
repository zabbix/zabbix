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

$headings[] = _('Triggers');
foreach ($data['hosts_by_name'] as $hostname => $hostid) {
	$headings[] = (new CColHeader($hostname))
		->addClass('vertical_rotation')
		->setTitle($hostname);
}

$table->setHeader($headings);

foreach ($data['triggers_by_name'] as $trigname => $host_to_trig) {
	$row = [(new CColHeader($trigname))->addClass(ZBX_STYLE_NOWRAP)];

	foreach ($data['hosts_by_name'] as $hostname => $hostid) {
		$trigger = null;
		if (array_key_exists($hostid, $host_to_trig)) {
			$triggerid = $host_to_trig[$hostid];
			$trigger = $data['db_triggers'][$triggerid];
		}

		if ($trigger) {
			$row[] = getTriggerOverviewCell($trigger, $data['dependencies']);
		}
		else {
			$row[] = new CCol();
		}
	}

	$table->addRow($row);
}

if ($data['exceeded_limit']) {
	$table->setFooter([
		(new CCol(_('Not all results are displayed. Please provide more specific search criteria.')))
			->setColSpan($table->getNumCols())
			->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
	]);
}

echo $table;
