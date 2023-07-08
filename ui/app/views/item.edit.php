<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$dir = '/../../include/views/js/';
$scripts = [
	// $this->readJsFile('common.item.edit.js.php', $data, $dir), all code should be moved to item.edit.js
	$this->readJsFile('item.preprocessing.js.php', $data, $dir),
	// $this->readJsFile('editabletable.js.php', $data, $dir), same as for common.item.edit.js.php
	$this->readJsFile('itemtest.js.php', $data + ['hostid' => $data['form']['hostid']], $dir)
];
// TODO: when checkInput fail $data will contain only 'main_block' property, no need to render at all.
$form = (new CForm('post'))
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('item')))->removeId())
	->addItem(getMessages())
	->addVar('context', $data['form']['context'])
	->addVar('hostid', $data['form']['hostid'])
	->addVar('itemid', $data['form']['itemid'] ? $data['form']['itemid'] : null);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['form']['itemid']) {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update-item',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone-item']),
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Execute now'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-execute-item']),
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Test'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test-item']),
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clear history and trends'),
			'confirmation' => _('History clearing can take a long time. Continue?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clear-item']),
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected item?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete-item']),
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-create-item',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Test'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test-item']),
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}

$value_types = [
	ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
	ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
	ITEM_VALUE_TYPE_STR => _('Character'),
	ITEM_VALUE_TYPE_LOG => _('Log'),
	ITEM_VALUE_TYPE_TEXT => _('Text'),
	ITEM_VALUE_TYPE_BINARY => _('Binary')
];
$type_with_key_select = [
	ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_DB_MONITOR,
	ITEM_TYPE_SNMPTRAP, ITEM_TYPE_JMX, ITEM_TYPE_IPMI
];
$tabs = (new CTabView())
	->addTab('item-tab', _('Item'),
		new CPartial('item.edit.item.tab', [
			'config' => $data['config'],
			'discovery_rule' => $data['discovery_rule'],
			'discovered' => (bool) $data['discovery_rule'],
			'display_interfaces' => $data['display_interfaces'],
			'form' => $data['form'],
			'form_name' => $form->getName(),
			'host' => $data['host'],
			'inventory_fields' => $data['inventory_fields'],
			'master_item' => $data['master_item'],
			'parent_templates' => $data['parent_templates'],
			'readonly' => $data['readonly'],
			'types' => $data['types'],
			'valuemap' => $data['valuemap'],
			'value_types' => $value_types,
			'type_with_key_select' => $type_with_key_select
		])
	)
	->addTab('tags-tab', _('Tags'),
		new CPartial('configuration.tags.tab', [
			'readonly' => $data['readonly'],
			'show_inherited_tags' => $data['form']['show_inherited_tags'],
			'source' => 'item',
			'tabs_id' => 'tabs',
			'tags' => $data['form']['tags'] ? $data['form']['tags'] : [['tag' => '', 'value' => '']],
			'tags_tab_id' => 'tags-tab'
		]),
		TAB_INDICATOR_TAGS
	)
	->addTab('processing-tab', _('Preprocessing'),
		new CPartial('item.edit.preprocessing.tab', [
			'form' => $data['form'],
			'preprocessing' => $data['form']['preprocessing'],
			'preprocessing_types' => $data['preprocessing_types'],
			'readonly' => $data['readonly'],
			'value_types' => $value_types
		]),
		TAB_INDICATOR_PREPROCESSING
	);

if (!$data['form_refresh']) {
	$tabs->setSelected(0);
}

$form->addItem($tabs);
$output = [
	'header' => $data['form']['itemid'] ? _('Item') : _('New item'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_ITEM_EDIT),
	'body' => $form->toString().implode('', $scripts),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('item.edit.js.php', [
		'field_switches' => CItemData::fieldSwitchingConfiguration(['is_discovery_rule' => false]),
		'value_type_keys' => $data['value_type_keys'],
		'optional_interfaces' => $data['optional_interfaces'],
		'testable_item_types' => $data['testable_item_types'],
		'type_with_key_select' => $type_with_key_select
	])
];

// TODO: remove
$output['script_inline'] = str_replace('<script>', '', $output['script_inline']);

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
