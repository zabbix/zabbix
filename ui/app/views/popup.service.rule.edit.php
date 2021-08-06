<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */

$form = (new CForm())
	->setId('service-rule-form')
	->setName('service-rule-form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		new CLabel(_('Set status to'), 'service_rule_status'),
		new CFormField(
			(new CSelect('new_status'))
				->setId('service_rule_new_status')
				->setFocusableElementId('service_rule_new_status_focusable')
				->setValue($data['form']['new_status'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getRuleStatusNames()))
		)
	])
	->addItem([
		new CLabel(_('Condition'), 'service_rule_condition'),
		new CFormField(
			(new CSelect('type'))
				->setId('service_rule_type')
				->setFocusableElementId('service_rule_type_focusable')
				->setValue($data['form']['type'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getRuleConditionNames()))
				->setOptionTemplate('#{*label}')
				->setSelectedOptionTemplate('#{*label}')
		)
	])
	->addItem([
		(new CLabel(_('N'), 'service_rule_limit_value'))->setId('service_rule_limit_value_label'),
		new CFormField([
			(new CTextBox('limit_value', $data['form']['limit_value'], false, 7))
				->setId('service_rule_limit_value')
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			(new CSpan('%'))
				->setId('service_rule_limit_value_unit')
				->addStyle('display: none;')
		])
	])
	->addItem([
		new CLabel(_('Status'), 'service_rule_limit_status'),
		new CFormField(
			(new CSelect('limit_status'))
				->setId('service_rule_limit_status')
				->setFocusableElementId('service_rule_limit_status_focusable')
				->setValue($data['form']['limit_status'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getRuleStatusNames()))
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			service_rule_edit_popup.init();
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['is_edit'] ? _('Additional rule') : _('New additional rule'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_rule_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.service.rule.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
