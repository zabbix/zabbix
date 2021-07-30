<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 */

$this->includeJsFile('configuration.host.edit.html.js.php');

$readonly = ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED);

$host_form = (new CForm())
	->setId($data['form_name'])
	->setName($data['form_name'])
	->addVar('action', $data['form_action'])
	->addVar('hostid', $data['hostid'])
	->addVar('clone_hostid', $data['clone_hostid'])
	->addVar('full_clone', $data['full_clone'])
	->addItem((new CInput('submit'))->addStyle('display: none;'));

// Host tab.
$discovered_by = null;
$interfaces_row = null;

if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	if ($data['editable_discovery_rules']) {
		$discovery_rule = new CLink($data['host']['discoveryRule']['name'],
			(new CUrl('host_prototypes.php'))
				->setArgument('form', 'update')
				->setArgument('parent_discoveryid', $data['host']['discoveryRule']['itemid'])
				->setArgument('hostid', $data['host']['hostDiscovery']['parent_hostid'])
				->setArgument('context', 'host')
		);
	}
	else {
		$discovery_rule = $data['host']['discoveryRule']
			? (new CSpan($data['host']['discoveryRule']['name']))
			: (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_GREY);
	}

	$discovered_by = [new CLabel(_('Discovered by')), new CFormField($discovery_rule)];

	$agent_interfaces = (new CDiv())
		->setId('agentInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'agent');

	$snmp_interfaces = (new CDiv())
		->setId('SNMPInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER.' '.ZBX_STYLE_LIST_VERTICAL_ACCORDION)
		->setAttribute('data-type', 'snmp');

	$jmx_interfaces = (new CDiv())
		->setId('JMXInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'jmx');

	$ipmi_interfaces = (new CDiv())
		->setId('IPMIInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'ipmi');
}
else {
	$agent_interfaces = (new CDiv())
		->setId('agentInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'agent');

	$snmp_interfaces = (new CDiv())
		->setId('SNMPInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER.' '.ZBX_STYLE_LIST_VERTICAL_ACCORDION)
		->setAttribute('data-type', 'snmp');

	$jmx_interfaces = (new CDiv())
		->setId('JMXInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'jmx');

	$ipmi_interfaces = (new CDiv())
		->setId('IPMIInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'ipmi');
}

$host_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem($discovered_by)
	->addItem([
		(new CLabel(_('Host name'), 'host'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('host', $data['host']['host'], $readonly, DB::getFieldLength('hosts', 'host')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Visible name'), 'visiblename'),
		new CFormField(
			(new CTextBox('visiblename', $data['host']['visiblename'], $readonly, DB::getFieldLength('hosts', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		(new CLabel(_('Groups'), 'groups__ms'))->setAsteriskMark(),
		new CFormField(
			(new CMultiSelect([
				'name' => 'groups[]',
				'object_name' => 'hostGroup',
				'disabled' => $readonly,
				'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
				'data' => $data['groups_ms'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => $host_form->getName(),
						'dstfld1' => 'groups_',
						'editable' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Interfaces')),
		new CFormField([
			new CDiv([renderInterfaceHeaders(), $agent_interfaces, $snmp_interfaces, $jmx_interfaces, $ipmi_interfaces]),
			($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED)
				? new CDiv(
					(new CButton('', _('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setMenuPopup([
							'type' => 'submenu',
							'data' => [
								'submenu' => getAddNewInterfaceSubmenu()
							]
						])
						->setAttribute('aria-label', _('Add new interface'))
				)
				: null
		])
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['host']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('hosts', 'description'))
		)
	])
	->addItem([
		new CLabel(_('Monitored by proxy'), 'label-proxy'),
		new CFormField(
			(new CSelect('proxy_hostid'))
				->setValue($data['host']['proxy_hostid'])
				->setFocusableElementId('label-proxy')
				->setReadonly($readonly)
				->addOptions(CSelect::createOptionsFromArray([0 => _('(no proxy)')] + $data['proxies']))
		)
	])
	->addItem([
		_('Enabled'),
		new CFormField(
			(new CCheckBox('status', HOST_STATUS_MONITORED))
				->setChecked($data['host']['status'] == HOST_STATUS_MONITORED)
		)
	]);

(new CScriptTag(
	'window.hostInterfaceManager = new HostInterfaceManager('.json_encode($data['host']['interfaces']).', '.
		json_encode([
			'interface_types' => [
				'AGENT' => INTERFACE_TYPE_AGENT,
				'SNMP' => INTERFACE_TYPE_SNMP,
				'JMX' => INTERFACE_TYPE_JMX,
				'IPMI' => INTERFACE_TYPE_IPMI
			],
			'interface_properties' => [
				'SNMP_V1' => SNMP_V1,
				'SNMP_V2C' => SNMP_V2C,
				'SNMP_V3' => SNMP_V3,
				'BULK_ENABLED' => SNMP_BULK_ENABLED,
				'INTERFACE_PRIMARY' => INTERFACE_PRIMARY,
				'INTERFACE_SECONDARY' => INTERFACE_SECONDARY,
				'INTERFACE_USE_IP' => INTERFACE_USE_IP,
				'SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
				'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
				'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,
				'SNMPV3_AUTHPROTOCOL_MD5' => ITEM_SNMPV3_AUTHPROTOCOL_MD5,
				'SNMPV3_PRIVPROTOCOL_DES' => ITEM_SNMPV3_PRIVPROTOCOL_DES
			],
			'styles' => [
				'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
				'ZBX_STYLE_HOST_INTERFACE_CONTAINER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER,
				'ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER,
				'ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS' => ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS,
				'ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE' => ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE,
				'ZBX_STYLE_HOST_INTERFACE_CELL_USEIP' => ZBX_STYLE_HOST_INTERFACE_CELL_USEIP,
				'ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE' => ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE,
				'ZBX_STYLE_LIST_ACCORDION_ITEM' => ZBX_STYLE_LIST_ACCORDION_ITEM,
				'ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED' => ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED,
				'ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND' => ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND,
				'ZBX_STYLE_HOST_INTERFACE_ROW' => ZBX_STYLE_HOST_INTERFACE_ROW,
				'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
			],
			'templates' => [
				'interface_row' => (new CPartial('configuration.host.interface.row'))->getOutput(),
				'no_interface_msg' => (new CDiv(_('No interfaces are defined.')))
					->addClass(ZBX_STYLE_GREY)
					->toString()
			]
		]).
	');'.
	'hostInterfaceManager.render();'.
	(($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) ? 'hostInterfaceManager.makeReadonly();' : '')
))
	->setOnDocumentReady()
	->show();

// Templates tab.
$templates_tab = (new CFormGrid())->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED);

if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$linked_template_table = (new CTable())
		->setHeader([_('Name')])
		->setId('linked-template')
		->addStyle('width: 100%;');

	foreach ($data['host']['parentTemplates'] as $template) {
		if ($data['allowed_ui_conf_templates']
				&& array_key_exists($template['templateid'], $data['editable_templates'])) {
			$template_link = (new CLink($template['name'],
				(new CUrl('templates.php'))
					->setArgument('form','update')
					->setArgument('templateid', $template['templateid'])
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linked_template_table->addRow([
			$template_link,
			(new CVar('templates[' . $template['templateid'] . ']', $template['templateid']))->removeId()
		]);
	}

	$templates_tab->addItem([
		new CLabel(_('Linked templates')),
		new CFormField(
			(new CDiv($linked_template_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
		)
	]);
}
else {
	$linked_template_table = (new CTable())
		->setHeader([_('Name'), _('Action')])
		->setId('linked-template')
		->setAttribute('style', 'width: 100%;');

	foreach ($data['host']['parentTemplates'] as $template) {
		if ($data['allowed_ui_conf_templates']
				&& array_key_exists($template['templateid'], $data['editable_templates'])) {
			$template_link = (new CLink($template['name'],
				(new CUrl('templates.php'))
					->setArgument('form','update')
					->setArgument('templateid', $template['templateid'])
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linked_template_table->addRow([
			[
				$template_link,
				(new CVar('templates[]', $template['templateid']))->removeId()
			],
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Unlink')))
						->addClass('js-tmpl-unlink ' . ZBX_STYLE_BTN_LINK)
						->setAttribute('data-templateid', $template['templateid']),
					(new CSimpleButton(_('Unlink and clear')))
						->addClass('js-tmpl-unlink-and-clear ' . ZBX_STYLE_BTN_LINK)
						->setAttribute('data-templateid', $template['templateid'])
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		]);
	}

	$add_templates_ms = (new CMultiSelect([
		'name' => 'add_templates[]',
		'object_name' => 'templates',
		'data' => array_key_exists('add_templates', $data['host']) ? $data['host']['add_templates'] : [],
		'popup' => [
			'parameters' => [
				'srctbl' => 'templates',
				'srcfld1' => 'hostid',
				'srcfld2' => 'host',
				'dstfrm' => $host_form->getName(),
				'dstfld1' => 'add_templates_',
				'disableids' => array_column($data['host']['parentTemplates'], 'templateid')
			]
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	$templates_tab
		->addItem([
			new CLabel(_('Linked templates')),
			new CFormField(
				(new CDiv($linked_template_table))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			)
		])
		->addItem([
			(new CLabel(_('Link new templates'), 'add_templates__ms')),
			new CFormField(
				(new CDiv($add_templates_ms))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			)
		]);
}

// IPMI tab.
if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$ipmi_authtype_select = [
		(new CTextBox('ipmi_authtype_name', ipmiAuthTypes($data['host']['ipmi_authtype']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		new CVar('ipmi_authtype', $data['host']['ipmi_authtype'])
	];
	$ipmi_privilege_select = [
		(new CTextBox('ipmi_privilege_name', ipmiPrivileges($data['host']['ipmi_privilege']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		new CVar('ipmi_privilege', $data['host']['ipmi_privilege'])
	];
}
else {
	$ipmi_authtype_select = new CListBox('ipmi_authtype', $data['host']['ipmi_authtype'], 7, ipmiAuthTypes());
	$ipmi_privilege_select = new CListBox('ipmi_privilege', $data['host']['ipmi_privilege'], 5, ipmiPrivileges());
}

$ipmi_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		new CLabel(_('Authentication algorithm'), 'ipmi_authtype'),
		new CFormField($ipmi_authtype_select)
	])
	->addItem([
		new CLabel(_('Privilege level'), 'ipmi_privilege'),
		new CFormField($ipmi_privilege_select)
	])
	->addItem([
		new CLabel(_('Username'), 'ipmi_username'),
		new CFormField(
			(new CTextBox('ipmi_username', $data['host']['ipmi_username'], $readonly))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
	])
	->addItem([
		new CLabel(_('Password'), 'ipmi_password'),
		new CFormField(
			(new CTextBox('ipmi_password', $data['host']['ipmi_password'], $readonly))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
	]);

// Tags tab.
$tags_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem(new CPartial('configuration.tags.tab', [
		'source' => 'host',
		'tags' => $data['host']['tags'],
		'readonly' => false
	]));

// Macros tab.
$macros_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem(
		(new CFormList('macrosFormList'))
			->addRow(null, (new CRadioButtonList('show_inherited_macros', 0))
				->addValue(_('Host macros'), 0)
				->addValue(_('Inherited and host macros'), 1)
				->setModern(true)
			)
			->addRow(null,
				new CPartial('hostmacros.list.html', [
					'macros' => $data['host']['macros'],
					'readonly' => $readonly
				]), 'macros_container'
			)
	);

// Inventory tab.
$inventory_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		null,
		new CFormField([
			(new CRadioButtonList('inventory_mode', (int) $data['host']['inventory_mode']))
				->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
				->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
				->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
				->setEnabled(!$readonly)
				->setModern(true),
			$readonly ? new CInput('hidden', 'inventory_mode', $data['host']['inventory_mode']) : null
		])
	]);

foreach ($data['inventory_fields'] as $inventory_no => $inventory_field) {
	$field_name = $inventory_field['db_field'];

	if (!array_key_exists($field_name, $data['host']['inventory'])) {
		$data['host']['inventory'][$field_name] = '';
	}

	if ($inventory_field['type'] == DB::FIELD_TYPE_TEXT) {
		$input_field = (new CTextArea('host_inventory['.$field_name.']', $data['host']['inventory'][$field_name]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH);
	}
	else {
		$input_field = (new CTextBox('host_inventory['.$field_name.']', $data['host']['inventory'][$field_name]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('maxlength', $inventory_field['length']);
	}

	if ($data['host']['inventory_mode'] == HOST_INVENTORY_DISABLED) {
		$input_field->setAttribute('disabled', 'disabled');
	}

	// Link to populating item at the right side (if any).
	if (array_key_exists($inventory_no, $data['inventory_items'])) {
		$item_name = $data['inventory_items'][$inventory_no]['name_expanded'];

		$link = (new CLink($item_name,
			(new CUrl('items.php'))
				->setArgument('form', 'update')
				->setArgument('itemid', $data['inventory_items'][$inventory_no]['itemid'])
				->setArgument('context', 'host')
				->getUrl()
		))->setTitle(_s('This field is automatically populated by item "%1$s".', $item_name));

		$inventory_item = (new CSpan([' &larr; ', $link]))->addClass('populating_item');
		$input_field->addClass('linked_to_item');

		if ($data['host']['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
			// This will be used for disabling fields via jquery.
			$input_field->setAttribute('disabled', 'disabled');
		}
		else {
			// Item links are visible only in automatic mode.
			$inventory_item->addStyle('display: none');
		}
	}
	else {
		$inventory_item = null;
	}

	$inventory_tab->addItem([
		new CLabel($inventory_field['title']),
		new CFormField([$input_field, $inventory_item])
	]);
}

// Encryption tab.
$is_psk_set = ($data['host']['tls_connect'] = HOST_ENCRYPTION_PSK || $data['host']['tls_accept'] & HOST_ENCRYPTION_PSK);
$encryption_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		new CLabel(_('Connections to host')),
		new CFormField(
			(new CRadioButtonList('tls_connect', (int) $data['host']['tls_connect']))
				->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
				->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
				->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
				->setModern(true)
				->setEnabled(!$readonly)
		)
	])
	->addItem([
		new CLabel(_('Connections from host')),
		new CFormField([
			(new CList())
				->addItem(
					(new CCheckBox('tls_in_none'))
						->setChecked(($data['host']['tls_accept'] & HOST_ENCRYPTION_NONE))
						->setLabel(_('No encryption'))
						->setEnabled(!$readonly)
				)
				->addItem(
					(new CCheckBox('tls_in_psk'))
						->setChecked(($data['host']['tls_accept'] & HOST_ENCRYPTION_PSK))
						->setLabel(_('PSK'))
						->setEnabled(!$readonly)
				)
				->addItem(
					(new CCheckBox('tls_in_cert'))
						->setChecked(($data['host']['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE))
						->setLabel(_('Certificate'))
						->setEnabled(!$readonly)
				),
			new CInput('hidden', 'tls_accept', $data['host']['tls_accept'])
		])
	])
	->addItem(
		(($data['hostid'] || $data['clone_hostid']) && $is_psk_set)
		? [
			(new CLabel(_('PSK'), 'change_psk'))->setAsteriskMark(),
			new CFormField(
				(new CSimpleButton(_('Change PSK')))
					->setId('change_psk')
					->addClass(ZBX_STYLE_BTN_GREY)
					->setEnabled(!$readonly)
			)
		]
		: null
	)
	->addItem([
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('tls_psk_identity', '', false, DB::getFieldLength('hosts', 'tls_psk_identity')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('tls_psk', '', false, DB::getFieldLength('hosts', 'tls_psk')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete()
		)
	])
	->addItem([
		new CLabel(_('Issuer'), 'tls_issuer'),
		new CFormField(
			(new CTextBox('tls_issuer', $data['host']['tls_issuer'], $readonly,
				DB::getFieldLength('hosts', 'tls_issuer')
			))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
		)
	])
	->addItem([
		new CLabel(_x('Subject', 'encryption certificate'), 'tls_subject'),
		new CFormField(
			(new CTextBox('tls_subject', $data['host']['tls_subject'], $readonly,
				DB::getFieldLength('hosts', 'tls_subject')
			))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
		)
	]);

// Value mapping tab.
if ($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
	$value_mapping_tab = (new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
		->addItem((new CFormList('valuemap-formlist'))
			->addRow(null, new CPartial('configuration.valuemap', [
				'source' => 'host',
				'valuemaps' => $data['host']['valuemaps'],
				'readonly' => $readonly,
				'form' => 'host'
			]))
		);
}

// Output.
$tabs = (new CTabView())
	->setSelected(0)
	->addTab('host-tab', _('Host'), $host_tab)
	->addTab('template-tab', _('Templates'), $templates_tab, TAB_INDICATOR_LINKED_TEMPLATE)
	->addTab('ipmi-tab', _('IPMI'), $ipmi_tab)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS)
	->addTab('macros-tab', _('Macros'), $macros_tab, TAB_INDICATOR_MACROS)
	->addTab('inventory-tab', _('Inventory'), $inventory_tab, TAB_INDICATOR_INVENTORY)
	->addTab('encryption-tab', _('Encryption'), $encryption_tab, TAB_INDICATOR_ENCRYPTION);;

if ($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
	$tabs->addTab('valuemap-tab', _('Value mapping'), $value_mapping_tab, TAB_INDICATOR_VALUEMAPS);
}

// Add footer buttons.
if (array_key_exists('buttons', $data)) {
	$primary_btn = array_shift($data['buttons']);
	$tabs->setFooter(makeFormFooter(
		$primary_btn,
		$data['buttons']
	));
}

$host_form
	->addItem($tabs)
	->show();

(new CScriptTag(
	'host_edit.init()'
))
	->setOnDocumentReady()
	->show();
