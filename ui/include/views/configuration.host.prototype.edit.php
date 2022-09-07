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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

require_once __DIR__.'/js/configuration.host.prototype.edit.js.php';
require_once __DIR__.'/js/common.template.edit.js.php';

$host_prototype = $data['host_prototype'];
$parent_host = $data['parent_host'];

$widget = (new CWidget())
	->setTitle(_('Host prototypes'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_HOST_PROTOTYPE_EDIT))
	->setNavigation(getHostNavigation('hosts', $data['discovery_rule']['hostid'], $data['discovery_rule']['itemid']));

$tabs = new CTabView();

if (!hasRequest('form_refresh')) {
	$tabs->setSelected(0);
}

$url = (new CUrl('host_prototypes.php'))
	->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
	->setArgument('context', $data['context'])
	->getUrl();

$form = (new CForm('post', $url))
	->setId('host-prototype-form')
	->setName('hostPrototypeForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', getRequest('form', 1))
	->addVar('parent_discoveryid', $data['discovery_rule']['itemid'])
	->addVar('tls_accept', $parent_host['tls_accept'])
	->addvar('context', $data['context']);

if ($host_prototype['hostid'] != 0) {
	$form->addVar('hostid', $host_prototype['hostid']);
}

$host_tab = new CFormList('hostlist');

if ($data['templates']) {
	$host_tab->addRow(_('Parent discovery rules'), $data['templates']);
}

$host_tab->addRow(
	(new CLabel(_('Host name'), 'host'))->setAsteriskMark(),
	(new CTextBox('host', $host_prototype['host'], (bool) $host_prototype['templateid']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('maxlength', 128)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
);

$name = ($host_prototype['name'] != $host_prototype['host']) ? $host_prototype['name'] : '';

$host_tab->addRow(
	_('Visible name'),
	(new CTextBox('name', $name, (bool) $host_prototype['templateid']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('maxlength', 128)
);

$templates_field_items = [];

if ($host_prototype['templateid']) {
	if ($host_prototype['templates']) {
		$linked_templates = (new CTable())
			->setHeader([_('Name')])
			->setId('linked-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

		foreach ($host_prototype['templates'] as $template) {
			$host_tab->addItem(
				(new CVar('templates['.$template['templateid'].']', $template['templateid']))->removeId()
			);

			if ($data['allowed_ui_conf_templates']
					&& array_key_exists($template['templateid'], $host_prototype['writable_templates'])) {
				$template_link = (new CLink($template['name'],
					(new CUrl('templates.php'))
						->setArgument('form', 'update')
						->setArgument('templateid', $template['templateid'])
				))->setTarget('_blank');
			}
			else {
				$template_link = new CSpan($template['name']);
			}

			$linked_templates->addRow([$template_link->addClass(ZBX_STYLE_WORDWRAP)]);
		}

		$templates_field_items[] = $linked_templates;
	}
}
else {
	if ($host_prototype['templates']) {
		$linked_templates = (new CTable())
			->setHeader([_('Name'), _('Action')])
			->setId('linked-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

		foreach ($host_prototype['templates'] as $template) {
			$host_tab->addItem((new CVar('templates['.$template['templateid'].']', $template['templateid']))->removeId());

			if ($data['allowed_ui_conf_templates']
					&& array_key_exists($template['templateid'], $host_prototype['writable_templates'])) {
				$template_link = (new CLink($template['name'],
					(new CUrl('templates.php'))
						->setArgument('form', 'update')
						->setArgument('templateid', $template['templateid'])
				))->setTarget('_blank');
			}
			else {
				$template_link = new CSpan($template['name']);
			}

			$linked_templates->addRow([
				$template_link->addClass(ZBX_STYLE_WORDWRAP),
				(new CCol(
					(new CSimpleButton(_('Unlink')))
						->setAttribute('data-templateid', $template['templateid'])
						->onClick('
							submitFormWithParam("'.$form->getName().'", `unlink[${this.dataset.templateid}]`, 1);
						')
						->addClass(ZBX_STYLE_BTN_LINK)
				))->addClass(ZBX_STYLE_NOWRAP)
			]);
		}

		$templates_field_items[] = $linked_templates;
	}

	$templates_field_items[] = (new CMultiSelect([
		'name' => 'add_templates[]',
		'object_name' => 'templates',
		'data' => $host_prototype['add_templates'],
		'popup' => [
			'parameters' => [
				'srctbl' => 'templates',
				'srcfld1' => 'hostid',
				'srcfld2' => 'host',
				'dstfrm' => $form->getName(),
				'dstfld1' => 'add_templates_',
				'disableids' => array_column($host_prototype['templates'], 'templateid')
			]
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
}

$host_tab
	->addRow(new CLabel(_('Templates'), 'add_templates__ms'),
		(count($templates_field_items) > 1)
			? (new CDiv($templates_field_items))->addClass('linked-templates')
			: $templates_field_items
	);

// Existing groups.
$host_tab->addRow(
	(new CLabel(_('Host groups'), 'group_links__ms'))->setAsteriskMark(),
	(new CMultiSelect([
		'name' => 'group_links[]',
		'object_name' => 'hostGroup',
		'disabled' => (bool) $host_prototype['templateid'],
		'data' => $data['groups_ms'],
		'popup' => [
			'parameters' => [
				'srctbl' => 'host_groups',
				'srcfld1' => 'groupid',
				'dstfrm' => $form->getName(),
				'dstfld1' => 'group_links_',
				'editable' => true,
				'normal_only' => true,
				'disableids' => array_column($data['groups_ms'], 'id')
			]
		]
	]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
);

// New group prototypes.
$host_tab->addRow(
	new CLabel(_('Group prototypes'), 'group_prototypes'),
	(new CDiv(
		(new CTable())
			->setId('tbl_group_prototypes')
			->addRow(
				(new CRow())
					->setId('row_new_group_prototype')
					->addItem(
						(new CCol(
							(new CButton('group_prototype_add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
						))->setAttribute('colspan', 5)
					)
			)
	))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);

$interface_header = renderInterfaceHeaders();

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

$host_tab->addRow(
	new CLabel(_('Interfaces')),
	[
		(new CRadioButtonList('custom_interfaces', (int) $host_prototype['custom_interfaces']))
			->addValue(_('Inherit'), HOST_PROT_INTERFACES_INHERIT)
			->addValue(_('Custom'), HOST_PROT_INTERFACES_CUSTOM)
			->setModern(true)
			->setReadonly($host_prototype['templateid'] != 0),
		(new CDiv([$interface_header, $agent_interfaces, $snmp_interfaces, $jmx_interfaces, $ipmi_interfaces]))
			->setId('interfaces-table')
			->addClass(ZBX_STYLE_HOST_INTERFACES),
		new CDiv(
			(new CButton('interface-add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->setMenuPopup([
					'type' => 'submenu',
					'data' => [
						'submenu' => getAddNewInterfaceSubmenu()
					]
				])
				->setAttribute('aria-label', _('Add new interface'))
				->addStyle(($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM)
					? null
					: 'display: none'
				)
				->setEnabled($host_prototype['templateid'] == 0)
		)
	]
);

// Display inherited parameters only for hosts prototypes on hosts.
if ($parent_host['status'] != HOST_STATUS_TEMPLATE) {
	$host_tab->addRow(
		_('Monitored by proxy'),
		(new CTextBox(
			'proxy_hostid',
			($parent_host['proxy_hostid'] != 0) ? $this->data['proxy']['host'] : _('(no proxy)'),
			true
		))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

$host_tab->addRow(_('Create enabled'),
	(new CCheckBox('status', HOST_STATUS_MONITORED))
		->setChecked(HOST_STATUS_MONITORED == $host_prototype['status'])
);
$host_tab->addRow(_('Discover'),
	(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))
		->setChecked($host_prototype['discover'] == ZBX_PROTOTYPE_DISCOVER)
		->setUncheckedValue(ZBX_PROTOTYPE_NO_DISCOVER)
);

$tabs->addTab('hostTab', _('Host'), $host_tab);

// Display inherited parameters only for hosts prototypes on hosts.
if ($parent_host['status'] != HOST_STATUS_TEMPLATE) {
	// IPMI
	$ipmi_tab = new CFormList();

	$ipmi_tab->addRow(new CLabel(_('Authentication algorithm'), 'label_ipmi_authtype'),
		(new CSelect())
			->setValue($parent_host['ipmi_authtype'])
			->setFocusableElementId('label_ipmi_authtype')
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
			->setReadonly()
			->setId('ipmi_authtype')
	);
	$ipmi_tab->addRow(new CLabel(_('Privilege level'), 'label_ipmi_privilege'),
		(new CSelect())
			->setValue($parent_host['ipmi_privilege'])
			->setFocusableElementId('label_ipmi_privilege')
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
			->setReadonly()
			->setId('ipmi_privilege')
	);
	$ipmi_tab->addRow(_('Username'),
		(new CTextBox(null, $parent_host['ipmi_username'], true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setId('ipmi_username')
	);
	$ipmi_tab->addRow(_('Password'),
		(new CTextBox(null, $parent_host['ipmi_password'], true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setId('ipmi_password')
	);

	$tabs->addTab('ipmi-tab', _('IPMI'), $ipmi_tab, TAB_INDICATOR_IPMI);
}

$tabs->addTab('tags-tab', _('Tags'),
	new CPartial('configuration.tags.tab', [
		'source' => 'host_prototype',
		'tags' => $data['tags'],
		'readonly' => $data['readonly'],
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]),
	TAB_INDICATOR_TAGS
);

$tabs->addTab('macroTab', _('Macros'),
	(new CFormList('macrosFormList'))
		->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
			->addValue(_('Host prototype macros'), 0)
			->addValue(_('Inherited and host prototype macros'), 1)
			->setModern(true)
		)
		->addRow(
			null,
			new CPartial(
				$data['show_inherited_macros']
					? 'hostmacros.inherited.list.html'
					: 'hostmacros.list.html',
				[
					'macros' => $data['macros'],
					'parent_hostid' => $data['parent_host']['hostid'],
					'readonly' => $data['templates']
				]
			),
			'macros_container'
		),
	TAB_INDICATOR_MACROS
);

$tabs->addTab('inventoryTab', _('Inventory'),
	(new CFormList('inventorylist'))
		->addRow(
			null,
			(new CRadioButtonList('inventory_mode', (int) $host_prototype['inventory_mode']))
				->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
				->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
				->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
				->setReadonly($host_prototype['templateid'] != 0)
				->setModern(true)
		),
	TAB_INDICATOR_INVENTORY
);

// Encryption tab.
$encryption_tab = (new CFormList('encryption'))
	->addRow(_('Connections to host'),
		(new CRadioButtonList('tls_connect', (int) $parent_host['tls_connect']))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
			->setEnabled(false)
	)
	->addRow(_('Connections from host'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setLabel(_('No encryption'))
				->setAttribute('disabled', 'disabled')
			)
			->addItem((new CCheckBox('tls_in_psk'))
				->setLabel(_('PSK'))
				->setAttribute('disabled', 'disabled')
			)
			->addItem((new CCheckBox('tls_in_cert'))
				->setLabel(_('Certificate'))
				->setAttribute('disabled', 'disabled')
			)
	)
	->addRow(_('PSK'),
		(new CSimpleButton(_('Change PSK')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(false),
		null,
		'tls_psk'
	)
	->addRow(_('Issuer'),
		(new CTextBox('tls_issuer', $parent_host['tls_issuer'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	)
	->addRow(_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', $parent_host['tls_subject'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	);

$tabs->addTab('encryptionTab', _('Encryption'), $encryption_tab, TAB_INDICATOR_ENCRYPTION);

if ($host_prototype['hostid'] != 0) {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			(new CButtonDelete(
				_('Delete selected host prototype?'),
				url_params(['form', 'hostid', 'parent_discoveryid', 'context']), 'context'
			))->setEnabled($host_prototype['templateid'] == 0),
			new CButtonCancel(url_params(['parent_discoveryid', 'context']))
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_params(['parent_discoveryid', 'context']))]
	));
}

$form->addItem($tabs);
$widget->addItem($form);

$widget->show();
