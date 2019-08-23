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


$this->includeJSfile('app/views/administration.user.edit.common.js.php');
$this->includeJSfile(($data['action'] === 'user.edit')
	? 'app/views/administration.user.edit.js.php'
	: 'app/views/administration.userprofile.edit.js.php'
);
$this->addJsFile('multiselect.js');

if ($data['action'] === 'user.edit') {
	$widget_name = _('Users');
}
else {
	$widget_name = _('User profile').NAME_DELIMITER;
	$widget_name .= ($data['name'] !== '' || $data['surname'] !== '')
		? $data['name'].' '.$data['surname']
		: $data['alias'];
}
$widget = (new CWidget())->setTitle($widget_name);
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

if ($data['action'] === 'user.edit') {
	$user_form_list
		->addRow((new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
			(new CTextBox('alias', $data['alias']))
				->setReadonly($data['db_user']['alias'] === ZBX_GUEST_USER)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('maxlength', DB::getFieldLength('users', 'alias'))
		)
		->addRow(_x('Name', 'user first name'),
			(new CTextBox('name', $data['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
		)
		->addRow(_('Surname'),
			(new CTextBox('surname', $data['surname']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('users', 'surname'))
		)
		->addRow(
			(new CLabel(_('Groups'), 'user_groups__ms'))->setAsteriskMark(),
			(new CMultiSelect([
				'name' => 'user_groups[]',
				'object_name' => 'usersGroups',
				'data' => $data['groups'],
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

if ($data['change_password']) {
	$user_form->disablePasswordAutofill();

	$password1 = (new CPassBox('password1', $data['password1']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired();

	if ($data['action'] !== 'user.edit') {
		$password1->setAttribute('autofocus', 'autofocus');
	}

	$user_form_list
		->addRow((new CLabel(_('Password'), 'password1'))->setAsteriskMark(), $password1)
		->addRow((new CLabel(_('Password (once again)'), 'password2'))->setAsteriskMark(),
			(new CPassBox('password2', $data['password2']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
		->addRow('', _('Password is not mandatory for non internal authentication type.'));
}
else {
	$user_form_list->addRow(_('Password'),
		(new CSimpleButton(_('Change password')))
			->setEnabled($data['action'] === 'userprofile.edit' || $data['db_user']['alias'] !== ZBX_GUEST_USER)
			->setAttribute('autofocus', 'autofocus')
			->onClick('javascript: submitFormWithParam("'.$user_form->getName().'", "change_password", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
	);
}

// Append languages to form list.
$lang = new CComboBox('lang', $data['lang']);

$all_locales_available = 1;

foreach (getLocales() as $localeid => $locale) {
	if (!$locale['display']) {
		continue;
	}

	/*
	 * Checking if this locale exists in the system. The only way of doing it is to try and set one
	 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
	 */
	$locale_available = ($localeid === 'en_GB' || setlocale(LC_MONETARY, zbx_locale_variants($localeid)));

	$lang->addItem($localeid, $locale['name'], null, $locale_available);

	$all_locales_available &= (int) $locale_available;
}

// Restoring original locale.
setlocale(LC_MONETARY, zbx_locale_variants(CWebUser::$data['lang']));

$language_error = '';
if (!function_exists('bindtextdomain')) {
	$language_error = 'Translations are unavailable because the PHP gettext module is missing.';
	$lang->setAttribute('disabled', 'disabled');
}
elseif ($all_locales_available == 0) {
	$language_error = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

$user_form_list
	->addRow(_('Language'),
		($language_error !== '')
			? [$lang, (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CSpan($language_error))
					->addClass('red')
					->addClass('wrap')
			]
			: $lang
	)
	->addRow(_('Theme'),
		new CComboBox('theme', $data['theme'], null, [THEME_DEFAULT => _('System default')] + Z::getThemes())
	);

// Append auto-login & auto-logout to form list.
if ($data['action'] === 'userprofile.edit' || $data['db_user']['alias'] !== ZBX_GUEST_USER) {
	$autologout = ($data['autologout'] !== '0') ? $data['autologout'] : DB::getDefault('users', 'autologout');

	$user_form_list->addRow(_('Auto-login'),
		(new CCheckBox('autologin'))
			->setUncheckedValue('0')
			->setChecked($data['autologin'])
	);
	$user_form_list->addRow(_('Auto-logout'), [
		(new CCheckBox(null))
			->setId('autologout_visible')
			->setChecked($data['autologout'] !== '0'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('autologout', $autologout))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
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

$tabs->addTab('userTab', _('User'), $user_form_list);

// Media tab.
if ($data['action'] === 'user.edit' || CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
	$media_form_list = new CFormList('userMediaFormList');
	$user_form->addVar('user_medias', $data['user_medias']);

	$media_table_info = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), ('Status'), _('Action')]);

	foreach ($data['user_medias'] as $index => $media) {
		if ($media['active'] == MEDIA_STATUS_ACTIVE) {
			$status = (new CLink(_('Enabled'), '#'))
				->onClick('return create_var("'.$user_form->getName().'","disable_media",'.$index.', true);')
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN);
		}
		else {
			$status = (new CLink(_('Disabled'), '#'))
				->onClick('return create_var("'.$user_form->getName().'","enable_media",'.$index.', true);')
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED);
		}

		$popup_options = [
			'dstfrm' => $user_form->getName(),
			'media' => $index,
			'mediatypeid' => $media['mediatypeid'],
			($media['mediatype'] == MEDIA_TYPE_EMAIL) ? 'sendto_emails' : 'sendto' => $media['sendto'],
			'period' => $media['period'],
			'severity' => $media['severity'],
			'active' => $media['active']
		];


		$media_severity = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_name = getSeverityName($severity, $data['config']);

			$media_active = ($media['severity'] & (1 << $severity));

			$media_severity[$severity] = (new CSpan(mb_substr($severity_name, 0, 1)))
				->setHint($severity_name.' ('.($media_active ? _('on') : _('off')).')', '', false)
				->addClass($media_active ? getSeverityStatusStyle($severity) : ZBX_STYLE_STATUS_DISABLED_BG);
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
							->onClick('javascript: removeMedia('.$index.');')
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('user_medias_'.$index)
		);
	}

	$media_form_list->addRow(_('Media'),
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

	$tabs->addTab('mediaTab', _('Media'), $media_form_list);
}

// Permissions tab.
if ($data['action'] === 'user.edit') {
	$permissions_form_list = new CFormList('permissionsFormList');

	$type_combobox = new CComboBox('type', $data['type'], 'submit()', user_type2str());

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$type_combobox->setEnabled(false);
		$permissions_form_list->addRow(_('User type'),
			[$type_combobox, ' ', new CSpan(_('User can\'t change type for himself'))]
		);
	}
	else {
		$permissions_form_list->addRow(_('User type'), $type_combobox);
	}

	$permissions_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Host group'), _('Permissions')]);

	if ($data['type'] == USER_TYPE_SUPER_ADMIN) {
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

// Messaging tab.
if ($data['action'] !== 'user.edit') {
	$messaging_form_list = (new CFormList())
		->addRow(_('Frontend messaging'),
			(new CCheckBox('messages[enabled]'))
				->setChecked($data['messages']['enabled'] == 1)
				->setUncheckedValue(0)
		)
		->addRow(_('Message timeout'),
			(new CTextBox('messages[timeout]', $data['messages']['timeout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
			'timeout_row'
		)
		->addRow(_('Play sound'),
			new CComboBox('messages[sounds.repeat]', $data['messages']['sounds.repeat'], 'if (IE) { submit() }', [
				1 => _('Once'),
				10 => _n('%1$s second', '%1$s seconds', 10),
				-1 => _('Message timeout')
			]),
			'repeat_row'
		);

	$zbx_sounds = array_flip(getSounds());

	$triggers_table = (new CTable())
		->addRow([
			(new CCheckBox('messages[triggers.recovery]'))
				->setLabel(_('Recovery'))
				->setChecked($data['messages']['triggers.recovery'] == 1)
				->setUncheckedValue(0),
			[
				new CComboBox('messages[sounds.recovery]', $data['messages']['sounds.recovery'], null, $zbx_sounds),
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
		$triggers_table->addRow([
			(new CCheckBox('messages[triggers.severities]['.$severity.']'))
				->setLabel(getSeverityName($severity, $data['config']))
				->setChecked(array_key_exists($severity, $data['messages']['triggers.severities']))
				->setUncheckedValue(0),
			[
				new CComboBox('messages[sounds.'.$severity.']', $data['messages']['sounds.'.$severity], null, $zbx_sounds),
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

	$messaging_form_list
		->addRow(_('Trigger severity'), $triggers_table, 'triggers_row')
		->addRow(_('Show suppressed problems'),
			(new CCheckBox('messages[show_suppressed]'))
				->setChecked($data['messages']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
		);

	$tabs->addTab('messagingTab', _('Messaging'), $messaging_form_list);
}

// Append buttons to form.
if ($data['action'] === 'user.edit') {
	if ($data['userid'] != 0) {
		$tabs->setFooter(makeFormFooter(
			(new CSubmitButton(_('Update'), 'action', 'user.update'))->setId('update'),
			[
				(new CRedirectButton(_('Delete'),
					'zabbix.php?action=user.delete&sid='.$data['sid'].'&userids[]='.$data['userid'],
					_('Delete selected user?')
				))
					->setEnabled(bccomp(CWebUser::$data['userid'], $data['userid']) != 0)
					->setId('delete'),
				(new CRedirectButton(_('Cancel'), 'zabbix.php?action=user.list'))->setId('cancel')
			]
		));
	}
	else {
		$tabs->setFooter(makeFormFooter(
			(new CSubmitButton(_('Add'), 'action', 'user.create'))->setId('add'),
			[(new CRedirectButton(_('Cancel'), 'zabbix.php?action=user.list'))->setId('cancel')]
		));
	}
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', 'userprofile.update'))->setId('update'),
		[(new CRedirectButton(_('Cancel'), ZBX_DEFAULT_URL))->setId('cancel')]
	));
}

// Append tab to form.
$user_form->addItem($tabs);
$widget
	->addItem($user_form)
	->show();
