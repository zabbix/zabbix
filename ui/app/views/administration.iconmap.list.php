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


/**
 * @var CView $this
 */

$html_page = (new CHtmlPage())
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
	$mappings = [];

	foreach ($icon_map['mappings'] as $mapping) {
		$mappings[] = [
			$data['inventory_list'][$mapping['inventory_link']].NAME_DELIMITER.$mapping['expression'],
			NBSP(), RARR(), NBSP(),
			$data['icon_list'][$mapping['iconid']],
			BR()
		];
	}

	$table->addRow([new CLink($icon_map['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.edit')
		->setArgument('iconmapid', $icon_map['iconmapid'])
	), $mappings]);
}

$html_page->addItem($table)->show();
