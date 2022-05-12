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
 * @var CPartial $this
 * @var array    $data
 */

$this->includeJsFile('configuration.host.edit.html.js.php');

$host_is_discovered = ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED);

$host_form = (new CForm())
	->setId($data['form_name'])
	->setName($data['form_name'])
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', $data['form_action'])
		->getUrl()
	)
	->addVar('hostid', $data['hostid'])
	->addVar('clone_hostid', $data['clone_hostid'])
	->addVar('full_clone', $data['full_clone'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

// Host tab.
$discovered_by = null;
$interfaces_row = null;

if ($host_is_discovered) {
	if ($data['editable_discovery_rules']) {
		$discovery_rule = (new CLink($data['host']['discoveryRule']['name'],
			(new CUrl('host_prototypes.php'))
				->setArgument('form', 'update')
				->setArgument('parent_discoveryid', $data['host']['discoveryRule']['itemid'])
				->setArgument('hostid', $data['host']['hostDiscovery']['parent_hostid'])
				->setArgument('context', 'host')
		))->setAttribute('target', '_blank');
	}
	else {
		$discovery_rule = $data['host']['discoveryRule']
			? (new CSpan($data['host']['discoveryRule']['name']))
			: (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_GREY);
	}

	$discovered_by = [new CLabel(_('Discovered by')), new CFormField($discovery_rule)];
}

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

$host_tab = (new CFormGrid())
	->addItem($discovered_by)
	->addItem([
		(new CLabel(_('Host name'), 'host'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('host', $data['host']['host'], $host_is_discovered, DB::getFieldLength('hosts', 'host')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Visible name'), 'visiblename'),
		new CFormField(
			(new CTextBox('visiblename', $data['host']['visiblename'], $host_is_discovered, DB::getFieldLength('hosts', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

$templates_field_items = [];

if ($host_is_discovered) {
	if ($data['host']['parentTemplates']) {
		$linked_templates = (new CTable())
			->setHeader([_('Name')])
			->setId('linked-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

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

			$linked_templates->addRow(
				(new CCol([
					$template_link,
					(new CVar('templates[' . $template['templateid'] . ']', $template['templateid']))->removeId()
				]))
					->addClass(ZBX_STYLE_WORDWRAP)
					->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			);
		}

		$templates_field_items[] = $linked_templates;
	}
}
else {
	if ($data['host']['parentTemplates']) {
		$linked_templates = (new CTable())
			->setHeader([_('Name'), _('Action')])
			->setId('linked-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

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

			$linked_templates->addRow([
				(new CCol([
					$template_link,
					(new CVar('templates[]', $template['templateid']))->removeId()
				]))
					->addClass(ZBX_STYLE_WORDWRAP)
					->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Unlink')))
							->onClick('host_edit.unlinkTemplate(this)')
							->addClass(ZBX_STYLE_BTN_LINK),
						$data['clone_hostid'] === null
							? (new CSimpleButton(_('Unlink and clear')))
								->onClick('host_edit.unlinkAndClearTemplate(this, '.
										json_encode($template['templateid']).')'
								)
								->addClass(ZBX_STYLE_BTN_LINK)
							: null
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]);
		}

		$templates_field_items[] = $linked_templates;
	}

	$templates_field_items[] = (new CMultiSelect([
		'name' => 'add_templates[]',
		'object_name' => 'templates',
		'data' => array_key_exists('add_templates', $data['host'])
			? $data['host']['add_templates']
			: [],
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
}

$host_tab
	->addItem([
		new CLabel(_('Templates')),
		(new CFormField(
			(count($templates_field_items) > 1)
				? (new CDiv($templates_field_items))->addClass('linked-templates')
				: $templates_field_items
		))
	])
	->addItem([
		(new CLabel(_('Groups'), 'groups__ms'))->setAsteriskMark(),
		new CFormField(
			(new CMultiSelect([
				'name' => 'groups[]',
				'object_name' => 'hostGroup',
				'disabled' => $host_is_discovered,
				'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
				'data' => $data['groups_ms'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => $host_form->getName(),
						'dstfld1' => 'groups_',
						'editable' => true,
						'disableids' => array_column($data['groups_ms'], 'id')
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
			$host_is_discovered
				? null
				: new CDiv(
					(new CButton(null, _('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setMenuPopup([
							'type' => 'submenu',
							'data' => [
								'submenu' => getAddNewInterfaceSubmenu()
							]
						])
						->setAttribute('aria-label', _('Add new interface'))
				)
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
				->setReadonly($host_is_discovered)
				->addOptions(CSelect::createOptionsFromArray([0 => _('(no proxy)')] + $data['proxies']))
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', HOST_STATUS_MONITORED))
				->setChecked($data['host']['status'] == HOST_STATUS_MONITORED)
		)
	]);

// IPMI tab.
if ($host_is_discovered) {
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
			(new CTextBox('ipmi_username', $data['host']['ipmi_username'], $host_is_discovered))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
	])
	->addItem([
		new CLabel(_('Password'), 'ipmi_password'),
		new CFormField(
			(new CTextBox('ipmi_password', $data['host']['ipmi_password'], $host_is_discovered))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
	]);

// Tags tab.
$tags_tab = new CPartial('configuration.tags.tab', [
	'source' => 'host',
	'tags' => $data['host']['tags'],
	'readonly' => $host_is_discovered,
	'tabs_id' => 'host-tabs'
]);

// Macros tab.
$macros_tab = (new CFormList('macrosFormList'))
	->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
		->addValue(_('Host macros'), 0)
		->addValue(_('Inherited and host macros'), 1)
		->setModern(true)
	)
	->addRow(null,
		new CPartial('hostmacros.list.html', [
			'macros' => $data['host']['macros'],
			'readonly' => $host_is_discovered
		]), 'macros_container'
	);

// Inventory tab.
$inventory_tab = (new CFormGrid())
	->addItem([
		null,
		new CFormField([
			(new CRadioButtonList('inventory_mode', (int) $data['host']['inventory_mode']))
				->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
				->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
				->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
				->setEnabled(!$host_is_discovered)
				->setModern(true),
			$host_is_discovered ? new CInput('hidden', 'inventory_mode', $data['host']['inventory_mode']) : null
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
		$item_name = $data['inventory_items'][$inventory_no]['name'];

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
$tls_accept = (int) $data['host']['tls_accept'];
$is_psk_set = ($data['host']['tls_connect'] == HOST_ENCRYPTION_PSK || $tls_accept & HOST_ENCRYPTION_PSK);

$encryption_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Connections to host')),
		new CFormField(
			(new CRadioButtonList('tls_connect', (int) $data['host']['tls_connect']))
				->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
				->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
				->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
				->setModern(true)
				->setEnabled(!$host_is_discovered)
		)
	])
	->addItem([
		new CLabel(_('Connections from host')),
		new CFormField([
			(new CList([
				(new CCheckBox('tls_in_none'))
					->setChecked(($tls_accept & HOST_ENCRYPTION_NONE))
					->setLabel(_('No encryption'))
					->setEnabled(!$host_is_discovered),
				(new CCheckBox('tls_in_psk'))
					->setChecked(($tls_accept & HOST_ENCRYPTION_PSK))
					->setLabel(_('PSK'))
					->setEnabled(!$host_is_discovered),
				(new CCheckBox('tls_in_cert'))
					->setChecked(($tls_accept & HOST_ENCRYPTION_CERTIFICATE))
					->setLabel(_('Certificate'))
					->setEnabled(!$host_is_discovered)
			]))
				->addClass(ZBX_STYLE_LIST_CHECK_RADIO),
			new CInput('hidden', 'tls_accept', $tls_accept)
		])
	])
	->addItem(
		($is_psk_set && !$data['is_psk_edit'] && ($data['hostid'] || $data['clone_hostid']))
		? [
			(new CLabel(_('PSK'), 'change_psk'))->setAsteriskMark(),
			new CFormField(
				(new CSimpleButton(_('Change PSK')))
					->setId('change_psk')
					->addClass(ZBX_STYLE_BTN_GREY)
					->setEnabled(!$host_is_discovered)
			)
		]
		: null
	)
	->addItem([
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('tls_psk_identity', $data['host']['tls_psk_identity'], false,
				DB::getFieldLength('hosts', 'tls_psk_identity')
			))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('tls_psk', $data['host']['tls_psk'], false, DB::getFieldLength('hosts', 'tls_psk')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete()
		)
	])
	->addItem([
		new CLabel(_('Issuer'), 'tls_issuer'),
		new CFormField(
			(new CTextBox('tls_issuer', $data['host']['tls_issuer'], $host_is_discovered,
				DB::getFieldLength('hosts', 'tls_issuer')
			))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
		)
	])
	->addItem([
		new CLabel(_x('Subject', 'encryption certificate'), 'tls_subject'),
		new CFormField(
			(new CTextBox('tls_subject', $data['host']['tls_subject'], $host_is_discovered,
				DB::getFieldLength('hosts', 'tls_subject')
			))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
		)
	]);

// Value mapping tab.
if (!$host_is_discovered) {
	$value_mapping_tab = (new CFormList('valuemap-formlist'))
		->addRow(null, new CPartial('configuration.valuemap', [
			'source' => 'host',
			'valuemaps' => $data['host']['valuemaps'],
			'readonly' => $host_is_discovered,
			'form' => 'host'
		]));
}

// main output
$tabs = (new CTabView(['id' => 'host-tabs']))
	->setSelected(0)
	->addTab('host-tab', _('Host'), $host_tab)
	->addTab('ipmi-tab', _('IPMI'), $ipmi_tab)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS)
	->addTab('macros-tab', _('Macros'), $macros_tab, TAB_INDICATOR_MACROS)
	->addTab('inventory-tab', _('Inventory'), $inventory_tab, TAB_INDICATOR_INVENTORY)
	->addTab('encryption-tab', _('Encryption'), $encryption_tab, TAB_INDICATOR_ENCRYPTION);

if (!$host_is_discovered) {
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
	->addItem($data['warning'])
	->addItem($tabs)
	->show();
