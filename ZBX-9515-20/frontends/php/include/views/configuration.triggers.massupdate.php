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


require_once dirname(__FILE__).'/js/configuration.triggers.edit.js.php';

$triggersWidget = new CWidget();

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	if (!empty($this->data['parent_discoveryid'])) {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));
	}
	else {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid']));
	}
}

if (!empty($this->data['parent_discoveryid'])) {
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGER PROTOTYPES'));
}
else {
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGERS'));
}

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('massupdate', $this->data['massupdate']);
$triggersForm->addVar('hostid', $this->data['hostid']);
$triggersForm->addVar('go', $this->data['go']);
if ($this->data['parent_discoveryid']) {
	$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
foreach ($this->data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

// create form list
$triggersFormList = new CFormList('triggersFormList');

// append severity to form list
$severityDiv = getSeverityControl();
$severityDiv->setAttribute('id', 'priority_div');

$triggersFormList->addRow(
	array(
		_('Severity'),
		SPACE,
		new CVisibilityBox('visible[priority]', !empty($this->data['visible']['priority']) ? 'yes' : 'no', 'priority_div', _('Original')),
	),
	$severityDiv
);

// append dependencies to form list
if (empty($this->data['parent_discoveryid'])) {
	$dependenciesTable = new CTable(_('No dependencies defined.'), 'formElementTable');
	$dependenciesTable->setAttribute('style', 'min-width: 500px;');
	$dependenciesTable->setAttribute('id', 'dependenciesTable');
	$dependenciesTable->setHeader(array(
		_('Name'),
		_('Action')
	));

	foreach ($this->data['dependencies'] as $dependency) {
		$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

		$row = new CRow(array(
			$dependency['host'].': '.$dependency['description'],
			new CButton('remove', _('Remove'), 'javascript: removeDependency(\''.$dependency['triggerid'].'\');', 'link_menu')
		));
		$row->setAttribute('id', 'dependency_'.$dependency['triggerid']);
		$dependenciesTable->addRow($row);
	}

	$dependenciesDiv = new CDiv(
		array(
			$dependenciesTable,
			new CButton('btn1', _('Add'),
				'return PopUp(\'popup.php?dstfrm=massupdate&dstact=add_dependency&reference=deptrigger'.
				'&dstfld1=new_dependency[]&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1'.
				'\', 1000, 700);',
				'link_menu'
			)
		),
		'objectgroup inlineblock border_dotted ui-corner-all'
	);
	$dependenciesDiv->setAttribute('id', 'dependencies_div');

	$triggersFormList->addRow(
		array(
			_('Replace depenencies'),
			SPACE,
			new CVisibilityBox('visible[dependencies]', !empty($this->data['visible']['dependencies']) ? 'yes' : 'no', 'dependencies_div', _('Original'))
		),
		$dependenciesDiv
	);
}

// append tabs to form
$triggersTab = new CTabView();
$triggersTab->addTab('triggersTab', _('Triggers massupdate'), $triggersFormList);
$triggersForm->addItem($triggersTab);

// append buttons to form
$triggersForm->addItem(makeFormFooter(
	array(new CSubmit('mass_save', _('Save'))),
	array(new CButtonCancel(url_param('groupid').url_param('parent_discoveryid')))
));

$triggersWidget->addItem($triggersForm);

return $triggersWidget;
