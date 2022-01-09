<?php declare(strict_types = 1);
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

$form = (new CForm())
	->setId('service-status-rule-form')
	->setName('service_status_rule_form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit', null))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Set status to'), 'service-status-rule-new-status-focusable'),
		new CFormField(
			(new CSelect('new_status'))
				->setId('service-status-rule-new-status')
				->setFocusableElementId('service-status-rule-new-status-focusable')
				->setValue($data['form']['new_status'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getProblemStatusNames()))
		)
	])
	->addItem([
		new CLabel(_('Condition'), 'service-status-rule-type-focusable'),
		new CFormField(
			(new CSelect('type'))
				->setId('service-status-rule-type')
				->setFocusableElementId('service-status-rule-type-focusable')
				->setValue($data['form']['type'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getStatusRuleTypeOptions()))
				->setOptionTemplate('#{*label}')
				->setSelectedOptionTemplate('#{*label}')
		)
	])
	->addItem([
		(new CLabel('N', 'service-status-rule-limit-value'))->setId('service-status-rule-limit-value-label'),
		new CFormField(
			new CHorList([
				(new CTextBox('limit_value', $data['form']['limit_value'], false, 7))
					->setId('service-status-rule-limit-value')
					->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
					->setAriaRequired(),
				(new CSpan('%'))
					->setId('service-status-rule-limit-value-unit')
					->addStyle('display: none;')
			])
		)
	])
	->addItem([
		new CLabel(_('Status'), 'service-status-rule-limit-status-focusable'),
		new CFormField(
			(new CSelect('limit_status'))
				->setId('service-status-rule-limit-status')
				->setFocusableElementId('service-status-rule-limit-status-focusable')
				->setValue($data['form']['limit_status'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getStatusNames()))
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			service_status_rule_edit_popup.init();
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
			'action' => 'service_status_rule_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.service.statusrule.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
