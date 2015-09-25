<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	$userWidget = (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$this->data['name'].' '.$this->data['surname']);
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
	$userFormList->addRow(_('Alias'), (new CTextBox('alias', $this->data['alias']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
	);
	$userFormList->addRow(_x('Name', 'user first name'),
		(new CTextBox('name', $this->data['name']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$userFormList->addRow(_('Surname'),
		(new CTextBox('surname', $this->data['surname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

// append user groups to form list
if (!$this->data['is_profile']) {
	$userForm->addVar('user_groups', $this->data['user_groups']);

	$lstGroups = new CListBox('user_groups_to_del[]', null, 10);
	$lstGroups->attributes['style'] = 'width: 320px';
	foreach ($this->data['groups'] as $group) {
		$lstGroups->addItem($group['usrgrpid'], $group['name']);
	}

	$userFormList->addRow(_('Groups'),
		[
			$lstGroups,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('add_group', _('Add')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup_usrgrp.php?dstfrm='.$userForm->getName().'&list_name=user_groups_to_del[]&var_name=user_groups");'),
			BR(),
			(count($this->data['user_groups']) > 0)
				? (new CSubmit('del_user_group', _('Delete selected')))->addClass(ZBX_STYLE_BTN_GREY)
				: null
		]
	);
}

// append password to form list
if ($data['auth_type'] == ZBX_AUTH_INTERNAL) {
	if ($data['userid'] == 0 || isset($this->data['change_password'])) {
		$userFormList->addRow(
			_('Password'),
			(new CPassBox('password1', $this->data['password1']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);
		$userFormList->addRow(
			_('Password (once again)'),
			(new CPassBox('password2', $this->data['password2']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		if (isset($this->data['change_password'])) {
			$userForm->addVar('change_password', $this->data['change_password']);
		}
	}
	else {
		$passwdButton = (new CSubmit('change_password', _('Change password')))->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['alias'] == ZBX_GUEST_USER) {
			$passwdButton->setAttribute('disabled', 'disabled');
		}

		$userFormList->addRow(_('Password'), $passwdButton);
	}
}
else {
	$userFormList->addRow(_('Password'),
		(new CSpan(_s('Unavailable for users with %1$s.')))->addClass(authentication2str($data['auth_type'])
	));
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
$autologoutCheckBox = (new CCheckBox('autologout_visible'))->setChecked(isset($this->data['autologout']));
if (isset($this->data['autologout'])) {
	$autologoutTextBox = (new CNumericBox('autologout', $this->data['autologout'], 4))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
}
else {
	$autologoutTextBox = (new CNumericBox('autologout', 900, 4))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		->setAttribute('disabled', 'disabled');
}

if ($this->data['alias'] != ZBX_GUEST_USER) {
	$userFormList->addRow(_('Auto-login'), (new CCheckBox('autologin'))->setChecked($this->data['autologin']));
	$userFormList->addRow(_('Auto-logout (min 90 seconds)'), [$autologoutCheckBox, $autologoutTextBox]);
}

$userFormList
	->addRow(_('Refresh (in seconds)'),
		(new CNumericBox('refresh', $this->data['refresh'], 4))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Rows per page'),
		(new CNumericBox('rows_per_page', $this->data['rows_per_page'], 6))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
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
				->onClick('return create_var("'.$userForm->getName().'","disable_media",'.$id.', true);')
				->removeSID();
		}
		else {
			$status = (new CLink(_('Disabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->onClick('return create_var("'.$userForm->getName().'","enable_media",'.$id.', true);')
				->removeSID();
		}

		$mediaUrl = 'popup_media.php'.
			'?dstfrm='.$userForm->getName().
			'&media='.$id.
			'&mediatypeid='.$media['mediatypeid'].
			'&sendto='.urlencode($media['sendto']).
			'&period='.$media['period'].
			'&severity='.$media['severity'].
			'&active='.$media['active'];

		$mediaSeverity = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityName = getSeverityName($severity, $this->data['config']);

			$mediaActive = ($media['severity'] & (1 << $severity));

			$mediaSeverity[$severity] = (new CSpan(mb_substr($severityName, 0, 1)))
				->setHint($severityName.' ('.($mediaActive ? _('on') : _('off')).')', '', false)
				->addClass($mediaActive ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY);
		}

		$mediaTableInfo->addRow(
			(new CRow([
				$media['description'],
				$media['sendto'],
				$media['period'],
				$mediaSeverity,
				$status,
				(new CCol(
					new CHorList([
						(new CButton(null, _('Edit')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->onClick('return PopUp("'.$mediaUrl.'");'),
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
				->onClick('return PopUp("popup_media.php?dstfrm='.$userForm->getName().'");')
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
	$userMessagingFormList->addRow(_('Message timeout (seconds)'),
		(new CNumericBox('messages[timeout]', $this->data['messages']['timeout'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
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
			new CLabel([
				(new CCheckBox('messages[triggers.recovery]'))
					->setChecked($this->data['messages']['triggers.recovery'] == 1),
				_('Recovery')], 'messages[triggers.recovery]'
			),
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
			new CLabel([
				(new CCheckBox('messages[triggers.severities]['.$severity.']'))
					->setChecked(isset($this->data['messages']['triggers.severities'][$severity])),
				getSeverityName($severity, $this->data['config'])], 'messages[triggers.severities]['.$severity.']'
			),
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

if (!$this->data['is_profile']) {
	/*
	 * Permissions tab
	 */
	$permissionsFormList = new CFormList('permissionsFormList');

	$userTypeComboBox = new CComboBox('user_type', $this->data['user_type'], 'submit();', [
		USER_TYPE_ZABBIX_USER => user_type2str(USER_TYPE_ZABBIX_USER),
		USER_TYPE_ZABBIX_ADMIN => user_type2str(USER_TYPE_ZABBIX_ADMIN),
		USER_TYPE_SUPER_ADMIN => user_type2str(USER_TYPE_SUPER_ADMIN)
	]);

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$userTypeComboBox->setEnabled(false);
		$permissionsFormList->addRow(_('User type'), [$userTypeComboBox, SPACE, new CSpan(_('User can\'t change type for himself'))]);
		$userForm->addVar('user_type', $this->data['user_type']);
	}
	else {
		$permissionsFormList->addRow(_('User type'), $userTypeComboBox);
	}

	$permissionsFormList = getPermissionsFormList($this->data['user_rights'], $this->data['user_type'], $permissionsFormList);
	$permissionsFormList->addInfo(_('Permissions can be assigned for user groups only.'));

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
