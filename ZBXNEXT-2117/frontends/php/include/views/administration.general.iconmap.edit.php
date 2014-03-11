<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

$iconMapTab = new CFormList('scriptsTab');

$name = new CTextBox('iconmap[name]', $this->data['iconmap']['name']);
$name->setAttribute('maxlength', 64);
$name->attr('autofocus', 'autofocus');
$iconMapTab->addRow(_('Name'), $name);

$iconMapTable = new CTable();
$iconMapTable->setAttribute('id', 'iconMapTable');

$iconMapForm = new CForm();
$iconMapForm->addVar('form', 1);
if (isset($this->data['iconmapid'])) {
	$iconMapForm->addVar('iconmapid', $this->data['iconmap']['iconmapid']);
}

// header
$iconMapTable->addRow(array(SPACE, SPACE, _('Inventory field'), _('Expression'), _('Icon'), SPACE, SPACE), 'header');

order_result($this->data['iconmap']['mappings'], 'sortorder');
$i = 0;
foreach ($this->data['iconmap']['mappings'] as $mapping) {
	$numSpan = new CSpan(($i + 1).':');
	$numSpan->addClass('rowNum');

	$profileLinksComboBox = new CComboBox('iconmap[mappings]['.$i.'][inventory_link]', $mapping['inventory_link']);
	$profileLinksComboBox->addItems($this->data['inventoryList']);

	$expressionTextBox = new CTextBox('iconmap[mappings]['.$i.'][expression]', $mapping['expression']);
	$expressionTextBox->setAttribute('maxlength', 64);
	$expressionTextBox = array($expressionTextBox);
	if (isset($mapping['iconmappingid'])) {
		$expressionTextBox[] = new CVar('iconmap[mappings]['.$i.'][iconmappingid]', $mapping['iconmappingid']);
	}

	$iconsComboBox = new CComboBox('iconmap[mappings]['.$i.'][iconid]', $mapping['iconid']);
	$iconsComboBox->addClass('mappingIcon');
	$iconsComboBox->addItems($this->data['iconList']);

	$iconPreviewImage = new CImg('imgstore.php?iconid='.$mapping['iconid'].'&width='.ZBX_ICON_PREVIEW_WIDTH.
		'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null, 'pointer preview');
	$iconPreviewImage->setAttribute('data-image-full', 'imgstore.php?iconid='.$mapping['iconid']);

	$row = new CRow(array(
		new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
		$numSpan,
		$profileLinksComboBox,
		$expressionTextBox,
		$iconsComboBox,
		$iconPreviewImage,
		new CButton('remove', _('Remove'), '', 'link_menu removeMapping'),
	), 'sortable');
	$row->setAttribute('id', 'iconmapidRow_'.$i);
	$iconMapTable->addRow($row);

	$i++;
}

// hidden row for js
reset($this->data['iconList']);
$firstIconId = key($this->data['iconList']);
$numSpan = new CSpan('0:');
$numSpan->addClass('rowNum');

$profileLinksComboBox = new CComboBox('iconmap[mappings][#{iconmappingid}][inventory_link]');
$profileLinksComboBox->addItems($this->data['inventoryList']);
$profileLinksComboBox->setAttribute('disabled', 'disabled');

$expressionTextBox = new CTextBox('iconmap[mappings][#{iconmappingid}][expression]');
$expressionTextBox->setAttribute('maxlength', 64);
$expressionTextBox->setAttribute('disabled', 'disabled');

$iconsComboBox = new CComboBox('iconmap[mappings][#{iconmappingid}][iconid]', $firstIconId);
$iconsComboBox->addClass('mappingIcon');
$iconsComboBox->addItems($this->data['iconList']);
$iconsComboBox->setAttribute('disabled', 'disabled');

$iconPreviewImage = new CImg('imgstore.php?iconid='.$firstIconId.'&width='.ZBX_ICON_PREVIEW_WIDTH.
	'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null, 'pointer preview');
$iconPreviewImage->setAttribute('data-image-full', 'imgstore.php?iconid='.$firstIconId);

// row template
$hiddenRowTemplate = new CRow(array(
	new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
	$numSpan,
	$profileLinksComboBox,
	$expressionTextBox,
	$iconsComboBox,
	$iconPreviewImage,
	new CButton('remove', _('Remove'), '', 'link_menu removeMapping'),
), 'hidden');
$hiddenRowTemplate->setAttribute('id', 'rowTpl');
$iconMapTable->addRow($hiddenRowTemplate);

// add row button
$iconMapTable->addRow(new CCol(new CButton('addMapping', _('Add'), '', 'link_menu'), null, 7));

// <default icon row>
$numSpan = new CSpan($i++.':');
$numSpan->addClass('rowNum');

$iconsComboBox = new CComboBox('iconmap[default_iconid]', $this->data['iconmap']['default_iconid']);
$iconsComboBox->addClass('mappingIcon');
$iconsComboBox->addItems($this->data['iconList']);

$iconPreviewImage = new CImg('imgstore.php?iconid='.$this->data['iconmap']['default_iconid'].
	'&width='.ZBX_ICON_PREVIEW_WIDTH.'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null, 'pointer preview');
$iconPreviewImage->setAttribute('data-image-full', 'imgstore.php?iconid='.$this->data['iconmap']['default_iconid']);

$iconMapTable->addRow(array(new CCol(_('Default'), null, 4), $iconsComboBox, $iconPreviewImage));
// </default icon row>

$iconMapTab->addRow(_('Mappings'), new CDiv($iconMapTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
$iconMapView = new CTabView();
$iconMapView->addTab('iconmap', _('Icon map'), $iconMapTab);
$iconMapForm->addItem($iconMapView);

// footer
$secondaryActions = array(new CButtonCancel());
if (isset($this->data['iconmapid'])) {
	array_unshift($secondaryActions,
		new CSubmit('clone', _('Clone')),
		new CButtonDelete(_('Delete icon map?'), url_param('form').url_param('iconmapid'))
	);
}
$iconMapForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $secondaryActions));

return $iconMapForm;
