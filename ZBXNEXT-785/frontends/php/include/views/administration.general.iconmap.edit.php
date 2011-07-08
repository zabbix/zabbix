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
include('include/views/js/administration.general.iconmap.js.php');

$TABLEiconMap = new CTable();
$TABLEiconMap->addStyle('margin-left: 20%');
$TABLEiconMap->setAttribute('id', 'iconMapTable');

$iconMapForm = new CForm();
$iconMapForm->addVar('form', 1);
$iconMapForm->addVar('config', 14);


// header
$TABLEiconMap->addRow(array(SPACE, _('Inventory field'), _('Expression'), _('Icon'), _('Icon preview'), SPACE));

foreach($this->data['iconmap']['mappings'] as $mapping){
	$CCprofileLinks = new CComboBox('iconmap[mappings][][profile_link]', $mapping['profile_link']);
	$CCprofileLinks->addItems($this->data['inventoryList']);

	$CBicons = new CComboBox('mapping[1][iconid]', $mapping['iconid']);
	$CBicons->addClass('mappingIcon');
	$CBicons->addItems($this->data['iconList']);

	$DIViconPreview = new CDiv(SPACE, 'sysmap_iconid_' . $mapping['iconid'], 'divPreview_' . $mapping['iconmappingid']);
	$DIViconPreview->addStyle('margin: 0 auto;');

	$TBexpression = new CTextBox('mapping[1][expresion]', $mapping['expression']);

	$TABLEiconMap->addRow(
		array(
			new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s'),
			$CCprofileLinks,
			$TBexpression,
			$CBicons,
			$DIViconPreview,
			new CSpan(_('Remove'), 'link_menu removeMapping'),
		), 'sortable'
	);
}

// hidden row for js
reset($this->data['iconList']);
$firstIconId = key($this->data['iconList']);
$CCprofileLinks = new CComboBox('iconmap[mappings][#{iconmappingid}][profile_link]');
$CCprofileLinks->addItems($this->data['inventoryList']);

$CBicons = new CComboBox('mapping[#{iconmappingid}][iconid]', $firstIconId);
$CBicons->addClass('mappingIcon');
$CBicons->addItems($this->data['iconList']);

$DIViconPreview = new CDiv(SPACE, 'sysmap_iconid_' . $firstIconId, 'divPreview_#{iconmappingid}');
$DIViconPreview->addStyle('margin: 0 auto;');

$TBexpression = new CTextBox('mapping[#{iconmappingid}][expresion]');

$hiddenRow = new CRow(array(
	new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s'),
	$CCprofileLinks,
	$TBexpression,
	$CBicons,
	$DIViconPreview,
	new CSpan(_('Remove'), 'link_menu removeMapping'),
), 'hidden');
$hiddenRow->setAttribute('id', 'rowTpl');
$TABLEiconMap->addRow($hiddenRow);


$SPANadd = new CSpan(_('Add'), 'link_menu');
$SPANadd->setAttribute('id', 'addMapping');
$COLadd = new CCol($SPANadd);
$COLadd->setColSpan(5);
$TABLEiconMap->addRow(array(SPACE, $COLadd));


$iconMapView = new CTabView();
$iconMapView->addTab('iconmap', _('Icon map'), $TABLEiconMap);
$iconMapForm->addItem($iconMapView);


// Footer
$footer = makeFormFooter(
	array(new CSubmit('save', _('Save'))),
	array(
		new CSubmit('clone', _('Clone')),
		new CSubmit('delete', _('Delete')),
		new CSubmit('cancel', _('Cance')),
	)
);
$iconMapForm->addItem($footer);


return $iconMapForm;
?>
