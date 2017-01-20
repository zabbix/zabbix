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


include('include/views/js/administration.general.iconmap.js.php');

$widget = (new CWidget())
	->setTitle(_('Icon mapping'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.iconmapping.php')))
	);

$iconMapTab = new CFormList();

$name = (new CTextBox('iconmap[name]', $this->data['iconmap']['name']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 64)
	->setAttribute('autofocus', 'autofocus');
$iconMapTab->addRow(_('Name'), $name);

$iconMapForm = (new CForm())
	->addVar('form', 1);
if (isset($this->data['iconmapid'])) {
	$iconMapForm->addVar('iconmapid', $this->data['iconmap']['iconmapid']);
}

$iconMapTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setId('iconMapTable')
	->setHeader(['', '', _('Inventory field'), _('Expression'), _('Icon'), '', _('Action')]);

order_result($this->data['iconmap']['mappings'], 'sortorder');
$i = 0;
foreach ($this->data['iconmap']['mappings'] as $mapping) {
	$numSpan = (new CSpan(($i + 1).':'))->addClass('rowNum');

	$profileLinksComboBox = new CComboBox('iconmap[mappings]['.$i.'][inventory_link]', $mapping['inventory_link'], null,
		$this->data['inventoryList']
	);

	$expressionTextBox = [
		(new CTextBox('iconmap[mappings]['.$i.'][expression]', $mapping['expression']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('maxlength', 64)
	];
	if (isset($mapping['iconmappingid'])) {
		$expressionTextBox[] = new CVar('iconmap[mappings]['.$i.'][iconmappingid]', $mapping['iconmappingid']);
	}

	$iconsComboBox = new CComboBox('iconmap[mappings]['.$i.'][iconid]', $mapping['iconid'], null,
		$this->data['iconList']
	);
	$iconsComboBox->addClass('mappingIcon');

	$iconPreviewImage = (new CImg('imgstore.php?iconid='.$mapping['iconid'].'&width='.ZBX_ICON_PREVIEW_WIDTH.
		'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null))
		->addClass('preview')
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->setAttribute('data-image-full', 'imgstore.php?iconid='.$mapping['iconid']);

	$iconMapTable->addRow(
		(new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			$numSpan,
			$profileLinksComboBox,
			$expressionTextBox,
			$iconsComboBox,
			(new CCol($iconPreviewImage))->setAttribute('style', 'vertical-align: middle;'),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('removeMapping')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('iconmapidRow_'.$i)
	);

	$i++;
}

// add row button
$iconMapTable->addRow((new CRow([
	(new CCol(
		(new CButton('addMapping', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
	))->setColSpan(7)
]))->setId('iconMapListFooter'));

// <default icon row>
$iconsComboBox = new CComboBox('iconmap[default_iconid]', $this->data['iconmap']['default_iconid'], null,
	$this->data['iconList']
);
$iconsComboBox->addClass('mappingIcon');

$iconPreviewImage = (new CImg('imgstore.php?iconid='.$this->data['iconmap']['default_iconid'].
	'&width='.ZBX_ICON_PREVIEW_WIDTH.'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null))
	->addClass(ZBX_STYLE_CURSOR_POINTER)
	->addClass('preview')
	->setAttribute('data-image-full', 'imgstore.php?iconid='.$this->data['iconmap']['default_iconid']);

$iconMapTable->addRow([
	(new CCol(_('Default')))->setColSpan(4),
	$iconsComboBox,
	(new CCol($iconPreviewImage))->setAttribute('style', 'vertical-align: middle;')
]);
// </default icon row>

$iconMapTab->addRow(_('Mappings'),
	(new CDiv($iconMapTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
$iconMapView = new CTabView();
$iconMapView->addTab('iconmap', _('Icon map'), $iconMapTab);

// footer
if (isset($this->data['iconmapid'])) {
	$iconMapView->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete icon map?'), url_param('form').url_param('iconmapid')),
			new CButtonCancel()
		]
	));
}
else {
	$iconMapView->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$iconMapForm->addItem($iconMapView);

$widget->addItem($iconMapForm);

return $widget;
