<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$triggersWidget = (new CWidget())->setTitle(_('Triggers'));

// append host summary to widget header
if (!empty($data['hostid'])) {
	$triggersWidget->addItem(get_header_host_table('triggers', $data['hostid']));
}

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('hostid', $data['hostid'])
	->addVar('action', $data['action']);

foreach ($data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

$triggersFormList = (new CFormList('triggersFormList'))
	->addRow(
		(new CVisibilityBox('visible[priority]', 'priority_div', _('Original')))
			->setLabel(_('Severity'))
			->setChecked(isset($data['visible']['priority']))
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv(
			new CSeverity([
				'name' => 'priority',
				'value' => (int) $data['priority']
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

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = (new CLink($depTriggerDescription,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}
	else {
		$description = $depTriggerDescription;
	}

	$dependenciesTable->addRow(
		(new CRow([
			$description,
			(new CCol(
				(new CButton('remove', _('Remove')))
					->onClick('javascript: removeDependency(\''.$dependency['triggerid'].'\');')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('dependency_'.$dependency['triggerid'])
	);
}

$dependenciesDiv = (new CDiv([
	$dependenciesTable,
	(new CButton('btn1', _('Add')))
		->onClick('return PopUp("popup.generic",'.
			CJs::encodeJson([
				'srctbl' => 'triggers',
				'srcfld1' => 'triggerid',
				'dstfrm' => 'massupdate',
				'dstfld1' => 'new_dependency',
				'dstact' => 'add_dependency',
				'reference' => 'deptrigger',
				'objname' => 'triggers',
				'multiselect' => '1',
				'with_triggers' => '1',
				'noempty' => '1'
			]).', null, this);'
		)
		->addClass(ZBX_STYLE_BTN_LINK)
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setId('dependencies_div');

$triggersFormList->addRow(
	(new CVisibilityBox('visible[dependencies]', 'dependencies_div', _('Original')))
		->setLabel(_('Replace dependencies'))
		->setChecked(isset($data['visible']['dependencies'])),
	$dependenciesDiv
);

$tags_table = (new CTable())->setId('tbl_tags');

foreach ($data['tags'] as $tag_key => $tag) {
	$tags_table->addRow([
		(new CTextBox('tags['.$tag_key.'][tag]', $tag['tag']))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('tags['.$tag_key.'][value]', $tag['value']))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value')),
		(new CCol(
			(new CButton('tags['.$tag_key.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}

$tags_table->setFooter(new CCol(
	(new CButton('tag_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$triggersFormList
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags_div', _('Original')))
			->setLabel(_('Replace tags'))
			->setChecked(isset($data['visible']['tags'])),
		(new CDiv([$tags_table]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			->setId('tags_div')
	)
	->addRow(
		(new CVisibilityBox('visible[manual_close]', 'manual_close_div', _('Original')))
			->setLabel(_('Allow manual close'))
			->setChecked(isset($data['visible']['manual_close'])),
		(new CDiv(
			(new CRadioButtonList('manual_close', (int) $data['manual_close']))
				->addValue(_('No'), ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED)
				->addValue(_('Yes'), ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
				->setModern(true)
		))->setId('manual_close_div')
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
