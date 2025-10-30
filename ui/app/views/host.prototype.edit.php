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
 * @var array $data
 */

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('host')))->removeId())
	->setId('host-prototype-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', $data['form_action'])
		->getUrl()
	)
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addvar('context', $data['context'])
	->addVar('parent_discoveryid', $data['discovery_rule']['itemid']);

if ($data['hostid'] != 0) {
	$form->addVar('hostid', $data['hostid']);
}

$host_tab = new CFormGrid();

if ($data['is_discovered_prototype']) {
	$discovered_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'host.prototype.edit')
		->setArgument('parent_discoveryid', $data['discovery_rule']['discoveryData']['parent_itemid'])
		->setArgument('hostid', $data['host_prototype']['discoveryData']['parent_hostid'])
		->setArgument('context', $data['context'])
		->getUrl();

	$host_tab->addItem([
		new CLabel(_('Discovered by')),
		new CFormField(new CLink($data['discovery_rule']['name'], $discovered_url))
	]);
}

if ($data['templates']) {
	$host_tab->addItem([
		new CLabel(_('Parent discovery rules')),
		new CFormField($data['templates'])
	]);
}

$host_tab->addItem([
		(new CLabel(_('Host name'), 'host'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('host', $data['host_prototype']['host'], $data['readonly'],
				DB::getFieldLength('hosts', 'host'))
			)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('data-prevent-validation-on-change', $data['readonly'] ? 1 : null)
		)
	]);

$name = ($data['host_prototype']['name'] !== $data['host_prototype']['host']) ? $data['host_prototype']['name'] : '';

$host_tab->addItem([
	new CLabel(_('Visible name'), 'name'),
	new CFormField(
		(new CTextBox('name', $name, $data['readonly'], DB::getFieldLength('hosts', 'name')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
]);

if ($data['host_prototype']['templates']) {
	$linked_templates = (new CTable())
		->setHeader([_('Name'), _('Action')])
		->setId('linked-templates')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setAttribute('data-field-type', 'array')
		->setAttribute('data-field-name', 'templates');

	foreach ($data['host_prototype']['templates'] as $template) {
		if ($data['user']['can_edit_templates'] && array_key_exists($template['templateid'],
				$data['editable_templates'])) {
			$template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $template['templateid'])
				->getUrl();

			$template_link = new CLink($template['name'], $template_url);
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$template_row = [$template_link];

		$template_row[] = (new CVar('templates[]', $template['templateid']))->removeId();

		$linked_templates->addRow([
			(new CCol($template_row))
				->addClass(ZBX_STYLE_WORDWRAP)
				->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
			(new CCol(
				new CHorList([
					(new CButtonLink(_('Unlink')))
						->setEnabled(!$data['readonly'])
						->addClass('js-unlink')
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		]);
	}

	$templates_field_items[] = $linked_templates;
}

$templates_field_items[] = (new CMultiSelect([
	'name' => 'add_templates[]',
	'object_name' => 'templates',
	'data' => array_key_exists('add_templates', $data['host_prototype'])
		? $data['host_prototype']['add_templates']
		: [],
	'readonly' => $data['readonly'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'templates',
			'srcfld1' => 'hostid',
			'srcfld2' => 'host',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'add_templates_',
			'disableids' => array_column($data['host_prototype']['templates'], 'templateid')
		]
	]
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$host_tab
	->addItem([
		new CLabel(_('Templates'), 'add_templates__ms'),
		new CFormField(
			count($templates_field_items) > 1
				? (new CDiv($templates_field_items))->addClass('linked-templates')
				: $templates_field_items
		)
]);

$host_tab->addItem([
	(new CLabel(_('Host groups'), 'group_links__ms'))->setAsteriskMark(),
	new CFormField(
		(new CMultiSelect([
			'name' => 'group_links[]',
			'object_name' => 'hostGroup',
			'readonly' => $data['readonly'],
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
	)
]);

$host_tab
	->addItem([
		new CLabel(_('Group prototypes'), 'group_prototypes'),
		new CFormField([
			(new CDiv(
				(new CTable())
					->setId('group_prototypes_table')
					->addRow(
						(new CRow())
							->addItem(
								(new CCol(
									(new CButton('group_prototype_add', _('Add')))
										->addClass(ZBX_STYLE_BTN_LINK)
										->addClass('element-table-add')
										->setEnabled(!$data['readonly'])
								))->setAttribute('colspan', 2)
							)
					)
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'group_prototypes')
				->setAttribute('data-prevent-validation-on-change', $data['readonly'] ? 1 : null),
			(new CTemplateTag('group-prototype-row-tmpl'))
				->addItem(
					(new CRow([
						new CCol([
							(new CTextBox('group_prototypes[#{row_index}][name]', '#{name}', $data['readonly']))
								->addStyle('width: 448px')
								->setAttribute('placeholder', '{#MACRO}')
								->setErrorContainer('group_prototypes_#{row_index}_error_container'),
							(new CInput('hidden', 'group_prototypes[#{row_index}][group_prototypeid]',
								'#{group_prototypeid}'
							))->setAttribute('data-field-type', 'hidden')
						]),
						(new CCol(
							(new CButtonLink(_('Remove')))
								->setAttribute('name', 'remove')
								->addClass('element-table-remove')
								->setEnabled(!$data['readonly'])
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
				->addItem(
					new CRow(
						(new CCol())
							->setId('group_prototypes_#{row_index}_error_container')
							->addClass(ZBX_STYLE_ERROR_CONTAINER)
							->setColSpan(2)
					)
				)
		])
	])
	->addItem([
		new CLabel(_('Interfaces')),
		new CFormField([
			(new CRadioButtonList('custom_interfaces', (int) $data['host_prototype']['custom_interfaces']))
				->addValue(_('Inherit'), HOST_PROT_INTERFACES_INHERIT)
				->addValue(_('Custom'), HOST_PROT_INTERFACES_CUSTOM)
				->setModern()
				->setReadonly($data['readonly']),
			(new CDiv([
				(new CDiv([
					renderInterfaceHeaders(),
					(new CDiv())
						->setId('agentInterfaces')
						->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
						->setAttribute('data-type', 'agent'),
					(new CDiv())
						->setId('SNMPInterfaces')
						->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER.' '.ZBX_STYLE_LIST_VERTICAL_ACCORDION)
						->setAttribute('data-type', 'snmp'),
					(new CDiv())
						->setId('JMXInterfaces')
						->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
						->setAttribute('data-type', 'jmx'),
					(new CDiv())
						->setId('IPMIInterfaces')
						->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
						->setAttribute('data-type', 'ipmi')
				]))
					->setId('interfaces_table')
					->addClass(ZBX_STYLE_HOST_INTERFACES),
				(new CButtonLink(_('Add')))
					->addClass('add-interface')
					->setMenuPopup([
						'type' => 'submenu',
						'data' => [
							'submenu' => getAddNewInterfaceSubmenu()
						]
					])
					->setAttribute('aria-label', _('Add new interface'))
					->addStyle($data['host_prototype']['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM
						? null
						: 'display: none'
					)
					->setEnabled(!$data['readonly'])
			]))
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'interfaces')
				->setAttribute('data-prevent-validation-on-change', $data['readonly'] ? 1 : null),
			(new CInput('hidden', 'main_interface_'.INTERFACE_TYPE_AGENT, 0))
				->setAttribute('data-prevent-validation-on-change', 1)
				->setAttribute('data-field-type', 'hidden'),
			(new CInput('hidden', 'main_interface_'.INTERFACE_TYPE_SNMP, 0))
				->setAttribute('data-prevent-validation-on-change', 1)
				->setAttribute('data-field-type', 'hidden'),
			(new CInput('hidden', 'main_interface_'.INTERFACE_TYPE_IPMI, 0))
				->setAttribute('data-prevent-validation-on-change', 1)
				->setAttribute('data-field-type', 'hidden'),
			(new CInput('hidden', 'main_interface_'.INTERFACE_TYPE_JMX, 0))
				->setAttribute('data-prevent-validation-on-change', 1)
				->setAttribute('data-field-type', 'hidden')
		])
	])
	->addItem((new CTemplateTag('host-interface-row-tmpl'))->addItem(
		new CPartial('configuration.host.interface.row', ['is_snmp' => false])
	))
	->addItem((new CTemplateTag('host-interface-row-snmp-tmpl'))->addItem(
		new CPartial('configuration.host.interface.row', ['is_snmp' => true])
	));

// Display inherited parameters only for hosts prototypes on hosts.
if ($data['parent_host']['status'] != HOST_STATUS_TEMPLATE) {
	$host_tab->addItem([
		new CLabel(_('Monitored by')),
		new CFormField(
			(new CRadioButtonList('monitored_by', (int) $data['parent_host']['monitored_by']))
				->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
				->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
				->addValue(_('Proxy group'), ZBX_MONITORED_BY_PROXY_GROUP)
				->setReadonly(true)
				->setModern()
		)
	]);

	if ($data['parent_host']['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
		$host_tab->addItem(
			new CFormField(
				(new CMultiSelect([
					'name' => 'proxyid',
					'object_name' => 'proxies',
					'multiple' => false,
					'data' => $data['ms_proxy'],
					'disabled' => true,
					'popup' => [
						'parameters' => [
							'srctbl' => 'proxies',
							'srcfld1' => 'proxyid',
							'srcfld2' => 'name',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'proxyid'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		);
	}
	elseif ($data['parent_host']['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
		$host_tab->addItem(
			new CFormField(
				(new CMultiSelect([
					'name' => 'proxy_groupid',
					'object_name' => 'proxy_groups',
					'multiple' => false,
					'data' => $data['ms_proxy_group'],
					'disabled' => true,
					'popup' => [
						'parameters' => [
							'srctbl' => 'proxy_groups',
							'srcfld1' => 'proxy_groupid',
							'srcfld2' => 'name',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'proxy_groupid'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		);
	}
}

$host_tab
	->addItem([
		new CLabel(_('Create enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', HOST_STATUS_MONITORED))
				->setChecked($data['host_prototype']['status'] == HOST_STATUS_MONITORED)
				->setUncheckedValue(HOST_STATUS_NOT_MONITORED)
				->setReadonly($data['is_discovered_prototype'])
		)
	])
	->addItem([
		new CLabel(_('Discover'), 'discover'),
		new CFormField(
			(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))
				->setChecked($data['host_prototype']['discover'] == ZBX_PROTOTYPE_DISCOVER)
				->setUncheckedValue(ZBX_PROTOTYPE_NO_DISCOVER)
				->setReadonly($data['is_discovered_prototype'])
		)
	]);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('host-tab', _('Host'), $host_tab);

if ($data['parent_host']['status'] != HOST_STATUS_TEMPLATE) {
	$ipmi_tab = (new CFormGrid())
		->addItem([
			new CLabel(_('Authentication algorithm'), 'label_ipmi_authtype'),
			new CFormField(
				(new CSelect('ipmi_authtype'))
					->setValue($data['parent_host']['ipmi_authtype'])
					->setFocusableElementId('label_ipmi_authtype')
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
					->setReadonly()
					->setId('ipmi_authtype')
			)
		])
		->addItem([
			new CLabel(_('Privilege level'), 'label_ipmi_privilege'),
			new CFormField(
				(new CSelect('ipmi_privilege'))
					->setValue($data['parent_host']['ipmi_privilege'])
					->setFocusableElementId('label_ipmi_privilege')
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
					->setReadonly()
					->setId('ipmi_privilege')
			)
		])
		->addItem([
			new CLabel(_('Username'), 'ipmi_username'),
			new CFormField(
				(new CTextBox('ipmi_username', $data['parent_host']['ipmi_username'], true))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setId('ipmi_username')
			)
		])
		->addItem([
			new CLabel(_('Password'), 'ipmi_password'),
			new CFormField(
				(new CTextBox('ipmi_password', $data['parent_host']['ipmi_password'], true))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setId('ipmi_password')
			)
		]);

	$tabs->addTab('ipmi-tab', _('IPMI'), $ipmi_tab, TAB_INDICATOR_IPMI);
}

$tabs->addTab('tags-tab', _('Tags'),
	new CPartial('configuration.tags.tab', [
		'source' => 'host_prototype',
		'tags' => $data['host_prototype']['tags'],
		'show_inherited_tags' => $data['show_inherited_tags'],
		'readonly' => $data['readonly'],
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab',
		'has_inline_validation' => true
	]),
	TAB_INDICATOR_TAGS
);

$macro_tab = (new CFormGrid())
	->addItem(
		(new CFormField(
			(new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
				->addValue(_('Host prototype macros'), 0)
				->addValue(_('Inherited and host prototype macros'), 1)
				->setModern()
		))->addStyle('grid-column: 2 / -3')
	)
	->addItem(
		(new CFormField(
			new CPartial(
				$data['show_inherited_macros'] ? 'hostmacros.inherited.list.html' : 'hostmacros.list.html',
				[
					'macros' => $data['host_prototype']['macros'],
					'parent_hostid' => $data['parent_host']['hostid'],
					'readonly' => $data['readonly']
				]
			)
		))
			->addStyle('grid-column: 2 / -3')
			->setId('macros_container')
	);

if (!$data['readonly']) {
	// "rowNum" is built-in variable dynamic rows.

	$macro_row_tmpl = (new CTemplateTag('macro-row-tmpl'))
		->addItem(
			(new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->addClass('macro')
						->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
						->setAttribute('placeholder', '{$MACRO}')
						->disableSpellcheck()
						->setErrorLabel(_('Macro'))
				]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->setErrorLabel(_('Value'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
						->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
						->setAttribute('placeholder', _('description'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					new CHorList([
						(new CButton('macros[#{rowNum}][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->addClass('form_row')
		)
		->addItem(
			new CRow(
				(new CCol())
					->setId('macros_#{rowNum}_error_container')
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
					->setColSpan(4)
			)
		);

	$macro_row_inherited_tmpl = (new CTemplateTag('macro-row-tmpl-inherited'))
		->addItem(
			(new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->addClass('macro')
						->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
						->setAttribute('placeholder', '{$MACRO}')
						->disableSpellcheck()
						->setErrorLabel(_('Macro')),
					new CInput('hidden', 'macros[#{rowNum}][inherited_type]', ZBX_PROPERTY_OWN)
				]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->setErrorLabel(_('Value'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CButton('macros[#{rowNum}][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP),
				[
					new CCol(
						(new CDiv())
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					),
					new CCol(),
					new CCol(
						(new CDiv())
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					)
				]
			]))->addClass('form_row')
		)
		->addItem(
			(new CRow([
				(new CCol(
					(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
						->setErrorContainer('macros_#{rowNum}_error_container')
						->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('description'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)->setColSpan(8)
			]))->addClass('form_row')
		)
		->addItem(
			new CRow(
				(new CCol())
					->setId('macros_#{rowNum}_error_container')
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
					->setColSpan(4)
			)
		);

	$macro_tab
		->addItem($macro_row_tmpl)
		->addItem($macro_row_inherited_tmpl);
}

$tabs
	->addTab('macros-tab', _('Macros'), $macro_tab, TAB_INDICATOR_HOST_PROTOTYPE_MACROS)
	->addTab('inventory-tab', _('Inventory'),
		(new CFormGrid())
			->addItem(
				(new CFormField(
					(new CRadioButtonList('inventory_mode', (int) $data['host_prototype']['inventory_mode']))
						->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
						->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
						->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
						->setReadonly($data['readonly'])
						->setModern()
				))->addStyle('grid-column: 2 / -3')
			),
			TAB_INDICATOR_INVENTORY
	);

$tls_accept = (int) $data['parent_host']['tls_accept'];
$is_psk_set = $data['parent_host']['tls_connect'] == HOST_ENCRYPTION_PSK || $tls_accept & HOST_ENCRYPTION_PSK;
$is_cert_set = $data['parent_host']['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE
	|| $tls_accept & HOST_ENCRYPTION_CERTIFICATE;

$encryption_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Connections to host')),
		new CFormField([
			(new CRadioButtonList('tls_connect', (int) $data['parent_host']['tls_connect']))
				->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
				->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
				->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
				->setReadonly(true)
				->setModern()
		])
	])
	->addItem([
		new CLabel(_('Connections from host')),
		new CFormField([
			(new CList([
				(new CCheckBox('tls_in_none'))
					->setChecked($tls_accept & HOST_ENCRYPTION_NONE)
					->setLabel(_('No encryption'))
					->setReadonly(true),
				(new CCheckBox('tls_in_psk'))
					->setChecked($tls_accept & HOST_ENCRYPTION_PSK)
					->setLabel(_('PSK'))
					->setReadonly(true),
				(new CCheckBox('tls_in_cert'))
					->setChecked($tls_accept & HOST_ENCRYPTION_CERTIFICATE)
					->setLabel(_('Certificate'))
					->setReadonly(true)
			]))
				->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
		])
	]);

if ($is_psk_set) {
	$encryption_tab->addItem(
		[
			(new CLabel(_('PSK'), 'change_psk')),
			new CFormField(
				(new CSimpleButton(_('Change PSK')))
					->setId('change_psk')
					->addClass(ZBX_STYLE_BTN_GREY)
					->setEnabled(false)
			)
		]
	);
}

if ($is_cert_set) {
	$encryption_tab
		->addItem([
			new CLabel(_('Issuer'), 'tls_issuer'),
			new CFormField(
				(new CTextBox('tls_issuer', $data['parent_host']['tls_issuer'], true,
					DB::getFieldLength('hosts', 'tls_issuer')
				))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			)
		])
		->addItem([
			new CLabel(_x('Subject', 'encryption certificate'), 'tls_subject'),
			new CFormField(
				(new CTextBox('tls_subject', $data['parent_host']['tls_subject'], true,
					DB::getFieldLength('hosts', 'tls_subject')
				))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			)
	]);
}

$tabs->addTab('encryption-tab', _('Encryption'), $encryption_tab, TAB_INDICATOR_ENCRYPTION);

if ($data['hostid'] == 0) {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_prototype_edit_popup.submit();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_prototype_edit_popup.submit();',
			'enabled' => !$data['is_discovered_prototype']
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_prototype_edit_popup.clone();',
			'enabled' => !$data['is_discovered_prototype']
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete host prototype?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_prototype_edit_popup.delete();',
			'enabled' => $data['host_prototype']['templateid'] == 0
		]
	];
}

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag(
			'host_prototype_edit_popup.init('.json_encode([
				'rules' => $data['js_validation_rules'],
				'group_prototypes' => $data['host_prototype']['groupPrototypes'],
				'inherited_interfaces' => array_values($data['parent_host']['interfaces']),
				'custom_interfaces' => array_values($data['host_prototype']['interfaces']),
				'parent_is_template' => $data['parent_host']['status'] == HOST_STATUS_TEMPLATE,
				'parent_hostid' => $data['parent_host']['hostid'],
				'readonly' => $data['readonly'],
				'warnings' => $data['warnings']
			], JSON_THROW_ON_ERROR).');'
		))->setOnDocumentReady()
	);

$output = [
	'header' => $data['hostid'] == 0 ? _('New host prototype') : _('Host prototype'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_PROTOTYPE_EDIT),
	'body' => $form->toString(),
	'script_inline' => getPagePostJs().$this->readJsFile('host.prototype.edit.js.php'),
	'buttons' => $buttons,
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
