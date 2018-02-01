<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$options = $data['options'];
$output = [
	'header' => $data['title'],
	'script_inline' => require 'app/views/popup.triggerwizard.js.php'
];

$form = (new CForm('post', 'zabbix.php'))
	->setName('sform')
	->addVar('sform', '1')
	->addVar('action', 'popup.triggerwizard')
	->addVar('itemid', $options['itemid'])
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

if (array_key_exists('triggerid', $options)) {
	$form->addVar('triggerid', $options['triggerid']);
}

$key_table = (new CTable())
	->setId('key_list')
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		_('Keyword'),
		_('Type'),
		_('Action')
	]);

$max_id = 0;
foreach ($data['keys'] as $id => $val) {
	$key_table->addRow(
		(new CRow([
			htmlspecialchars($val['value']),
			$val['type'],
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('remove_keyword("keytr'.$id.'");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('keytr'.$id)
	);

	$form
		->addVar('keys['.$id.'][value]', $val['value'])
		->addVar('keys['.$id.'][type]', $val['type']);

	$max_id = max($max_id, $id);
}

$output['script_inline'] .= 'key_count='.($max_id + 1).';'."\n";

$expression_table = (new CTable())
	->setId('exp_list')
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		_('Expression'),
		_('Type'),
		_('Position'),
		_('Action')
	]);

$max_id = 0;
foreach ($data['expressions'] as $id => $expr) {
	$imgup = (new CImg('images/general/arrow_up.png', 'up', 12, 14))
		->onClick('element_up("logtr'.$id.'");')
		->onMouseover('this.style.cursor = "pointer";')
		->addClass('updown');

	$imgdn = (new CImg('images/general/arrow_down.png', 'down', 12, 14))
		->onClick('element_down("logtr'.$id.'");')
		->onMouseover('this.style.cursor = "pointer";')
		->addClass('updown');

	$expression_table->addRow(
		(new CRow([
			htmlspecialchars($expr['value']),
			($expr['type'] == CTextTriggerConstructor::EXPRESSION_TYPE_MATCH) ? _('Include') : _('Exclude'),
			[$imgup, ' ', $imgdn],
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('remove_expression("logtr'.$id.'");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('logtr'.$id)
	);

	$form
		->addVar('expressions['.$id.'][value]', $expr['value'])
		->addVar('expressions['.$id.'][type]', $expr['type']);

	$max_id = max($max_id, $id);
}

$output['script_inline'] .= 'logexpr_count='.($max_id + 1).';'."\n";
$output['script_inline'] .= 'jQuery(document).ready(function(){processExpressionList();});'."\n";

$form->addItem(
	(new CTabView())
		->addTab('trigger_tab', null,
			(new CFormList())
				->addRow(_('Name'),
					(new CTextBox('description', $options['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
				->addRow(_('Item'), [
					(new CTextBox('item', $options['item_name']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('item')
						->setAttribute('disabled', 'disabled'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton(null, _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('return PopUp("popup.generic",'.
							CJs::encodeJson([
								'srctbl' => 'items',
								'srcfld1' => 'itemid',
								'srcfld2' => 'name',
								'dstfrm' => $form->getName(),
								'dstfld1' => 'itemid',
								'dstfld2' => 'item'
							]).', null, this);'
						)
				])
				->addRow(_('Severity'), new CSeverity([
					'name' => 'priority',
					'value' => (int) $options['priority']
				]))
				->addRow(_('Expression'),
					(new CTextBox('expression'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('logexpr')
				)
				->addRow(null, [
					(new CCheckBox('iregexp'))->setLabel('iregexp'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('add_key_and', _('AND')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('add_keyword_and();'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('add_key_or', _('OR')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('add_keyword_or();'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CComboBox('expr_type', null, null, [
						CTextTriggerConstructor::EXPRESSION_TYPE_MATCH => _('Include'),
						CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH => _('Exclude')
					]))->setId('expr_type'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('add_exp', _('Add')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('add_logexpr();')
				])
				->addRow(null,
					(new CDiv($key_table))
						->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
						->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				)
				->addRow(null,
					(new CDiv($expression_table))
						->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
						->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				)
				->addRow(_('URL'), (new CTextBox('url', $options['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
				->addRow(_('Description'),
					(new CTextArea('comments', $options['comments']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
				->addRow(_('Enabled'),
					(new CCheckBox('status'))->setChecked($options['status'] == TRIGGER_STATUS_ENABLED)
				)
		)
);

$output['body'] = $form->toString();
$output['buttons'] = [
	[
		'title' => array_key_exists('triggerid', $options) ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return validateTriggerWizard("'.$form->getName().'", '.
						'jQuery(window.document.forms["'.$form->getName().'"]).closest("[data-dialogueid]")'.
							'.attr("data-dialogueid"));'
	]
];

echo (new CJson())->encode($output);
