<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	->setTitle(_('Icon mapping'))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.iconmapping.php'))
				->addItem(new CSubmit('form', _('Create icon map')))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$iconMapTable = (new CTableInfo())
	->setHeader([
		_('Name'),
		_('Icon map')
	])
	->addItem(BR());

foreach ($this->data['iconmaps'] as $iconMap) {
	$row = [];
	foreach ($iconMap['mappings'] as $mapping) {
		$row[] = $this->data['inventoryList'][$mapping['inventory_link']].NAME_DELIMITER.
				$mapping['expression'].SPACE.'&rArr;'.SPACE.$this->data['iconList'][$mapping['iconid']];
		$row[] = BR();
	}

	$iconMapTable->addRow([
		new CLink($iconMap['name'], 'adm.iconmapping.php?form=update&iconmapid='.$iconMap['iconmapid']),
		$row
	]);
}

$widget->addItem($iconMapTable);

return $widget;
