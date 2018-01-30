<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$output = [
	'header' => $data['title'],
	'body' => '',
	'controls' => '',
	'script_inline' => '',
	'buttons' => null
];

$controls = [];
$form = null;

$options = $data['options'];
$page_filter = $data['page_filter'];

// Construct table header
$header_form = new CForm();
foreach ($options as $option_key => $option_value) {
	if ($option_value === true) {
		$header_form->addVar($option_key, 1);
	}
	elseif ($option_value) {
		$header_form->addVar($option_key, $option_value);
	}
}

// Only host id.
if (array_key_exists('only_hostid', $options)) {
	$host = $options['only_hostid'];

	if ($host) {
		$controls[] = [
			new CLabel(_('Host'), 'hostid'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CComboBox('hostid', $host['hostid']))
				->addItem($host['hostid'], $host['name'])
				->setEnabled(false)
				->setTitle(_('You can not switch hosts for current selection.'))
		];
	}
}
else {
	// Show Group dropdown in header for these specified sources.
	$show_group_cmb_box = ['triggers', 'items', 'applications', 'graphs', 'graph_prototypes', 'item_prototypes',
		'templates', 'hosts', 'host_templates'
	];

	if (in_array($data['popup_type'], $show_group_cmb_box)
			&& ($data['popup_type'] !== 'item_prototypes' || !$options['parent_discoveryid'])) {
		$controls[] = [
			new CLabel(_('Group'), 'groupid'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$page_filter->getGroupsCB(['action' => 'javascript: reloadPopup(this.form);'])
		];
	}

	// Show Type dropdown in header for help items.
	if ($data['popup_type'] === 'help_items') {
		$cmb_types = new CComboBox('itemtype', $options['itemtype'], 'javascript: reloadPopup(this.form);');

		foreach ($data['allowed_item_types'] as $type) {
			$cmb_types->addItem($type, item_type2str($type));
		}

		$controls[] = [
			new CLabel(_('Type'), 'itemtype'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$cmb_types
		];
	}

	// Show Host dropdown in header for these specified sources.
	$show_host_cmb_box = ['triggers', 'items', 'applications', 'graphs', 'graph_prototypes', 'item_prototypes'];
	if (in_array($data['popup_type'], $show_host_cmb_box)
			&& ($data['popup_type'] !== 'item_prototypes' || !$options['parent_discoveryid'])) {
		$controls[] = [
			new CLabel(_('Host'), 'hostid'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$page_filter->getHostsCB(['action' => 'javascript: reloadPopup(this.form);'])
		];
	}
}

if (in_array($data['popup_type'], ['applications', 'triggers'])) {
	if (!array_key_exists('noempty', $options)) {
		$value1 = strpos($options['dstfld1'], 'id') !== false ? 0 : '';
		$value2 = strpos($options['dstfld2'], 'id') !== false ? 0 : '';
		$value3 = strpos($options['dstfld3'], 'id') !== false ? 0 : '';

		$empty_script = get_window_opener($options['dstfrm'], $options['dstfld1'], $value1);
		$empty_script .= get_window_opener($options['dstfrm'], $options['dstfld2'], $value2);
		$empty_script .= get_window_opener($options['dstfrm'], $options['dstfld3'], $value3);
		$empty_script .= ' overlayDialogueDestroy(jQuery(this).closest("[data-dialogueid]").attr("data-dialogueid"));';
		$empty_script .= ' return false;';

		$controls[] = [(new CButton('empty', _('Empty')))->onClick($empty_script)];
	}
}

if ($controls) {
	$header_form->addItem(new CList($controls));
	$output['controls'] = $header_form->toString();
}

// Create form.
if ($data['form']) {
	$form = (new CForm())
		->setName($data['form']['name'])
		->setId($data['form']['id']);
}

$table_columns = [];

if ($page_filter->hostsAll) {
	$table_columns[] = _('Host');
}

if ($data['multiselect'] && $options['selectLimit'] != 1 && $form !== null) {
	$ch_box = (new CColHeader(
		(new CCheckBox('all_records'))
			->onClick("javascript: checkAll('".$form->getName()."', 'all_records', 'item');")
	))->addClass(ZBX_STYLE_CELL_WIDTH);

	$table_columns[] = $ch_box;
}

$table = (new CTableInfo())->setHeader(array_merge($table_columns, $data['table_columns']));

$js_action_onclick = ' jQuery(this).removeAttr("onclick");'.
	' overlayDialogueDestroy(jQuery(this).closest("[data-dialogueid]").attr("data-dialogueid"));'.
	' return false;';

// Output table rows.
switch ($data['popup_type']) {
	case 'hosts':
	case 'host_groups':
	case 'host_templates':
	case 'templates':
	case 'applications':
		foreach ($data['table_records'] as $item) {
			$check_box = $data['multiselect']
				? new CCheckBox('item['.$item['id'].']', $item['id'])
				: null;

			if (array_key_exists($item['id'], $options['excludeids'])) {
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

			$alias = (new CLink($user['alias'], 'javascript:void(0);'))
				->setId('spanid'.$user['userid'])
				->onClick($js_action.$js_action_onclick);

			$table->addRow([$check_box, $alias, $user['name'], $user['surname']]);

			$entry = [];
			$srcfld1 = $options['srcfld1'];
			if ($srcfld1 === 'userid') {
				$entry['id'] = $user['userid'];
			}
			elseif ($srcfld1 === 'alias') {
				$entry['name'] = $user['alias'];
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

			if (array_key_exists($item['usrgrpid'], $options['excludeids'])) {
				if ($data['multiselect']) {
					$check_box->setChecked(1);
					$check_box->setEnabled(false);
				}
				$name = $item['name'];
			}
			else {
				if ($data['multiselect']) {
					$js_action = "javascript: addValue(".zbx_jsvalue($options['reference']).', '.
							zbx_jsvalue($item['usrgrpid']).', '.$options['parentid'].');';
				}
				else {
					$values = [
						$options['dstfld1'] => $item[$options['srcfld1']],
						$options['dstfld2'] => $item[$options['srcfld2']]
					];
					$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.
							zbx_jsvalue($values).', '.$options['parentid'].');';
				}

				$name = (new CLink($item['name'], 'javascript: void(0);'))
							->setId('spanid'.$item['usrgrpid'])
							->onClick($js_action.$js_action_onclick);
			}

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
					$values[$options['dstfld3']] = $trigger[$trigger[$options['srcfld3']]];
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
				getSeverityCell($trigger['priority'], $options['config']),
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

			if (array_key_exists($sysmap['sysmapid'], $options['excludeids'])) {
				if ($data['multiselect']) {
					$check_box->setChecked(1);
					$check_box->setEnabled(false);
				}
				$name = $sysmap['name'];
			}
			else {
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
			}

			$table->addRow([$data['multiselect'] ? $check_box : null, $name]);
		}
		break;

	case 'help_items':
		foreach ($data['table_records'] as $item) {
			$action = get_window_opener($options['dstfrm'], $options['dstfld1'], $item[$options['srcfld1']]);
			$action .= $options['srcfld2']
				? get_window_opener($options['dstfrm'], $options['dstfld2'], $item[$options['srcfld2']])
				: '';

			$name = (new CLink($item['key'], 'javascript:void(0);'))->onClick($action.$js_action_onclick);
			$table->addRow([$name, $item['description']]);
		}
		unset($data['table_records']);
		break;

	case 'drules':
		foreach ($data['table_records'] as $item) {
			$action = get_window_opener($options['dstfrm'], $options['dstfld1'], $item[$options['srcfld1']]);
			$action .= $options['srcfld2']
				? get_window_opener($options['dstfrm'], $options['dstfld2'], $item[$options['srcfld2']])
				: '';

			$name = (new CLink($item['name'], 'javascript:void(0);'))->onClick($action.$js_action_onclick);
			$table->addRow($name);
		}
		unset($data['table_records']);
		break;

	case 'dchecks':
		foreach ($data['table_records'] as $d_rule) {
			foreach ($d_rule['dchecks'] as $d_check) {
				$name = $d_rule['name'].
					NAME_DELIMITER.discovery_check2str($d_check['type'], $d_check['key_'], $d_check['ports']);

				$action = get_window_opener($options['dstfrm'], $options['dstfld1'], $d_check[$options['srcfld1']]);
				$action .= $options['srcfld2']
					? get_window_opener($options['dstfrm'], $options['dstfld2'], $name)
					: '';

				$table->addRow(
					(new CLink($name, 'javascript:void(0);'))->onClick($action.$js_action_onclick)
				);
			}
		}
		unset($data['table_records']);
		break;

	case 'screens2':
		foreach ($data['table_records'] as $screen) {
			$screen['resourceid'] = $screen['screenid'];
			$name = new CLink($screen['name'], 'javascript:void(0);');

			$action = get_window_opener($options['dstfrm'], $options['dstfld1'], $screen[$options['dstfld1']]);
			$action .= $options['srcfld2']
				? get_window_opener($options['dstfrm'], $options['dstfld2'], $screen[$options['srcfld2']])
				: '';

			$name->onClick($action.$js_action_onclick);
			$table->addRow($name);
		}
		unset($data['table_records']);
		break;

	case 'items':
	case 'item_prototypes':
		foreach ($data['table_records'] as &$item) {
			$host = reset($item['hosts']);
			$item['hostname'] = $host['name'];

			$description = new CLink($item['name_expanded'], 'javascript:void(0);');
			$item['name'] = $item['hostname'].NAME_DELIMITER.$item['name_expanded'];
			$item['master_itemname'] = $item['name_expanded'].NAME_DELIMITER.$item['key_'];

			$checkbox_key = is_numeric($item[$options['srcfld1']])
				? $item[$options['srcfld1']]
				: zbx_jsValue($item[$options['srcfld1']]);

			if ($data['multiselect']) {
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($item['itemid']).', '.zbx_jsvalue($options['dstfld1']).');';
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
				($options['hostid'] > 0) ? null : $item['hostname'],
				($data['multiselect'] && $options['selectLimit'] != 1)
					? new CCheckBox('item['.$checkbox_key.']', $item['itemid'])
					: null,
				$description,
				$item['key_'],
				item_type2str($item['type']),
				itemValueTypeString($item['value_type']),
				(new CSpan(itemIndicator($item['status'], $item['state'])))
					->addClass(itemIndicatorStyle($item['status'], $item['state']))
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
		break;

	case 'graphs':
	case 'graph_prototypes':
		foreach ($data['table_records'] as $graph) {
			$host = reset($graph['hosts']);
			$graph['hostname'] = $host['name'];
			$description = new CLink($graph['name'], 'javascript:void(0);');
			$graph['name'] = $graph['hostname'].NAME_DELIMITER.$graph['name'];

			if ($data['multiselect']) {
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($graph['graphid']).');';
			}
			else {
				$values = [
					$options['dstfld1'] => $graph[$options['srcfld1']],
					$options['dstfld2'] => $graph[$options['srcfld2']]
				];
				$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.
					zbx_jsvalue($values).');';
			}

			$description->onClick($js_action.$js_action_onclick);

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
				$data['multiselect']
					? new CCheckBox('graphs['.zbx_jsValue($graph[$options['srcfld1']]).']', $graph['graphid'])
					: null,
				$description,
				$graphtype
			]);
			unset($description);
		}
		break;

	case 'screens':
		foreach ($data['table_records'] as $screen) {
			$checkbox_key = is_numeric($screen[$options['srcfld1']])
				? $screen[$options['srcfld1']]
				: zbx_jsValue($screen[$options['srcfld1']]);

			$check_box = $data['multiselect']
				? new CCheckBox('item['.$checkbox_key.']', $screen['screenid'])
				: null;

			$name = new CLink($screen['name'], 'javascript:void(0);');

			if ($data['multiselect']) {
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($screen['screenid']).');';
			}
			else {
				$values = [
					$options['dstfld1'] => $screen[$options['srcfld1']],
					$options['dstfld2'] => $screen[$options['srcfld2']]
				];
				$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.zbx_jsvalue($values).');';
			}
			$name->onClick($js_action.$js_action_onclick);

			$table->addRow([$check_box, $name]);
		}
		break;

	case 'proxies':
		foreach ($data['table_records'] as $proxy) {
			$proxy['hostid'] = $proxy['proxyid'];

			$action = get_window_opener($options['dstfrm'], $options['dstfld1'], $proxy[$options['srcfld1']]);
			if (array_key_exists('srcfld2', $options)) {
				$action .= get_window_opener($options['dstfrm'], $options['dstfld2'], $proxy[$options['srcfld2']]);
			}

			$table->addRow(
				(new CLink($proxy['host'], 'javascript:void(0);'))->onClick($action.$js_action_onclick)
			);
		}
		break;

	case 'scripts':
		foreach ($data['table_records'] as $script) {
			$description = new CLink($script['name'], 'javascript:void(0);');

			$check_box = $data['multiselect']
				? new CCheckBox('scripts['.zbx_jsValue($script[$options['srcfld1']]).']', $script['scriptid'])
				: null;

			if ($data['multiselect']) {
				$js_action = 'javascript: addValue('.zbx_jsvalue($options['reference']).', '.
					zbx_jsvalue($script['scriptid']).');';
			}
			else {
				$values = [
					$options['dstfld1'] => $script[$options['srcfld1']],
					$options['dstfld2'] => $script[$options['srcfld2']]
				];
				$js_action = 'javascript: addValues('.zbx_jsvalue($options['dstfrm']).', '.
					zbx_jsvalue($values).');';
			}
			$description->onClick($js_action.$js_action_onclick);

			if ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
				switch ($script['execute_on']) {
					case ZBX_SCRIPT_EXECUTE_ON_AGENT:
						$script_execute_on = _('Agent');
						break;
					case ZBX_SCRIPT_EXECUTE_ON_SERVER:
						$script_execute_on = _('Server');
						break;
					case ZBX_SCRIPT_EXECUTE_ON_PROXY:
						$script_execute_on = _('Server (proxy)');
						break;
				}
			}
			else {
				$script_execute_on = '';
			}
			$table->addRow([
				$check_box,
				$description,
				$script_execute_on,
				zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
			]);
		}
		unset($data['table_records']);
		break;
}

// Add submit button at footer.
if ($data['multiselect'] && $form !== null) {
	$output['buttons'] = [
		[
			'title' => _('Select'),
			'class' => '',
			'action' => 'return addSelectedValues('.zbx_jsvalue($form->getId()).', '.
						zbx_jsvalue($options['reference']).', '.$options['parentid'].'); '.
						'overlayDialogueDestroy(jQuery(this).closest("[data-dialogueid]").attr("data-dialogueid"));'
		]
	];
}

$types = ['users', 'templates', 'hosts', 'host_templates', 'host_groups', 'applications'];
if (array_key_exists('table_records', $data) && (in_array($data['popup_type'], $types) || $data['multiselect'])) {
	$output['script_inline'] .= 'var popup_reference = '.zbx_jsvalue($data['table_records'], true).';';
}

$output['script_inline'] .= '
jQuery(document).ready(function() {
	cookie.init();
	chkbxRange.init();
});';

if ($form) {
	$form->addItem($table);
	$output['body'] = (new CDiv([$data['message'], $form]))->toString();
}
else {
	$output['body'] = (new CDiv([$data['message'], $table]))->toString();
}

echo (new CJson())->encode($output);
