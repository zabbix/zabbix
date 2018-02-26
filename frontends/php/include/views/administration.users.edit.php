<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


include('include/views/js/administration.users.edit.js.php');

if ($this->data['is_profile']) {
	$userWidget = ($this->data['name'] !== '' || $this->data['surname'] !== '')
		? (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$this->data['name'].' '.$this->data['surname'])
		: (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$this->data['alias']);
}
else {
	$userWidget = (new CWidget())->setTitle(_('Users'));
}

// create form
$userForm = (new CForm())
	->setName('userForm')
	->addVar('form', $this->data['form']);

if ($data['userid'] != 0) {
	$userForm->addVar('userid', $data['userid']);
}

/*
 * User tab
 */
$userFormList = new CFormList('userFormList');

if (!$data['is_profile']) {
	$userFormList->addRow(
		(new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
		(new CTextBox('alias', $this->data['alias']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		->setAttribute('maxlength', DB::getFieldLength('users', 'alias'))
	);
	$userFormList->addRow(_x('Name', 'user first name'),
		(new CTextBox('name', $this->data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
	);
	$userFormList->addRow(_('Surname'),
		(new CTextBox('surname', $this->data['surname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'surname'))
	);
}

// append user groups to form list
if (!$this->data['is_profile']) {
	$user_groups = [];

	foreach ($this->data['groups'] as $group) {
		$user_groups[] = CArrayHelper::renameKeys($group, ['usrgrpid' => 'id']);
	}

	$userFormList->addRow(
		(new CLabel(_('Groups'), 'user_groups[]'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'user_groups[]',
			'objectName' => 'usersGroups',
			'data' => $user_groups,
			'popup' => [
				'parameters' => [
					'srctbl' => 'usrgrp',
					'dstfrm' => $userForm->getName(),
					'dstfld1' => 'user_groups_',
					'srcfld1' => 'usrgrpid',
					'multiselect' => '1'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);
}

// append password to form list
if ($data['auth_type'] == ZBX_AUTH_INTERNAL) {
	if ($data['userid'] == 0 || isset($this->data['change_password'])) {
		$userFormList->addRow(
			(new CLabel(_('Password'), 'password1'))->setAsteriskMark(),
			(new CPassBox('password1', $this->data['password1']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		);
		$userFormList->addRow(
			(new CLabel(_('Password (once again)'), 'password2'))->setAsteriskMark(),
			(new CPassBox('password2', $this->data['password2']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		);

		if (isset($this->data['change_password'])) {
			$userForm->addVar('change_password', $this->data['change_password']);
		}
	}
	else {
		$passwdButton = (new CSimpleButton(_('Change password')))
			->onClick('javascript: submitFormWithParam("'.$userForm->getName().'", "change_password", "1");')
			->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['alias'] == ZBX_GUEST_USER) {
			$passwdButton->setAttribute('disabled', 'disabled');
		}

		$userFormList->addRow(_('Password'), $passwdButton);
	}
}
else {
	$userFormList->addRow(_('Password'),
		new CSpan(_s('Unavailable for users with %1$s.', authentication2str($data['auth_type'])))
	);
}

// append languages to form list
$languageComboBox = new CComboBox('lang', $this->data['lang']);

$allLocalesAvailable = true;
foreach (getLocales() as $localeId => $locale) {
	if ($locale['display']) {
		// checking if this locale exists in the system. The only way of doing it is to try and set one
		// trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC
		$localeExists = (setlocale(LC_MONETARY , zbx_locale_variants($localeId)) || $localeId == 'en_GB');

		$languageComboBox->addItem(
			$localeId,
			$locale['name'],
			($localeId == $this->data['lang']) ? true : null,
			$localeExists
		);

		$allLocalesAvailable &= $localeExists;
	}
}

// restoring original locale
setlocale(LC_MONETARY, zbx_locale_variants(CWebUser::$data['lang']));

$languageError = '';
if (!function_exists('bindtextdomain')) {
	$languageError = 'Translations are unavailable because the PHP gettext module is missing.';
	$languageComboBox->setAttribute('disabled', 'disabled');
}
elseif (!$allLocalesAvailable) {
	$languageError = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

$userFormList->addRow(
	_('Language'),
	$languageError
		? [$languageComboBox, SPACE, (new CSpan($languageError))->addClass('red')->addClass('wrap')]
		: $languageComboBox
);

// append themes to form list
$themes = array_merge([THEME_DEFAULT => _('System default')], Z::getThemes());
$userFormList->addRow(_('Theme'), new CComboBox('theme', $this->data['theme'], null, $themes));

// append auto-login & auto-logout to form list
$autologoutCheckBox = (new CCheckBox('autologout_visible'))->setChecked($data['autologout_visible']);
if ($data['autologout_visible']) {
	$autologoutTextBox = (new CTextBox('autologout', $data['autologout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
}
else {
	$autologoutTextBox = (new CTextBox('autologout', DB::getDefault('users', 'autologout')))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		->setAttribute('disabled', 'disabled');
}

if ($this->data['alias'] != ZBX_GUEST_USER) {
	$userFormList->addRow(_('Auto-login'), (new CCheckBox('autologin'))->setChecked($this->data['autologin']));
	$userFormList->addRow(_('Auto-logout'), [
		$autologoutCheckBox,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$autologoutTextBox
	]);
}

$userFormList
	->addRow((new CLabel(_('Refresh'), 'refresh'))->setAsteriskMark(),
		(new CTextBox('refresh', $data['refresh']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Rows per page'), 'rows_per_page'))->setAsteriskMark(),
		(new CNumericBox('rows_per_page', $this->data['rows_per_page'], 6))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('URL (after login)'),
		(new CTextBox('url', $this->data['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

/*
 * Media tab
 */
if (uint_in_array(CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
	$userMediaFormList = new CFormList('userMediaFormList');
	$userForm->addVar('user_medias', $this->data['user_medias']);

	$mediaTableInfo = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), ('Status'), _('Action')]);

	foreach ($this->data['user_medias'] as $id => $media) {
		if (!isset($media['active']) || !$media['active']) {
			$status = (new CLink(_('Enabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->onClick('return create_var("'.$userForm->getName().'","disable_media",'.$id.', true);');
		}
		else {
			$status = (new CLink(_('Disabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->onClick('return create_var("'.$userForm->getName().'","enable_media",'.$id.', true);');
		}

		$popup_options = [
			'dstfrm' => $userForm->getName(),
			'media' => $id,
			'mediatypeid' => $media['mediatypeid'],
			'period' => $media['period'],
			'severity' => $media['severity'],
			'active' => $media['active']
		];

		if ($media['mediatype'] == MEDIA_TYPE_EMAIL) {
			foreach ($media['sendto'] as $email) {
				$popup_options['sendto_emails'][] = $email;
			}
		}
		else {
			$popup_options['sendto'] = $media['sendto'];
		}

		$mediaSeverity = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityName = getSeverityName($severity, $this->data['config']);

			$mediaActive = ($media['severity'] & (1 << $severity));

			$mediaSeverity[$severity] = (new CSpan(mb_substr($severityName, 0, 1)))
				->setHint($severityName.' ('.($mediaActive ? _('on') : _('off')).')', '', false)
				->addClass($mediaActive ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY);
		}

		if ($media['mediatype'] == MEDIA_TYPE_EMAIL) {
			$media['sendto'] = implode(', ', $media['sendto']);
		}

		if (strlen($media['sendto']) > 50) {
			$media['sendto'] = (new CSpan(mb_substr($media['sendto'], 0, 50).'...'))->setHint($media['sendto']);
		}

		$mediaTableInfo->addRow(
			(new CRow([
				$media['description'],
				$media['sendto'],
				(new CDiv($media['period']))
					->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
				$mediaSeverity,
				$status,
				(new CCol(
					new CHorList([
						(new CButton(null, _('Edit')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->onClick('return PopUp("popup.media",'.CJs::encodeJson($popup_options).', null, this);'),
						(new CButton(null, _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->onClick('javascript: removeMedia('.$id.');')
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('user_medias_'.$id)
		);
	}

	$userMediaFormList->addRow(_('Media'),
		(new CDiv([
			$mediaTableInfo,
			(new CButton(null, _('Add')))
				->onClick('return PopUp("popup.media",'.
					CJs::encodeJson([
						'dstfrm' => $userForm->getName()
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_LINK),
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

/*
 * Profile fields
 */
if ($this->data['is_profile']) {
	$zbxSounds = getSounds();

	$userMessagingFormList = new CFormList();
	$userMessagingFormList->addRow(_('Frontend messaging'),
		(new CCheckBox('messages[enabled]'))->setChecked($this->data['messages']['enabled'] == 1)
	);
	$userMessagingFormList->addRow(_('Message timeout'),
		(new CTextBox('messages[timeout]', $this->data['messages']['timeout']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
		'timeout_row'
	);

	$repeatSound = new CComboBox('messages[sounds.repeat]', $this->data['messages']['sounds.repeat'],
		'if (IE) { submit() }',
		[
			1 => _('Once'),
			10 => '10 '._('Seconds'),
			-1 => _('Message timeout')
		]
	);
	$userMessagingFormList->addRow(_('Play sound'), $repeatSound, 'repeat_row');

	$soundList = new CComboBox('messages[sounds.recovery]', $this->data['messages']['sounds.recovery']);
	foreach ($zbxSounds as $filename => $file) {
		$soundList->addItem($file, $filename);
	}

	$triggersTable = (new CTable())
		->addRow([
			(new CCheckBox('messages[triggers.recovery]'))
				->setLabel(_('Recovery'))
				->setChecked($this->data['messages']['triggers.recovery'] == 1),
			[
				$soundList,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: testUserSound('messages_sounds.recovery');"),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
			]
		]);

	$msgVisibility = ['1' => [
		'messages[timeout]',
		'messages[sounds.repeat]',
		'messages[sounds.recovery]',
		'messages[triggers.recovery]',
		'timeout_row',
		'repeat_row',
		'triggers_row'
	]];

	// trigger sounds
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$soundList = new CComboBox('messages[sounds.'.$severity.']', $this->data['messages']['sounds.'.$severity]);
		foreach ($zbxSounds as $filename => $file) {
			$soundList->addItem($file, $filename);
		}

		$triggersTable->addRow([
			(new CCheckBox('messages[triggers.severities]['.$severity.']'))
				->setLabel(getSeverityName($severity, $this->data['config']))
				->setChecked(isset($this->data['messages']['triggers.severities'][$severity])),
			[
				$soundList,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick( "javascript: testUserSound('messages_sounds.".$severity."');"),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
			]
		]);

		zbx_subarray_push($msgVisibility, 1, 'messages[triggers.severities]['.$severity.']');
		zbx_subarray_push($msgVisibility, 1, 'messages[sounds.'.$severity.']');
	}

	$userMessagingFormList->addRow(_('Trigger severity'), $triggersTable, 'triggers_row');
}

// append form lists to tab
$userTab = new CTabView();
if (!$this->data['form_refresh']) {
	$userTab->setSelected(0);
}
$userTab->addTab('userTab', _('User'), $userFormList);
if (isset($userMediaFormList)) {
	$userTab->addTab('mediaTab', _('Media'), $userMediaFormList);
}

// Permissions tab.
if (!$data['is_profile']) {
	$permissionsFormList = new CFormList('permissionsFormList');

	$userTypeComboBox = new CComboBox('user_type', $data['user_type'], 'submit();', [
		USER_TYPE_ZABBIX_USER => user_type2str(USER_TYPE_ZABBIX_USER),
		USER_TYPE_ZABBIX_ADMIN => user_type2str(USER_TYPE_ZABBIX_ADMIN),
		USER_TYPE_SUPER_ADMIN => user_type2str(USER_TYPE_SUPER_ADMIN)
	]);

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$userTypeComboBox->setEnabled(false);
		$permissionsFormList->addRow(_('User type'), [$userTypeComboBox, SPACE, new CSpan(_('User can\'t change type for himself'))]);
		$userForm->addVar('user_type', $data['user_type']);
	}
	else {
		$permissionsFormList->addRow(_('User type'), $userTypeComboBox);
	}

	$permissions_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Host group'), _('Permissions')]);

	if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
		$permissions_table->addRow([italic(_('All groups')), permissionText(PERM_READ_WRITE)]);
	}
	else {
		foreach ($data['groups_rights'] as $groupid => $group_rights) {
			if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
				$group_name = ($groupid == 0)
					? italic(_('All groups'))
					: [$group_rights['name'], SPACE, italic('('._('including subgroups').')')];
			}
			else {
				$group_name = $group_rights['name'];
			}
			$permissions_table->addRow([$group_name, permissionText($group_rights['permission'])]);
		}
	}

	$permissionsFormList
		->addRow(_('Permissions'),
			(new CDiv($permissions_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
		->addInfo(_('Permissions can be assigned for user groups only.'));

	$userTab->addTab('permissionsTab', _('Permissions'), $permissionsFormList);
}

if (isset($userMessagingFormList)) {
	$userTab->addTab('messagingTab', _('Messaging'), $userMessagingFormList);
}

// append buttons to form
if ($data['userid'] != 0) {
	$buttons = [
		new CButtonCancel()
	];

	if (!$this->data['is_profile']) {
		$deleteButton = new CButtonDelete(_('Delete selected user?'), url_param('form').url_param('userid'));
		if (bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
			$deleteButton->setAttribute('disabled', 'disabled');
		}

		array_unshift($buttons, $deleteButton);
	}

	$userTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$userTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

// append tab to form
$userForm->addItem($userTab);

// append form to widget
$userWidget->addItem($userForm);

return $userWidget;
