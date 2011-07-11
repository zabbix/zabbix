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
$table = new CTableInfo();
$table->setHeader(array(_('Name'), _('Icon map')));

foreach($this->data['iconmaps'] as $iconmap){
	$mappings = $iconmap['mappings'];
	order_result($mappings, 'sortorder');

	$row = array();
	foreach($mappings as $mapping){
		$row[] = $mapping['inventory_link'] . ':' . $mapping['expression'] . SPACE.RARR.SPACE . $mapping['iconid'];
	}
	$table->addRow(array(
		new CLink($iconmap['name'],'config.php?form=update&iconmapid='.$iconmap['iconmapid'].url_param('config')),
		$row
	));
}

return $table;
?>
