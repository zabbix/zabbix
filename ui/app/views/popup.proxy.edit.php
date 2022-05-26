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
 * @var array $data
 */

$form = (new CForm('post'))
	->setId('proxy-form')
	->setName('proxy_form')
	->addStyle('display: none;')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

// Proxy tab.

$interface = (new CTable())
	->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')])
	->addRow([
		(new CTextBox('ip', $data['form']['interface']['ip'], false, DB::getFieldLength('interface', 'ip')))
			->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH),
		(new CTextBox('dns', $data['form']['interface']['dns'], false, DB::getFieldLength('interface', 'dns')))
			->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH),
		(new CRadioButtonList('useip', (int) $data['form']['interface']['useip']))
			->addValue('IP', INTERFACE_USE_IP)
			->addValue('DNS', INTERFACE_USE_DNS)
			->setModern(true),
		(new CTextBox('port', $data['form']['interface']['port'], false, DB::getFieldLength('interface', 'port')))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
			->setAriaRequired()
	]);

$proxy_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Proxy name'), 'host'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('host', $data['form']['host'], false, DB::getFieldLength('hosts', 'host')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Proxy mode'), 'status'),
		new CFormField(
			(new CRadioButtonList('status', $data['form']['status']))
				->addValue(_('Active'), HOST_STATUS_PROXY_ACTIVE)
				->addValue(_('Passive'), HOST_STATUS_PROXY_PASSIVE)
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
		(new CLabel(_('Proxy address'), 'proxy_address'))->addClass('js-proxy-address'),
		(new CFormField(
			(new CTextBox('proxy_address', $data['form']['proxy_address'], false,
				DB::getFieldLength('hosts', 'proxy_address')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-proxy-address')
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setMaxlength(DB::getFieldLength('hosts', 'description'))
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
				DB::getFieldLength('hosts', 'tls_psk_identity')
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
			(new CTextBox('tls_psk', $data['form']['tls_psk'], false, DB::getFieldLength('hosts', 'tls_psk')))
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

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('proxy-tab', _('Proxy'), $proxy_tab)
	->addTab('proxy-encryption-tab', _('Encryption'), $encryption_tab, TAB_INDICATOR_PROXY_ENCRYPTION);

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
		$this->readJsFile('popup.proxy.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
