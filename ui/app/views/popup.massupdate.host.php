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

// create form
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('host')))->removeId())
	->setId('massupdate-form')
	->addVar('action', 'popup.massupdate.host')
	->addVar('hostids', $data['hostids'], 'ids')
	->addVar('tls_accept', HOST_ENCRYPTION_NONE)
	->addVar('update', '1')
	->addVar('location_url', $data['location_url'])
	->disablePasswordAutofill();

$host_tab = new CFormList('hostFormList');

$host_tab->addRow(
	(new CVisibilityBox('visible[templates]', 'linked-templates-field', _('Original')))
		->setLabel(_('Link templates'))
		->setAttribute('autofocus', 'autofocus'),
	(new CDiv([
		(new CRadioButtonList('mass_action_tpls', ZBX_ACTION_ADD))
			->addValue(_('Link'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Unlink'), ZBX_ACTION_REMOVE)
			->setModern(true)
			->addStyle('margin-bottom: 5px;'),
		(new CMultiSelect([
			'name' => 'templates[]',
			'object_name' => 'templates',
			'data' => [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'templates_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->addStyle('margin-bottom: 5px;'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('mass_clear_tpls'))->setLabel(_('Clear when unlinking')))
	]))->setId('linked-templates-field')
);

$host_tab->addRow(
	(new CVisibilityBox('visible[groups]', 'groups-field', _('Original')))->setLabel(_('Host groups')),
	(new CDiv([
		(new CRadioButtonList('mass_update_groups', ZBX_ACTION_ADD))
			->addValue(_('Add'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_ACTION_REMOVE)
			->setModern(true)
			->addStyle('margin-bottom: 5px;'),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'hostGroup',
			'add_new' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			'data' => [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groups_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	]))->setId('groups-field')
);

// append description to form list
$host_tab->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))->setLabel(_('Description')),
	(new CTextArea('description', ''))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setMaxlength(DB::getFieldLength('hosts', 'description'))
);

// Append "Monitored by" to form list.
$host_tab->addRow(
	(new CVisibilityBox('visible[monitored_by]', 'monitored-by-field', _('Original')))->setLabel(_('Monitored by')),
	(new CDiv([
		(new CRadioButtonList('monitored_by', ZBX_MONITORED_BY_SERVER))
			->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
			->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
			->addValue(_('Proxy group'), ZBX_MONITORED_BY_PROXY_GROUP)
			->setModern(),
		(new CDiv(
			(new CMultiSelect([
				'name' => 'proxyid',
				'object_name' => 'proxies',
				'multiple' => false,
				'data' => [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'proxies',
						'srcfld1' => 'proxyid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'proxyid'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		))
			->addClass('js-field-proxy')
			->addStyle('margin-top: 5px;'),
		(new CDiv(
			(new CMultiSelect([
				'name' => 'proxy_groupid',
				'object_name' => 'proxy_groups',
				'multiple' => false,
				'data' => [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'proxy_groups',
						'srcfld1' => 'proxy_groupid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'proxy_groupid'
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass('js-field-proxy-group')
			->addStyle('margin-top: 5px;')
	]))->setId('monitored-by-field')
);

// append status to form list
$host_tab->addRow(
	(new CVisibilityBox('visible[status]', 'status', _('Original')))->setLabel(_('Status')),
	(new CSelect('status'))
		->setValue(HOST_STATUS_MONITORED)
		->setId('status')
		->addOptions(CSelect::createOptionsFromArray([
			HOST_STATUS_MONITORED => _('Enabled'),
			HOST_STATUS_NOT_MONITORED => _('Disabled')
		]))
);

$ipmi_tab = new CFormList('ipmiFormList');

// append ipmi to form list
$ipmi_tab->addRow(
	(new CVisibilityBox('visible[ipmi_authtype]', 'ipmi_authtype', _('Original')))
		->setLabel(_('Authentication algorithm')),
	(new CSelect('ipmi_authtype'))
		->setId('ipmi_authtype')
		->setValue(IPMI_AUTHTYPE_DEFAULT)
		->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
)
->addRow(
	(new CVisibilityBox('visible[ipmi_privilege]', 'ipmi_privilege', _('Original')))->setLabel(_('Privilege level')),
	(new CSelect('ipmi_privilege'))
		->setId('ipmi_privilege')
		->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
		->setValue(IPMI_PRIVILEGE_USER)
)
->addRow(
	(new CVisibilityBox('visible[ipmi_username]', 'ipmi_username', _('Original')))->setLabel(_('Username')),
	(new CTextBox('ipmi_username', '', false, DB::getFieldLength('hosts', 'ipmi_username')))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->disableAutocomplete()
)
->addRow(
	(new CVisibilityBox('visible[ipmi_password]', 'ipmi_password', _('Original')))->setLabel(_('Password')),
	(new CTextBox('ipmi_password', '', false, DB::getFieldLength('hosts', 'ipmi_password')))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->disableAutocomplete()
);

$inventory_tab = new CFormList('inventoryFormList');

// append inventories to form list
$inventory_tab->addRow(
	(new CVisibilityBox('visible[inventory_mode]', 'inventory_mode', _('Original')))->setLabel(_('Inventory mode')),
	(new CRadioButtonList('inventory_mode', HOST_INVENTORY_DISABLED))
		->setId('inventory_mode')
		->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
		->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
		->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
		->setModern(true)
);

$tags_tab = new CFormList('tagsFormList');

// append tags table to form list
$tags_tab->addRow(
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

$hostInventoryTable = DB::getSchema('host_inventory');
foreach ($data['inventories'] as $field => $fieldInfo) {

	if ($hostInventoryTable['fields'][$field]['type'] & DB::FIELD_TYPE_TEXT) {
		$fieldInput = (new CTextArea('host_inventory['.$field.']', ''))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH);
	}
	else {
		$fieldInput = (new CTextBox('host_inventory['.$field.']', ''))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('maxlength', $hostInventoryTable['fields'][$field]['length']);
	}

	$inventory_tab->addRow(
		(new CVisibilityBox('visible['.$field.']', $fieldInput->getId(), _('Original')))->setLabel($fieldInfo['title']),
		$fieldInput, null, 'formrow-inventory'
	);
}

$encryption_tab = new CFormList('encryption');

$encryption_table = (new CFormList('encryption-field'))
	->addRow(_('Connections to host'),
		(new CRadioButtonList('tls_connect', HOST_ENCRYPTION_NONE))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
			->setEnabled(true)
	)
	->addRow(_('Connections from host'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setLabel(_('No encryption'))
				->setEnabled(true)
			)
			->addItem((new CCheckBox('tls_in_psk'))
				->setLabel(_('PSK'))
				->setEnabled(true)
			)
			->addItem((new CCheckBox('tls_in_cert'))
				->setLabel(_('Certificate'))
				->setEnabled(true)
			)
	)
	->addRow(
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
		(new CTextBox('tls_psk_identity', '', false, 128))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
		(new CTextBox('tls_psk', '', false, 512))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired()
			->disableAutocomplete()
	)
	->addRow(_('Issuer'),
		(new CTextBox('tls_issuer', '', false, 1024))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
	)
	->addRow(_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', '', false, 1024))
			->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
	);

$encryption_tab->addRow(
	(new CVisibilityBox('visible[encryption]', 'encryption-field', _('Original')))->setLabel(_('Connections')),
	$encryption_table->addStyle('margin-top: -5px;')
);

// append tabs to form
$tabs = (new CTabView())
	->addTab('hostTab', _('Host'), $host_tab)
	->addTab('ipmiTab', _('IPMI'), $ipmi_tab)
	->addTab('tagsTab', _('Tags'), $tags_tab)
	->addTab('macros_tab', _('Macros'), new CPartial('massupdate.macros.tab', [
		'visible' => [],
		'macros' => [['macro' => '', 'type' => ZBX_MACRO_TYPE_TEXT, 'value' => '', 'description' => '']],
		'macros_checkbox' => [ZBX_ACTION_ADD => 0, ZBX_ACTION_REPLACE => 0, ZBX_ACTION_REMOVE => 0,
			ZBX_ACTION_REMOVE_ALL => 0
		]
	]))
	->addTab('inventoryTab', _('Inventory'), $inventory_tab)
	->addTab('encryptionTab', _('Encryption'), $encryption_tab)
	->setSelected(0);

if (!$data['discovered_host']) {
	$tabs->addTab('valuemaps_tab', _('Value mapping'), new CPartial('massupdate.valuemaps.tab', [
		'visible' => [],
		'hostids' => $data['hostids'],
		'context' => 'host'
	]));
}

$form->addItem($tabs);

$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.tmpl.js.php')));
$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.macros.js.php')));

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_MASSUPDATE_HOST),
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
