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

$form_action = (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.ldap.check')
	->getUrl();

$form = (new CForm('post', $form_action))
	->addItem(getMessages())
	->addVar('row_index', $data['row_index'])
	->addVar('userdirectoryid', $data['userdirectoryid']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form
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
				(new CTextBox('host', $data['host'], false, DB::getFieldLength('userdirectory_ldap', 'host')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Port'), 'port'))->setAsteriskMark(),
			new CFormField(
				(new CNumericBox('port', $data['port'], 5, false, false, false))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Base DN'), 'base_dn'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('base_dn', $data['base_dn'], false, DB::getFieldLength('userdirectory_ldap', 'base_dn')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			(new CLabel(_('Search attribute'), 'search_attribute'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('search_attribute', $data['search_attribute'], false,
					DB::getFieldLength('userdirectory_ldap', 'search_attribute')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
		])
		->addItem([
			new CLabel(_('Bind DN'), 'bind_dn'),
			new CFormField(
				(new CTextBox('bind_dn', $data['bind_dn'], false, DB::getFieldLength('userdirectory_ldap', 'bind_dn')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Bind password'), 'bind_password'),
			new CFormField($data['add_ldap_server'] == 0
				? [
					array_key_exists('bind_password', $data)
						? (new CVar('bind_password', $data['bind_password']))->removeId()
						: null,
					(new CSimpleButton(_('Change password')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->setId('bind-password-btn'),
					(new CPassBox('bind_password', '', DB::getFieldLength('userdirectory_ldap', 'bind_password')))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->addStyle('display: none;')
						->setAttribute('disabled', 'disabled')
				]
				: (new CPassBox('bind_password', '', DB::getFieldLength('userdirectory_ldap', 'bind_password')))
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
			new CLabel(_('Configure JIT provisioning'), 'provision_status'),
			new CFormField(
				(new CCheckBox('provision_status', JIT_PROVISIONING_ENABLED))
					->setChecked($data['provision_status'] == JIT_PROVISIONING_ENABLED)
			)
		])
		->addItem([
			(new CLabel([_('Group configuration'), makeHelpIcon(
				_('memberOf is a preferable way to configure groups because it is faster. Use groupOfNames if your LDAP server does not support memberOf or group filtering is required.')
			)], 'group_configuration'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CRadioButtonList('group_configuration', $data['group_configuration']))
					->setId('group-configuration')
					->addValue('memberOf', CControllerPopupLdapEdit::LDAP_MEMBER_OF)
					->addValue('groupOfNames', CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES)
					->setModern(true)
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('Group base DN'), 'group_basedn'))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names'),
			(new CFormField(
				(new CTextBox('group_basedn', $data['group_basedn'], false,
					DB::getFieldLength('userdirectory_ldap', 'group_basedn')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names')
		])
		->addItem([
			(new CLabel(_('Group name attribute'), 'group_name'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('group_name', $data['group_name'], false,
					DB::getFieldLength('userdirectory_ldap', 'group_name')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('Group member attribute'), 'group_member'))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names'),
			(new CFormField(
				(new CTextBox('group_member', $data['group_member'], false,
					DB::getFieldLength('userdirectory_ldap', 'group_member')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names')
		])
		->addItem([
			(new CLabel([_('Reference attribute'),
				makeHelpIcon(_('Use %{ref} in group filter to reference value of this user attribute.'))
			], 'user_ref_attr'))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names'),
			(new CFormField(
				(new CTextBox('user_ref_attr', $data['user_ref_attr'], false,
					DB::getFieldLength('userdirectory_ldap', 'user_ref_attr')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names')
		])
		->addItem([
			(new CLabel(_('Group filter'), 'group_filter'))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names'),
			(new CFormField(
				(new CTextBox('group_filter', $data['group_filter'], false,
					DB::getFieldLength('userdirectory_ldap', 'group_filter')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', CLdap::DEFAULT_FILTER_GROUP)
			))
				->addClass('allow-jit-provisioning')
				->addClass('group-of-names')
		])
		->addItem([
			(new CLabel(_('User group membership attribute'), 'group_membership'))
				->addClass('allow-jit-provisioning')
				->addClass('member-of'),
			(new CFormField(
				(new CTextBox('group_membership', $data['group_membership'], false,
					DB::getFieldLength('userdirectory_ldap', 'group_membership')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', CLdap::DEFAULT_MEMBERSHIP_ATTRIBUTE)
			))
				->addClass('allow-jit-provisioning')
				->addClass('member-of')
		])
		->addItem([
			(new CLabel(_('User name attribute'), 'user_username'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('user_username', $data['user_username'], false,
					DB::getFieldLength('userdirectory_ldap', 'user_username')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('User last name attribute'), 'user_lastname'))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CTextBox('user_lastname', $data['user_lastname'], false,
					DB::getFieldLength('userdirectory_ldap', 'user_lastname')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))->addClass('allow-jit-provisioning')
		])
		->addItem([
			(new CLabel(_('User group mapping')))
				->addClass('allow-jit-provisioning')
				->setAsteriskMark(),
			(new CFormField(
				(new CDiv(
					(new CTable())
						->setId('ldap-user-groups-table')
						->setHeader(
							(new CRowHeader([
								_('LDAP group pattern'), _('User groups'), _('User role'), _('Action')
							]))->addClass(ZBX_STYLE_GREY)
						)
						->addItem(
							(new CTag('tfoot', true))->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))->addClass('js-add')
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
			(new CLabel([
				_('Media type mapping'),
				makeHelpIcon(
					_("Map user's LDAP media attributes (e.g. email) to Zabbix user media for sending notifications.")
			)]))->addClass('allow-jit-provisioning'),
			(new CFormField(
				(new CDiv(
					(new CTable())
						->setId('ldap-media-type-mapping-table')
						->setHeader(
							(new CRowHeader([
								_('Name'), _('Media type'), _('Attribute'), _('Action')
							]))->addClass(ZBX_STYLE_GREY)
						)
						->addItem(
							(new CTag('tfoot', true))->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))->addClass('js-add')
								))->setColSpan(5)
							)
						)
						->addStyle('width: 100%;')
				))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			))->addClass('allow-jit-provisioning')
		])
		->addItem(
			(new CFormFieldsetCollapsible(_('Advanced configuration')))
				->setId('advanced-configuration')
				->addItem([
					new CLabel(_('StartTLS'), 'start_tls'),
					new CFormField(
						(new CCheckBox('start_tls', ZBX_AUTH_START_TLS_ON))
							->setChecked($data['start_tls'] == ZBX_AUTH_START_TLS_ON)
					)
				])
				->addItem([
					new CLabel(_('Search filter'), 'search_filter'),
					new CFormField(
						(new CTextBox('search_filter', $data['search_filter'], false,
							DB::getFieldLength('userdirectory_ldap', 'search_filter')
						))
							->setAttribute('placeholder', CLdap::DEFAULT_FILTER_USER)
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					)
				])
		)
	)
	->addItem(
		(new CScriptTag('
			ldap_edit_popup.init('.json_encode([
				'provision_groups' => $data['provision_groups'],
				'provision_media' => $data['provision_media']
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
