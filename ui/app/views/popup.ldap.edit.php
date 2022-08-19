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
			new CLabel(_('Allow JIT provisioning'), 'allow_jit_provisioning'),
			new CFormField(
				(new CCheckBox('allow_jit_provisioning'))->setChecked($data['allow_jit_provisioning'])
			)
		])
		->addItem([
			(new CLabel(_('Group base DN'), 'group_base_dn'))
				->setAsteriskMark()
				->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('group_base_dn', $data['group_base_dn'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('Group name attribute'), 'group_name_attribute'))
				->setAsteriskMark()
				->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('group_name_attribute', $data['group_name_attribute'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('Group member attribute'), 'group_member_attribute'))
				->setAsteriskMark()
				->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('group_member_attribute', $data['group_member_attribute'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')

			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('Group filter'), 'group_filter'))
				->setAsteriskMark()
				->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('group_filter', $data['group_filter'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('User group membership attribute'), 'user_group_membership'))
				->setAsteriskMark()
				->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('user_group_membership', $data['user_group_membership'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('User name attribute'), 'user_name_attribute'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('user_name_attribute', $data['user_name_attribute'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('User last name attribute'), 'user_last_name_attribute'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('user_last_name_attribute', $data['user_last_name_attribute'], false,
//					DB::getFieldLength('userdirectory', 'name')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([(new CLabel(_('User group mapping')))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CDiv(
					(new CTable())
						->setId('ldap-user-groups-table')
						->setHeader(
							(new CRowHeader([
								'',
								(new CColHeader(_('LDAP group pattern ')))->addClass(ZBX_STYLE_LEFT)->addStyle('width: 35%'),
								(new CColHeader(_('User groups')))->addClass(ZBX_STYLE_LEFT)->addStyle('width: 35%'),
								(new CColHeader(_('User role')))->addClass(ZBX_STYLE_LEFT),
								(new CColHeader(_('Action')))->addClass(ZBX_STYLE_LEFT)
							]))->addClass(ZBX_STYLE_GREY)
						)
						->addItem(
							(new CTag('tfoot', true))
								->addItem(
									(new CCol(
										(new CSimpleButton(_('Add')))
											->addClass(ZBX_STYLE_BTN_LINK)
											->addClass('js-add')
									))->setColSpan(5)
								)
						)
						->addStyle('width: 100%;')
				))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			))->addClass('allow-jit-provisioning')
		])
		->addItem([(new CLabel([
				_('Media type mapping'),
				makeHelpIcon(
					_('Map user’s LDAP media attributes (e.g. email) to Zabbix user media for sending notifications.')
			)]))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CDiv(
					(new CTable())
						->setId('ldap-media-type-mapping-table')
						->setHeader(
							(new CRowHeader([
								(new CColHeader(_('Name ')))->addClass(ZBX_STYLE_LEFT)->addStyle('width: 35%'),
								(new CColHeader(_('Media type')))->addClass(ZBX_STYLE_LEFT)->addStyle('width: 35%'),
								(new CColHeader(_('Attribute')))->addClass(ZBX_STYLE_NOWRAP)->addClass(ZBX_STYLE_LEFT),
								(new CColHeader(_('')))->addClass(ZBX_STYLE_LEFT)
							]))->addClass(ZBX_STYLE_GREY)
						)
						->addItem(
							(new CTag('tfoot', true))
								->addItem(
									(new CCol(
										(new CSimpleButton(_('Add')))
											->addClass(ZBX_STYLE_BTN_LINK)
											->addClass('js-add')
									))->setColSpan(5)
								)
						)
						->addStyle('width: 100%;')
				))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			))->addClass('allow-jit-provisioning')
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
			ldap_edit_popup.init('. json_encode([
				'ldap_user_groups' => $data['ldap_user_groups'],
				'ldap_media_type_mappings' => $data['ldap_media_type_mappings']
			], JSON_FORCE_OBJECT) .');
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
