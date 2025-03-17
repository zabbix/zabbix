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
 * @var CPartial $this
 * @var array $data
 */

$discovered_trigger = array_key_exists('discovered_trigger', $data) ? $data['discovered_trigger'] : false;
$readonly = $data['readonly'];

$trigger_form_grid = new CFormGrid();
if ($data['templates']) {
	$trigger_form_grid->addItem([new CLabel(_('Parent triggers')), new CFormField($data['templates'])]);
}

if ($discovered_trigger) {
	$discovered_trigger_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'trigger.prototype.edit')
		->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
		->setArgument('triggerid', $data['triggerDiscovery']['parent_triggerid'])
		->setArgument('context', $data['context'])
		->setArgument('prototype', '1')
		->getUrl();

	$trigger_form_grid->addItem([new CLabel(_('Discovered by')), new CFormField(
		(new CLink($data['discoveryRule']['name'], $discovered_trigger_url))
			->setAttribute('data-parent_discoveryid', $data['discoveryRule']['itemid'])
			->setAttribute('data-triggerid', $data['triggerDiscovery']['parent_triggerid'])
			->setAttribute('data-context', $data['context'])
			->setAttribute('data-prototype', '1')
			->addClass('js-related-trigger-edit')
	)]);
}

$trigger_form_grid
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField((new CTextBox('name', $data['description'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)])
	->addItem([
		(new CLabel(_('Event name'), 'event_name')),
		new CFormField((new CTextAreaFlexible('event_name', $data['event_name']))
			->setReadonly($readonly)
			->setMaxlength(DB::getFieldLength('triggers', 'event_name'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableSpellcheck()
		)])
	->addItem([
		new CLabel(_('Operational data'), 'opdata'),
		new CFormField((new CTextBox('opdata', $data['opdata'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	]);

if ($discovered_trigger) {
	$trigger_form_grid->addItem([(new CVar('priority', (int) $data['priority']))->removeId()]);
	$severity = (new CSeverity('priority_names', (int) $data['priority']))->setReadonly($readonly);
}
else {
	$severity = new CSeverity('priority', (int) $data['priority']);
}

$trigger_form_grid->addItem([new CLabel(_('Severity'), 'priority'), new CFormField($severity)]);

$expression_row = [
	(new CTextArea('expression', $data['expression']))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($readonly)
		->disableSpellcheck()
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', _('Add')))
		->setId('insert-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$readonly)
];

// Append "Insert expression" button.
$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_row[] = (new CButton('insert_macro', _('Insert expression')))
	->setId('insert-macro')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setMenuPopup(CMenuPopupHelper::getTriggerMacro())
	->setEnabled(!$readonly);

$expression_constructor_buttons = [];

// Append "Add" button.
$expression_constructor_buttons[] = (new CButton('add_expression', _('Add')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "And" button.
$expression_constructor_buttons[] = (new CButton('and_expression', _('And')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Or" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[] = (new CButton('or_expression', _('Or')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Replace" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[] = (new CButton('replace_expression', _('Replace')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

$input_method_toggle = (new CButtonLink( _('Expression constructor')))->setId('expression-constructor');

$expression_row[] = [
	(new CDiv($expression_constructor_buttons))
		->setId('expression-constructor-buttons')
		->addClass(ZBX_STYLE_FORM_SUBFIELD)
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid
	->addItem([
		(new CLabel(_('Expression'), 'expression'))->setAsteriskMark(),
		(new CFormField($expression_row))->setId('expression-row')
	])
	->addItem((new CFormField())
		->setId('expression-table')
		->addStyle('display: none')
	);

$input_method_toggle = new CDiv(
	(new CButtonLink(_('Close expression constructor')))->setId('close-expression-constructor')
);
$trigger_form_grid->addItem((new CFormField([null, $input_method_toggle]))
	->addStyle('display: none')
	->setId('close-expression-constructor-field')
);

$trigger_form_grid->addItem([new CLabel(_('OK event generation'), 'recovery_mode'),
	new CFormField((new CRadioButtonList('recovery_mode', (int) $data['recovery_mode']))
		->addValue(_('Expression'), ZBX_RECOVERY_MODE_EXPRESSION)
		->addValue(_('Recovery expression'), ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION)
		->addValue(_('None'), ZBX_RECOVERY_MODE_NONE)
		->setModern()
		->setReadonly($readonly)
	)
]);

$recovery_expression_row = [
	(new CTextArea('recovery_expression', $data['recovery_expression']))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($readonly)
		->disableSpellcheck()
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', _('Add')))
		->setId('insert-recovery-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$readonly)
];

$recovery_constructor_buttons = [];

// Append "Add" button.
$recovery_constructor_buttons[] = (new CButton('add_expression_recovery', _('Add')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "And" button.
$recovery_constructor_buttons[] = (new CButton('and_expression_recovery', _('And')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Or" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CButton('or_expression_recovery', _('Or')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Replace" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CButton('replace_expression_recovery', _('Replace')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

$input_method_toggle = (new CButtonLink(_('Expression constructor')))
	->setId('recovery-expression-constructor');

$recovery_expression_row[] = [
	(new CDiv($recovery_constructor_buttons))
		->setId('recovery-constructor-buttons')
		->addClass(ZBX_STYLE_FORM_SUBFIELD)
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid
	->addItem([
		(new CLabel(_('Recovery expression'), 'recovery_expression'))->setAsteriskMark(),
		(new CFormField($recovery_expression_row))->setId('recovery-expression-row')
	])
	->addItem(
		(new CFormField())
			->setId('recovery-expression-table')
			->addStyle('display: none')
	);

$input_method_toggle = (new CButtonLink(_('Close expression constructor')))
	->setId('close-recovery-expression-constructor');

$trigger_form_grid
	->addItem((new CFormField([null, $input_method_toggle]))
		->addStyle('display: none')
		->setId('close-recovery-expression-constructor-field')
	)
	->addItem([new CLabel(_('PROBLEM event generation mode'), 'type'),
		new CFormField((new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('Single'), TRIGGER_MULT_EVENT_DISABLED)
			->addValue(_('Multiple'), TRIGGER_MULT_EVENT_ENABLED)
			->setModern()
			->setReadonly($readonly)
		)
	])
	->addItem([new CLabel(_('OK event closes'), 'correlation_mode'),
		(new CFormField((new CRadioButtonList('correlation_mode', (int) $data['correlation_mode']))
			->addValue(_('All problems'), ZBX_TRIGGER_CORRELATION_NONE)
			->addValue(_('All problems if tag values match'), ZBX_TRIGGER_CORRELATION_TAG)
			->setModern()
			->setReadonly($readonly)
		))->setId('ok-event-closes')
	])
	->addItem([(new CLabel(_('Tag for matching'), 'correlation_tag'))->setAsteriskMark(),
		(new CFormField((new CTextBox('correlation_tag', $data['correlation_tag'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		))->setId('correlation_tag')
	])
	->addItem([new CLabel(_('Allow manual close'), 'manual_close'),
		new CFormField(
			(new CCheckBox('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED))
				->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
				->setReadonly($readonly)
		)
	])
	->addItem([
		new CLabel([
			_('Menu entry name'),
			makeHelpIcon([_('Menu entry name is used as a label for the trigger URL in the event context menu.')])
		], 'url_name'),
		new CFormField((new CTextBox('url_name', array_key_exists('url_name', $data) ? $data['url_name'] : '',
			$discovered_trigger, DB::getFieldLength('triggers', 'url_name')
		))
			->setAttribute('placeholder', _('Trigger URL'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([new CLabel(_('Menu entry URL'), 'url'),
		new CFormField(
			(new CTextBox('url', array_key_exists('url', $data) ? $data['url'] : '', $discovered_trigger,
				DB::getFieldLength('triggers', 'url')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([new CLabel(_('Description'), 'description'),
		new CFormField((new CTextArea('description', array_key_exists('comments', $data) ? $data['comments'] : ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('triggers', 'comments'))
			->setReadonly($discovered_trigger)
		)
	]);

$disabled_by_lld_icon = $data['status'] == TRIGGER_STATUS_DISABLED && array_key_exists('triggerDiscovery', $data)
		&& $data['triggerDiscovery'] && $data['triggerDiscovery']['disable_source'] == ZBX_DISABLE_SOURCE_LLD
	? makeWarningIcon(_('Disabled automatically by an LLD rule.'))
	: null;

if (array_key_exists('parent_discoveryid', $data)) {
	$trigger_form_grid
		->addItem([new CLabel(_('Create enabled'), 'status'),
			new CFormField((new CCheckBox('status', TRIGGER_STATUS_ENABLED))
				->setChecked($data['status'] == TRIGGER_STATUS_ENABLED)
			)
		])
		->addItem([new CLabel(_('Discover'), 'discover'),
			new CFormField(
				(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))
					->setChecked($data['discover'] == ZBX_PROTOTYPE_DISCOVER)
			)
		]);
} else {
	$trigger_form_grid
		->addItem([
			new CLabel([_('Enabled'), $disabled_by_lld_icon], 'status'),
			new CFormField((new CCheckBox('status', TRIGGER_STATUS_ENABLED))
				->setChecked($data['status'] == TRIGGER_STATUS_ENABLED)
			)
		]);
}

$trigger_form_grid->show();
