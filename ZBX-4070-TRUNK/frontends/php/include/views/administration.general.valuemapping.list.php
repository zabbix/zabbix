<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
$valueMappingForm = new CForm();
$valueMappingForm->setName('valuemappingForm');
$valueMappingForm->addVar('config', 6);
$valueMappingForm->addItem(BR());

$valueMappingTable = new CTableInfo();
$valueMappingTable->setHeader(array(_('Name'), _('Value map')));

foreach ($this->data['valuemaps'] as $valuemap) {
	$maps = $valuemap['maps'];
	order_result($maps, 'value');

	$mappings_row = array();
	foreach ($maps as $map) {
		array_push($mappings_row, $map['value'], SPACE.RARR.SPACE, $map['newvalue'], BR());
	}
	$valueMappingTable->addRow(array(new CLink($valuemap['name'],'config.php?form=update&valuemapid='.$valuemap['valuemapid'].url_param('config')), empty($mappings_row) ? SPACE : $mappings_row));
}

$valueMappingForm->addItem($valueMappingTable);

return $valueMappingForm;
?>
