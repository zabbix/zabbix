<?php

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$inline_js = '';

$form = (new CForm())
	->cleanItems()
	->setId('popup.condition')
	->setName('popup.condition')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', 'popup.condition');

switch($data['condition_type']) {
	case '0':
		require_once dirname(__FILE__).'/../../include/correlation.inc.php';

		$popup_action = 'return conditionPopupSubmit(\'correlation.edit\');';

		// Collect all options for combobox.
		$combobox_options = [];
		foreach ([ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE] as $type) {
			foreach (getOperatorsByCorrConditionType($type) as $value) {
				$combobox_options[$type][$value] = corrConditionOperatorToString($value);
			}
		}

		$flex_container = (new CDiv())->addClass('condition-container');

		// Type select.
		$event_correlation_type_div = (new CDiv(new CComboBox(
			'new_condition[type]',
			null,
			'conditionFormSelector()',
			corrConditionTypes()
		)))->addClass('condition-column condition-column-select');

		$flex_container->addItem($event_correlation_type_div);

		// Old|New event tag form elements.
		$equel_span = new CSpan(corrConditionOperatorToString(
			getOperatorsByCorrConditionType(ZBX_CORR_CONDITION_OLD_EVENT_TAG)[0]
		));
		$tag_textarea = (new CTextAreaFlexible('new_condition[tag]'))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('placeholder', _('tag'));

		$flex_container->addItem([
			(new CDiv($equel_span))
				->setAttribute('data-type', ZBX_CORR_CONDITION_OLD_EVENT_TAG)
				->addClass('condition-column condition-column-active'),
			(new CDiv($tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_OLD_EVENT_TAG)
				->addClass('condition-column condition-column-active')
		]);

		$flex_container->addItem([
			(new CDiv($equel_span))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_TAG)
				->addClass('condition-column'),
			(new CDiv($tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_TAG)
				->addClass('condition-column')
		]);

		// New event host group form elements.
		$hostgroup_condition_select = new CComboBox(
			'new_condition[operator]',
			null,
			null,
			$combobox_options[ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP]
		);
		$hostgroup_multiselect = (new CMultiSelect([
			'name' => 'new_condition[groupids][]',
			'object_name' => 'hostGroup',
			'default_value' => 0,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'new_condition_groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$inline_js .= $hostgroup_multiselect->getPostJS();

		$flex_container->addItem([
			(new CDiv($hostgroup_condition_select))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP)
				->addClass('condition-column'),
			(new CDiv($hostgroup_multiselect))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP)
				->addClass('condition-column')
		]);

		// Event tag pairs form elements.
		$new_event_tag_textarea = (new CTextAreaFlexible('new_condition[newtag]'))
			->setAttribute('placeholder', _('new event tag'));
		$new_event_tag_span = new CSpan(corrConditionOperatorToString(
			getOperatorsByCorrConditionType(ZBX_CORR_CONDITION_EVENT_TAG_PAIR)[0]
		));
		$new_event_old_tag_textarea = (new CTextAreaFlexible('new_condition[oldtag]'))
			->setAttribute('placeholder', _('old event tag'));

		$flex_container->addItem([
			(new CDiv($new_event_tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_EVENT_TAG_PAIR)
				->addClass('condition-column'),
			(new CDiv($new_event_tag_span))
				->setAttribute('data-type', ZBX_CORR_CONDITION_EVENT_TAG_PAIR)
				->addClass('condition-column'),
			(new CDiv($new_event_old_tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_EVENT_TAG_PAIR)
				->addClass('condition-column')
		]);

		// Old|New event tag value form elements.
		$event_new_value_textarea = (new CTextAreaFlexible('new_condition[value]'))
			->setAttribute('placeholder', _('value'));
		$event_condition_select = new CComboBox(
			'new_condition[operator]',
			null,
			null,
			$combobox_options[ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE]
		);
		$event_new_tag_textarea = (new CTextAreaFlexible('new_condition[tag]'))->setAttribute('placeholder', _('tag'));

		$flex_container->addItem([
			(new CDiv($event_new_value_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE)
				->addClass('condition-column'),
			(new CDiv($event_condition_select))
				->setAttribute('data-type', ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE)
				->addClass('condition-column'),
			(new CDiv($event_new_tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE)
				->addClass('condition-column')
		]);

		$flex_container->addItem([
			(new CDiv($event_new_value_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE)
				->addClass('condition-column'),
			(new CDiv($event_condition_select))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE)
				->addClass('condition-column'),
			(new CDiv($event_new_tag_textarea))
				->setAttribute('data-type', ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE)
				->addClass('condition-column')
		]);

		$form->addItem($flex_container);
		break;
	case '1':
		require_once dirname(__FILE__).'/../../include/actions.inc.php';

		$popup_action = 'return conditionPopupSubmit(\'action.edit\');';

		// Collect all options for combobox.
		$action_condition_options = [];
		$combobox_options = [];
		foreach ($data['allowed_conditions'] as $type) {
			$action_condition_options[$type] = condition_type2str($type);
			foreach (get_operators_by_conditiontype($type) as $value) {
				$combobox_options[$type][$value] = condition_operator2str($value);
			}
		}

		$flex_container = (new CDiv())->addClass('condition-container');

		// Type select.
		$action_condition_type_div = (new CDiv(new CComboBox(
			'new_condition[conditiontype]',
			null,
			'conditionFormSelector()',
			$action_condition_options
		)))->addClass('condition-column condition-column-select');

		$flex_container->addItem($action_condition_type_div);

		// Trigger name form elements.
		if (in_array(CONDITION_TYPE_TRIGGER_NAME, $data['allowed_conditions'])) {
			$is_active = $data['allowed_conditions'][0] == CONDITION_TYPE_TRIGGER_NAME;

			$trig_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_TRIGGER_NAME]
			);
			$trig_name_textarea = (new CTextAreaFlexible('new_condition[value]', ''))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($trig_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER_NAME)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : '')),
				(new CDiv($trig_name_textarea))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER_NAME)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : ''))
			]);
		}

		// Trigger form elements.
		if (in_array(CONDITION_TYPE_TRIGGER, $data['allowed_conditions'])) {
			$trigger_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_TRIGGER]
			);
			$trigger_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value][]',
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
				->removeId()
				->setId('trigger_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $trigger_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($trigger_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER)
					->addClass('condition-column'),
				(new CDiv($trigger_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER)
					->addClass('condition-column')
			]);
		}

		// Trigger severity form elements.
		if (in_array(CONDITION_TYPE_TRIGGER_SEVERITY, $data['allowed_conditions'])) {
			$severityNames = [];
			for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
				$severityNames[] = getSeverityName($severity, $data['severities']);
			}

			$trigseverity_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_TRIGGER_SEVERITY]
			);
			$severity_select = new CComboBox('new_condition[value]', null, null, $severityNames);

			$flex_container->addItem([
				(new CDiv($trigseverity_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER_SEVERITY)
					->addClass('condition-column'),
				(new CDiv($severity_select))
					->setAttribute('data-type', CONDITION_TYPE_TRIGGER_SEVERITY)
					->addClass('condition-column')
			]);
		}

		// Application form elements.
		if (in_array(CONDITION_TYPE_APPLICATION, $data['allowed_conditions'])) {
			$is_active = $data['allowed_conditions'][0] == CONDITION_TYPE_APPLICATION;

			$app_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_APPLICATION]
			);
			$app_textarea = (new CTextAreaFlexible('new_condition[value]'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($app_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_APPLICATION)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : '')),
				(new CDiv($app_textarea))
					->setAttribute('data-type', CONDITION_TYPE_APPLICATION)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : ''))
			]);
		}

		// Host form elements.
		if (in_array(CONDITION_TYPE_HOST, $data['allowed_conditions'])) {
			$host_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_HOST]
			);
			$host_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value][]',
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
				->removeId()
				->setId('host_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $host_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($host_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_HOST)
					->addClass('condition-column'),
				(new CDiv($host_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_HOST)
					->addClass('condition-column')
			]);
		}

		// Host group form elements.
		if (in_array(CONDITION_TYPE_HOST_GROUP, $data['allowed_conditions'])) {
			$hostgroup_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_HOST_GROUP]
			);
			$hostgroup_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value][]',
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
				->removeId()
				->setId('hostgroup_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $hostgroup_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($hostgroup_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_HOST_GROUP)
					->addClass('condition-column'),
				(new CDiv($hostgroup_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_HOST_GROUP)
					->addClass('condition-column')
			]);
		}

		// Problem is supressed form elements.
		if (in_array(CONDITION_TYPE_SUPPRESSED, $data['allowed_conditions'])) {
			$problem_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_SUPPRESSED]
			);

			$flex_container->addItem([
				(new CDiv($problem_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_SUPPRESSED)
					->addClass('condition-column')
			]);
		}

		// Tag form elements.
		if (in_array(CONDITION_TYPE_EVENT_TAG, $data['allowed_conditions'])) {
			$tag_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_EVENT_TAG]
			);
			$tag_textarea = (new CTextAreaFlexible('new_condition[value]'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('placeholder', _('tag'));

			$flex_container->addItem([
				(new CDiv($tag_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TAG)
					->addClass('condition-column'),
				(new CDiv($tag_textarea))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TAG)
					->addClass('condition-column')
			]);
		}

		// Tag value form elements.
		if (in_array(CONDITION_TYPE_EVENT_TAG_VALUE, $data['allowed_conditions'])) {
			$tag_value_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_EVENT_TAG_VALUE]
			);
			$tag_value_textarea = (new CTextAreaFlexible('new_condition[value]'))
				->setAttribute('placeholder', _('value'));
			$tag_value_tag_textarea = (new CTextAreaFlexible('new_condition[value2]'))
				->setAttribute('placeholder', _('tag'));

			$flex_container->addItem([
				(new CDiv($tag_value_textarea))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TAG_VALUE)
					->addClass('condition-column'),
				(new CDiv($tag_value_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TAG_VALUE)
					->addClass('condition-column'),
				(new CDiv($tag_value_tag_textarea))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TAG_VALUE)
					->addClass('condition-column')
			]);
		}

		// Template form elements.
		if (in_array(CONDITION_TYPE_TEMPLATE, $data['allowed_conditions'])) {
			$template_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_TEMPLATE]
			);
			$template_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value][]',
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
				->removeId()
				->setId('template_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $template_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($template_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_TEMPLATE)
					->addClass('condition-column'),
				(new CDiv($template_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_TEMPLATE)
					->addClass('condition-column')
			]);
		}

		// Time period form elements.
		if (in_array(CONDITION_TYPE_TIME_PERIOD, $data['allowed_conditions'])) {
			$time_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_TIME_PERIOD]
			);
			$time_textbox = (new CTextBox('new_condition[value]', ZBX_DEFAULT_INTERVAL))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($time_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_TIME_PERIOD)
					->addClass('condition-column'),
				(new CDiv($time_textbox))
					->setAttribute('data-type', CONDITION_TYPE_TIME_PERIOD)
					->addClass('condition-column')
			]);
		}

		// Discovery host ip form elements.
		if (in_array(CONDITION_TYPE_DHOST_IP, $data['allowed_conditions'])) {
			$is_active = $data['allowed_conditions'][0] == CONDITION_TYPE_DHOST_IP;

			$hostip_condition_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DHOST_IP]
			);
			$hostip_textbox = (new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($hostip_condition_select))
					->setAttribute('data-type', CONDITION_TYPE_DHOST_IP)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : '')),
				(new CDiv($hostip_textbox))
					->setAttribute('data-type', CONDITION_TYPE_DHOST_IP)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : ''))
			]);
		}

		// Discovery check form elements.
		if (in_array(CONDITION_TYPE_DCHECK, $data['allowed_conditions'])) {
			$dcheck_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DCHECK]
			);
			$dcheck_popup_select = [
				(new CInput('hidden', 'new_condition[value]', '0'))
					->removeId()
					->setId('dcheck_new_condition_value'),
				(new CTextBox('dcheck', '', true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('btn1', _('Select')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('return PopUp("popup.generic",'.
						CJs::encodeJson([
							'srctbl' => 'dchecks',
							'srcfld1' => 'dcheckid',
							'srcfld2' => 'name',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'dcheck_new_condition_value',
							'dstfld2' => 'dcheck',
							'writeonly' => '1'
						]).', null, this);'
					)
			];

			$flex_container->addItem([
				(new CDiv($dcheck_select))
					->setAttribute('data-type', CONDITION_TYPE_DCHECK)
					->addClass('condition-column'),
				(new CDiv($dcheck_popup_select))
					->setAttribute('data-type', CONDITION_TYPE_DCHECK)
					->addClass('condition-column')
			]);
		}

		// Discovery object form elements.
		if (in_array(CONDITION_TYPE_DOBJECT, $data['allowed_conditions'])) {
			$dobject_options = [];
			foreach ([EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE] as $object) {
				$dobject_options[$object] = discovery_object2str($object);
			}

			$dobject_span = new CSpan($combobox_options[CONDITION_TYPE_DOBJECT][CONDITION_OPERATOR_EQUAL]);
			$dobject_value_select = new CComboBox('new_condition[value]', null, null, $dobject_options);

			$flex_container->addItem([
				(new CDiv($dobject_span))
					->setAttribute('data-type', CONDITION_TYPE_DOBJECT)
					->addClass('condition-column'),
				(new CDiv($dobject_value_select))
					->setAttribute('data-type', CONDITION_TYPE_DOBJECT)
					->addClass('condition-column')
			]);
		}

		// Discovery rule form elements.
		if (in_array(CONDITION_TYPE_DRULE, $data['allowed_conditions'])) {
			$drule_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DRULE]
			);
			$drule_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value][]',
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
				->removeId()
				->setId('drule_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $drule_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($drule_select))
					->setAttribute('data-type', CONDITION_TYPE_DRULE)
					->addClass('condition-column'),
				(new CDiv($drule_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_DRULE)
					->addClass('condition-column')
			]);
		}

		// Discovery status form elements.
		if (in_array(CONDITION_TYPE_DSTATUS, $data['allowed_conditions'])) {
			$dstatus_options = [];
			foreach ([DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER, DOBJECT_STATUS_LOST] as $stat) {
				$dstatus_options[$stat] = discovery_object_status2str($stat);
			}
			$dstatus_span = new CSpan($combobox_options[CONDITION_TYPE_DSTATUS][CONDITION_OPERATOR_EQUAL]);
			$dstatus_select = new CComboBox('new_condition[value]', null, null, $dstatus_options);

			$flex_container->addItem([
				(new CDiv($dstatus_span))
					->setAttribute('data-type', CONDITION_TYPE_DSTATUS)
					->addClass('condition-column'),
				(new CDiv($dstatus_select))
					->setAttribute('data-type', CONDITION_TYPE_DSTATUS)
					->addClass('condition-column')
			]);
		}

		// Proxy form elements.
		if (in_array(CONDITION_TYPE_PROXY, $data['allowed_conditions'])) {
			$proxy_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_PROXY]
			);
			$proxy_multiselect = (new CMultiSelect([
				'name' => 'new_condition[value]',
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
				->removeId()
				->setId('proxy_new_condition')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$inline_js .= $proxy_multiselect->getPostJS();

			$flex_container->addItem([
				(new CDiv($proxy_select))
					->setAttribute('data-type', CONDITION_TYPE_PROXY)
					->addClass('condition-column'),
				(new CDiv($proxy_multiselect))
					->setAttribute('data-type', CONDITION_TYPE_PROXY)
					->addClass('condition-column')
			]);
		}

		// Received value form elements.
		if (in_array(CONDITION_TYPE_DVALUE, $data['allowed_conditions'])) {
			$dvalue_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DVALUE]
			);
			$dvalue_textarea = (new CTextAreaFlexible('new_condition[value]'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($dvalue_select))
					->setAttribute('data-type', CONDITION_TYPE_DVALUE)
					->addClass('condition-column'),
				(new CDiv($dvalue_textarea))
					->setAttribute('data-type', CONDITION_TYPE_DVALUE)
					->addClass('condition-column')
			]);
		}

		// Service port form elements.
		if (in_array(CONDITION_TYPE_DSERVICE_PORT, $data['allowed_conditions'])) {
			$dservice_port_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DSERVICE_PORT]
			);
			$dservice_port_textbox = (new CTextBox('new_condition[value]', '0-1023,1024-49151'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($dservice_port_select))
					->setAttribute('data-type', CONDITION_TYPE_DSERVICE_PORT)
					->addClass('condition-column'),
				(new CDiv($dservice_port_textbox))
					->setAttribute('data-type', CONDITION_TYPE_DSERVICE_PORT)
					->addClass('condition-column')
			]);
		}

		// Service type form elements.
		if (in_array(CONDITION_TYPE_DSERVICE_TYPE, $data['allowed_conditions'])) {
			$dservice_type_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DSERVICE_TYPE]
			);
			$discoveryCheckTypes = discovery_check_type2str();
			order_result($discoveryCheckTypes);

			$dservice_types = new CComboBox('new_condition[value]', null, null, $discoveryCheckTypes);

			$flex_container->addItem([
				(new CDiv($dservice_type_select))
					->setAttribute('data-type', CONDITION_TYPE_DSERVICE_TYPE)
					->addClass('condition-column'),
				(new CDiv($dservice_types))
					->setAttribute('data-type', CONDITION_TYPE_DSERVICE_TYPE)
					->addClass('condition-column')
			]);
		}

		// Discovery uptime|downtime form elements.
		if (in_array(CONDITION_TYPE_DUPTIME, $data['allowed_conditions'])) {
			$duptime_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_DUPTIME]
			);
			$duptime_numericbox = (new CNumericBox('new_condition[value]', 600, 15))
				->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);

			$flex_container->addItem([
				(new CDiv($duptime_select))
					->setAttribute('data-type', CONDITION_TYPE_DUPTIME)
					->addClass('condition-column'),
				(new CDiv($duptime_numericbox))
					->setAttribute('data-type', CONDITION_TYPE_DUPTIME)
					->addClass('condition-column')
			]);
		}

		// Host name form elements.
		if (in_array(CONDITION_TYPE_HOST_NAME, $data['allowed_conditions'])) {
			$is_active = $data['allowed_conditions'][0] == CONDITION_TYPE_HOST_NAME;

			$hostname_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_HOST_NAME]
			);
			$hostname_textarea = (new CTextAreaFlexible('new_condition[value]'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($hostname_select))
					->setAttribute('data-type', CONDITION_TYPE_HOST_NAME)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : '')),
				(new CDiv($hostname_textarea))
					->setAttribute('data-type', CONDITION_TYPE_HOST_NAME)
					->addClass('condition-column '.($is_active ? 'condition-column-active' : ''))
			]);
		}

		// Host metadata form elements.
		if (in_array(CONDITION_TYPE_HOST_METADATA, $data['allowed_conditions'])) {
			$host_metadata_select = new CComboBox(
				'new_condition[operator]',
				null,
				null,
				$combobox_options[CONDITION_TYPE_HOST_METADATA]
			);
			$host_metadata_textarea = (new CTextAreaFlexible('new_condition[value]'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

			$flex_container->addItem([
				(new CDiv($host_metadata_select))
					->setAttribute('data-type', CONDITION_TYPE_HOST_METADATA)
					->addClass('condition-column'),
				(new CDiv($host_metadata_textarea))
					->setAttribute('data-type', CONDITION_TYPE_HOST_METADATA)
					->addClass('condition-column')
			]);
		}

		// Event type form elements.
		if (in_array(CONDITION_TYPE_EVENT_TYPE, $data['allowed_conditions'])) {
			$event_type_span = new CSpan($combobox_options[CONDITION_TYPE_EVENT_TYPE][CONDITION_OPERATOR_EQUAL]);
			$event_type_select = new CComboBox('new_condition[value]', null, null, eventType());

			$flex_container->addItem([
				(new CDiv($event_type_span))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TYPE)
					->addClass('condition-column'),
				(new CDiv($event_type_select))
					->setAttribute('data-type', CONDITION_TYPE_EVENT_TYPE)
					->addClass('condition-column')
			]);
		}

		$form->addItem($flex_container);
		break;
}

$output = [
	'header' => $data['title'],
	'script_inline' => [require 'app/views/popup.condition.js.php', $inline_js],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => $popup_action
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
