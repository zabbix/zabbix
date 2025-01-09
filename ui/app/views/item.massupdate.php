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
 */

// Create form.
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('item')))->removeId())
	->setId('massupdate-form')
	->setName('massupdate-form')
	->addVar('ids', $data['ids'])
	->addVar('action', $data['action'])
	->addVar('prototype', $data['prototype'])
	->addVar('update', '1')
	->addVar('location_url', $data['location_url'])
	->addVar('context', $data['context'], uniqid('context_'))
	->disablePasswordAutofill();

// Create item form list.
$item_form_list = (new CFormList('item-form-list'))
	// Append type to form list.
	->addRow(
		(new CVisibilityBox('visible[type]', 'type', _('Original')))
			->setLabel(_('Type'))
			->setAttribute('autofocus', 'autofocus'),
		(new CSelect('type'))
			->setId('type')
			->setValue(ITEM_TYPE_ZABBIX)
			->addOptions(CSelect::createOptionsFromArray($data['item_types']))
	);

// Append hosts interface select to form list.
if ($data['single_host_selected'] && $data['context'] === 'host') {
	$item_form_list->addRow(
		(new CVisibilityBox('visible[interfaceid]', 'interface-field', _('Original')))
			->setLabel(_('Host interface'))
			->setAttribute('data-multiple-interface-types', $data['multiple_interface_types']),
		(new CDiv([
			getInterfaceSelect($data['interfaces'])
				->setId('interface-select')
				->setValue('0')
				->addClass(ZBX_STYLE_ZSELECT_HOST_INTERFACE),
			(new CSpan(_('No interface found')))
				->addClass(ZBX_STYLE_RED)
				->setId('interface_not_defined')
				->addStyle('display: none;')
		]))->setId('interface-field'),
		'interface_row'
	);
}

$item_form_list
	// Append JMX endpoint to form list.
	->addRow(
		(new CVisibilityBox('visible[jmx_endpoint]', 'jmx_endpoint', _('Original')))->setLabel(_('JMX endpoint')),
		(new CTextBox('jmx_endpoint', ZBX_DEFAULT_JMX_ENDPOINT))->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append ITEM_TYPE_HTTPAGENT URL field.
	->addRow(
		(new CVisibilityBox('visible[url]', 'url', _('Original')))->setLabel(_('URL')),
		(new CTextBox('url', '', false, DB::getFieldLength('items', 'url')))
			->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append ITEM_TYPE_HTTPAGENT Request body type.
	->addRow(
		(new CVisibilityBox('visible[post_type]', 'post_type_container', _('Original')))
			->setLabel(_('Request body type')),
		(new CRadioButtonList('post_type', (int) DB::getDefault('items', 'post_type')))
			->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
			->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
			->addValue(_('XML data'), ZBX_POSTTYPE_XML)
			->setId('post_type_container')
			->setModern(true)
	)
	// Append ITEM_TYPE_HTTPAGENT Request body.
	->addRow(
		(new CVisibilityBox('visible[posts]', 'posts', _('Original')))->setLabel(_('Request body')),
		(new CTextArea('posts', ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableSpellcheck()
	);

// Append ITEM_TYPE_HTTPAGENT Headers fields.
$data['headers'] = [['name' => '', 'value' => '']];

$item_form_list
	->addRow(
		(new CVisibilityBox('visible[headers]', 'headers_pairs', _('Original')))->setLabel(_('Headers')),
		(new CDiv([
			(new CTable())
				->setId('headers-table')
				->setAttribute('style', 'width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->setFooter(
					(new CCol(
						(new CButtonLink(_('Add')))->addClass('element-table-add')
					))->setColSpan(5)
				),
			new CTemplateTag('item-header-row-tmpl',
				(new CRow([
					(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextBox('headers[#{rowNum}][name]', '#{name}'))
						->removeId()
						->setAttribute('placeholder', _('name'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
					RARR(),
					(new CTextBox('headers[#{rowNum}][value]', '#{value}', false, 2000))
						->removeId()
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
					(new CButtonLink(_('Remove')))->addClass('element-table-remove')
				]))->addClass('form_row')
			)
		]))
			->setId('headers_pairs')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
	// Append value type to form list.
	->addRow(
		(new CVisibilityBox('visible[value_type]', 'value_type', _('Original')))
			->setLabel(_('Type of information')),
		(new CSelect('value_type'))
			->setId('value_type')
			->setValue(ITEM_VALUE_TYPE_UINT64)
			->addOptions(CSelect::createOptionsFromArray([
				ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
				ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
				ITEM_VALUE_TYPE_STR => _('Character'),
				ITEM_VALUE_TYPE_LOG => _('Log'),
				ITEM_VALUE_TYPE_TEXT => _('Text'),
				ITEM_VALUE_TYPE_BINARY => _('Binary')
			]))
	)
	// Append units to form list.
	->addRow(
		(new CVisibilityBox('visible[units]', 'units', _('Original')))->setLabel(_('Units')),
		(new CTextBox('units', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append authtype to form list.
	->addRow(
		(new CVisibilityBox('visible[authtype]', 'authtype', _('Original')))->setLabel(_('Authentication method')),
		(new CSelect('authtype'))
			->setId('authtype')
			->setValue(ITEM_AUTHTYPE_PASSWORD)
			->addOptions(CSelect::createOptionsFromArray([
				ITEM_AUTHTYPE_PASSWORD => _('Password'),
				ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
			]))
	)
	// Append username to form list.
	->addRow(
		(new CVisibilityBox('visible[username]', 'username', _('Original')))
			->setLabel(_('User name')),
		(new CTextBox('username', ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableAutocomplete()
	)
	// Append publickey to form list.
	->addRow(
		(new CVisibilityBox('visible[publickey]', 'publickey', _('Original')))->setLabel(_('Public key file')),
		(new CTextBox('publickey', ''))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append privatekey to form list.
	->addRow(
		(new CVisibilityBox('visible[privatekey]', 'privatekey', _('Original')))->setLabel(_('Private key file')),
		(new CTextBox('privatekey', ''))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append password to form list.
	->addRow(
		(new CVisibilityBox('visible[password]', 'password', _('Original')))->setLabel(_('Password')),
		(new CTextBox('password', ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableAutocomplete()
	);

// Create preprocessing form list.
$preprocessing_form_list = (new CFormList('preprocessing-form-list'))
	// Append item pre-processing to form list.
	->addRow(
		(new CVisibilityBox('visible[preprocessing]', 'preprocessing-field', _('Original')))
			->setLabel([
				_('Preprocessing steps'),
				makeHelpIcon([
					_('Preprocessing is a transformation before saving the value to the database. It is possible to define a sequence of preprocessing steps, and those are executed in the order they are set.'),
					BR(), BR(),
					_('However, if "Check for not supported value" steps are configured, they are always placed and executed first (with "any error" being the last of them).')
				])
			]),
		(new CDiv([
			(new CRadioButtonList('preprocessing_action', ZBX_ACTION_REPLACE))
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove all'), ZBX_ACTION_REMOVE_ALL)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			getItemPreprocessing([], false, $data['preprocessing_types'])
		]))->setId('preprocessing-field')
	);

$custom_intervals = (new CTable())
	->setId('custom_intervals')
	->setHeader([
		new CColHeader(_('Type')),
		new CColHeader(_('Interval')),
		new CColHeader(_('Period')),
		''
	])
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR);

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay']))
			->setAdaptiveWidth(100)
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period']))
			->setAdaptiveWidth(110)
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]'))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->addStyle('max-width:100px;width:100%;')
			->addClass(ZBX_STYLE_DISPLAY_NONE);
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]'))
			->setAdaptiveWidth(100)
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->addClass(ZBX_STYLE_DISPLAY_NONE);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]'))
			->setAdaptiveWidth(110)
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->addClass(ZBX_STYLE_DISPLAY_NONE);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule']))
			->setAdaptiveWidth(100)
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$custom_intervals->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

$custom_intervals->addRow([(new CButton('interval_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

// Append update interval to form list.
$item_form_list
	// Append delay to form list.
	->addRow(
		(new CVisibilityBox('visible[delay]', 'update_interval', _('Original')))->setLabel(_('Update interval')),
		(new CFormList('update_interval'))
			->addClass(ZBX_STYLE_TABLE_SUBFORMS)
			->addRow(_('Delay'), (new CTextBox('delay', ZBX_ITEM_DELAY_DEFAULT))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH))
			->addRow(_('Custom intervals'), $custom_intervals)
	)
	// Append timeout to form list.
	->addRow(
		(new CVisibilityBox('visible[timeout]', 'timeout-field', _('Original')))->setLabel(_('Timeout')),
		(new CDiv([
			(new CRadioButtonList('custom_timeout', ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED))
				->addValue(_('Global'), ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED)
				->addValue(_('Override'), ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('timeout', DB::getDefault('items', 'timeout')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->setId('timeout-field')
			->addClass('wrap-multiple-controls')
	)
	// Append history to form list.
	->addRow(
		(new CVisibilityBox('visible[history]', 'history-field', _('Original')))
			->setLabel(_('History')),
		(new CDiv([
			(new CRadioButtonList('history_mode', ITEM_STORAGE_CUSTOM))
				->addValue(_('Do not store'), ITEM_STORAGE_OFF)
				->addValue(_('Store up to'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('history', DB::getDefault('items', 'history')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('history-field')
	)
	// Append trends to form list.
	->addRow(
		(new CVisibilityBox('visible[trends]', 'trends-field', _('Original')))->setLabel(_('Trends')),
		(new CDiv([
			(new CRadioButtonList('trends_mode', ITEM_STORAGE_CUSTOM))
				->addValue(_('Do not store'), ITEM_STORAGE_OFF)
				->addValue(_('Store up to'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('trends', DB::getDefault('items', 'trends')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('trends-field')
	);

// Append status to form list.
$item_form_list->addRow(
	(new CVisibilityBox('visible[status]', 'status', _('Original')))
		->setLabel($data['prototype'] ? _('Create enabled') : _('Status')),
	(new CRadioButtonList('status', ITEM_STATUS_ACTIVE))
		->addValue($data['prototype'] ? _('Yes') : item_status2str(ITEM_STATUS_ACTIVE), ITEM_STATUS_ACTIVE)
		->addValue($data['prototype'] ? _('No') : item_status2str(ITEM_STATUS_DISABLED), ITEM_STATUS_DISABLED)
		->setModern(true)
);

if ($data['prototype']) {
	$item_form_list->addRow(
		(new CVisibilityBox('visible[discover]', 'discover', _('Original')))->setLabel(_('Discover')),
		(new CRadioButtonList('discover', ZBX_PROTOTYPE_DISCOVER))
			->addValue(_('Yes'), ZBX_PROTOTYPE_DISCOVER)
			->addValue(_('No'), ZBX_PROTOTYPE_NO_DISCOVER)
			->setModern(true)
	);
}

// Append logtime to form list.
$item_form_list->addRow(
	(new CVisibilityBox('visible[logtimefmt]', 'logtimefmt', _('Original')))->setLabel(_('Log time format')),
	(new CTextBox('logtimefmt', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// Append value map select when only one host or template is selected.
if ($data['single_host_selected'] && ($data['context'] === 'template' || !$data['discovered_host'])) {
	$item_form_list->addRow(
		(new CVisibilityBox('visible[valuemapid]', 'valuemapid-field', _('Original')))->setLabel(_('Value mapping')),
		(new CMultiSelect([
			'name' => 'valuemapid',
			'object_name' => $data['context'] === 'host' ? 'valuemaps' : 'template_valuemaps',
			'multiselect_id' => 'valuemapid-field',
			'multiple' => false,
			'data' => [],
			'popup' => [
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'valuemaps' : 'template_valuemaps',
					'srcfld1' => 'valuemapid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'valuemapid',
					'hostids' => [$data['hostid']],
					'context' => $data['context'],
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

$item_form_list->addRow(
		(new CVisibilityBox('visible[allow_traps]', 'allow_traps', _('Original')))->setLabel(_('Enable trapping')),
		(new CRadioButtonList('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF))
			->addValue(_('Yes'), HTTPCHECK_ALLOW_TRAPS_ON)
			->addValue(_('No'), HTTPCHECK_ALLOW_TRAPS_OFF)
			->setModern(true)
	)
	->addRow(
		(new CVisibilityBox('visible[trapper_hosts]', 'trapper_hosts', _('Original')))->setLabel(_('Allowed hosts')),
		(new CTextBox('trapper_hosts', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append master item select to form list.
if ($data['single_host_selected']) {
	if (!$data['prototype']) {
		$item_form_list->addRow(
			(new CVisibilityBox('visible[master_itemid]', 'master-item-field', _('Original')))
				->setLabel(_('Master item')),
			(new CMultiSelect([
				'name' => 'master_itemid',
				'object_name' => 'items',
				'multiselect_id' => 'master-item-field',
				'multiple' => false,
				'data' => [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'master_itemid',
						'hostid' => $data['hostid'],
						'only_hostid' => $data['hostid']
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		);
	}
	else {
		$item_form_list->addRow(
			(new CVisibilityBox('visible[master_itemid]', 'master_item-field', _('Original')))
				->setLabel(_('Master item')),
			(new CDiv([
				(new CVar('master_itemname')),
				[
					(new CTextBox('master_itemname', '', true))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired(),
					(new CVar('master_itemid', '', 'master_itemid')),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('button', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->removeId()
						->setAttribute('data-hostid', $data['hostid'])
						->onClick('
							PopUp("popup.generic", {
								srctbl: "items",
								srcfld1: "itemid",
								srcfld2: "name",
								dstfrm: "'.$form->getName().'",
								dstfld1: "master_itemid",
								dstfld2: "master_itemname",
								only_hostid: this.dataset.hostid,
								normal_only: 1
							}, {dialogue_class: "modal-popup-generic"});
						'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('button', _('Select prototype')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->removeId()
						->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
						->onClick('
							PopUp("popup.generic", {
								srctbl: "item_prototypes",
								srcfld1: "itemid",
								srcfld2: "name",
								dstfrm: "'.$form->getName().'",
								dstfld1: "master_itemid",
								dstfld2: "master_itemname",
								parent_discoveryid: this.dataset.parent_discoveryid
							}, {dialogue_class: "modal-popup-generic"});
						')
				]
			]))->setId('master_item-field')
		);
	}
}

// Append description to form list.
$item_form_list->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))->setLabel(_('Description')),
	(new CTextArea('description', ''))
		->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setMaxlength(DB::getFieldLength('items', 'description'))
);

/*
 * Tags tab
 */
$tags_form_list = (new CFormList('tags-form-list'))
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags-field', _('Original')))->setLabel(_('Tags')),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable([['tag' => '', 'value' => '']])
				->setHeader([_('Name'), _('Value'), ''])
				->addClass('tags-table')
		]))->setId('tags-field')
	);

$tabs = (new CTabView())
	->addTab('item_tab', $data['prototype'] ? _('Item prototype') : _('Item'), $item_form_list)
	->addTab('tags_tab', _('Tags'), $tags_form_list)
	->addTab('preprocessing_tab', _('Preprocessing'), $preprocessing_form_list)
	->setSelected(0);

// Append tabs to form.
$form->addItem($tabs);

$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.tmpl.js.php')));
$form->addItem(new CJsScript($this->readJsFile('item.massupdate.js.php', $data)));
$form->addItem(new CJsScript($this->readJsFile('../../../include/views/js/item.preprocessing.js.php')));
$form->addItem(new CJsScript($this->readJsFile('../../../include/views/js/itemtest.js.php')));

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_MASSUPDATE_ITEM),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitPopup(overlay);'
		]
	]
];

$output['script_inline'] = $this->readJsFile('popup.massupdate.js.php');
$output['script_inline'] .= getPagePostJs();

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
