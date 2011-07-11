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

$iconMapTab = new CFormList('scriptsTab');

$TBname = new CTextBox('iconmap[name]', $this->data['iconmap']['name']);
$TBname->setAttribute('maxlength', 64);
$iconMapTab->addRow(_('Name'), $TBname);

$TABLEiconMap = new CTable();
$TABLEiconMap->setAttribute('id', 'iconMapTable');

$iconMapForm = new CForm();
$iconMapForm->addVar('form', 1);
$iconMapForm->addVar('config', 14);
if(isset($this->data['iconmap']['iconmapid'])){
	$iconMapForm->addVar('iconmap[iconmapid]', $this->data['iconmap']['iconmapid']);
}


// header
$TABLEiconMap->addRow(array(SPACE, _('Inventory field'), _('Expression'), _('Icon'), SPACE, SPACE));

foreach($this->data['iconmap']['mappings'] as $iconmappingid => $mapping){
	$CCprofileLinks = new CComboBox('iconmap[mappings][' . $iconmappingid . '][inventory_link]', $mapping['inventory_link']);
	$CCprofileLinks->addItems($this->data['inventoryList']);

	$TBexpression = new CTextBox('iconmap[mappings][' . $iconmappingid . '][expression]', $mapping['expression']);
	$TBexpression->setAttribute('maxlength', 64);

	$CBicons = new CComboBox('iconmap[mappings][' . $iconmappingid . '][iconid]', $mapping['iconid']);
	$CBicons->addClass('mappingIcon');
	$CBicons->addItems($this->data['iconList']);

	$DIViconPreview = new CDiv(SPACE, 'sysmap_iconid_' . $mapping['iconid'], 'divPreview_' . $iconmappingid);
	$DIViconPreview->addStyle('margin: 0 auto;');

	$row = new CRow(array(
		new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
		$CCprofileLinks,
		$TBexpression,
		$CBicons,
		$DIViconPreview,
		new CSpan(_('Remove'), 'link_menu removeMapping'),
	), 'sortable');
	$row->setAttribute('id', 'iconmapidRow_' . $iconmappingid);
	$TABLEiconMap->addRow($row);
}

// hidden row for js
reset($this->data['iconList']);
$firstIconId = key($this->data['iconList']);

$CCprofileLinks = new CComboBox('iconmap[mappings][#{iconmappingid}][inventory_link]');
$CCprofileLinks->addItems($this->data['inventoryList']);
$CCprofileLinks->setAttribute('disabled', 'disabled');

$TBexpression = new CTextBox('iconmap[mappings][#{iconmappingid}][expression]');
$TBexpression->setAttribute('maxlength', 64);
$TBexpression->setAttribute('disabled', 'disabled');

$CBicons = new CComboBox('iconmap[mappings][#{iconmappingid}][iconid]', $firstIconId);
$CBicons->addClass('mappingIcon');
$CBicons->addItems($this->data['iconList']);
$CBicons->setAttribute('disabled', 'disabled');

$DIViconPreview = new CDiv(SPACE, 'sysmap_iconid_' . $firstIconId, 'divPreview_#{iconmappingid}');
$DIViconPreview->addStyle('margin: 0 auto;');

$hiddenRow = new CRow(array(
	new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
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


$iconMapTab->addRow(_('Mappings'), new CDiv($TABLEiconMap, 'objectgroup inlineblock border_dotted ui-corner-all'));
$iconMapView = new CTabView();
$iconMapView->addTab('iconmap', _('Icon map'), $iconMapTab);
$iconMapForm->addItem($iconMapView);


// Footer
$footer = makeFormFooter(
	array(new CSubmit('save', _('Save'))),
	array(
		new CSubmit('clone', _('Clone')),
		new CSubmit('delete', _('Delete')),
		new CButtonCancel(url_param('config')),
	)
);
$iconMapForm->addItem($footer);


return $iconMapForm;
?>
