<?php
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


use Widgets\TrigOver\Includes\ViewHelper;

/**
 * @var CPartial $this
 * @var array    $data
 */

$table = (new CTableInfo())->setHeadingColumn(0);

$header[] = $data['is_template_dashboard'] ? _('Host') : _('Hosts');

foreach ($data['triggers_by_name'] as $trigname => $host_to_trig) {
	$header[] = (new CSpan($trigname))
		->addClass(ZBX_STYLE_TEXT_VERTICAL)
		->setTitle($trigname);
}

$table->setHeader($header);

foreach ($data['hosts_by_name'] as $hostname => $hostid) {
	$name = (new CLinkAction($data['db_hosts'][$hostid]['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostid));
	$row = [(new CColHeader($name))->addClass(ZBX_STYLE_NOWRAP)];

	foreach ($data['triggers_by_name'] as $trigname => $host_to_trig) {
		$trigger = null;
		if (array_key_exists($hostid, $host_to_trig)) {
			$triggerid = $host_to_trig[$hostid];
			$trigger = $data['db_triggers'][$triggerid];
		}

		if ($trigger) {
			$row[] = ViewHelper::getTriggerOverviewCell($trigger, $data['dependencies']);
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
