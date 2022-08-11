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
$this->addJsFile('popup.operation.common.js');
$this->addJsFile('configuration.action.edit.js.php');

$form = (new CForm())
	->setName('action.edit')
	->setId('action-form')
	->addVar('actionid', $data['actionid'])
	->addStyle('display: none;')
	//->addItem(getMessages())
	->addItem((new CInput('submit'))->addStyle('display: none;'));

if ($data['actionid']) {
	$form->addVar('actionid', $data['actionid']);
}

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('conditions-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

// $i = 0;
// if ($data['action']['filter']['conditions']) {
//	$actionConditionStringValues = actionConditionValueToString([$data['action']]);
//	foreach ($data['action']['filter']['conditions'] as $cIdx => $condition) {
//		if (!isset($condition['conditiontype'])) {
//			$condition['conditiontype'] = 0;
//		}
//		if (!isset($condition['operator'])) {
//			$condition['operator'] = 0;
//		}
//		if (!isset($condition['value'])) {
//			$condition['value'] = '';
//		}
//		if (!array_key_exists('value2', $condition)) {
//			$condition['value2'] = '';
//		}
//		if (!str_in_array($condition['conditiontype'], $data['allowedConditions'])) {
//			continue;
//		}

//		$label = isset($condition['formulaid']) ? $condition['formulaid'] : num2letter($i);

//		$labelSpan = (new CSpan($label))
//			->addClass('label')
//			->setAttribute('data-conditiontype', $condition['conditiontype'])
//			->setAttribute('data-formulaid', $label);

//		$condition_table->addRow(
//			[
//				$labelSpan,
//				(new CCol(getConditionDescription($condition['conditiontype'], $condition['operator'],
//					$actionConditionStringValues[0][$cIdx], $condition['value2']
//				)))->addClass(ZBX_STYLE_TABLE_FORMS_OVERFLOW_BREAK),
//				(new CCol([
//					(new CButton('remove', _('Remove')))
//						->onClick('removeCondition('.$i.');')
//						->addClass(ZBX_STYLE_BTN_LINK)
//						->setId('conditions_table'),
//						//->removeId(),
//					new CVar('conditions['.$i.']', $condition)
//				]))->addClass(ZBX_STYLE_NOWRAP)
//			],
//			null, 'conditions_'.$i
//		);

//		$i++;
//	}
//}

$formula = (new CTextBox('formula', $data['action']['filter']['formula'], false,
	DB::getFieldLength('actions', 'formula')
))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) &hellip;');

// todo: add formula and type of calculation when at least 2 conditions are added -> in js???

$condition_table->setFooter(
	(new CSimpleButton(_('Add')))
		->setAttribute('data-eventsource', $data['eventsource'])
		->onClick('PopUp("popup.condition.actions",
			{
				type: '.ZBX_POPUP_CONDITION_TYPE_ACTION.',
				source: '.$data['eventsource'].'.
			},
			{
				dialogue_class: "modal-popup-medium",
				dialogueid: "action-edit-condition"
			}
		);')
		->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')
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




// Operations table.
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

$operations_table->setFooter(
	(new CSimpleButton(_('Add')))
		->setAttribute('data-actionid', 0)
		->setAttribute('data-eventsource', $data['eventsource'])
		->onClick('
			operation_details.open(this, this.dataset.actionid, this.dataset.eventsource, '.ACTION_OPERATION.');
		')
		// TODO : fix the input to action edit popup open!!!
		->addClass(ZBX_STYLE_BTN_LINK)
);

// Operations tab.
$operations_tab = (new CFormGrid());

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_tab->addItem([
		(new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		//(new CTextBox('esc_period', $data['action']['esc_period']))
		(new CTextBox('esc_period', '1h'))
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
					action_edit_popup.open(this, this.dataset.actionid, this.dataset.eventsource,
					'.ACTION_RECOVERY_OPERATION.'
				);
			')
			// TODO : fix the input to action edit popup open!!!
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
		(new CScriptTag('action_edit_popup.init('.json_encode([
				'condition_operators' => condition_operator2str(),
				'condition_types' => condition_type2str()
			]).');'))
			->setOnDocumentReady()
	);

$buttons = [
	[
		'title' => _('Add'),
		'class' => 'js-add',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'action_edit_popup.submit();'
	]
	// todo: add function submit()
];

$output = [
	'header' => _('Actions'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::CONFIGURATION_ACTION_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.action.edit.js.php')
];

echo json_encode($output);
