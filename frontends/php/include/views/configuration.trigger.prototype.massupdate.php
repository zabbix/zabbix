<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$triggersWidget = (new CWidget())->setTitle(_('Trigger prototypes'));

// append host summary to widget header
$triggersWidget->addItem(get_header_host_table('trigger_prototypes', $data['hostid'], $data['parent_discoveryid']));

$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('action', $data['action'])
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

foreach ($data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

$triggersFormList = (new CFormList('triggersFormList'))
	->addRow(
		[_('Severity'), SPACE,
			(new CVisibilityBox('visible[priority]', 'priority_div', _('Original')))
				->setChecked(isset($data['visible']['priority']))
		],
		(new CDiv(
			new CSeverity([
				'name' => 'priority',
				'value' => $data['priority']
			])
		))->setId('priority_div')
	);

// append dependencies to form list
$dependenciesTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('Action')]);

foreach ($data['dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$depTriggerDescription = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$description = (new CLink($depTriggerDescription,
			'trigger_prototypes.php?form=update'.url_param('parent_discoveryid').'&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}
	elseif ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = (new CLink($depTriggerDescription,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}

	$row = new CRow([$description,
		(new CCol(
			(new CButton('remove', _('Remove')))
				->onClick('javascript: removeDependency(\''.$dependency['triggerid'].'\');')
				->addClass(ZBX_STYLE_BTN_LINK)
		))->addClass(ZBX_STYLE_NOWRAP)
	]);

	$row->setId('dependency_'.$dependency['triggerid']);
	$dependenciesTable->addRow($row);
}

$dependenciesDiv = (new CDiv([
	$dependenciesTable,
	new CHorList([
		(new CButton('add_dep_trigger', _('Add')))
			->onClick('return PopUp("popup.php?dstfrm=massupdate&dstact=add_dependency&reference=deptrigger'.
				'&dstfld1=new_dependency&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1'.
				'&with_triggers=1&normal_only=1&noempty=1");')
			->addClass(ZBX_STYLE_BTN_LINK),
		(new CButton('add_dep_trigger_prototype', _('Add prototype')))
			->onClick('return PopUp("popup.php?dstfrm=massupdate&dstact=add_dependency&reference=deptrigger'.
				'&dstfld1=new_dependency&srctbl=trigger_prototypes&objname=triggers&srcfld1=triggerid'.
				url_param('parent_discoveryid').'&multiselect=1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	])
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setId('dependencies_div');

$triggersFormList->addRow(
	[_('Replace dependencies'), SPACE,
		(new CVisibilityBox('visible[dependencies]', 'dependencies_div', _('Original')))
			->setChecked(isset($data['visible']['dependencies']))
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
