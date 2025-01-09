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

$inline_js = getPagePostJs().$this->readJsFile('popup.condition.edit.js.php');

$form = (new CForm())
	->setId('popup.condition')
	->setName('popup.condition')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('row_index', $data['row_index'] ? $data['row_index'] : 0)
	->addVar('type', $data['type']);

if ($data['type'] == ZBX_POPUP_CONDITION_TYPE_ACTION) {
	$form->addVar('source', $data['eventsource']);
}
elseif ($data['type'] == ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION) {
	$form->addVar('source', $data['source']);
}

$condition_type = (int) $data['last_type'];
$form_grid = (new CFormGrid());

switch ($data['type']) {
	case ZBX_POPUP_CONDITION_TYPE_ACTION:
		require_once __DIR__ .'/../../include/actions.inc.php';

		// Collect all operators options.
		$operators_by_condition = [];
		$action_conditions = [];
		foreach ($data['allowed_conditions'] as $type) {
			if ($data['eventsource'] == EVENT_SOURCE_SERVICE && $type == ZBX_CONDITION_TYPE_EVENT_TAG) {
				$action_conditions[$type] = _('Service tag name');
			}
			elseif ($data['eventsource'] == EVENT_SOURCE_SERVICE && $type == ZBX_CONDITION_TYPE_EVENT_TAG_VALUE) {
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
		$form_grid
			->addItem([
				new CLabel(_('Type'), 'label-condition-type'),
				new CFormField((new CSelect('condition_type'))
					->setFocusableElementId('label-condition-type')
					->setValue($condition_type)
					->setId('condition-type')
					->addOptions(CSelect::createOptionsFromArray($action_conditions))
				)
			]);

		switch ($condition_type) {
			// Trigger form elements.
			case ZBX_CONDITION_TYPE_TRIGGER:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_TRIGGER] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$trigger_multiselect = $data['trigger_context'] === 'host'
					? (new CMultiSelect([
						'name' => 'value[]',
						'object_name' => 'triggers',
						'default_value' => 0,
						'popup' => [
							'parameters' => [
								'srctbl' => 'triggers',
								'srcfld1' => 'triggerid',
								'dstfrm' => $form->getName(),
								'dstfld1' => 'trigger_new_condition',
								'with_triggers' => true,
								'real_hosts' => true
							]
						]
					]))
						->setId('trigger_new_condition')
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					: (new CMultiSelect([
						'name' => 'value[]',
						'object_name' => 'triggers',
						'default_value' => 0,
						'popup' => [
							'parameters' => [
								'srctbl' => 'template_triggers',
								'srcfld1' => 'triggerid',
								'dstfrm' => $form->getName(),
								'dstfld1' => 'trigger_new_condition',
								'with_triggers' => true
							]
						]
					]))
						->setId('trigger_new_condition')
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $trigger_multiselect->getPostJS();

				$form_grid
					->addItem([new CLabel(_('Operator')), new CFormField($operator)])
					->addItem([
						new CLabel(_('Trigger source')),
						new CFormField((new CRadioButtonList('trigger_context', $data['trigger_context']))
							->addValue(_('Host'), 'host')
							->addValue(_('Template'), 'template')
							->setModern(true))
					])
					->addItem([
						(new CLabel(_('Triggers'), 'trigger_new_condition_ms'))->setAsteriskMark(),
						new CFormField($trigger_multiselect)
					]);

				break;

			// Trigger severity form elements.
			case ZBX_CONDITION_TYPE_TRIGGER_SEVERITY:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_TRIGGER_SEVERITY] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						new CLabel(_('Severity')),
						new CFormField(new CSeverity('value', TRIGGER_SEVERITY_NOT_CLASSIFIED))
					]);

				break;

			// Host form elements.
			case ZBX_CONDITION_TYPE_HOST:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_HOST] as $key => $value) {
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
							'dstfld1' => 'host_new_condition'
						]
					]
				]))
					->setId('host_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $host_multiselect->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Hosts'), 'host_new_condition_ms'))->setAsteriskMark(),
						new CFormField($host_multiselect)
					]);

				break;

			// Host group form elements.
			case ZBX_CONDITION_TYPE_HOST_GROUP:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_HOST_GROUP] as $key => $value) {
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
							'dstfld1' => 'hostgroup_new_condition'
						]
					]
				]))
					->setId('hostgroup_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $hostgroup_multiselect->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Host groups'), 'hostgroup_new_condition_ms'))->setAsteriskMark(),
						new CFormField($hostgroup_multiselect)
					]);

				break;

			// Problem is suppressed form elements.
			case ZBX_CONDITION_TYPE_SUPPRESSED:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_NO))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_SUPPRESSED] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$form_grid->addItem([
					new CLabel(_('Operator')),
					new CFormField($operator)
				]);

				break;

			// Tag form elements.
			case ZBX_CONDITION_TYPE_EVENT_TAG:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_EVENT_TAG] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CTextAreaFlexible('value'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$inline_js .= $new_condition_value->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Tag'), 'value'))->setAsteriskMark(),
						new CFormField($new_condition_value)
					]);

				break;

			// Tag value form elements.
			case ZBX_CONDITION_TYPE_EVENT_TAG_VALUE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_EVENT_TAG_VALUE] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value2 = (new CTextAreaFlexible('value2'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value2');
				$new_condition_value = (new CTextAreaFlexible('value'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$inline_js .= $new_condition_value2->getPostJS();
				$inline_js .= $new_condition_value->getPostJS();

				$form_grid
					->addItem([
						(new CLabel(_('Tag'), 'value2'))->setAsteriskMark(),
						new CFormField($new_condition_value2)
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

			// Template form elements.
			case ZBX_CONDITION_TYPE_TEMPLATE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_TEMPLATE] as $key => $value) {
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
							'dstfld1' => 'template_new_condition'
						]
					]
				]))
					->setId('template_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $template_multiselect->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Templates'), 'template_new_condition_ms'))->setAsteriskMark(),
						new CFormField($template_multiselect)
					]);

				break;

			// Time period form elements.
			case ZBX_CONDITION_TYPE_TIME_PERIOD:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_IN))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_TIME_PERIOD] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', ZBX_DEFAULT_INTERVAL))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Value'), 'value'))->setAsteriskMark(),
						new CFormField($new_condition_value)
					]);

				break;

			// Discovery host ip form elements.
			case ZBX_CONDITION_TYPE_DHOST_IP:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DHOST_IP] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', '192.168.0.1-127,192.168.2.1'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Value'), 'value'))->setAsteriskMark(),
						new CFormField($new_condition_value)
					]);

				break;

			// Discovery check form elements.
			case ZBX_CONDITION_TYPE_DCHECK:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DCHECK] as $key => $value) {
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
							'return PopUp("popup.generic", '.json_encode([
								'srctbl' => 'dchecks',
								'srcfld1' => 'dcheckid',
								'srcfld2' => 'name',
								'dstfrm' => $form->getName(),
								'dstfld1' => 'dcheck_new_condition_value',
								'dstfld2' => 'dcheck'
							], JSON_THROW_ON_ERROR).', {dialogue_class: "modal-popup-generic"});'
						)
				];

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Discovery check')))->setAsteriskMark(),
						new CFormField($dcheck_popup_select)
					]);

				break;

			// Discovery object form elements.
			case ZBX_CONDITION_TYPE_DOBJECT:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(
						$operators_by_condition[ZBX_CONDITION_TYPE_DOBJECT][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);
				$new_condition_value = (new CRadioButtonList('value', EVENT_OBJECT_DHOST))
					->setModern(true)
					->addValue(discovery_object2str(EVENT_OBJECT_DHOST), EVENT_OBJECT_DHOST)
					->addValue(discovery_object2str(EVENT_OBJECT_DSERVICE), EVENT_OBJECT_DSERVICE);

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					])
					->addItem([
						new CLabel(_('Discovery object')),
						new CFormField($new_condition_value)
					]);

				break;

			// Discovery rule form elements.
			case ZBX_CONDITION_TYPE_DRULE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DRULE] as $key => $value) {
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

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Discovery rules'), 'drule_new_condition_ms'))->setAsteriskMark(),
						new CFormField($drule_multiselect)
					]);

				break;

			// Discovery status form elements.
			case ZBX_CONDITION_TYPE_DSTATUS:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue(
						$operators_by_condition[ZBX_CONDITION_TYPE_DSTATUS][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);
				$new_condition_value = (new CRadioButtonList('value', DOBJECT_STATUS_UP))
					->setModern(true)
					->addValue(discovery_object_status2str(DOBJECT_STATUS_UP), DOBJECT_STATUS_UP)
					->addValue(discovery_object_status2str(DOBJECT_STATUS_DOWN), DOBJECT_STATUS_DOWN)
					->addValue(discovery_object_status2str(DOBJECT_STATUS_DISCOVER), DOBJECT_STATUS_DISCOVER)
					->addValue(discovery_object_status2str(DOBJECT_STATUS_LOST), DOBJECT_STATUS_LOST);

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					])
					->addItem([
						new CLabel(_('Discovery status')),
						new CFormField($new_condition_value)
					]);

				break;

			// Proxy form elements.
			case ZBX_CONDITION_TYPE_PROXY:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_PROXY] as $key => $value) {
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
							'srcfld2' => 'name',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'proxy_new_condition'
						]
					]
				]))
					->setId('proxy_new_condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $proxy_multiselect->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Proxy'), 'proxy_new_condition_ms'))->setAsteriskMark(),
						new CFormField($proxy_multiselect)
					]);

				break;

			// Received value form elements.
			case ZBX_CONDITION_TYPE_DVALUE:
				$new_condition_value = (new CTextAreaFlexible('value'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$inline_js .= $new_condition_value->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator'), 'label-operator'),
						new CFormField(
							(new CSelect('operator'))
								->setValue(CONDITION_OPERATOR_EQUAL)
								->setFocusableElementId('label-operator')
								->addOptions(
									CSelect::createOptionsFromArray($operators_by_condition[ZBX_CONDITION_TYPE_DVALUE])
								)
						)
					])
					->addItem([
						new CLabel(_('Value'), 'value'),
						new CFormField($new_condition_value)
					]);

				break;

			// Service port form elements.
			case ZBX_CONDITION_TYPE_DSERVICE_PORT:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DSERVICE_PORT] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$new_condition_value = (new CTextBox('value', '0-1023,1024-49151'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Value'), 'value'))->setAsteriskMark(),
						new CFormField($new_condition_value)
					]);

				break;

			// Service type form elements.
			case ZBX_CONDITION_TYPE_DSERVICE_TYPE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DSERVICE_TYPE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$discovery_check_types = discovery_check_type2str();
				order_result($discovery_check_types);

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						new CLabel(_('Service type'), 'label-condition-service-type'),
						new CFormField((new CSelect('value'))
							->setFocusableElementId('label-condition-service-type')
							->addOptions(CSelect::createOptionsFromArray($discovery_check_types))
						)
					]);

				break;

			// Discovery uptime|downtime form elements.
			case ZBX_CONDITION_TYPE_DUPTIME:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_MORE_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_DUPTIME] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CNumericBox('value', 600, 7))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH)
					->setId('value');

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						new CLabel(_('Value'), 'value'),
						new CFormField($new_condition_value)
					]);

				break;

			// Event name form elements.
			case ZBX_CONDITION_TYPE_EVENT_NAME:
			// Host name form elements.
			case ZBX_CONDITION_TYPE_HOST_NAME:
			// Host metadata form elements.
			case ZBX_CONDITION_TYPE_HOST_METADATA:
			// Service name form elements.
			case ZBX_CONDITION_TYPE_SERVICE_NAME:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_LIKE))->setModern(true);
				foreach ($operators_by_condition[$condition_type] as $key => $value) {
					$operator->addValue($value, $key);
				}
				$new_condition_value = (new CTextAreaFlexible('value'))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('value');

				$inline_js .= $new_condition_value->getPostJS();

				$help_icon = $condition_type == ZBX_CONDITION_TYPE_EVENT_NAME
					? makeHelpIcon(_('Event name matches Trigger name (with macros expanded) unless a custom Event name is specified in Trigger settings.'))
					: null;

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel([_('Value'), $help_icon], 'value'))->setAsteriskMark(),
						new CFormField($new_condition_value)
					]);

				break;

			// Event type form elements.
			case ZBX_CONDITION_TYPE_EVENT_TYPE:
				$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
					->setModern(true)
					->addValue($operators_by_condition[ZBX_CONDITION_TYPE_EVENT_TYPE][CONDITION_OPERATOR_EQUAL],
						CONDITION_OPERATOR_EQUAL
					);

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
					])
					->addItem([
						new CLabel(_('Event type'), 'label-condition-event-type'),
						new CFormField((new CSelect('value'))
							->setFocusableElementId('label-condition-event-type')
							->addOptions(CSelect::createOptionsFromArray(eventType()))
						)
					]);

				break;

			// Service form elements.
			case ZBX_CONDITION_TYPE_SERVICE:
				$operator = (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))->setModern(true);
				foreach ($operators_by_condition[ZBX_CONDITION_TYPE_SERVICE] as $key => $value) {
					$operator->addValue($value, $key);
				}

				$service_multiselect = (new CMultiSelect([
					'name' => 'value[]',
					'object_name' => 'services',
					'custom_select' => true
				]))
					->setId('service-new-condition')
					->addClass('new-condition')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

				$inline_js .= $service_multiselect->getPostJS();

				$form_grid
					->addItem([
						new CLabel(_('Operator')),
						new CFormField($operator)
					])
					->addItem([
						(new CLabel(_('Services'), 'service-new-condition_ms'))->setAsteriskMark(),
						new CFormField($service_multiselect)
					]);

				break;
		}
		break;

	case ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION:
		require_once __DIR__.'/../../include/actions.inc.php';

		// Collect all options for select.
		$condition_options = [];
		foreach ($data['allowed_conditions'] as $type) {
			$condition_options[$type] = condition_type2str($type);
		}

		// Type select.
		$form_grid
			->addItem([
				new CLabel(_('Type'), 'label-condition-type'),
				new CFormField((new CSelect('condition_type'))
					->setFocusableElementId('label-condition-type')
					->setValue($condition_type)
					->setId('condition-type')
					->addOptions(CSelect::createOptionsFromArray($condition_options))
				)
			]);

		// Acknowledge form elements.
		$operators_options = [];
		foreach (get_operators_by_conditiontype(ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED) as $type) {
			$operators_options[$type] = condition_operator2str($type);
		}

		$operator = (new CRadioButtonList('', CONDITION_OPERATOR_EQUAL))
			->setModern(true)
			->addValue(condition_operator2str(CONDITION_OPERATOR_EQUAL), CONDITION_OPERATOR_EQUAL);

		$condition_value = (new CRadioButtonList('value', EVENT_NOT_ACKNOWLEDGED))
			->setModern(true)
			->addValue(_('No'), EVENT_NOT_ACKNOWLEDGED)
			->addValue(_('Yes'), EVENT_ACKNOWLEDGED);

		$form_grid
			->addItem([
				new CLabel(_('Operator')),
				new CFormField([$operator, new CVar('operator', CONDITION_OPERATOR_EQUAL)])
			])
			->addItem([
				new CLabel(_('Acknowledged')),
				new CFormField($condition_value)
			]);

		break;
}

$form->addItem($form_grid);

$output = [
	'header' => $data['title'],
	'script_inline' => $inline_js.'condition_popup.init();',
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'condition_popup.submit()'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
