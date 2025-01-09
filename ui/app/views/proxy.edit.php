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

$form = (new CForm('post'))
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('proxy')))->removeId())
	->setId('proxy-form')
	->setName('proxy_form')
	->addStyle('display: none;')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

// Proxy tab.

$local_address = (new CTable())
	->setHeader([_('Address'), _('Port')])
	->addRow([
		(new CTextBox('local_address', $data['form']['local_address'], false,
			DB::getFieldLength('proxy', 'local_address')
		))->setWidth(336),
		(new CTextBox('local_port', $data['form']['local_port'], false, DB::getFieldLength('proxy', 'local_port')))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
			->setAriaRequired()
	]);
$interface = (new CTable())
	->setHeader([_('Address'), _('Port')])
	->addRow([
		(new CTextBox('address', $data['form']['address'], false, DB::getFieldLength('proxy', 'address')))
			->setWidth(336),
		(new CTextBox('port', $data['form']['port'], false, DB::getFieldLength('proxy', 'port')))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
			->setAriaRequired()
	]);

$proxy_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Proxy name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('proxy', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Proxy group'), 'proxy_groupid_ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'proxy_groupid',
				'object_name' => 'proxy_groups',
				'multiple' => false,
				'data' => $data['ms_proxy_group'],
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
	])
	->addItem([
		(new CLabel(_('Address for active agents')))
			->addClass('js-local-address')
			->setAsteriskMark(),
		(new CFormField(
			(new CDiv($local_address))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))->addClass('js-local-address')
	])
	->addItem([
		new CLabel(_('Proxy mode'), 'operating_mode'),
		new CFormField(
			(new CRadioButtonList('operating_mode', $data['form']['operating_mode']))
				->addValue(_('Active'), PROXY_OPERATING_MODE_ACTIVE)
				->addValue(_('Passive'), PROXY_OPERATING_MODE_PASSIVE)
				->setModern(true)
		)
	])
	->addItem([
		(new CLabel(_('Interface')))
			->addClass('js-interface')
			->setAsteriskMark(),
		(new CFormField(
			(new CDiv($interface))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))->addClass('js-interface')
	])
	->addItem([
		(new CLabel(_('Proxy address'), 'allowed_addresses'))->addClass('js-proxy-address'),
		(new CFormField(
			(new CTextBox('allowed_addresses', $data['form']['allowed_addresses'], false,
				DB::getFieldLength('proxy', 'allowed_addresses')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-proxy-address')
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setMaxlength(DB::getFieldLength('proxy', 'description'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

// Encryption tab.

$encryption_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Connections to proxy'), 'tls_connect'),
		new CFormField(
			(new CRadioButtonList('tls_connect', $data['form']['tls_connect']))
				->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
				->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
				->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
				->setModern(true)
		)
	])
	->addItem([
		new CLabel(_('Connections from proxy')),
		new CFormField(
			(new CList())
				->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
				->addItem(
					(new CCheckBox('tls_accept_none'))
						->setLabel(_('No encryption'))
						->setChecked(($data['form']['tls_accept'] & HOST_ENCRYPTION_NONE) != 0)
				)
				->addItem(
					(new CCheckBox('tls_accept_psk'))
						->setLabel(_('PSK'))
						->setChecked(($data['form']['tls_accept'] & HOST_ENCRYPTION_PSK) != 0)
				)
				->addItem(
					(new CCheckBox('tls_accept_certificate'))
						->setLabel(_('Certificate'))
						->setChecked(($data['form']['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0)
				)
		)
	])
	->addItem([
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))
			->addClass('js-tls-psk-identity')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('tls_psk_identity', $data['form']['tls_psk_identity'], false,
				DB::getFieldLength('proxy', 'tls_psk_identity')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->addClass('js-tls-psk-identity')
	])
	->addItem([
		(new CLabel(_('PSK'), 'tls_psk'))
			->addClass('js-tls-psk')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('tls_psk', $data['form']['tls_psk'], false, DB::getFieldLength('proxy', 'tls_psk')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->disableAutocomplete()
		))->addClass('js-tls-psk')
	]);

if ($data['form']['tls_connect'] == HOST_ENCRYPTION_PSK
		|| ($data['form']['tls_accept'] & HOST_ENCRYPTION_PSK) != 0) {
	$encryption_tab->addItem([
		(new CLabel(_('PSK')))
			->addClass('js-tls-psk-change')
			->setAsteriskMark(),
		(new CFormField(
			(new CSimpleButton(_('Change PSK')))
				->setId('tls-psk-change')
				->addClass(ZBX_STYLE_BTN_GREY)
		))->addClass('js-tls-psk-change')
	]);
}

$encryption_tab
	->addItem([
		(new CLabel(_('Issuer'), 'tls_issuer'))->addClass('js-tls-issuer'),
		(new CFormField(
			(new CTextBox('tls_issuer', $data['form']['tls_issuer'], false,
				DB::getFieldLength('hosts', 'tls_issuer')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-tls-issuer')
	])
	->addItem([
		(new CLabel(_x('Subject', 'encryption certificate'), 'tls_subject'))->addClass('js-tls-subject'),
		(new CFormField(
			(new CTextBox('tls_subject', $data['form']['tls_subject'], false,
				DB::getFieldLength('hosts', 'tls_subject')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-tls-subject')
	]);

// Timeouts tab.
$custom_timeouts_disabled = $data['form']['custom_timeouts'] == ZBX_PROXY_CUSTOM_TIMEOUTS_DISABLED;
$version_mismatch_hint = $data['version_mismatch']
	? new CSpan(makeWarningIcon(_('Timeouts are disabled because the proxy and server versions do not match.')))
	: null;

$timeouts_tab = (new CFormGrid())
	->addItem([
		new CLabel([_('Timeouts for item types'), $version_mismatch_hint], 'custom_timeouts'),
		new CFormField([
			(new CRadioButtonList('custom_timeouts', $data['form']['custom_timeouts']))
				->addValue(_('Global'), ZBX_PROXY_CUSTOM_TIMEOUTS_DISABLED)
				->addValue(_('Override'), ZBX_PROXY_CUSTOM_TIMEOUTS_ENABLED)
				->setModern()
				->setEnabled(!$data['version_mismatch']),
			$data['user']['can_edit_global_timeouts']
				? (new CLink(_('Global timeouts'),
					(new CUrl('zabbix.php'))->setArgument('action', 'timeouts.edit')
				))
					->addClass(ZBX_STYLE_LINK)
					->setTarget('_blank')
				: null
		])
	])
	->addItem([
		(new CLabel(_('Zabbix agent'), 'timeout_zabbix_agent'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_zabbix_agent', $data['form']['timeout_zabbix_agent'], false,
				DB::getFieldLength('proxy', 'timeout_zabbix_agent')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Simple check'), 'timeout_simple_check'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_simple_check', $data['form']['timeout_simple_check'], false,
				DB::getFieldLength('proxy', 'timeout_simple_check')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('SNMP agent'), 'timeout_snmp_agent'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_snmp_agent', $data['form']['timeout_snmp_agent'], false,
				DB::getFieldLength('proxy', 'timeout_snmp_agent')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('External check'), 'timeout_external_check'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_external_check', $data['form']['timeout_external_check'], false,
				DB::getFieldLength('proxy', 'timeout_external_check')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Database monitor'), 'timeout_db_monitor'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_db_monitor', $data['form']['timeout_db_monitor'], false,
				DB::getFieldLength('proxy', 'timeout_db_monitor')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('HTTP agent'), 'timeout_http_agent'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_http_agent', $data['form']['timeout_http_agent'], false,
				DB::getFieldLength('proxy', 'timeout_http_agent')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('SSH agent'), 'timeout_ssh_agent'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_ssh_agent', $data['form']['timeout_ssh_agent'], false,
				DB::getFieldLength('proxy', 'timeout_ssh_agent')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('TELNET agent'), 'timeout_telnet_agent'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_telnet_agent', $data['form']['timeout_telnet_agent'], false,
				DB::getFieldLength('proxy', 'timeout_telnet_agent')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Script'), 'timeout_script'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_script', $data['form']['timeout_script'], false,
				DB::getFieldLength('proxy', 'timeout_script')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Browser'), 'timeout_browser'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout_browser', $data['form']['timeout_browser'], false,
				DB::getFieldLength('proxy', 'timeout_browser')
			))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setReadonly($custom_timeouts_disabled)
				->setAriaRequired()
		)
	]);

$tabs = (new CTabView(['id' => 'proxy-tabs']))
	->setSelected(0)
	->addTab('proxy-tab', _('Proxy'), $proxy_tab)
	->addTab('proxy-encryption-tab', _('Encryption'), $encryption_tab, TAB_INDICATOR_PROXY_ENCRYPTION)
	->addTab('proxy-timeouts-tab', _('Timeouts'), $timeouts_tab, TAB_INDICATOR_PROXY_TIMEOUTS);

// Output.

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			proxy_edit_popup.init('.json_encode([
				'proxyid' => $data['proxyid']
			]).');
		'))->setOnDocumentReady()
	);

if ($data['proxyid'] !== null) {
	$title = _('Proxy');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'proxy_edit_popup.submit();'
		],
		[
			'title' => _('Refresh configuration'),
			'confirmation' => _('Refresh configuration of the selected proxy?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-refresh-config']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'proxy_edit_popup.refreshConfig();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'proxy_edit_popup.clone('.json_encode([
				'title' => _('New proxy'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'proxy_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected proxy?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'proxy_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New proxy');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'proxy_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::ADMINISTRATION_PROXY_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('proxy.edit.js.php'),
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
