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

$form_action = (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.ldap.check')
	->getUrl();

$form = (new CForm('post', $form_action))
	->addItem(getMessages())
	->addItem((new CInput('submit'))->addStyle('display: none;'))
	->addVar('row_index', $data['row_index'])
	->addVar('userdirectoryid', $data['userdirectoryid'] ?? null)
	->addItem((new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['name'], false, DB::getFieldLength('userdirectory', 'name')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
		])
		->addItem([
			(new CLabel(_('Host'), 'host'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('host', $data['host'], false, DB::getFieldLength('userdirectory', 'host')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Port'), 'port'))->setAsteriskMark(),
			new CFormField(
				(new CNumericBox('port', $data['port'], 5))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Base DN'), 'base_dn'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('base_dn', $data['base_dn'], false, DB::getFieldLength('userdirectory', 'base_dn')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Search attribute'), 'search_attribute'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('search_attribute', $data['search_attribute'], false,
					DB::getFieldLength('userdirectory', 'search_attribute')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			new CLabel(_('Bind DN'), 'bind_dn'),
			new CFormField(
				(new CTextBox('bind_dn', $data['bind_dn'], false, DB::getFieldLength('userdirectory', 'bind_dn')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Bind password'), 'bind_password'),
			new CFormField($data['add_ldap_server'] == 0
				? [
					array_key_exists('bind_password', $data)
						? new CVar('bind_password', $data['bind_password'])
						: null,
					(new CSimpleButton(_('Change password')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->setId('bind-password-btn'),
					(new CPassBox('bind_password', '', DB::getFieldLength('userdirectory', 'bind_password')))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->addStyle('display: none;')
						->setAttribute('disabled', 'disabled')
				]
				: (new CPassBox('bind_password', '', DB::getFieldLength('userdirectory', 'bind_password')))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Description'), 'description'),
			new CFormField(
				(new CTextArea('description', $data['description'], ['rows' => 3]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setMaxlength(DB::getFieldLength('userdirectory', 'description'))
			)
		])
		->addItem([
			new CLabel(_('Advanced configuration'), 'advanced_configuration'),
			new CFormField(
				(new CCheckBox('advanced_configuration'))->setChecked($data['advanced_configuration'])
			)
		])
		->addItem([
			(new CLabel(_('StartTLS'), 'start_tls'))->addClass('advanced-configuration'),
			(new CFormField(
				(new CCheckBox('start_tls', ZBX_AUTH_START_TLS_ON))
					->setChecked($data['start_tls'] == ZBX_AUTH_START_TLS_ON)
			))->addClass('advanced-configuration')
		])
		->addItem([
			(new CLabel(_('Search filter'), 'search_filter'))->addClass('advanced-configuration'),
			(new CFormField(
				(new CTextBox('search_filter', $data['search_filter'], false,
					DB::getFieldLength('userdirectory', 'search_filter')
				))
					->setAttribute('placeholder', '(%{attr}=%{user})')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))->addClass('advanced-configuration')
		])
	)
	->addItem(
		(new CScriptTag('
			ldap_edit_popup.init();
		'))->setOnDocumentReady()
	);

if ($data['add_ldap_server']) {
	$title = _('New LDAP server');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ldap_edit_popup.submit();'
		]
	];
}
else {
	$title = _('LDAP Server');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ldap_edit_popup.submit();'
		]
	];
}

$buttons[] = [
	'title' => _('Test'),
	'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test']),
	'keepOpen' => true,
	'isSubmit' => false,
	'action' => 'ldap_edit_popup.openTestPopup();'
];

$output = [
	'header' => $title,
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.ldap.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
