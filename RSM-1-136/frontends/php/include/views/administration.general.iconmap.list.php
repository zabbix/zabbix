<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
$table = new CTableInfo(_('No icon map defined.'));
$table->setHeader(array(_('Name'), _('Icon map')));
$table->addItem(BR());

foreach ($this->data['iconmaps'] as $iconmap) {
	$mappings = $iconmap['mappings'];
	order_result($mappings, 'sortorder');

	$row = array();
	foreach ($mappings as $mapping) {
		$row[] = $this->data['inventoryList'][$mapping['inventory_link']].':'.
				$mapping['expression'].SPACE.RARR.SPACE.$this->data['iconList'][$mapping['iconid']];
		$row[] = BR();
	}
	$table->addRow(array(
		new CLink($iconmap['name'], 'adm.iconmapping.php?form=update&iconmapid='.$iconmap['iconmapid']),
		$row
	));
}

return $table;
?>
