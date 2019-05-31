<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$this->includeJSfile('app/views/administration.user.edit.js.php');
$this->addJsFile('multiselect.js');

if ($data['is_profile']) {
	$widget = ($data['name'] !== '' || $data['surname'] !== '')
		? (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['name'].' '.$data['surname'])
		: (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['alias']);
}
else {
	$widget = (new CWidget())->setTitle(_('Users'));
}

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

// Create form.
$user_form = (new CForm())
	->setName('user_form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', $data['action'])
	->addVar('userid', $data['userid']);


// Create form list and user tab.
$user_form_list = new CFormList('user_form_list');
$form_autofocus = false;

if (!$data['is_profile']) {
	$user_form_list->addRow(
		(new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
		(new CTextBox('alias', $data['alias']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('users', 'alias'))
	);
	$form_autofocus = true;
	$user_form_list->addRow(_x('Name', 'user first name'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
	);
	$user_form_list->addRow(_('Surname'),
		(new CTextBox('surname', $data['surname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'surname'))
	);

	$user_groups = [];

	foreach ($data['groups'] as $group) {
		$user_groups[] = CArrayHelper::renameKeys($group, ['usrgrpid' => 'id']);
	}

	$user_form_list->addRow(
		(new CLabel(_('Groups'), 'user_groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'user_groups[]',
			'object_name' => 'usersGroups',
			'data' => $user_groups,
			'popup' => [
				'parameters' => [
					'srctbl' => 'usrgrp',
					'srcfld1' => 'usrgrpid',
					'dstfrm' => $user_form->getName(),
					'dstfld1' => 'user_groups_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);
}

// Append password to form list.
if ($data['userid'] == 0 || $data['change_password']) {
	$user_form->disablePasswordAutofill();
	$password_box = new CPassBox('password1', $data['password1']);

	if (!$form_autofocus) {
		$form_autofocus = true;
		$password_box->setAttribute('autofocus', 'autofocus');
	}

	$user_form_list->addRow(
		(new CLabel(_('Password'), 'password1'))->setAsteriskMark(),
		$password_box
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);
	$user_form_list->addRow(
		(new CLabel(_('Password (once again)'), 'password2'))->setAsteriskMark(),
		(new CPassBox('password2', $data['password2']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);

	if ($data['change_password']) {
		$user_form->addVar('change_password', $data['change_password']);
	}

	$user_form_list->addRow('', _('Password is not mandatory for non internal authentication type.'));
}
else {
	$passwd_btn = (new CSimpleButton(_('Change password')))
		->onClick('javascript: submitFormWithParam("'.$user_form->getName().'", "change_password", "1");')
		->addClass(ZBX_STYLE_BTN_GREY);
	if ($data['alias'] == ZBX_GUEST_USER) {
		$passwd_btn->setAttribute('disabled', 'disabled');
	}

	if (!$form_autofocus) {
		$form_autofocus = true;
		$passwd_btn->setAttribute('autofocus', 'autofocus');
	}

	$user_form_list->addRow(_('Password'), $passwd_btn);
}

// Append languages to form list.
$lang_combobox = new CComboBox('lang', $data['lang']);

$all_locales_available = true;
foreach (getLocales() as $localeid => $locale) {
	if ($locale['display']) {
		/*
		 * Checking if this locale exists in the system. The only way of doing it is to try and set one
		 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
		 */
		$locale_exists = ($localeid === 'en_GB' || setlocale(LC_MONETARY , zbx_locale_variants($localeid)));

		$lang_combobox->addItem(
			$localeid,
			$locale['name'],
			($localeid == $data['lang']) ? true : null,
			$locale_exists
		);

		$all_locales_available &= $locale_exists;
	}
}

// Restoring original locale.
setlocale(LC_MONETARY, zbx_locale_variants(CWebUser::$data['lang']));

$language_error = '';
if (!function_exists('bindtextdomain')) {
	$language_error = 'Translations are unavailable because the PHP gettext module is missing.';
	$lang_combobox->setAttribute('disabled', 'disabled');
}
elseif (!$all_locales_available) {
	$language_error = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

if (!$form_autofocus && $lang_combobox->getAttribute('disabled') === null) {
	$lang_combobox->setAttribute('autofocus', 'autofocus');
	$form_autofocus = true;
}

$user_form_list->addRow(
	_('Language'),
	$language_error
		? [$lang_combobox, SPACE,
			(new CSpan($language_error))
				->addClass('red')
				->addClass('wrap')]
		: $lang_combobox
);

// Append themes to form list.
$themes = array_merge([THEME_DEFAULT => _('System default')], Z::getThemes());
$themes_combobox = new CComboBox('theme', $data['theme'], null, $themes);

if (!$form_autofocus) {
	$themes_combobox->setAttribute('autofocus', 'autofocus');
	$form_autofocus = true;
}

$user_form_list->addRow(_('Theme'), $themes_combobox);

// Append auto-login & auto-logout to form list.
$autologout_checkbox = (new CCheckBox('autologout_visible'))->setChecked($data['autologout_visible']);
if ($data['autologout_visible']) {
	$autologout_textbox = (new CTextBox('autologout', $data['autologout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
}
else {
	$autologout_textbox = (new CTextBox('autologout', DB::getDefault('users', 'autologout')))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		->setAttribute('disabled', 'disabled');
}

if ($data['alias'] != ZBX_GUEST_USER) {
	$user_form_list->addRow(_('Auto-login'), (new CCheckBox('autologin'))->setChecked($data['autologin']));
	$user_form_list->addRow(_('Auto-logout'), [
		$autologout_checkbox,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$autologout_textbox
	]);
}

$user_form_list
	->addRow((new CLabel(_('Refresh'), 'refresh'))->setAsteriskMark(),
		(new CTextBox('refresh', $data['refresh']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Rows per page'), 'rows_per_page'))->setAsteriskMark(),
		(new CNumericBox('rows_per_page', $data['rows_per_page'], 6))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('URL (after login)'),
		(new CTextBox('url', $data['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Media tab.
if (!$data['is_profile'] || ($data['is_profile'] && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
	$user_media_form_list = new CFormList('userMediaFormList');
	$user_form->addVar('user_medias', $data['user_medias']);

	$media_table_info = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), ('Status'), _('Action')]);

	foreach ($data['user_medias'] as $id => $media) {
		if (!array_key_exists('active', $media) || !$media['active']) {
			$status = (new CLink(_('Enabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->onClick('return create_var("'.$user_form->getName().'","disable_media",'.$id.', true);');
		}
		else {
			$status = (new CLink(_('Disabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->onClick('return create_var("'.$user_form->getName().'","enable_media",'.$id.', true);');
		}

		$popup_options = [
			'dstfrm' => $user_form->getName(),
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

		$media_severity = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_name = getSeverityName($severity, $data['config']);
			$severity_status_style = getSeverityStatusStyle($severity);

			$media_active = ($media['severity'] & (1 << $severity));

			$media_severity[$severity] = (new CSpan(mb_substr($severity_name, 0, 1)))
				->setHint($severity_name.' ('.($media_active ? _('on') : _('off')).')', '', false)
				->addClass($media_active ? $severity_status_style : ZBX_STYLE_STATUS_DISABLED_BG);
		}

		if ($media['mediatype'] == MEDIA_TYPE_EMAIL) {
			$media['sendto'] = implode(', ', $media['sendto']);
		}

		if (mb_strlen($media['sendto']) > 50) {
			$media['sendto'] = (new CSpan(mb_substr($media['sendto'], 0, 50).'...'))->setHint($media['sendto']);
		}

		$media_table_info->addRow(
			(new CRow([
				$media['description'],
				$media['sendto'],
				(new CDiv($media['period']))
					->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
				(new CDiv($media_severity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
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

	$user_media_form_list->addRow(_('Media'),
		(new CDiv([
			$media_table_info,
			(new CButton(null, _('Add')))
				->onClick('return PopUp("popup.media",'.
					CJs::encodeJson([
						'dstfrm' => $user_form->getName()
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_LINK),
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

// Append form lists to tab.
$tabs->addTab('userTab', _('User'), $user_form_list);

if (!$data['is_profile'] || ($data['is_profile'] && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
	$tabs->addTab('mediaTab', _('Media'), $user_media_form_list);
}

if ($data['is_profile']) {
	// Messaging tab.

	$zbx_sounds = getSounds();

	$user_messaging_form_list = new CFormList();
	$user_messaging_form_list->addRow(_('Frontend messaging'),
		(new CCheckBox('messages[enabled]'))
			->setChecked($data['messages']['enabled'] == 1)
			->setUncheckedValue(0)
	);
	$user_messaging_form_list->addRow(_('Message timeout'),
		(new CTextBox('messages[timeout]', $data['messages']['timeout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
		'timeout_row'
	);

	$repeatSound = new CComboBox('messages[sounds.repeat]', $data['messages']['sounds.repeat'],
		'if (IE) { submit() }',
		[
			1 => _('Once'),
			10 => '10 '._('Seconds'),
			-1 => _('Message timeout')
		]
	);
	$user_messaging_form_list->addRow(_('Play sound'), $repeatSound, 'repeat_row');

	$sound_list = new CComboBox('messages[sounds.recovery]', $data['messages']['sounds.recovery']);
	foreach ($zbx_sounds as $filename => $file) {
		$sound_list->addItem($file, $filename);
	}

	$triggers_table = (new CTable())
		->addRow([
			(new CCheckBox('messages[triggers.recovery]'))
				->setLabel(_('Recovery'))
				->setChecked($data['messages']['triggers.recovery'] == 1)
				->setUncheckedValue(0),
			[
				$sound_list,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: testUserSound('messages_sounds.recovery');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
					->removeId()
			]
		]);

	$msg_visibility = ['1' => [
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
		$sound_list = new CComboBox('messages[sounds.'.$severity.']', $data['messages']['sounds.'.$severity]);
		foreach ($zbx_sounds as $filename => $file) {
			$sound_list->addItem($file, $filename);
		}

		$triggers_table->addRow([
			(new CCheckBox('messages[triggers.severities]['.$severity.']'))
				->setLabel(getSeverityName($severity, $data['config']))
				->setChecked(array_key_exists($severity, $data['messages']['triggers.severities']))
				->setUncheckedValue(0),
			[
				$sound_list,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: testUserSound('messages_sounds.".$severity."');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
					->removeId()
			]
		]);

		zbx_subarray_push($msg_visibility, 1, 'messages[triggers.severities]['.$severity.']');
		zbx_subarray_push($msg_visibility, 1, 'messages[sounds.'.$severity.']');
	}

	$user_messaging_form_list
		->addRow(_('Trigger severity'), $triggers_table, 'triggers_row')
		->addRow(_('Show suppressed problems'),
			(new CCheckBox('messages[show_suppressed]'))
				->setChecked($data['messages']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
		);

	$tabs->addTab('messagingTab', _('Messaging'), $user_messaging_form_list);
}
else {
	// Permissions tab.

	$permissions_form_list = new CFormList('permissionsFormList');

	$user_type_combobox = new CComboBox('user_type', $data['user_type'], 'submit()', [
		USER_TYPE_ZABBIX_USER => user_type2str(USER_TYPE_ZABBIX_USER),
		USER_TYPE_ZABBIX_ADMIN => user_type2str(USER_TYPE_ZABBIX_ADMIN),
		USER_TYPE_SUPER_ADMIN => user_type2str(USER_TYPE_SUPER_ADMIN)
	]);

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$user_type_combobox->setEnabled(false);
		$permissions_form_list->addRow(_('User type'),
			[$user_type_combobox, SPACE, new CSpan(_('User can\'t change type for himself'))]
		);
		$user_form->addItem((new CVar('user_type', $data['user_type']))->removeId());
	}
	else {
		$permissions_form_list->addRow(_('User type'), $user_type_combobox);
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

	$permissions_form_list
		->addRow(_('Permissions'),
			(new CDiv($permissions_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
		->addInfo(_('Permissions can be assigned for user groups only.'));

	$tabs->addTab('permissionsTab', _('Permissions'), $permissions_form_list);
}

// Append buttons to form.
if ($data['userid'] != 0) {
	$buttons = [
		(new CRedirectButton(_('Cancel'),
			$data['is_profile'] ? ZBX_DEFAULT_URL : 'zabbix.php?action=user.list')
		)->setId('cancel')
	];

	if (!$data['is_profile']) {
		$delete_btn = (new CRedirectButton(_('Delete'),
			'zabbix.php?action=user.delete&sid='.$data['sid'].'&group_userid[]='.$data['userid'],
			_('Delete selected user?')
		))->setId('delete');

		if (bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
			$delete_btn->setAttribute('disabled', 'disabled');
		}

		array_unshift($buttons, $delete_btn);
	}

	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', $data['is_profile'] ? 'profile.update' : 'user.update'))
			->setId('update'),
		$buttons
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Add'), 'action', 'user.create'))->setId('add'),
		[(new CRedirectButton(_('Cancel'), 'zabbix.php?action=user.list'))->setId('cancel')]
	));
}

// Append tab to form.
$user_form->addItem($tabs);

$widget->addItem($user_form)->show();
