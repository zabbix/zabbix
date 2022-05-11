<?php
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
 */

$inline_js = '';

$form = (new CForm())
	->cleanItems()
	->setId('popup.condition')
	->setName('popup.condition')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', $data['action'])
	->addVar('type', $data['type']);

if (array_key_exists('source', $data)) {
	$form->addVar('source', $data['source']);
}

$condition_type = (int) $data['last_type'];

$form_list = (new CFormList())->cleanItems();

switch ($data['type']) {
	case ZBX_POPUP_CONDITION_TYPE_EVENT_CORR:
		// Type select.
		$form_list->addRow(new CLabel(_('Type'), 'label-condition-type'), (new CSelect('condition_type'))
			->setFocusableElementId('label-condition-type')
			->setValue($condition_type)
			->setId('condition-type')
			->addOptions(CSelect::createOptionsFromArray(CCorrelationHelper::getConditionTypes()))
		);

		$inline_js .= '$(() => $("#condition-type").on("change",'
			.'(e) => reloadPopup($(e.target).closest("form").get(0), "popup.condition.event.corr")));';

		switch ($condition_type) {
			// Old|New event tag form elements.
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(CCorrelationHelper::getLabelByOperator(
						CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_OLD_EVENT_TAG)[0]
					), CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_OLD_EVENT_TAG)[0]);
				$new_condition_tag = (new CTextAreaFlexible('tag'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_tag->getPostJS();

				$form_list
					->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					->addRow(_('Tag'), $new_condition_tag);
				break;

			// New event host group form elements.
			case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach (CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP)
						as $value) {
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
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $hostgroup_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Host groups'), $hostgroup_multiselect);
				break;

			// Event tag pair form elements.
			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(CCorrelationHelper::getLabelByOperator(
						CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_EVENT_TAG_PAIR)[0]
					), CCorrelationHelper::getOperatorsByConditionType(ZBX_CORR_CONDITION_EVENT_TAG_PAIR)[0]);
				$new_condition_oldtag = (new CTextAreaFlexible('oldtag'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
				$new_condition_newtag = (new CTextAreaFlexible('newtag'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_oldtag->getPostJS();
				$inline_js .= $new_condition_newtag->getPostJS();

				$form_list
					->addRow(_('Old tag name'), $new_condition_oldtag)
					->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					->addRow(_('New tag name'), $new_condition_newtag);
				break;

			// Old|New event tag value form elements.
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach (CCorrelationHelper::getOperatorsByConditionType($condition_type) as $value) {
					$operator->addValue(CCorrelationHelper::getLabelByOperator($value), $value);
				}

				$new_condition_tag = (new CTextAreaFlexible('tag'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
				$new_condition_value = (new CTextAreaFlexible('value'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_tag->getPostJS();
				$inline_js .= $new_condition_value->getPostJS();

				$form_list
					->addRow(_('Tag'), $new_condition_tag)
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;
		}
		break;

	case ZBX_POPUP_CONDITION_TYPE_ACTION:
		require_once dirname(__FILE__).'/../../include/actions.inc.php';

		// Collect all operators options.
		$operators_by_condition = [];
		$action_conditions = [];
		foreach ($data['allowed_conditions'] as $type) {
			if ($data['source'] == EVENT_SOURCE_SERVICE && $type == CONDITION_TYPE_EVENT_TAG) {
				$action_conditions[$type] = _('Service tag name');
			}
			elseif ($data['source'] == EVENT_SOURCE_SERVICE && $type == CONDITION_TYPE_EVENT_TAG_VALUE) {
				$action_conditions[$type] = _('Service tag value');
			}
			else {
				$action_conditions[$type] = condition_type2str($type);
			}

			foreach (get_operators_by_conditiontype($type) as $value) {
				$operators_by_condition[$type][$value] = condition_operator2str($value);
			}
		}

		// Type select.
		$form_list->addRow(new CLabel(_('Type'), 'label-condition-type'), (new CSelect('condition_type'))
			->setFocusableElementId('label-condition-type')
			->setValue($condition_type)
			->setId('condition-type')
			->addOptions(CSelect::createOptionsFromArray($action_conditions))
		);

		$inline_js .= '$(() => $("#condition-type").on("change",'
			.'(e) => reloadPopup($(e.target).closest("form").get(0), "popup.condition.actions")));';

		switch ($condition_type) {
			// Trigger form elements.
			case CONDITION_TYPE_TRIGGER:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_TRIGGER] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$trigger_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'triggers',
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'triggers',
							'srcfld1' => 'triggerid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'trigger_new_condition',
							'editable' => true,
							'noempty' => true
						]
					]
				]))
					->setId('trigger_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $trigger_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Triggers'), $trigger_multiselect);
				break;

			// Trigger severity form elements.
			case CONDITION_TYPE_TRIGGER_SEVERITY:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_TRIGGER_SEVERITY] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Severity'), new CSeverity('value', TRIGGER_SEVERITY_NOT_CLASSIFIED));
				break;

			// Host form elements.
			case CONDITION_TYPE_HOST:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_HOST] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$host_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'hosts',
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'host_new_condition',
							'editable' => true
						]
					]
				]))
					->setId('host_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $host_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Hosts'), $host_multiselect);
				break;

			// Host group form elements.
			case CONDITION_TYPE_HOST_GROUP:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_HOST_GROUP] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$hostgroup_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'hostGroup',
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'hostgroup_new_condition',
							'editable' => true
						]
					]
				]))
					->setId('hostgroup_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $hostgroup_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Host groups'), $hostgroup_multiselect);
				break;

			// Problem is suppressed form elements.
			case CONDITION_TYPE_SUPPRESSED:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_NO))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_SUPPRESSED] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$form_list->addRow(_('Operator'), $operator);
				break;

			// Tag form elements.
			case CONDITION_TYPE_EVENT_TAG:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_EVENT_TAG] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CTextAreaFlexible('value'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_value->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow((new CLabel(_('Tag')))->setAsteriskMark(), $new_condition_value);
				break;

			// Tag value form elements.
			case CONDITION_TYPE_EVENT_TAG_VALUE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_EVENT_TAG_VALUE] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value2 = (new CTextAreaFlexible('value2'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
				$new_condition_value = (new CTextAreaFlexible('value'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_value2->getPostJS();
				$inline_js .= $new_condition_value->getPostJS();

				$form_list
					->addRow((new CLabel(_('Tag')))->setAsteriskMark(), $new_condition_value2)
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Template form elements.
			case CONDITION_TYPE_TEMPLATE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_TEMPLATE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$template_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'templates',
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'templates',
							'srcfld1' => 'hostid',
							'srcfld2' => 'host',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'template_new_condition',
							'editable' => true
						]
					]
				]))
					->setId('template_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $template_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Templates'), $template_multiselect);
				break;

			// Time period form elements.
			case CONDITION_TYPE_TIME_PERIOD:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_IN))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_TIME_PERIOD] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', ZBX_DEFAULT_INTERVAL))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Discovery host ip form elements.
			case CONDITION_TYPE_DHOST_IP:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DHOST_IP] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', '192.168.0.1-127,192.168.2.1'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Discovery check form elements.
			case CONDITION_TYPE_DCHECK:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DCHECK] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$dcheck_popup_select = [
					(new CInput('hidden', 'value', '0'))
						->removeId()
						->setId('dcheck_new_condition_value'),
					(new CTextBox('dcheck', '', true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('btn1', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick(
							'return PopUp("popup.generic", '. json_encode([
								'srctbl' => 'dchecks',
								'srcfld1' => 'dcheckid',
								'srcfld2' => 'name',
								'dstfrm' => $form->getName(),
								'dstfld1' => 'dcheck_new_condition_value',
								'dstfld2' => 'dcheck',
								'writeonly' => '1'
							]).', {dialogue_class: "modal-popup-generic"});'
						)
				];

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Discovery check'), $dcheck_popup_select);
				break;

			// Discovery object form elements.
			case CONDITION_TYPE_DOBJECT:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(
						$operators_by_condition[CONDITION_TYPE_DOBJECT][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);
				$new_condition_value = (new CRadioButtonList('value', EVENT_OBJECT_DHOST))
					->setModern(true)
					->addValue(discovery_object2str(EVENT_OBJECT_DHOST), EVENT_OBJECT_DHOST)
					->addValue(discovery_object2str(EVENT_OBJECT_DSERVICE), EVENT_OBJECT_DSERVICE);

				$form_list
					->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					->addRow(_('Discovery object'), $new_condition_value);
				break;

			// Discovery rule form elements.
			case CONDITION_TYPE_DRULE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DRULE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$drule_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'drules',
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'drules',
							'srcfld1' => 'druleid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'drule_new_condition'
						]
					]
				]))
					->setId('drule_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $drule_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Discovery rules'), $drule_multiselect);
				break;

			// Discovery status form elements.
			case CONDITION_TYPE_DSTATUS:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(
						$operators_by_condition[CONDITION_TYPE_DSTATUS][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);
				$new_condition_value = (new CRadioButtonList('value', DOBJECT_STATUS_UP))
				->setModern(true)
				->addValue(discovery_object_status2str(DOBJECT_STATUS_UP), DOBJECT_STATUS_UP)
				->addValue(discovery_object_status2str(DOBJECT_STATUS_DOWN), DOBJECT_STATUS_DOWN)
				->addValue(discovery_object_status2str(DOBJECT_STATUS_DISCOVER), DOBJECT_STATUS_DISCOVER)
				->addValue(discovery_object_status2str(DOBJECT_STATUS_LOST), DOBJECT_STATUS_LOST);

				$form_list
					->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					->addRow(_('Discovery status'), $new_condition_value);
				break;

			// Proxy form elements.
			case CONDITION_TYPE_PROXY:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_PROXY] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$proxy_multiselect = (new CMultiSelect([
					'name' => 'value',
					'object_name' => 'proxies',
					'multiple' => false,
					'default_value' => 0,
					'popup' => [
						'parameters' => [
							'srctbl' => 'proxies',
							'srcfld1' => 'proxyid',
							'srcfld2' => 'host',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'proxy_new_condition'
						]
					]
				]))
					->setId('proxy_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $proxy_multiselect->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Proxy'), $proxy_multiselect);
				break;

			// Received value form elements.
			case CONDITION_TYPE_DVALUE:
				$new_condition_value = (new CTextAreaFlexible('value'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_value->getPostJS();

				$form_list
					->addRow(new CLabel(_('Operator'), 'label-operator'), (new CSelect('operator'))
						->setValue(CONDITION_OPERATOR_EQUAL)
						->setFocusableElementId('label-operator')
						->addOptions(CSelect::createOptionsFromArray($operators_by_condition[CONDITION_TYPE_DVALUE]))
					)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Service port form elements.
			case CONDITION_TYPE_DSERVICE_PORT:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DSERVICE_PORT] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', '0-1023,1024-49151'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Service type form elements.
			case CONDITION_TYPE_DSERVICE_TYPE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DSERVICE_TYPE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$discovery_check_types = discovery_check_type2str();
				order_result($discovery_check_types);

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(new CLabel(_('Service type'), 'label-condition-service-type'), (new CSelect('value'))
						->setFocusableElementId('label-condition-service-type')
						->addOptions(CSelect::createOptionsFromArray($discovery_check_types))
					);
				break;

			// Discovery uptime|downtime form elements.
			case CONDITION_TYPE_DUPTIME:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_MORE_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_DUPTIME] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CNumericBox('value', 600, 15))->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow(_('Value'), $new_condition_value);
				break;

			// Trigger name form elements.
			case CONDITION_TYPE_TRIGGER_NAME:
			// Host name form elements.
			case CONDITION_TYPE_HOST_NAME:
			// Host metadata form elements.
			case CONDITION_TYPE_HOST_METADATA:
			// Service name form elements.
			case CONDITION_TYPE_SERVICE_NAME:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_LIKE))->setModern(true);
				foreach ($operators_by_condition[$condition_type] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CTextAreaFlexible('value'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $new_condition_value->getPostJS();

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow((new CLabel(_('Value')))->setAsteriskMark(), $new_condition_value);
				break;

			// Event type form elements.
			case CONDITION_TYPE_EVENT_TYPE:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue($operators_by_condition[CONDITION_TYPE_EVENT_TYPE][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);

				$form_list
					->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					->addRow(new CLabel(_('Event type'), 'label-condition-event-type'), (new CSelect('value'))
						->setFocusableElementId('label-condition-event-type')
						->addOptions(CSelect::createOptionsFromArray(eventType()))
					);
				break;

			// Service form elements.
			case CONDITION_TYPE_SERVICE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[CONDITION_TYPE_SERVICE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$service_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'services',
					'custom_select' => true
				]))
					->setId('service-new-condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $service_multiselect->getPostJS().
					'$("#service-new-condition")
						.multiSelect("getSelectButton")
						.addEventListener("click", selectServices);';

				$form_list
					->addRow(_('Operator'), $operator)
					->addRow((new CLabel(_('Services')))->setAsteriskMark(), $service_multiselect);
				break;
		}
		break;

	case ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION:
		require_once dirname(__FILE__).'/../../include/actions.inc.php';

		// Collect all options for select.
		$condition_options = [];
		foreach ($data['allowed_conditions'] as $type) {
			$condition_options[$type] = condition_type2str($type);
		}

		// Type select.
		$form_list->addRow(new CLabel(_('Type'), 'label-condition-type'), (new CSelect('condition_type'))
			->setFocusableElementId('label-condition-type')
			->setValue($condition_type)
			->setId('condition-type')
			->addOptions(CSelect::createOptionsFromArray($condition_options))
		);

		$inline_js .= '$(() => $("#condition-type").on("change",'
			.'(e) => reloadPopup($(e.target).closest("form").get(0), "popup.condition.operations")));';

		// Acknowledge form elements.
		$operators_options = [];
		foreach (get_operators_by_conditiontype(CONDITION_TYPE_EVENT_ACKNOWLEDGED) as $type) {
			$operators_options[$type] = condition_operator2str($type);
		}

		$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
			->setModern(true)
			->addValue(condition_operator2str(CONDITION_OPERATOR_EQUAL), CONDITION_OPERATOR_EQUAL);

		$condition_value = (new CRadioButtonList('value', EVENT_NOT_ACKNOWLEDGED))
			->setModern(true)
			->addValue(_('No'), EVENT_NOT_ACKNOWLEDGED)
			->addValue(_('Yes'), EVENT_ACKNOWLEDGED);

		$form_list
			->addRow(_('Operator'), [$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
			->addRow(_('Acknowledged'), $condition_value);
		break;
}

$form->addItem([
	$form_list,
	(new CInput('submit', 'submit'))->addStyle('display: none;')
]);

$output = [
	'header' => $data['title'],
	'script_inline' => $inline_js,
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validateConditionPopup(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
