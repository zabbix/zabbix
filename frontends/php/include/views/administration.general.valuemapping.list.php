<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.valuemapping.php'))
				->addItem(new CSubmit('form', _('Create value map')))
				->addItem((new CButton('form', _('Import')))
					->onClick('redirect("conf.import.php?rules_preset=valuemap")')
				)
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$form = (new CForm())
	->setName('valuemap_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_valuemaps'))
				->onClick("checkAll('".$form->getName()."', 'all_valuemaps', 'valuemapids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder']),
		_('Value map'),
		_('Used in items')
	]);

foreach ($data['valuemaps'] as $valuemap) {
	$mappings = [];

	foreach ($valuemap['mappings'] as $mapping) {
		$mappings[] = $mapping['value'].' &rArr; '.$mapping['newvalue'];
		$mappings[] = BR();
	}
	array_pop($mappings);

	$table->addRow([
		new CCheckBox('valuemapids['.$valuemap['valuemapid'].']', $valuemap['valuemapid']),
		new CLink($valuemap['name'], 'adm.valuemapping.php?form=update&valuemapid='.$valuemap['valuemapid']),
		$mappings,
		$valuemap['used_in_items'] ? (new CCol(_('Yes')))->addClass(ZBX_STYLE_GREEN) : ''
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'valuemapids', [
		'valuemap.export' => ['name' => _('Export')],
		'valuemap.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected value maps?')]
	])
]);

$widget->addItem($form);

return $widget;
