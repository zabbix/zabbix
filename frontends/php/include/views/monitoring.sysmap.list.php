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
	->setTitle(_('Maps'))
	->setControls((new CTag('nav', true,
		(new CForm('get'))
			->cleanItems()
			->addItem((new CList())
				->addItem(new CSubmit('form', _('Create map')))
				->addItem(
					(new CButton('form', _('Import')))
						->onClick('redirect("map.import.php?rules_preset=map")')
						->removeId()
				)
		)))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(
		(new CFilter())
			->setProfile('web.sysmapconf.filter', 0)
			->addFilterTab(_('Filter'), [
				(new CFormList())->addRow(_('Name'),
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			])
	);

// create form
$sysmapForm = (new CForm())->setName('frm_maps');

// create table
$sysmapTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_maps'))->onClick("checkAll('".$sysmapForm->getName()."', 'all_maps', 'maps');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Width'), 'width', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Height'), 'height', $this->data['sort'], $this->data['sortorder']),
		_('Actions')
	]);

foreach ($this->data['maps'] as $map) {
	$user_type = CWebUser::getType();
	if ($user_type == USER_TYPE_SUPER_ADMIN || $map['editable']) {
		$checkbox = new CCheckBox('maps['.$map['sysmapid'].']', $map['sysmapid']);
		$action = new CLink(_('Properties'), 'sysmaps.php?form=update&sysmapid='.$map['sysmapid']);
		$constructor = new CLink(_('Constructor'), 'sysmap.php?sysmapid='.$map['sysmapid']);
	}
	else {
		$checkbox = (new CCheckBox('maps['.$map['sysmapid'].']', $map['sysmapid']))
			->setAttribute('disabled', 'disabled');
		$action = '';
		$constructor = '';
	}
	$sysmapTable->addRow([
		$checkbox,
		new CLink($map['name'], 'zabbix.php?action=map.view&sysmapid='.$map['sysmapid']),
		$map['width'],
		$map['height'],
		new CHorList([$action, $constructor])
	]);
}

// append table to form
$sysmapForm->addItem([
	$sysmapTable,
	$this->data['paging'],
	new CActionButtonList('action', 'maps', [
		'map.export' => ['name' => _('Export')],
		'map.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected maps?')]
	])
]);

// append form to widget
$widget->addItem($sysmapForm);

return $widget;
