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
$triggersWidget->addItem(get_header_host_table('triggers', $data['hostid'], $data['parent_discoveryid']));

$triggersWidget->setTitle(_('Trigger prototypes'));

$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('action', $data['action']);
$triggersForm->addVar('parent_discoveryid', $data['parent_discoveryid']);

foreach ($data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

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

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$description = new CLink($depTriggerDescription,
			'trigger_prototypes.php?form=update'.url_param('parent_discoveryid').'&triggerid='.$dependency['triggerid']
		);
		$description->setAttribute('target', '_blank');
	}
	elseif ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = new CLink($depTriggerDescription,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		);
		$description->setAttribute('target', '_blank');
	}

	$row = new CRow([$description, new CButton('remove', _('Remove'),
		'javascript: removeDependency(\''.$dependency['triggerid'].'\');',
		'link_menu'
	)]);

	$row->setAttribute('id', 'dependency_'.$dependency['triggerid']);
	$dependenciesTable->addRow($row);
}

$addButton = new CButton('add_dep_trigger', _('Add'), 'return PopUp("popup.php?dstfrm=massupdate&dstact=add_dependency'.
		'&reference=deptrigger&dstfld1=new_dependency&srctbl=triggers&objname=triggers&srcfld1=triggerid'.
		'&multiselect=1&with_triggers=1&normal_only=1&noempty=1");',
	'link_menu'
);
$addPrototypeButton = new CButton('add_dep_trigger_prototype', _('Add prototype'), 'return PopUp("popup.php?'.
		'dstfrm=massupdate&dstact=add_dependency&reference=deptrigger&dstfld1=new_dependency&srctbl=trigger_prototypes'.
		'&objname=triggers&srcfld1=triggerid'.url_param('parent_discoveryid').'&multiselect=1");',
	'link_menu'
);

$dependenciesDiv = new CDiv(
	[$dependenciesTable, $addButton, SPACE, SPACE, SPACE, $addPrototypeButton],
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
	[new CButtonCancel(url_param('parent_discoveryid'))]
));

// append tabs to form
$triggersForm->addItem($triggersTab);
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
