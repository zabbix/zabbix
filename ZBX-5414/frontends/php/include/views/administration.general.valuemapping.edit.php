<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


include('include/views/js/administration.general.valuemapping.edit.js.php');

$valueMappingForm = new CForm();
$valueMappingForm->setName('valueMappingForm');
$valueMappingForm->addVar('form', $this->data['form']);
$valueMappingForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$valueMappingForm->addVar('valuemapid', $this->data['valuemapid']);

// create form list
$valueMappingFormList = new CFormList('valueMappingFormList');

// name
$valueMappingFormList->addRow(_('Name'), new CTextBox('mapname', $this->data['mapname'], 40, null, 64));

// mappings
$mappingsTable = new CTable(SPACE, 'formElementTable');
$mappingsTable->setAttribute('id', 'mappingsTable');
$mappingsTable->addRow(array(_('Value'), SPACE, _('Mapped to'), SPACE));
$addCol = new CCol(new CButton('addMapping', _('Add'), '', 'link_menu'));
$addCol->setColSpan(4);
$mappingsTable->addRow($addCol);
$valueMappingFormList->addRow(_('Mappings'), new CDiv($mappingsTable, 'border_dotted inlineblock objectgroup'));

// add mappings to form by js
if (empty($this->data['mappings'])) {
	zbx_add_post_js('mappingsManager.addNew();');
}
else {
	$json = new CJSON();
	zbx_add_post_js('mappingsManager.addExisting('.$json->encode(array_values($this->data['mappings'])).');');
}

// append tab
$valueMappingTab = new CTabView();
$valueMappingTab->addTab('valuemapping', _('Value mapping'), $valueMappingFormList);
$valueMappingForm->addItem($valueMappingTab);

// append buttons
$saveButton = new CSubmit('save', _('Save'));
if (!empty($this->data['valuemapid'])) {
	$valueMappingForm->addItem(makeFormFooter(array($saveButton), array(
		new CButtonDelete($this->data['confirmMessage'], url_param('valuemapid')),
		new CButtonCancel())
	));
}
else {
	$valueMappingForm->addItem(makeFormFooter(array($saveButton), new CButtonCancel()));
}

return $valueMappingForm;
