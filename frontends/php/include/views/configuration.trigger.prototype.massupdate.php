<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.trigger.massupdate.js.php';

$widget = (new CWidget())->setTitle(_('Trigger prototypes'));

// Append host summary to widget header.
$widget->addItem(get_header_host_table('trigger_prototypes', $data['hostid'], $data['parent_discoveryid']));

// Create form.
$form = (new CForm())
	->setName('triggersForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', $data['action'])
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

foreach ($data['g_triggerid'] as $triggerid) {
	$form->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

/*
 * Trigger prototype tab
 */
$trigger_form_list = (new CFormList('trigger-form-list'))
	->addRow(
		(new CVisibilityBox('visible[priority]', 'priority-div', _('Original')))
			->setLabel(_('Severity'))
			->setChecked(array_key_exists('priority', $data['visible']))
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv(
			new CSeverity([
				'name' => 'priority',
				'value' => (int) $data['priority']
			])
		))->setId('priority-div')
	)
	->addRow(
		(new CVisibilityBox('visible[manual_close]', 'manual-close-div', _('Original')))
			->setLabel(_('Allow manual close'))
			->setChecked(array_key_exists('manual_close', $data['visible'])),
		(new CDiv(
			(new CRadioButtonList('manual_close', (int) $data['manual_close']))
				->addValue(_('No'), ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED)
				->addValue(_('Yes'), ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
				->setModern(true)
		))->setId('manual-close-div')
	);

/*
 * Tags tab
 */
$tags_form_list = (new CFormList('tags-form-list'))
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))
			->setLabel(_('Tags'))
			->setChecked(array_key_exists('tags', $data['visible'])),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable($data['tags'])
				->setHeader([_('Name'), _('Value'), _('Action')])
				->setId('tags-table')
		]))->setId('tags-div')
	);

/*
 * Dependencies tab
 */
$dependencies_form_list = new CFormList('dependencies-form-list');

$dependencies_table = (new CTable())
	->addStyle('width: 100%;')
	->setHeader([_('Name'), _('Action')]);

foreach ($data['dependencies'] as $dependency) {
	$dependencies_form_list->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$dependency_description = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$description = (new CLink($dependency_description,
			'trigger_prototypes.php?form=update'.url_param('parent_discoveryid').'&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}
	elseif ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = (new CLink($dependency_description,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}

	$dependencies_table->addRow(
		(new CRow([
			$description,
			(new CCol(
				(new CButton('remove', _('Remove')))
					->onClick('javascript: removeDependency(\''.$dependency['triggerid'].'\');')
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('dependency_'.$dependency['triggerid'])
	);
}

$dependencies_div = (new CDiv([
	$dependencies_table,
	new CHorList([
		(new CButton('add_dep_trigger', _('Add')))
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'massupdate',
					'dstfld1' => 'new_dependency',
					'dstact' => 'add_dependency',
					'reference' => 'deptrigger',
					'multiselect' => '1',
					'objname' => 'triggers',
					'with_triggers' => '1',
					'normal_only' => '1',
					'noempty' => '1'
				]).', null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK),
		(new CButton('add_dep_trigger_prototype', _('Add prototype')))
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'trigger_prototypes',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'massupdate',
					'dstfld1' => 'new_dependency',
					'dstact' => 'add_dependency',
					'reference' => 'deptrigger',
					'multiselect' => '1',
					'objname' => 'triggers',
					'parent_discoveryid' => $data['parent_discoveryid']
				]).', null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK)
	])
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setId('dependencies-div');

$dependencies_form_list->addRow(
	(new CVisibilityBox('visible[dependencies]', 'dependencies-div', _('Original')))
		->setLabel(_('Replace dependencies'))
		->setChecked(array_key_exists('dependencies', $data['visible'])),
	$dependencies_div
);

// Append tabs to the form.
$tabs = (new CTabView())
	->addTab('trigger_tab', _('Trigger prototype'), $trigger_form_list)
	->addTab('tags_tab', _('Tags'), $tags_form_list)
	->addTab('dependencies_tab', _('Dependencies'), $dependencies_form_list);

if (!hasRequest('massupdate') && !hasRequest('add_dependency')) {
	$tabs->setSelected(0);
}

// Append buttons to the form.
$tabs->setFooter(makeFormFooter(
	new CSubmit('massupdate', _('Update')),
	[new CButtonCancel(url_param('parent_discoveryid'))]
));

$form->addItem($tabs);

$widget->addItem($form);

return $widget;
