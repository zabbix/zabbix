<?php

//declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

//require_once dirname(__FILE__).'/js/configuration.action.edit.js.php';

$form = (new CForm())
//	->setName('action_form')
//	->addItem(getMessages())
	->setId('action-form')
	->addStyle('display: none;')
	->addItem((new CInput('submit', null))->addStyle('display: none;'));
// check if I need the 'null'??

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('conditionTable')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$condition_table->setFooter(
	(new CSimpleButton(_('Add')))
		->setAttribute('data-eventsource', $data['eventsource'])
		->onClick('
			PopUp("popup.condition.actions", {
				type: '.ZBX_POPUP_CONDITION_TYPE_ACTION.',
				source: this.dataset.eventsource,
			}, {dialogue_class: "modal-popup-medium"});
		')
		->addClass(ZBX_STYLE_BTN_LINK)
);

// action tab
$action_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['action']['name']?:null))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	])
	->addItem([
		new CLabel(_('Conditions')),
		(new CFormField($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);

$action_tab
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField((new CCheckBox('status', ACTION_STATUS_ENABLED))->setChecked($data['action']['status'] == ACTION_STATUS_ENABLED))
	])
	->addItem(
		new CFormField((new CLabel(_('At least one operation must exist.')))->setAsteriskMark())
	);

// Operations tab.
$operations_tab = (new CFormGrid());

// operations table
$operations_table = (new CTable())
	->setId('op-table')
	->setAttribute('style', 'width: 100%;');

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_table->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Action')]);
	//$delays = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);
}
else {
	$operations_table->setHeader([_('Details'), _('Action')]);
}

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_tab->addItem([
		(new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		//(new CTextBox('esc_period', $data['action']['esc_period']))
		(new CTextBox('esc_period', '1h'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	]);
}

$operations_table->setFooter(
	(new CSimpleButton(_('Add')))
		->setAttribute('data-actionid', 0)
		->setAttribute('data-eventsource', $data['eventsource'])
		->onClick('
			operation_details.open(this, this.dataset.actionid, this.dataset.eventsource, '.ACTION_OPERATION.');
		')
		->addClass(ZBX_STYLE_BTN_LINK)
);

$operations_tab->addItem([
	new CLabel(_('Operations')),
	(new CFormField($operations_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);

// Recovery operations table.
if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	// Create operation table.
	$operations_table = (new CTable())
		->setId('rec-table')
		->setAttribute('style', 'width: 100%;');
		$operations_table->setHeader([_('Details'), _('Action')]);

	$operations_table->setFooter(
		(new CSimpleButton(_('Add')))
			//	->setAttribute('data-actionid', $data['actionid'])
			->setAttribute('data-eventsource', $data['eventsource'])
			->onClick('
				operation_details.open(this, this.dataset.actionid, this.dataset.eventsource,
					'.ACTION_RECOVERY_OPERATION.'
				);
			')
			->addClass(ZBX_STYLE_BTN_LINK)
	);

	$operations_tab->addItem([
		new CLabel(_('Recovery operations')),
		(new CFormField($operations_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);
}

// Update operations.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_SERVICE) {
	//$action_formname = $actionForm->getName();

	$operations_table = (new CTable())
		->setId('upd-table')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Details'), _('Action')]);

	$operations_table->setFooter(
			(new CSimpleButton(_('Add')))
			->setAttribute('data-actionid', $data['actionid'])
			->setAttribute('data-eventsource', $data['eventsource'])
			->onClick('
				operation_details.open(this, this.dataset.actionid, this.dataset.eventsource,
					'.ACTION_UPDATE_OPERATION.'
				);
			')
			->addClass(ZBX_STYLE_BTN_LINK)
	);

	$operations_tab->addItem([
		new CLabel(_('Update operations')),
		(new CFormField($operations_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);
}

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$operations_tab
		->addItem([
			new CLabel(_('Pause operations for suppressed problems'), 'pause_suppressed'),
			new CFormField(
				(new CCheckBox('pause_suppressed', ACTION_PAUSE_SUPPRESSED_TRUE))
				//->setChecked($data['action']['pause_suppressed'] == ACTION_PAUSE_SUPPRESSED_TRUE)
				->setChecked(true)
			)
		])
		->addItem([
			new CLabel(_('Notify about canceled escalations'), 'notify_if_canceled'),
			new CFormField(
				(new CCheckBox('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_TRUE))
					//->setChecked($data['action']['notify_if_canceled'] == ACTION_NOTIFY_IF_CANCELED_TRUE)
					->setChecked(true)
			)
		]);
}

$operations_tab->addItem(
	new CFormField((new CLabel(_('At least one operation must exist.')))->setAsteriskMark())
);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('action-tab', _('Action'), $action_tab)
	->addTab('action-operations-tab', _('Operations'), $operations_tab, TAB_INDICATOR_OPERATIONS);

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('action_edit_popup.init();'))->setOnDocumentReady()
	);

$title = _('Action');
$buttons = [
	[
		'title' => _('Add'),
		'class' => 'js-add',
		'keepOpen' => true,
		'isSubmit' => true,
		// add function submit()
		'action' => 'action_edit_popup.submit();'
	]
];

$output = [
	'header' => $title,
	// 'doc_url' => CDocHelper::getUrl(CDocHelper::CONFIGURATION_ACTION_EDIT), do I need this?
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.action.edit.js.php')
];

echo json_encode($output);
