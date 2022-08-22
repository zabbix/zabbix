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

$this->addJsFile('popup.operation.common.js');
$this->addJsFile('configuration.action.edit.js.php');

$form = (new CForm())
	->setName('action.edit')
	->setId('action-form')
	->addVar('actionid', $data['actionid']?:null)
	->addVar('eventsource', $data['eventsource'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

// Action tab.
$action_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['action']['name']?:''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	]);

$formula = (new CTextBox('formula', $data['action']['filter']['formula'],
	DB::getFieldLength('actions', 'formula')
))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) &hellip;');

$action_tab
	->addItem([
		(new CLabel(_('Type of calculation'), 'label-evaltype'))->setId('label-evaltype'),
		(new CFormField([
			(new CSelect('evaltype'))
				->setId('evaltype')
				->setFocusableElementId('label-evaltype')
				->setValue($data['action']['filter']['evaltype'])
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
					CONDITION_EVAL_TYPE_AND => _('And'),
					CONDITION_EVAL_TYPE_OR => _('Or'),
					CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
				])),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$formula
		]))->setId('evaltype-formfield')
	]);

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('conditionTable')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$condition_table->setFooter(
	(new CSimpleButton(_('Add')))
	->setAttribute('data-eventsource', $data['eventsource'])
		->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('js-condition-create')
);

// action tab
$action_tab
	->addItem([
		new CLabel(_('Conditions')),
		(new CFormField($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField((new CCheckBox('status', ACTION_STATUS_ENABLED))
			->setChecked($data['action']['status'] == ACTION_STATUS_ENABLED))
	])
	->addItem(
		new CFormField((new CLabel(_('At least one operation must exist.')))->setAsteriskMark())
	);

// Operations table.
$operations_table = (new CTable())
	->setId('op-table')
	->setAttribute('style', 'width: 100%;');

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_table->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Action')]);
	$delays = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);
}
else {
	$operations_table->setHeader([_('Details'), _('Action')]);
}

$operations_table->setFooter(
	(new CSimpleButton(_('Add')))
		->setAttribute('data-actionid', 0)
		->setAttribute('data-eventsource', $data['eventsource'])
		->addClass('js-operation-details')
		->setAttribute('actionid', $data['actionid'])
		->setAttribute('eventsource', $data['eventsource'])
		->setAttribute('operation_type', ACTION_OPERATION)
		// TODO : fix the input to action edit popup open!!!
		->addClass(ZBX_STYLE_BTN_LINK)
);

// Operations tab.
$operations_tab = (new CFormGrid());

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_tab->addItem([
		(new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		(new CTextBox('esc_period', $data['action']['esc_period']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	]);
}

$operations_tab->addItem([
	new CLabel(_('Operations')),
	(new CFormField($operations_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);

if ($data['action']['operations']) {
	$actionOperationDescriptions = getActionOperationDescriptions($data['eventsource'], [$data['action']], ACTION_OPERATION);
}


// Recovery operations table.
if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	// Create operation table.
	$operations_table = (new CTable())
		->setId('rec-table')
		->setAttribute('style', 'width: 100%;');
		$operations_table->setHeader([_('Details'), _('Action')]);

	$operations_table->setFooter(
		(new CSimpleButton(_('Add')))
			->setAttribute('data-actionid', $data['actionid'])
			->setAttribute('data-eventsource', $data['eventsource'])
//			->onClick('
//					action_edit_popup.open(this, this.dataset.actionid, this.dataset.eventsource,
//					'.ACTION_RECOVERY_OPERATION.'
//				);
//			')
			->addClass('js-recovery-operations-create')
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
	$operations_table = (new CTable())
		->setId('upd-table')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Details'), _('Action')]);

	$operations_table->setFooter(
			(new CSimpleButton(_('Add')))
			->setAttribute('data-actionid', $data['actionid'])
			->setAttribute('data-eventsource', $data['eventsource'])
//			->onClick('
//			operation_details.open(this, this.dataset.actionid, this.dataset.eventsource,
//					'.ACTION_UPDATE_OPERATION.'
//				);
//			')
			->addClass('js-update-operations-create')
				// TODO : fix the input to action edit popup open!!!
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
				->setChecked($data['action']['pause_suppressed'] == ACTION_PAUSE_SUPPRESSED_TRUE)
				->setChecked(true)
			)
		])
		->addItem([
			new CLabel(_('Notify about canceled escalations'), 'notify_if_canceled'),
			new CFormField(
				(new CCheckBox('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_TRUE))
					->setChecked($data['action']['notify_if_canceled'] == ACTION_NOTIFY_IF_CANCELED_TRUE)
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
		(new CScriptTag('action_edit_popup.init('.json_encode([
				'condition_operators' => condition_operator2str(),
				'condition_types' => condition_type2str(),
				'conditions' => $data['action']['filter']['conditions'],
				'actionid' => $data['actionid'],
				'eventsource' => $data['eventsource']
			]).');
			'))->setOnDocumentReady()
	);

if ($data['actionid'] !== '') {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'action_edit_popup.submit();'
		],
//		[
//			'title' => _('Clone'),
//			'class' => ZBX_STYLE_BTN_ALT,
//			'keepOpen' => true,
//			'isSubmit' => false,
//			'action' => 'action_edit_popup.clone();'
//		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'action_edit_popup.clone('.json_encode([
					'title' => _('New action'),
					// todo : ask what title should be here
					// todo: remove id
					'buttons' => [
						[
							'title' => _('Add'),
							'class' => 'js-add',
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'action_edit_popup.submit();'
						],
						[
							'title' => _('Cancel'),
							'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
							'cancel' => true,
							'action' => ''
						]
					]
				]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete current action?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'action_edit_popup.delete('.json_encode($data['actionid']).');'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'action_edit_popup.submit();'
		]
	];
}


$output = [
	'header' => _('Actions'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::CONFIGURATION_ACTION_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.action.edit.js.php')
];

echo json_encode($output);
