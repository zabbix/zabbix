<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Value mapping'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			->addItem(makeAdministrationGeneralMenu('adm.valuemapping.php'))
			->addItem(new CSubmit('form', _('Create value map')))
		)
	);

$valuemap_table = (new CTableInfo())->setHeader([_('Name'), _('Value map')]);

foreach ($data['valuemaps'] as $valuemap) {
	$mappings = [];

	foreach ($valuemap['mappings'] as $mapping) {
		$mappings[] = $mapping['value'].' &rArr; '.$mapping['newvalue'];
		$mappings[] = BR();
	}
	array_pop($mappings);

	$valuemap_table->addRow([
		new CLink($valuemap['name'], 'adm.valuemapping.php?form=update&valuemapid='.$valuemap['valuemapid']),
		$mappings
	]);
}

$widget->addItem($valuemap_table);

return $widget;
