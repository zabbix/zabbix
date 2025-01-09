<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

// Create form.
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('trigger')))->removeId())
	->setId('massupdate-form')
	->addVar('action', $data['prototype'] ? 'trigger.prototype.massupdate' : 'trigger.massupdate')
	->addVar('ids', $data['ids'])
	->addVar('update', '1')
	->addVar('location_url', $data['location_url'])
	->addVar('context', $data['context'])
	->disablePasswordAutofill();

	if ($data['prototype']) {
		$form
			->addVar('parent_discoveryid', $data['parent_discoveryid'])
			->addVar('prototype', $data['prototype']);
	}

/*
 * Trigger tab
 */
$trigger_form_list = (new CFormList('trigger-form-list'))
	->addRow(
		(new CVisibilityBox('visible[priority]', 'priority', _('Original')))
			->setLabel(_('Severity'))
			->setAttribute('autofocus', 'autofocus'),
		(new CSeverity('priority'))->setId('priority')
	)
	->addRow(
		(new CVisibilityBox('visible[manual_close]', 'manual-close', _('Original')))
			->setLabel(_('Allow manual close')),
		(new CRadioButtonList('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED))
			->setId('manual-close')
			->addValue(_('No'), ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED)
			->addValue(_('Yes'), ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
			->setModern(true)
	);

/*
 * Tags tab
 */
$tags_form_list = (new CFormList('tags-form-list'))
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags-field', _('Original')))->setLabel(_('Tags')),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable([['tag' => '', 'value' => '']])
				->setHeader([_('Name'), _('Value'), ''])
				->addClass('tags-table')
		]))->setId('tags-field')
	);

/*
 * Dependencies tab
 */
$dependencies_form_list = new CFormList('dependencies-form-list');

$dependencies_table = (new CTable())
	->setId('dependency-table')
	->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
	->addStyle('width: 100%;')
	->setHeader([_('Name'), (new CColHeader(_('Action')))->setWidth(50)]);

$normal_only = $data['prototype'] ? ['normal_only' => 1] : [];

if ($data['context'] === 'host') {
	$buttons[] = (new CButton('add_dep_trigger', _('Add')))
		->onClick('
			PopUp("popup.generic", '.json_encode([
				'srctbl' => 'triggers',
				'srcfld1' => 'triggerid',
				'dstfrm' => 'massupdate',
				'dstfld1' => 'new_dependency',
				'dstact' => 'add_dependency',
				'reference' => 'deptrigger',
				'objname' => 'triggers',
				'multiselect' => 1,
				'with_triggers' => 1,
				'real_hosts' => 1
			] + $normal_only).', {dialogue_class: "modal-popup-generic"});
		')
		->addClass(ZBX_STYLE_BTN_LINK);
}
else {
	$buttons[] = (new CButton('add_dep_trigger', _('Add')))
		->onClick('
			PopUp("popup.generic", '.json_encode([
				'srctbl' => 'template_triggers',
				'srcfld1' => 'triggerid',
				'dstfrm' => 'massupdate',
				'dstfld1' => 'new_dependency',
				'dstact' => 'add_dependency',
				'reference' => 'deptrigger',
				'objname' => 'triggers',
				'multiselect' => 1,
				'with_triggers' => 1
			]).', {dialogue_class: "modal-popup-generic"});
		')
		->addClass(ZBX_STYLE_BTN_LINK);
}

if ($data['prototype']) {
	$buttons[] = (new CButton('add_dep_trigger_prototype', _('Add prototype')))
		->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
		->onClick('
			PopUp("popup.generic", {
				srctbl: "trigger_prototypes",
				srcfld1: "triggerid",
				dstfrm: "massupdate",
				dstfld1: "new_dependency",
				dstact: "add_dependency",
				reference: "deptrigger_prototype",
				objname: "triggers",
				parent_discoveryid: this.dataset.parent_discoveryid,
				multiselect: 1
			}, {dialogue_class: "modal-popup-generic"});
		')
		->addClass(ZBX_STYLE_BTN_LINK);
}

if ($data['context'] === 'template') {
	$buttons[] = (new CButton('add_dep_host_trigger', _('Add host trigger')))
		->onClick('
			PopUp("popup.generic", '.json_encode([
				'srctbl' => 'triggers',
				'srcfld1' => 'triggerid',
				'dstfrm' => 'massupdate',
				'dstfld1' => 'new_dependency',
				'dstact' => 'add_dependency',
				'reference' => 'deptrigger',
				'objname' => 'triggers',
				'multiselect' => 1,
				'with_triggers' => 1,
				'real_hosts' => 1
			] + $normal_only).', {dialogue_class: "modal-popup-generic"});
		')
		->addClass(ZBX_STYLE_BTN_LINK);
}

$dependencies_form_list->addRow(
	(new CVisibilityBox('visible[dependencies]', 'dependencies-field', _('Original')))
		->setLabel(_('Replace dependencies')),
	(new CDiv([$dependencies_table, new CHorList($buttons)]))->setId('dependencies-field')
);

// Append tabs to the form.
$tabs = (new CTabView())
	->addTab('trigger_tab', $data['prototype'] ? _('Trigger prototype') :_('Trigger'), $trigger_form_list)
	->addTab('tags_tab', _('Tags'), $tags_form_list)
	->addTab('dependencies_tab', _('Dependencies'), $dependencies_form_list)
	->setSelected(0);

// Append tabs to form.
$form->addItem($tabs);

$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.tmpl.js.php')));
$form->addItem(new CJsScript($this->readJsFile('trigger.massupdate.js.php')));

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_MASSUPDATE_TRIGGER),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitPopup(overlay);'
		]
	]
];

$output['script_inline'] = $this->readJsFile('popup.massupdate.js.php');
$output['script_inline'] .= getPagePostJs();

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
