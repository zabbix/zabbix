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
if (!empty($data['hostid'])) {
	$triggersWidget->addItem(get_header_host_table('triggers', $data['hostid']));
}

$triggersWidget->setTitle(_('Triggers'));

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('hostid', $data['hostid']);
$triggersForm->addVar('action', $data['action']);

foreach ($data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

// create form list
$triggersFormList = new CFormList('triggersFormList');

// append severity to form list
$severityDiv = new CSeverity([
	'id' => 'priority_div',
	'name' => 'priority',
	'value' => $data['priority']
]);

$triggersFormList->addRow(
	[_('Severity'), SPACE,
		new CVisibilityBox('visible[priority]', isset($data['visible']['priority']), 'priority_div', _('Original')),
	],
	$severityDiv
);

// append dependencies to form list
$dependenciesTable = (new CTable(_('No dependencies defined.')))->
	addClass('formElementTable')->
	setAttribute('style', 'min-width: 500px;')->
	setAttribute('id', 'dependenciesTable')->
	setHeader([_('Name'), _('Action')]);

foreach ($data['dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$depTriggerDescription = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = new CLink($depTriggerDescription,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		);
		$description->setAttribute('target', '_blank');
	}
	else {
		$description = $depTriggerDescription;
	}

	$row = new CRow([$description, new CButton('remove', _('Remove'),
		'javascript: removeDependency(\''.$dependency['triggerid'].'\');',
		'link_menu'
	)]);

	$row->setAttribute('id', 'dependency_'.$dependency['triggerid']);
	$dependenciesTable->addRow($row);
}

$dependenciesDiv = new CDiv(
	[
		$dependenciesTable,
		new CButton('btn1', _('Add'),
			'return PopUp("popup.php?dstfrm=massupdate&dstact=add_dependency&reference=deptrigger'.
				'&dstfld1=new_dependency&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1'.
				'&with_triggers=1&noempty=1");',
			'link_menu'
		)
	],
	'objectgroup inlineblock border_dotted ui-corner-all'
);
$dependenciesDiv->setAttribute('id', 'dependencies_div');

$triggersFormList->addRow(
	[_('Replace dependencies'), SPACE,
		new CVisibilityBox('visible[dependencies]', isset($data['visible']['dependencies']), 'dependencies_div',
			_('Original')
		)
	],
	$dependenciesDiv
);

$triggersTab = new CTabView();
$triggersTab->addTab('triggersTab', _('Mass update'), $triggersFormList);

// append buttons to form
$triggersTab->setFooter(makeFormFooter(
	new CSubmit('massupdate', _('Update')),
	[new CButtonCancel(url_param('hostid'))]
));

// append tabs to form
$triggersForm->addItem($triggersTab);
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
