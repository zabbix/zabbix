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

// Make 'empty' button.
if ($data['popup_type'] === 'triggers' && !array_key_exists('noempty', $options)) {
	$value1 = (strpos($options['dstfld1'], 'id') !== false) ? 0 : '';
	$value2 = (strpos($options['dstfld2'], 'id') !== false) ? 0 : '';
	$value3 = (strpos($options['dstfld3'], 'id') !== false) ? 0 : '';

	$empty_btn = (new CButton('empty', _('Empty')))
		->addStyle('float: right; margin-left: 5px;')
		->onClick('popup_generic.setEmpty(event, '.json_encode([
			$options['dstfld1'] => $value1,
			$options['dstfld2'] => $value2,
			$options['dstfld3'], $value3
		]).')');
}
else {
	$empty_btn = null;
}

// Add host group multiselect control.
if (array_key_exists('groups', $data['filter'])) {
	$multiselect_options = $data['filter']['groups'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$hostgroup_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	$controls[] = (new CFormList())->addRow(new CLabel(_('Host group'), 'popup_host_group_ms'), $hostgroup_ms);

	$script_inline .= $hostgroup_ms->getPostJS(). 'popup_generic.initGroupsFilter();';
}

// Add host multiselect.
if (array_key_exists('hosts', $data['filter'])) {
	$multiselect_options = $data['filter']['hosts'];
	$multiselect_options['popup']['parameters']['dstfrm'] = $header_form->getId();

	$host_ms = (new CMultiSelect($multiselect_options))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);
	if ($multiselect_options['disabled']) {
		$host_ms->setTitle(_('You cannot switch hosts for current selection.'));
	}
	$controls[] = (new CFormList())->addRow(new CLabel(_('Host'), 'popup_host_ms'), [$empty_btn, $host_ms]);

	$script_inline .= $host_ms->getPostJS(). 'popup_generic.initHostsFilter();';
}
elseif ($empty_btn) {
	$controls[] = (new CFormList())->addRow($empty_btn);
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
		(new CCheckBox('all_records'))->onClick("javascript: checkAll('".$form->getName()."', 'all_records', 'item');")
	))->addClass(ZBX_STYLE_CELL_WIDTH);

	$table_columns[] = $ch_box;
}

$table = (new CTableInfo())->setHeader(array_merge($table_columns, $data['table_columns']));

if ($data['preselect_required']) {
	$table->setNoDataMessage(_('Specify some filter condition to see the values.'));
}

$js_action_onclick = 'popup_generic.closePopup(event);';

// Output table rows.
switch ($data['popup_type']) {
	case 'hosts':
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
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
						zbx_jsvalue($item['id']).', '.$options['parentid'].');';

				$name = (new CLink($item['name'], 'javascript:void(0);'))
					->setId('spanid'.$item['id'])
					->onClick($js_action.$js_action_onclick);
			}

			$table->addRow([$check_box, $name]);
		}
		break;

	case 'users':
		foreach ($data['table_records'] as &$user) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$user['userid'].']', $user['userid'])
				: null;

			$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($user['userid']).', '.$options['parentid'].');';

			$username = (new CLink($user['username'], 'javascript:void(0);'))
				->setId('spanid'.$user['userid'])
				->onClick($js_action.$js_action_onclick);

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

			$js_action = "javascript: addValue(".zbx_jsvalue($options['reference']).', '.
				zbx_jsvalue($item['usrgrpid']).', '.$options['parentid'].');';

			$name = (new CLink($item['name'], 'javascript: void(0);'))
						->setId('spanid'.$item['usrgrpid'])
						->onClick($js_action.$js_action_onclick);

			$table->addRow([$check_box, $name]);

			$item['id'] = $item['usrgrpid'];
		}
		unset($item);
		break;

	case 'triggers':
	case 'trigger_prototypes':
		foreach ($data['table_records'] as &$trigger) {
			$host = reset($trigger['hosts']);
			$trigger['hostname'] = $host['name'];

			$description = new CLink($trigger['description'], 'javascript:void(0);');
			$trigger['description'] = $trigger['hostname'].NAME_DELIMITER.$trigger['description'];

			$check_box = $data['multiselect']
				? new CCheckBox('item['.zbx_jsValue($trigger[$options['srcfld1']]).']', $trigger['triggerid'])
				: null;

			if ($data['multiselect']) {
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($trigger['triggerid']).', '.$options['parentid'].');';
			}
			else {
				$values = [
					$options['dstfld1'] => $trigger[$options['srcfld1']],
					$options['dstfld2'] => $trigger[$options['srcfld2']]
				];
				if (array_key_exists('dstfld3', $options)) {
					if (array_key_exists($options['srcfld3'], $trigger) && array_key_exists($trigger[$options['srcfld3']], $trigger)) {
						$values[$options['dstfld3']] = $trigger[$trigger[$options['srcfld3']]];
					}
					else {
						$values[$options['dstfld3']] = null;
					}
				}
				$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).','.zbx_jsvalue($values).');';
			}

			$description->onClick($js_action.$js_action_onclick);

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

			if ($data['multiselect']) {
				$js_action = "javascript: addValue(".zbx_jsvalue($options['reference']).', '.
						zbx_jsvalue($sysmap['sysmapid']).', '.$options['parentid'].');';
			}
			else {
				$values = [
					$options['dstfld1'] => $sysmap[$options['srcfld1']],
					$options['dstfld2'] => $sysmap[$options['srcfld2']]
				];
				$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.
						zbx_jsvalue($values).');';
			}

			$name = (new CLink($sysmap['name'], 'javascript:void(0);'))
						->setId('spanid'.$sysmap['sysmapid'])
						->onClick($js_action.$js_action_onclick);

			$table->addRow([$data['multiselect'] ? $check_box : null, $name]);
		}
		break;

	case 'help_items':
		foreach ($data['table_records'] as $key => $item) {
			$item['key'] = $key;

			$action = 'popup_generic.setPopupOpenerFieldValues('.json_encode([
				$options['dstfld1'] => $item[$options['srcfld1']]
			]).');';
			$action .= 'document.getElementById('.json_encode($options['dstfld1']).')'.
					'.dispatchEvent(new CustomEvent(\'help_items.paste\'));';
			$action .= 'updateItemFormElements();';
			$action .= $options['srcfld2']
				? 'popup_generic.setPopupOpenerFieldValues('.json_encode([
					$options['dstfld2'] => $item[$options['srcfld2']]
				]).')'
				: '';

			$name = (new CLink($item['key'], 'javascript:void(0);'))->onClick($action.$js_action_onclick);
			$table->addRow([$name, $item['description']]);
		}
		unset($data['table_records']);
		break;

	case 'dchecks':
		foreach ($data['table_records'] as $d_rule) {
			foreach ($d_rule['dchecks'] as $d_check) {
				$name = $d_rule['name'].
					NAME_DELIMITER.discovery_check2str($d_check['type'], $d_check['key_'], $d_check['ports']);

				$action = 'popup_generic.setPopupOpenerFieldValues('.json_encode([
					$options['dstfld1'] => $d_check[$options['srcfld1']]
				]).');';

				$action .= $options['srcfld2']
					? 'popup_generic.setPopupOpenerFieldValues('.json_encode([$options['dstfld2'] => $name]).');'
					: '';

				$table->addRow(
					(new CLink($name, 'javascript:void(0);'))->onClick($action.$js_action_onclick)
				);
			}
		}
		unset($data['table_records']);
		break;

	case 'items':
	case 'item_prototypes':
		if ($options['srcfld2'] !== '' && $options['dstfld2'] !== '') {
			// TODO: this condition must be removed after all item and item_prototype fields changing to multiselect
			foreach ($data['table_records'] as &$item) {
				$host = reset($item['hosts']);
				$item['hostname'] = $host['name'];

				$description = new CLink($item['name'], 'javascript:void(0);');
				$item['name'] = $item['hostname'].NAME_DELIMITER.$item['name'];

				$checkbox_key = is_numeric($item[$options['srcfld1']])
					? $item[$options['srcfld1']]
					: zbx_jsValue($item[$options['srcfld1']]);

				if ($data['multiselect']) {
					$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
						zbx_jsvalue($item['itemid']).', '.$options['parentid'].');';
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

					$submit_parent = array_key_exists('submit_parent', $options) ? 'true' : 'false';
					$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.
						zbx_jsvalue($values).', '.$submit_parent.');';
				}

				$description->onClick($js_action.$js_action_onclick);

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

				$table->addRow([
					$data['multiselect']
						? new CCheckBox('item['.$item[$options['srcfld1']].']', $item['itemid'])
						: null,
					(new CLink($item['name'], 'javascript:void(0);'))
						->onClick('javascript: addValue('.
							json_encode($options['reference']).', '.
							json_encode($item['itemid']).', '.
							$options['parentid'].
							');'.$js_action_onclick),
					(new CDiv($item['key_']))->addClass(ZBX_STYLE_WORDWRAP),
					item_type2str($item['type']),
					itemValueTypeString($item['value_type']),
					($data['popup_type'] === 'items')
						? (new CSpan(itemIndicator($item['status'], $item['state'])))
							->addClass(itemIndicatorStyle($item['status'], $item['state']))
						: null
				]);

				$item = [
					'id' => $item['itemid'],
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
				(new CLink($graph['name'], 'javascript:void(0);'))
					->onClick('javascript: addValue('.
						json_encode($options['reference']).', '.
						json_encode($graph['graphid']).', '.
						$options['parentid'].
						');'.$js_action_onclick
					),

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
		$inline_js = 'addValue('.json_encode($options['reference']).',%1$s,'.$options['parentid'].');%2$s';

		foreach ($data['table_records'] as $valuemap) {
			$table->addRow([
				new CCheckBox('item['.$valuemap['id'].']', $valuemap['id']),
				(new CLink($valuemap['name'], '#'))
					->setId('spanid'.$valuemap['id'])
					->onClick(sprintf($inline_js, $valuemap['id'], $js_action_onclick))
			]);
		}
		break;

	case 'valuemaps':
		$inline_js = 'addValue('.json_encode($options['reference']).',%1$s,'.$options['parentid'].');%2$s';

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
				$js_action = 'addValue('.json_encode($options['reference']).', '.
					json_encode($valuemap['id']).', '.$options['parentid'].');';

				$name[] = (new CLink($valuemap['name'], '#'))
					->setId('spanid'.$valuemap['id'])
					->onClick(sprintf($inline_js, $valuemap['id'], $js_action_onclick));
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
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
						zbx_jsvalue($item['id']).', '.$options['parentid'].');';

				$name = (new CLink($item['name'], 'javascript:void(0);'))
					->setId('spanid'.$item['id'])
					->onClick($js_action.$js_action_onclick);
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
	$output['buttons'] = [
		[
			'title' => _('Select'),
			'class' => '',
			'isSubmit' => true,
			'action' => 'return addSelectedValues('.zbx_jsvalue($options['reference']).', '.$options['parentid'].');'
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
