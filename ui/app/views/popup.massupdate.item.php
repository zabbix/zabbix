<?php declare(strict_types = 0);
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

// Create form.
$form = (new CForm())
	->setId('massupdate-form')
	->setName('massupdate-form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
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
		(new CVisibilityBox('visible[interfaceid]', 'interfaceDiv', _('Original')))
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
		]))->setId('interfaceDiv'),
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
		(new CDiv(
			(new CRadioButtonList('post_type', (int) DB::getDefault('items', 'post_type')))
				->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
				->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
				->addValue(_('XML data'), ZBX_POSTTYPE_XML)
				->setModern(true)
		))->setId('post_type_container')
	)
	->addRow(
		(new CVisibilityBox('visible[timeout]', 'timeout', _('Original')))->setLabel(_('Timeout')),
		(new CTextBox('timeout', DB::getDefault('items', 'timeout')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append ITEM_TYPE_HTTPAGENT Request body.
	->addRow(
		(new CVisibilityBox('visible[posts]', 'posts', _('Original')))->setLabel(_('Request body')),
		(new CTextArea('posts', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append ITEM_TYPE_HTTPAGENT Headers fields.
$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [json_encode([['name' => '', 'value' => '']])];

$item_form_list
	->addRow(
		(new CVisibilityBox('visible[headers]', 'headers_pairs', _('Original')))->setLabel(_('Headers')),
		(new CDiv([
			(new CTable())
				->addStyle('width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
				->setFooter(new CRow(
					(new CCol(
						(new CButton(null, _('Add')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setAttribute('data-row-action', 'add_row')
					))->setColSpan(5)
				)),
			(new CTag('script', true))
				->setAttribute('type', 'text/x-jquery-tmpl')
				->addItem(new CRow([
					(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextBox('headers[name][#{index}]', '#{name}'))->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
					'&rArr;',
					(new CTextBox('headers[value][#{index}]', '#{value}', false, 2000))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
					(new CButton(null, _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setAttribute('data-row-action', 'remove_row')
				])),
			$headers
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('headers_pairs')
			->setAttribute('data-sortable-pairs-table', '1')
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
				ITEM_VALUE_TYPE_TEXT => _('Text')
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
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
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
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete()
	);

// Create preprocessing form list.
$preprocessing_form_list = (new CFormList('preprocessing-form-list'))
	// Append item pre-processing to form list.
	->addRow(
		(new CVisibilityBox('visible[preprocessing]', 'preprocessing_div', _('Original')))
			->setLabel(_('Preprocessing steps')),
		(new CDiv(getItemPreprocessing($form, [], false, $data['preprocessing_types'])))
			->setId('preprocessing_div')
	);

// Prepare Update interval for form list.
$update_interval = (new CTable())
	->setId('update_interval')
	->addRow([_('Delay'),
		(new CDiv((new CTextBox('delay', ZBX_ITEM_DELAY_DEFAULT))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)))
	]);

$custom_intervals = (new CTable())
	->setId('custom_intervals')
	->setHeader([
		new CColHeader(_('Type')),
		new CColHeader(_('Interval')),
		new CColHeader(_('Period')),
		(new CColHeader(_('Action')))->setWidth(50)
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
			->addStyle('max-width:100px;width:100%;display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]'))
			->setAdaptiveWidth(100)
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->addStyle('display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]'))
			->setAdaptiveWidth(110)
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->addStyle('display: none;');
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

$update_interval->addRow(
	(new CRow([
		(new CCol(_('Custom intervals')))->addStyle('vertical-align: top;'),
		new CCol($custom_intervals)
	]))
);

// Append update interval to form list.
$item_form_list
	// Append delay to form list.
	->addRow(
		(new CVisibilityBox('visible[delay]', 'update_interval_div', _('Original')))->setLabel(_('Update interval')),
		(new CDiv($update_interval))->setId('update_interval_div')
	)
	// Append history to form list.
	->addRow(
		(new CVisibilityBox('visible[history]', 'history_div', _('Original')))
			->setLabel(_('History storage period')),
		(new CDiv([
			(new CRadioButtonList('history_mode', ITEM_STORAGE_CUSTOM))
				->addValue(_('Do not keep history'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('history', DB::getDefault('items', 'history')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('history_div')
	)
	// Append trends to form list.
	->addRow(
		(new CVisibilityBox('visible[trends]', 'trends_div', _('Original')))->setLabel(_('Trend storage period')),
		(new CDiv([
			(new CRadioButtonList('trends_mode', ITEM_STORAGE_CUSTOM))
				->addValue(_('Do not keep trends'), ITEM_STORAGE_CUSTOM)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('trends', DB::getDefault('items', 'trends')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('trends_div')
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
		(new CVisibilityBox('visible[valuemapid]', 'valuemapid_div', _('Original')))->setLabel(_('Value mapping')),
		(new CDiv([
			(new CMultiSelect([
				'name' => 'valuemapid',
				'object_name' => 'valuemaps',
				'multiple' => false,
				'data' => [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'valuemaps',
						'srcfld1' => 'valuemapid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'valuemapid',
						'hostids' => [$data['hostid']],
						'context' => $data['context'],
						'editable' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		]))->setId('valuemapid_div')
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
		$master_item = (new CDiv([
			(new CMultiSelect([
				'name' => 'master_itemid',
				'object_name' => 'items',
				'multiple' => false,
				'data' => [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'master_itemid',
						'hostid' => $data['hostid'],
						'only_hostid' => $data['hostid'],
						'webitems' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(true)
		]))->setId('master_item');
	}
	else {
		$master_item = [
			(new CTextBox('master_itemname', '', true))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CVar('master_itemid', '', 'master_itemid'))
		];

		$master_item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$master_item[] = (new CButton('button', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->removeId()
			->onClick(
				'return PopUp("popup.generic", '.json_encode([
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'master_itemid',
					'dstfld2' => 'master_itemname',
					'only_hostid' => $data['hostid'],
					'with_webitems' => 1,
					'normal_only' => 1
				]).', {dialogue_class: "modal-popup-generic"});'
			);

		$master_item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$master_item[] = (new CButton('button', _('Select prototype')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->removeId()
			->onClick(
				'return PopUp("popup.generic", '.json_encode([
					'srctbl' => 'item_prototypes',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'master_itemid',
					'dstfld2' => 'master_itemname',
					'parent_discoveryid' => $data['parent_discoveryid']
				]).', {dialogue_class: "modal-popup-generic"});'
			);
	}

	$item_form_list->addRow(
		(new CVisibilityBox('visible[master_itemid]', 'master_item', _('Original')))->setLabel(_('Master item')),
		(new CDiv([
			(new CVar('master_itemname')),
			$master_item
		]))->setId('master_item')
	);
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
		(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))->setLabel(_('Tags')),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable([['tag' => '', 'value' => '']])
				->setHeader([_('Name'), _('Value'), _('Action')])
				->setId('tags-table')
		]))->setId('tags-div')
	);

$tabs = (new CTabView())
	->addTab('item_tab', $data['prototype'] ? _('Item prototype') : _('Item'), $item_form_list)
	->addTab('tags_tab', _('Tags'), $tags_form_list)
	->addTab('preprocessing_tab', _('Preprocessing'), $preprocessing_form_list)
	->setSelected(0);

// Append tabs to form.
$form->addItem($tabs);

$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.tmpl.js.php')));
$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.item.js.php', $data)));
$form->addItem(new CJsScript($this->readJsFile('../../../include/views/js/item.preprocessing.js.php')));
$form->addItem(new CJsScript($this->readJsFile('../../../include/views/js/editabletable.js.php')));
$form->addItem(new CJsScript($this->readJsFile('../../../include/views/js/itemtest.js.php')));

$output = [
	'header' => $data['title'],
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
