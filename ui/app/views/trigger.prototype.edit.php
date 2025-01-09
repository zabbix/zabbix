<?php
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

$trigger_form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('trigger')))->removeId())
	->setId('trigger-prototype-form')
	->setName('trigger_edit_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem((new CVar('parent_discoveryid', $data['parent_discoveryid']))->removeId())
	->addVar('hostid', $data['hostid'])
	->addVar('context', $data['context'])
	->addVar('expr_temp', $data['expr_temp'], 'expr_temp')
	->addVar('recovery_expr_temp', $data['recovery_expr_temp'], 'recovery_expr_temp')
	->addStyle('display: none;');

// Enable form submitting on Enter.
$trigger_form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['triggerid'] !== null) {
	$trigger_form->addVar('triggerid', $data['triggerid']);
}

if ($data['limited']) {
	$trigger_form
		->addItem((new CVar('opdata', $data['opdata']))->removeId())
		->addItem((new CVar('recovery_mode', $data['recovery_mode']))->removeId())
		->addItem((new CVar('type', $data['type']))->removeId())
		->addItem((new CVar('correlation_mode', $data['correlation_mode']))->removeId())
		->addItem((new CVar('manual_close', $data['manual_close']))->removeId());
}

// Append tabs to form.
$triggers_tab = (new CTabView())
	->addTab('triggersTab',_('Trigger prototype'),
		new CPartial('trigger.edit.trigger.tab', $data += [
			'readonly' => $data['limited'],
			'form_name' => $trigger_form->getName()
		])
	)
	->addTab('tags-tab', _('Tags'),
		new CPartial('configuration.tags.tab', [
			'source' => 'trigger_prototype',
			'tags' => $data['tags'],
			'show_inherited_tags' => $data['show_inherited_tags'],
			'tabs_id' => 'tabs',
			'tags_tab_id' => 'tags-tab'
		]), TAB_INDICATOR_TAGS
	)
	->addTab('dependenciesTab', _('Dependencies'), new CPartial('trigger.edit.dependencies.tab', $data),
		TAB_INDICATOR_DEPENDENCY
	);

if ($data['form_refresh'] == 0) {
	$triggers_tab->setSelected(0);
}

if (!$data['triggerid']) {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'trigger_edit_popup.submit();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'trigger_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT, 'js-clone',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'trigger_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete trigger prototype?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => !$data['limited'],
			'action' => 'trigger_edit_popup.delete();'
		]
	];
}

$popup_parameters = [
	'dstfrm' => $trigger_form->getName(),
	'context' => $data['context']
];

$backurl = (new CUrl('zabbix.php'))
	->setArgument('action', 'trigger.prototype.list')
	->setArgument('context', $data['context']);

if (array_key_exists('parent_discoveryid', $data)) {
	$popup_parameters['parent_discoveryid'] = $data['parent_discoveryid'];

	$backurl->setArgument('parent_discoveryid', $data['parent_discoveryid']);
}

if ($data['hostid']) {
	$popup_parameters['hostid'] = $data['hostid'];
}

$trigger_form
	->addItem($triggers_tab)
	->addItem((new CScriptTag('trigger_edit_popup.init('.json_encode([
			'triggerid' => $data['triggerid'],
			'expression_popup_parameters' => $popup_parameters,
			'readonly' => $data['limited'],
			'dependencies' => $data['db_dependencies'],
			'action' => 'trigger.prototype.edit',
			'context' => $data['context'],
			'db_trigger' => $data['db_trigger'],
			'backurl' => $backurl->getUrl(),
			'overlayid' => 'trigger.prototype.edit',
			'parent_discoveryid' => array_key_exists('parent_discoveryid', $data) ? $data['parent_discoveryid'] : null
		]).');'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['triggerid'] === null ? _('New trigger prototype') : _('Trigger prototype'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TRIGGER_PROTOTYPE_EDIT),
	'body' => $trigger_form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('trigger.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
