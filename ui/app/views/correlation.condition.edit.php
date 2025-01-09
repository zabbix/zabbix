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

$inline_js = getPagePostJs().$this->readJsFile('correlation.condition.edit.js.php');

$form = (new CForm())
	->setId('correlation-condition-form')
	->setName('conditions')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('conditiontype', $data['conditiontype'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$condition_type = (int) $data['last_type'];

// Type select.
$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Type'), 'label-condition-type'),
		new CFormField((new CSelect('conditiontype'))
			->setFocusableElementId('label-condition-type')
			->setValue($condition_type)
			->setId('condition-type')
			->addOptions(CSelect::createOptionsFromArray(CCorrelationHelper::getConditionTypes()))
		)
	]);

switch ($condition_type) {
	// Old|New event tag form elements.
	case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
	case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
			->setModern(true)
			->addValue(CCorrelationHelper::getLabelByOperator(
				CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_OLD_EVENT_TAG)[0]
			), CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_OLD_EVENT_TAG)[0]);

		$new_condition_tag = (new CTextAreaFlexible('tag'))
			->setId('tag')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_grid
			->addItem([
				new CLabel(_('Operator')),
				new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
			])
			->addItem([
				(new CLabel(_('Tag'), 'tag'))->setAsteriskMark(),
				new CFormField($new_condition_tag)
			]);
		break;

	// New event host group form elements.
	case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
		$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);

		foreach (CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP) as $value) {
			$operator->addValue(CCorrelationHelper::getLabelByOperator($value), $value);
		}

		$hostgroup_multiselect = (new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'default_value' => 0,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groupids_'
				]
			]
		]))
			->setId('groupids_')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_grid
			->addItem([
				new CLabel(_('Operator')),
				new CFormField($operator)
			])
			->addItem([
				(new CLabel(_('Host groups'), 'groupids__ms'))->setAsteriskMark(),
				new CFormField($hostgroup_multiselect)
			]);
		break;

	// Event tag pair form elements.
	case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
		$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
			->setModern(true)
			->addValue(CCorrelationHelper::getLabelByOperator(
				CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_EVENT_TAG_PAIR)[0]
			), CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_EVENT_TAG_PAIR)[0]);

		$new_condition_oldtag = (new CTextAreaFlexible('oldtag'))
			->setId('oldtag')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$new_condition_newtag = (new CTextAreaFlexible('newtag'))
			->setId('newtag')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_grid
			->addItem([
				(new CLabel(_('Old tag name'), 'oldtag'))->setAsteriskMark(),
				new CFormField($new_condition_oldtag)
			])
			->addItem([
				new CLabel(_('Operator')),
				new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
			])
			->addItem([
				(new CLabel(_('New tag name'), 'newtag'))->setAsteriskMark(),
				new CFormField($new_condition_newtag)
			]);
		break;

	// Old|New event tag value form elements.
	case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
	case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
		$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);

		foreach (CCorrelationHelper::getOperatorsByConditionType($condition_type) as $value) {
			$operator->addValue(CCorrelationHelper::getLabelByOperator($value), $value);
		}

		$new_condition_tag = (new CTextAreaFlexible('tag'))
			->setId('tag')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$new_condition_value = (new CTextAreaFlexible('value'))
			->setId('value')
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_grid
			->addItem([
				(new CLabel(_('Tag'), 'tag'))->setAsteriskMark(),
				new CFormField($new_condition_tag)
			])
			->addItem([
				new CLabel(_('Operator')),
				new CFormField($operator)
			])
			->addItem([
				new CLabel(_('Value'), 'value'),
				new CFormField($new_condition_value)
			]);
		break;
}

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('correlation_condition_popup.init();'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'script_inline' => getPagePostJs().$this->readJsFile('correlation.condition.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'correlation_condition_popup.submit()'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
