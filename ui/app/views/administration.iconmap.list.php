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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

$widget = (new CWidget())
	->setTitle(_('Icon mapping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_ICONMAP_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('Create icon map'),
					(new CUrl('zabbix.php'))->setArgument('action', 'iconmap.edit')
				))
		))->setAttribute('aria-label', _('Content controls'))
	);

$table = (new CTableInfo())->setHeader([_('Name'), _('Icon map')]);

foreach ($data['iconmaps'] as $icon_map) {
	$row = [];
	foreach ($icon_map['mappings'] as $mapping) {
		$row[] = $data['inventory_list'][$mapping['inventory_link']].NAME_DELIMITER.
				$mapping['expression'].SPACE.'&rArr;'.SPACE.$data['icon_list'][$mapping['iconid']];
		$row[] = BR();
	}

	$table->addRow([new CLink($icon_map['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.edit')
		->setArgument('iconmapid', $icon_map['iconmapid'])
	), $row]);
}

$widget->addItem($table)->show();
