<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$widget = (new CWidget())
	->setTitle(_('Value mapping'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.valuemapping.php')))
	);

$valueMappingForm = (new CForm())
	->setName('valueMappingForm')
	->addVar('form', $this->data['form']);

if ($this->data['valuemapid'] != 0) {
	$valueMappingForm->addVar('valuemapid', $this->data['valuemapid']);
}

$valueMappingFormList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('mapname', $this->data['mapname'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// mappings
$mappingsTable = (new CTable())
	->setNoDataMessage(null)
	->setId('mappingsTable')
	->addRow([_('Value'), SPACE, _('Mapped to'), SPACE])
	->addRow((new CCol(
		(new CButton('addMapping', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
	))->setColSpan(4));
$valueMappingFormList->addRow(_('Mappings'), (new CDiv($mappingsTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

// add mappings to form by js
if ($this->data['mappings']) {
	zbx_add_post_js('mappingsManager.addExisting('.zbx_jsvalue($this->data['mappings']).');');
}
else {
	zbx_add_post_js('mappingsManager.addNew();');
}

// append tab
$valueMappingTab = new CTabView();
$valueMappingTab->addTab('valuemapping', _('Value mapping'), $valueMappingFormList);

// append buttons
if ($this->data['valuemapid'] != 0) {
	$valueMappingTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete($this->data['confirmMessage'], url_param('valuemapid')),
			new CButtonCancel()
		]
	));
}
else {
	$valueMappingTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$valueMappingForm->addItem($valueMappingTab);

$widget->addItem($valueMappingForm);

return $widget;
