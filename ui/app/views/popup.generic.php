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
 * @var array $data
 */

$output = [
	'header' => $data['title'],
	'body' => '',
	'controls' => '',
	'script_inline' => '',
	'buttons' => null
];

$options = $data['options'];
$controls = [];
$form = null;
$script_inline = '';

// Construct table header.
$header_form = ($data['popup_type'] === 'help_items') ? (new CForm())->cleanItems() : new CDiv();
$header_form->setId('generic-popup-form');

// Add host group multiselect control.
if (array_key_exists('groups', $data['filter'])) {
	$multiselect_options = $data['filter']['groups'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$hostgroup_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	$controls[] = (new CFormList())->addRow(new CLabel(_('Host group'), 'popup_host_group_ms'), $hostgroup_ms);

	$script_inline .= $hostgroup_ms->getPostJS(). 'popup_generic.initGroupsFilter();';
}

// Add template group multiselect control.
if (array_key_exists('templategroups', $data['filter'])) {
	$multiselect_options = $data['filter']['templategroups'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$templategroup_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	$controls[] = (new CFormList())
		->addRow(new CLabel(_('Template group'), 'popup_template_group_ms'), $templategroup_ms);

	$script_inline .= $templategroup_ms->getPostJS(). 'popup_generic.initTemplategroupsFilter();';
}

// Add host multiselect.
if (array_key_exists('hosts', $data['filter'])) {
	$multiselect_options = $data['filter']['hosts'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$host_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	if ($multiselect_options['disabled']) {
		$host_ms->setTitle(_('You cannot switch hosts for current selection.'));
	}
	$controls[] = (new CFormList())->addRow(new CLabel(_('Host'), 'popup_host_ms'), $host_ms);

	$script_inline .= $host_ms->getPostJS(). 'popup_generic.initHostsFilter();';
}

// Add template multiselect.
if (array_key_exists('templates', $data['filter'])) {
	$multiselect_options = $data['filter']['templates'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$template_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	if ($multiselect_options['disabled']) {
		$template_ms->setTitle(_('You cannot switch templates for current selection.'));
	}
	$controls[] = (new CFormList())->addRow(new CLabel(_('Template'), 'popup_template_ms'), $template_ms);

	$script_inline .= $template_ms->getPostJS(). 'popup_generic.initTemplatesFilter();';
}

// Show Type dropdown in header for help items.
if ($data['popup_type'] === 'help_items') {
	$types_select = (new CSelect('itemtype'))
		->setId('itemtype')
		->setFocusableElementId('label-itemtype')
		->setAttribute('autofocus', 'autofocus')
		->setValue($options['itemtype']);

	$script_inline .= 'popup_generic.initHelpItems();';

	$header_form
		->addVar('srctbl', $data['popup_type'])
		->addVar('srcfld1', $options['srcfld1'])
		->addVar('dstfrm', $options['dstfrm'])
		->addVar('dstfld1', $options['dstfld1']);

	foreach (CControllerPopupGeneric::ALLOWED_ITEM_TYPES as $type) {
		$types_select->addOption(new CSelectOption($type, item_type2str($type)));
	}

	$controls[] = [
		new CLabel(_('Type'), $types_select->getFocusableElementId()),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$types_select
	];
}

if ($controls) {
	$header_form->addItem($controls);
	$output['controls'] = $header_form->toString();
}

// Create form.
if ($data['form']) {
	$form = (new CForm())
		->cleanItems()
		->setName($data['form']['name'])
		->setId($data['form']['id']);
}

$table_columns = [];

if ($data['multiselect'] && $form !== null) {
	$ch_box = (new CColHeader(
		(new CCheckBox('all_records'))->onClick("checkAll('".$form->getName()."', 'all_records', 'item');")
	))->addClass(ZBX_STYLE_CELL_WIDTH);

	$table_columns[] = $ch_box;
}

$table = (new CTableInfo())->setHeader(array_merge($table_columns, $data['table_columns']));

if ($data['preselect_required']) {
	$table->setNoDataMessage(_('Specify some filter condition to see the values.'));
}

// Output table rows.
switch ($data['popup_type']) {
	case 'hosts':
	case 'template_groups':
	case 'host_groups':
	case 'proxies':
	case 'host_templates':
	case 'templates':
	case 'drules':
	case 'roles':
	case 'api_methods':
	case 'dashboard':
		foreach ($data['table_records'] as $item) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$item['id'].']', $item['id'])
				: null;

			if (array_key_exists('_disabled', $item)) {
				if ($data['multiselect']) {
					$check_box->setChecked(1);
					$check_box->setEnabled(false);
				}
				$name = $item['name'];

				unset($data['table_records'][$item['id']]);
			}
			else {
				$name = (new CLink($item['name']))
					->setId('spanid'.$item['id'])
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-itemid', $item['id'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.itemid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					');
			}

			$table->addRow([$check_box, $name]);
		}
		break;

	case 'users':
		foreach ($data['table_records'] as &$user) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$user['userid'].']', $user['userid'])
				: null;

			$username = (new CLink($user['username']))
				->setId('spanid'.$user['userid'])
				->setAttribute('data-reference', $options['reference'])
				->setAttribute('data-userid', $user['userid'])
				->setAttribute('data-parentid', $options['parentid'])
				->onClick('
					addValue(this.dataset.reference, this.dataset.userid, this.dataset.parentid ?? null);
					popup_generic.closePopup(event);
				');

			$table->addRow([$check_box, $username, $user['name'], $user['surname']]);

			$entry = [];
			$srcfld1 = $options['srcfld1'];
			if ($srcfld1 === 'userid') {
				$entry['id'] = $user['userid'];
			}
			elseif ($srcfld1 === 'username') {
				$entry['name'] = $user['username'];
			}

			$srcfld2 = $options['srcfld2'];
			if ($srcfld2 === 'fullname') {
				$entry['name'] = getUserFullname($user);
			}
			elseif (array_key_exists($srcfld2, $user)) {
				$entry[$srcfld2] = $user[$srcfld2];
			}

			$user = $entry;
		}
		unset($user);
		break;

	case 'usrgrp':
		foreach ($data['table_records'] as &$item) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$item['usrgrpid'].']', $item['usrgrpid'])
				: null;

			$name = (new CLink($item['name']))
				->setId('spanid'.$item['usrgrpid'])
				->setAttribute('data-reference', $options['reference'])
				->setAttribute('data-usrgrpid', $item['usrgrpid'])
				->setAttribute('data-parentid', $options['parentid'])
				->onClick('
					addValue(this.dataset.reference, this.dataset.usrgrpid, this.dataset.parentid ?? null);
					popup_generic.closePopup(event);
				');

			$table->addRow([$check_box, $name]);

			$item['id'] = $item['usrgrpid'];
		}
		unset($item);
		break;

	case 'triggers':
	case 'template_triggers':
	case 'trigger_prototypes':
		foreach ($data['table_records'] as &$trigger) {
			$host = reset($trigger['hosts']);
			$trigger['hostname'] = $host['name'];

			$description = new CLink($trigger['description']);
			$trigger['description'] = $trigger['hostname'].NAME_DELIMITER.$trigger['description'];

			$check_box = $data['multiselect']
				? new CCheckBox('item['.zbx_jsValue($trigger[$options['srcfld1']]).']', $trigger['triggerid'])
				: null;

			if ($data['multiselect']) {
				$description
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-triggerid', $trigger['triggerid'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.triggerid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					');
			}
			else {
				$values = [];

				if ($options['dstfld1'] !== '' && $options['srcfld1'] !== '') {
					$values[$options['dstfld1']] = $trigger[$options['srcfld1']];
				}
				if ($options['dstfld2'] !== '' && $options['srcfld2'] !== '') {
					$values[$options['dstfld2']] = $trigger[$options['srcfld2']];
				}
				if ($options['dstfld3'] !== '' && $options['srcfld3'] !== '') {
					$values[$options['dstfld3']] = $trigger[$options['srcfld3']];
				}

				$description
					->setAttribute('data-dstfrm', $options['dstfrm'])
					->setAttribute('data-values', json_encode($values))
					->onClick('
						addValues(this.dataset.dstfrm, JSON.parse(this.dataset.values));
						popup_generic.closePopup(event);
					');
			}

			if ($trigger['dependencies']) {
				$description = [$description, BR(), bold(_('Depends on')), BR()];

				$dependencies = CMacrosResolverHelper::resolveTriggerNames(
					zbx_toHash($trigger['dependencies'], 'triggerid')
				);

				foreach ($dependencies as $dependency) {
					$description[] = $dependency['description'];
					$description[] = BR();
				}
				array_pop($description);
			}

			$table->addRow([
				$check_box,
				$description,
				CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
				(new CSpan(triggerIndicator($trigger['status'], $trigger['state'])))
					->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
			]);

			if ($data['multiselect']) {
				$trigger = [
					'id' => $trigger['triggerid'],
					'name' => $trigger['description'],
					'triggerid' => $trigger['triggerid'],
					'description' => $trigger['description'],
					'expression' => $trigger['expression'],
					'priority' => $trigger['priority'],
					'status' => $trigger['status'],
					'host' => $trigger['hostname']
				];
			}
		}
		unset($trigger);
		break;

	case 'sysmaps':
		foreach ($data['table_records'] as $sysmap) {
			if ($data['multiselect']) {
				$check_box = new CCheckBox('item['.$sysmap['sysmapid'].']', $sysmap['sysmapid']);
			}

			$name = (new CLink($sysmap['name']))->setId('spanid'.$sysmap['sysmapid']);

			if ($data['multiselect']) {
				$name
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-sysmapid', $sysmap['sysmapid'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.sysmapid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					');
			}
			else {
				$values = [];

				if ($options['dstfld1'] !== '' && $options['srcfld1'] !== '') {
					$values[$options['dstfld1']] = $sysmap[$options['srcfld1']];
				}
				if ($options['dstfld2'] !== '' && $options['srcfld2'] !== '') {
					$values[$options['dstfld2']] = $sysmap[$options['srcfld2']];
				}
				if ($options['dstfld3'] !== '' && $options['srcfld3'] !== '') {
					$values[$options['dstfld3']] = $sysmap[$options['srcfld3']];
				}

				$name
					->setAttribute('data-dstfrm', $options['dstfrm'])
					->setAttribute('data-values', json_encode($values))
					->onClick('
						addValues(this.dataset.dstfrm, JSON.parse(this.dataset.values));
						popup_generic.closePopup(event);
					');
			}

			$table->addRow([$data['multiselect'] ? $check_box : null, $name]);
		}
		break;

	case 'help_items':
		foreach ($data['table_records'] as $key => $item) {
			$item['key'] = $key;

			$values = [
				$options['dstfld1'] => $item[$options['srcfld1']]
			];

			if ($options['dstfld2'] !== '' && $options['srcfld2'] !== '') {
				$values[$options['dstfld2']] = $item[$options['srcfld2']];
			}

			$name = (new CLink($key))
				->setAttribute('data-dstfld1', $options['dstfld1'])
				->setAttribute('data-dstfld2', $options['dstfld2'])
				->setAttribute('data-values', json_encode($values))
				->onClick('
					const values = JSON.parse(this.dataset.values);

					popup_generic.setPopupOpenerFieldValues({[this.dataset.dstfld1]: values[this.dataset.dstfld1]});

					document
						.getElementById(this.dataset.dstfld1)
						.dispatchEvent(new CustomEvent("help_items.paste"));

					updateItemFormElements();

					if (this.dataset.dstfld2 in values) {
						popup_generic.setPopupOpenerFieldValues({[this.dataset.dstfld2]: values[this.dataset.dstfld2]});
					}

					popup_generic.closePopup(event);
				');
			$table->addRow([$name, $item['description']]);
		}
		unset($data['table_records']);
		break;

	case 'dchecks':
		foreach ($data['table_records'] as $d_rule) {
			foreach ($d_rule['dchecks'] as $d_check) {
				$name = $d_rule['name'].
					NAME_DELIMITER.discovery_check2str($d_check['type'], $d_check['key_'], $d_check['ports']);

				$values = [
					$options['dstfld1'] => $d_check[$options['srcfld1']]
				];

				if ($options['dstfld2'] !== '' && $options['srcfld2'] === 'name') {
					$values[$options['dstfld2']] = $name;
				}

				$table->addRow(
					(new CLink($name))
						->setAttribute('data-dstfld1', $options['dstfld1'])
						->setAttribute('data-dstfld2', $options['dstfld2'])
						->setAttribute('data-values', json_encode($values))
						->onClick('
							const values = JSON.parse(this.dataset.values);

							popup_generic.setPopupOpenerFieldValues({
								[this.dataset.dstfld1]: values[this.dataset.dstfld1]
							});

							if (this.dataset.dstfld2 in values) {
								popup_generic.setPopupOpenerFieldValues({
									[this.dataset.dstfld2]: values[this.dataset.dstfld2]
								});
							}

							popup_generic.closePopup(event);
						')
				);
			}
		}
		unset($data['table_records']);
		break;

	case 'items':
	case 'template_items':
	case 'item_prototypes':

		if ($options['srcfld2'] !== '' && $options['dstfld2'] !== '') {
			// TODO: this condition must be removed after all item and item_prototype fields changing to multiselect
			foreach ($data['table_records'] as &$item) {
				$host = reset($item['hosts']);
				$item['hostname'] = $host['name'];

				$description = (new CLink($item['name']))->addClass(ZBX_STYLE_WORDBREAK);
				$item['name'] = $item['hostname'].NAME_DELIMITER.$item['name'];

				$checkbox_key = is_numeric($item[$options['srcfld1']])
					? $item[$options['srcfld1']]
					: zbx_jsValue($item[$options['srcfld1']]);

				if ($data['multiselect']) {
					$description
						->setAttribute('data-reference', $options['reference'])
						->setAttribute('data-itemid', $options['itemid'])
						->setAttribute('data-parentid', $options['parentid'])
						->onClick('
							addValue(this.dataset.reference, this.dataset.itemid, this.dataset.parentid ?? null);
							popup_generic.closePopup(event);
						');
				}
				else {
					$values = [];

					if ($options['dstfld1'] !== '' && $options['srcfld1'] !== '') {
						$values[$options['dstfld1']] = $item[$options['srcfld1']];
					}
					if ($options['dstfld2'] !== '' && $options['srcfld2'] !== '') {
						$values[$options['dstfld2']] = $item[$options['srcfld2']];
					}
					if ($options['dstfld3'] !== '' && $options['srcfld3'] !== '') {
						$values[$options['dstfld3']] = $item[$options['srcfld3']];
					}

					$description
						->setAttribute('data-dstfrm', $options['dstfrm'])
						->setAttribute('data-values', json_encode($values))
						->onClick('
							addValues(this.dataset.dstfrm, JSON.parse(this.dataset.values));
							popup_generic.closePopup(event);
						');
				}

				$table->addRow([
					$data['multiselect'] ? new CCheckBox('item['.$checkbox_key.']', $item['itemid']) : null,
					$description,
					(new CDiv($item['key_']))->addClass(ZBX_STYLE_WORDWRAP),
					item_type2str($item['type']),
					itemValueTypeString($item['value_type']),
					($data['popup_type'] === 'items')
						? (new CSpan(itemIndicator($item['status'], $item['state'])))
							->addClass(itemIndicatorStyle($item['status'], $item['state']))
						: null
				]);

				if ($data['multiselect']) {
					$item = [
						'id' => $item['itemid'],
						'itemid' => $item['itemid'],
						'name' => $item['name'],
						'key_' => $item['key_'],
						'flags' => $item['flags'],
						'type' => $item['type'],
						'value_type' => $item['value_type'],
						'host' => $item['hostname']
					];
				}
			}
			unset($item);
		}
		else {
			foreach ($data['table_records'] as &$item) {
				$host = reset($item['hosts']);
				$item_pattern = array_key_exists('pattern', $item) ? $item['pattern'] : $item['itemid'];

				$table->addRow([
					$data['multiselect']
						? new CCheckBox('item['.$item['itemid'].']', $item_pattern)
						: null,
					(new CLink($item['name']))
						->setAttribute('data-reference', $options['reference'])
						->setAttribute('data-pattern', $item_pattern)
						->setAttribute('data-parentid', $options['parentid'])
						->onClick('
							addValue(this.dataset.reference, this.dataset.pattern, this.dataset.parentid ?? null);
							popup_generic.closePopup(event);
						')
						->addClass(ZBX_STYLE_WORDBREAK),
					(new CDiv($item['key_']))->addClass(ZBX_STYLE_WORDWRAP),
					item_type2str($item['type']),
					itemValueTypeString($item['value_type']),
					($data['popup_type'] === 'items')
						? (new CSpan(itemIndicator($item['status'], $item['state'])))
							->addClass(itemIndicatorStyle($item['status'], $item['state']))
						: null
				]);

				$item = [
					'id' => $item_pattern,
					'itemid' => $item['itemid'],
					'name' => $options['patternselect']
						? $item['name']
						: $host['name'].NAME_DELIMITER.$item['name'],
					'key_' => $item['key_'],
					'flags' => $item['flags'],
					'type' => $item['type'],
					'value_type' => $item['value_type'],
					'host' => $host['name']
				];
			}
			unset($item);
		}
		break;

	case 'graphs':
	case 'graph_prototypes':
		foreach ($data['table_records'] as &$graph) {
			switch ($graph['graphtype']) {
				case GRAPH_TYPE_STACKED:
					$graphtype = _('Stacked');
					break;
				case GRAPH_TYPE_PIE:
					$graphtype = _('Pie');
					break;
				case GRAPH_TYPE_EXPLODED:
					$graphtype = _('Exploded');
					break;
				default:
					$graphtype = _('Normal');
					break;
			}

			$table->addRow([
				// Multiselect checkbox.
				$data['multiselect']
					? new CCheckBox('item['.json_encode($graph[$options['srcfld1']]).']', $graph['graphid'])
					: null,

				// Clickable graph name.
				(new CLink($graph['name']))
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-graphid', $graph['graphid'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.graphid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					'),

				// Graph type.
				$graphtype
			]);

			if ($options['patternselect']) {
				$graph_name = $graph['name'];
			}
			else {
				if ($data['popup_type'] === 'graphs') {
					$host_name = $graph['hosts'][0]['name'];
				}
				else {
					$host_names = array_column($graph['hosts'], 'name', 'hostid');
					$host_name = $host_names[$graph['discoveryRule']['hostid']];
				}

				$graph_name = $host_name.NAME_DELIMITER.$graph['name'];
			}

			// For returned data array.
			$graph = [
				'id' => $graph['graphid'],
				'name' => $graph_name
			];
		}
		unset($graph);
		break;

	case 'valuemap_names':
		foreach ($data['table_records'] as $valuemap) {
			$table->addRow([
				new CCheckBox('item['.$valuemap['id'].']', $valuemap['id']),
				(new CLink($valuemap['name'], '#'))
					->setId('spanid'.$valuemap['id'])
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-valuemapid', $valuemap['id'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.valuemapid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					')
			]);
		}
		break;

	case 'valuemaps':
		foreach ($data['table_records'] as $valuemap) {
			$name = [];
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$valuemap['id'].']', $valuemap['id'])
				: null;

			$name[] = (new CSpan($valuemap['hostname']))->addClass(ZBX_STYLE_GREY);
			$name[] = NAME_DELIMITER;

			if (array_key_exists('_disabled', $valuemap) && $valuemap['_disabled']) {
				if ($data['multiselect']) {
					$check_box->setChecked(1);
					$check_box->setEnabled(false);
				}
				$name[] = (new CSpan($valuemap['name']))->addClass(ZBX_STYLE_GREY);

				unset($data['table_records'][$valuemap['id']]);
			}
			else {
				$name[] = (new CLink($valuemap['name'], '#'))
					->setId('spanid'.$valuemap['id'])
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-valuemapid', $valuemap['id'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.valuemapid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					');
			}

			$mappings_table = [];

			foreach (array_slice($valuemap['mappings'], 0, 3) as $mapping) {
				switch ($mapping['type']) {
					case VALUEMAP_MAPPING_TYPE_EQUAL:
						$value = '='.$mapping['value'];
						break;

					case VALUEMAP_MAPPING_TYPE_GREATER_EQUAL:
						$value = '>='.$mapping['value'];
						break;

					case VALUEMAP_MAPPING_TYPE_LESS_EQUAL:
						$value = '<='.$mapping['value'];
						break;

					case VALUEMAP_MAPPING_TYPE_DEFAULT:
						$value = new CTag('em', true, _('default'));
						break;

					default:
						$value = $mapping['value'];
				}

				$mappings_table[] = new CDiv($value);
				$mappings_table[] = new CDiv('⇒');
				$mappings_table[] = new CDiv($mapping['newvalue']);
			}

			$hellip = (count($valuemap['mappings']) > 3) ? '&hellip;' : null;
			$table->addRow([$check_box, $name, [
				(new CDiv($mappings_table))->addClass(ZBX_STYLE_VALUEMAP_MAPPINGS_TABLE), $hellip
			]]);
		}
		break;

	case 'sla':
		foreach ($data['table_records'] as $item) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$item['id'].']', $item['id'])
				: null;

			if (array_key_exists('_disabled', $item)) {
				if ($data['multiselect']) {
					$check_box->setChecked(1);
					$check_box->setEnabled(false);
				}
				$name = $item['name'];

				unset($data['table_records'][$item['id']]);
			}
			else {
				$name = (new CLink($item['name']))
					->setId('spanid'.$item['id'])
					->setAttribute('data-reference', $options['reference'])
					->setAttribute('data-itemid', $item['id'])
					->setAttribute('data-parentid', $options['parentid'])
					->onClick('
						addValue(this.dataset.reference, this.dataset.itemid, this.dataset.parentid ?? null);
						popup_generic.closePopup(event);
					');
			}

			if (array_key_exists('status', $item)) {
				$status_tag = $item['status'] == ZBX_SLA_STATUS_ENABLED
					? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
					: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
			}
			else {
				$status_tag = null;
			}

			$table->addRow([$check_box, $name, $status_tag]);
		}
		break;
}

// Add submit button at footer.
if ($data['multiselect'] && $form !== null) {
	$form
		->setAttribute('data-reference', $options['reference'])
		->setAttribute('data-parentid', $options['parentid']);

	$output['buttons'] = [
		[
			'title' => _('Select'),
			'class' => '',
			'isSubmit' => true,
			'action' => '
				const form = document.getElementById("'.$form->getId().'");
				addSelectedValues(form.dataset.reference, form.dataset.parentid ?? null);
			'
		]
	];
}

// Types require results returned as array.
$types = [
	'api_methods',
	'dashboard',
	'graphs',
	'graph_prototypes',
	'hosts',
	'host_templates',
	'host_groups',
	'template_groups',
	'items',
	'item_prototypes',
	'proxies',
	'roles',
	'templates',
	'users',
	'usrgrp',
	'sla',
	'valuemaps'
];

if (array_key_exists('table_records', $data) && ($data['multiselect'] || in_array($data['popup_type'], $types))) {
	$output['data'] = $data['table_records'];
}

$script_inline .= 'popup_generic.init();';

$output['script_inline'] = $this->readJsFile('popup.generic.js.php').
	'jQuery(document).ready(function() {'.
		$script_inline.
	'});';

if ($form) {
	$form->addItem([
		$table,
		(new CInput('submit', 'submit'))
			->addStyle('display: none;')
			->removeId()
	]);
	$output['body'] = (new CDiv([$data['messages'], $form]))->toString();
}
else {
	$output['body'] = (new CDiv([$data['messages'], $table]))->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
