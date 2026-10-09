<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$dir = '/../../include/views/js/';
$scripts = [
	$this->readJsFile('itemtest.js.php', $data + ['hostid' => $data['host']['hostid']], $dir),
	$this->readJsFile('configuration.host.discovery.edit.overr.js.php', $data['lldrule'], $dir)
];
$lldrule = $data['lldrule'];

$form = (new CForm())
	->setId('lldrule-prototype-form')
	->setName('itemForm')
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('lldrule')))->removeId())
	->addItem(getMessages())
	->addVar('context', $data['context'])
	->addVar('hostid', $data['host']['hostid'])
	->addVar('itemid', $lldrule['itemid'])
	->addVar('parent_discoveryid', $data['parent_discovery']['itemid'])
	->addVar('templateid', $lldrule['templateid'])
	->addVar('discovered', $lldrule['discovered'] ? 1 : 0)
	->addVar('host_discovered', $data['host']['flags'] & ZBX_FLAG_DISCOVERY_CREATED ? 1 : 0)
	->addStyle('display: none;');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($lldrule['itemid']) {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => !$lldrule['discovered']
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => !$lldrule['discovered']
		]
	];

	$buttons[] = [
		'title' => _('Test'),
		'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test-item']),
		'keepOpen' => true,
		'isSubmit' => false
	];

	$buttons[] = [
		'title' => _('Delete'),
		'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
		'keepOpen' => true,
		'isSubmit' => false,
		'enabled' => !$lldrule['templated']
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Test'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test-item']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('lldrule-prototype-tab', _('Discovery prototype'),
		new CPartial('lldrule.edit.prototype.tab', [
			'can_edit_source_timeouts' => $data['can_edit_source_timeouts'],
			'form_name' => $form->getName(),
			'host' => $data['host'],
			'lldrule' => $lldrule,
			'readonly' => $data['readonly'],
			'types' => $data['types']
		])
	)
	->addTab('processing-tab', _('Preprocessing'),
		new CPartial('item.edit.preprocessing.tab', [
			'preprocessing_types' => $data['preprocessing_types']
		]),
		TAB_INDICATOR_PREPROCESSING
	)
	->addTab('lldrule-macros-tab', _('LLD macros'),
		new CPartial('lldrule.edit.lldmacros.tab', ['readonly' => $data['readonly']]),
		TAB_INDICATOR_LLD_MACROS
	)
	->addTab('lldrule-filters-tab', _('Filters'),
		new CPartial('lldrule.edit.filters.tab',
			['filter' => $lldrule['filter'], 'readonly' => $lldrule['discovered_lld']]
		),
		TAB_INDICATOR_FILTERS
	);

$overrides_table = (new CTable())
	->setId('lld-overrides-table')
	->addClass('lld-overrides-table')
	->setHeader([
		new CColHeader(),
		(new CColHeader())->setWidth('15'),
		(new CColHeader(_('Name')))->setWidth('350'),
		(new CColHeader(_('Stop processing')))->setWidth('100'),
		(new CColHeader(_('Action')))->setWidth('50')
	])
	->addRow(
		(new CCol(
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$data['readonly'])
					->removeId()
			))
		))
	);

$tabs->addTab('overridesTab', _('Overrides'),
	(new CFormGrid())
		->addItem([
			new CLabel(_('Overrides')),
			new CFormField(
				(new CDiv($overrides_table))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			)
		]),
	TAB_INDICATOR_OVERRIDES
);

$form->addItem($tabs);

$return_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'lldrule.prototype.list')
	->setArgument('parent_discoveryid', $data['parent_discovery']['itemid'])
	->setArgument('context', $data['context'])
	->getUrl();

$output = [
	'header' => $data['lldrule']['itemid'] ? _('Discovery prototype') : _('New discovery prototype'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_DISCOVERY_EDIT),
	'body' => $form->toString().implode('', $scripts),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('lldrule.prototype.edit.js.php').
		$this->readJsFile('item.edit.preprocessing.tab.js.php', null, '/../partials/js').
		$this->readJsFile('lldrule.edit.prototype.tab.js.php', null, '/../partials/js').
		$this->readJsFile('lldrule.edit.lldmacros.tab.js.php', null, '/../partials/js').
		$this->readJsFile('lldrule.edit.filters.tab.js.php', null, '/../partials/js').
		$this->readJsFile('host.interface.selector.js.php', null, '/../partials/js').
		'lldrule_prototype_edit.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'test_rules' => $data['js_test_validation_rules'],
			'testable_item_types' => $data['testable_item_types'],
			'lldrule' => $lldrule,
			'host' => $data['host'],
			'inherited_timeouts' => $data['inherited_timeouts'],
			'field_switches' => CItemData::fieldSwitchingConfiguration(['is_discovery_rule' => true]),
			'interface_types' => itemTypeInterface(),
			'return_url' => $return_url
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
