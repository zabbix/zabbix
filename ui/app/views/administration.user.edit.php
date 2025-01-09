<?php
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

$this->includeJsFile('administration.user.edit.common.js.php');
$this->includeJsFile(($data['action'] === 'user.edit')
	? 'administration.user.edit.js.php'
	: 'administration.userprofile.edit.js.php'
);

$html_page = new CHtmlPage();

if ($data['action'] === 'user.edit') {
	$widget_name = _('Users');
	$doc_url = CDocHelper::USERS_USER_EDIT;
	$csrf_token = CCsrfTokenHelper::get('user');
}
else {
	$widget_name = _('User profile').NAME_DELIMITER;
	$widget_name .= ($data['name'] !== '' || $data['surname'] !== '')
		? $data['name'].' '.$data['surname']
		: $data['username'];
	$html_page->setTitleSubmenu(getUserSettingsSubmenu());
	$doc_url = CDocHelper::USERS_USERPROFILE_EDIT;
	$csrf_token = CCsrfTokenHelper::get('userprofile');
}

$html_page
	->setTitle($widget_name)
	->setDocUrl(CDocHelper::getUrl($doc_url));

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

if ($data['readonly'] == true) {
	CMessageHelper::addWarning(
		_('This user is IdP provisioned. Manual changes for provisioned fields are not allowed.')
	);
	show_messages();
}

// Create form.
$user_form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('user-form')
	->setName('user_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('userid', $data['userid']);

// Create form list and user tab.
$user_form_list = new CFormList('user_form_list');

if ($data['action'] === 'user.edit') {
	$user_form_list
		->addRow((new CLabel(_('Username'), 'username'))->setAsteriskMark(),
			(new CTextBox('username', $data['username']))
				->setReadonly($data['db_user']['username'] === ZBX_GUEST_USER || $data['readonly'])
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('maxlength', DB::getFieldLength('users', 'username'))
		)
		->addRow(_x('Name', 'user first name'),
			(new CTextBox('name', $data['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
				->setReadonly($data['readonly'])
		)
		->addRow(_('Last name'),
			(new CTextBox('surname', $data['surname']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('users', 'surname'))
				->setReadonly($data['readonly'])
		)
		->addRow(
			new CLabel(_('Groups'), 'user_groups__ms'),
			(new CMultiSelect([
				'name' => 'user_groups[]',
				'object_name' => 'usersGroups',
				'data' => $data['groups'],
				'readonly' => $data['readonly'],
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

	$password_requirements = [];

	if ($data['password_requirements']['min_length'] > 1) {
		$password_requirements[] = _s('must be at least %1$d characters long',
			$data['password_requirements']['min_length']
		);
	}

	if ($data['password_requirements']['check_rules'] & PASSWD_CHECK_CASE) {
		$password_requirements[] = new CListItem([
			_('must contain at least one lowercase and one uppercase Latin letter'),
			' (', (new CSpan('A-Z'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ', ',
			(new CSpan('a-z'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
		]);
	}

	if ($data['password_requirements']['check_rules'] & PASSWD_CHECK_DIGITS) {
		$password_requirements[] = new CListItem([
			_('must contain at least one digit'),
			' (', (new CSpan('0-9'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
		]);
	}

	if ($data['password_requirements']['check_rules'] & PASSWD_CHECK_SPECIAL) {
		$password_requirements[] = new CListItem([
			_('must contain at least one special character'),
			' (', (new CSpan(' !"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
		]);
	}

	if ($data['password_requirements']['check_rules'] & PASSWD_CHECK_SIMPLE) {
		$password_requirements[] = _("must not contain user's name, surname or username");
		$password_requirements[] = _('must not be one of common or context-specific passwords');
	}

	$password_hint_icon = $password_requirements
		? makeHelpIcon([
			_('Password requirements:'),
			(new CList($password_requirements))->addClass(ZBX_STYLE_LIST_DASHED)
		])
		: null;

	$current_password = (new CPassBox('current_password'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired()
		->setAttribute('autocomplete', 'off');

	if ($data['action'] !== 'user.edit') {
		$current_password->setAttribute('autofocus', 'autofocus');
	}

	if (CWebUser::$data['userid'] == $data['userid']) {
		$user_form_list
			->addRow((new CLabel(_('Current password'), 'current_password'))->setAsteriskMark(), $current_password);
	}

	$user_form_list
		->addRow((new CLabel([_('Password'), $password_hint_icon], 'password1'))->setAsteriskMark(), [
			// Hidden dummy login field for protection against chrome error when password autocomplete.
			(new CInput('text', null, null))
				->setAttribute('tabindex', '-1')
				->addStyle('position: absolute; left: -100vw;'),
			(new CPassBox('password1', $data['password1']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
				->setAttribute('autocomplete', 'off')
		])
		->addRow((new CLabel(_('Password (once again)'), 'password2'))->setAsteriskMark(),
			(new CPassBox('password2', $data['password2']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
				->setAttribute('autocomplete', 'off')
		)
		->addRow('', _('Password is not mandatory for non internal authentication type.'));
}
else {
	$change_password_enabled = !$data['readonly'] && $data['internal_auth'];

	if ($change_password_enabled && $data['action'] === 'user.edit') {
		$change_password_enabled = $data['db_user']['username'] !== ZBX_GUEST_USER;
	}

	$hint = !$change_password_enabled
		? $hint = makeErrorIcon(_('Password can only be changed for users using the internal Zabbix authentication.'))
		: null;

	$user_form_list->addRow(_('Password'), [
		(new CSimpleButton(_('Change password')))
			->setEnabled($change_password_enabled)
			->setAttribute('autofocus', 'autofocus')
			->onClick('submitFormWithParam("'.$user_form->getName().'", "change_password", "1");')
			->addClass(ZBX_STYLE_BTN_GREY),
		$hint
	]);
}

// Append languages, timezones & themes to form list.
$lang_select = (new CSelect('lang'))
	->setFocusableElementId('label-lang')
	->setValue($data['lang'])
	->addOption(new CSelectOption(LANG_DEFAULT, _('System default')));

$timezone_select = (new CSelect('timezone'))->setFocusableElementId('label-timezone');
$theme_select = (new CSelect('theme'))
	->setFocusableElementId('label-theme')
	->setValue($data['theme'])
	->addOption(new CSelectOption(THEME_DEFAULT, _('System default')));

$language_error = null;
if ($data['action'] === 'user.edit' && $data['db_user']['username'] === ZBX_GUEST_USER) {
	$lang_select
		->setName(null)
		->setReadonly();
	$theme_select
		->setName(null)
		->setReadonly();
	$timezone_select
		->addOption(new CSelectOption(TIMEZONE_DEFAULT, $data['timezones'][TIMEZONE_DEFAULT]))
		->setValue(TIMEZONE_DEFAULT)
		->setReadonly();
}
else {
	$all_locales_available = true;

	foreach (getLocales() as $localeid => $locale) {
		if (!$locale['display']) {
			continue;
		}

		/*
		 * Checking if this locale exists in the system. The only way of doing it is to try and set one
		 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
		 */
		$locale_available = setlocale(LC_MONETARY, zbx_locale_variants($localeid));

		$lang_select->addOption((new CSelectOption($localeid, $locale['name']))->setDisabled(!$locale_available));

		if (!$locale_available) {
			$all_locales_available = false;
		}
	}

	// Restoring original locale.
	setlocale(LC_MONETARY, zbx_locale_variants(CWebUser::$data['lang']));

	if (!function_exists('bindtextdomain')) {
		$language_error = makeErrorIcon('Translations are unavailable because the PHP gettext module is missing.');

		$lang_select->setReadonly();
	}
	elseif (!$all_locales_available) {
		$language_error = makeWarningIcon(
			_('You are not able to choose some of the languages, because locales for them are not installed on the web server.')
		);
	}

	$timezone_select
		->addOptions(CSelect::createOptionsFromArray($data['timezones']))
		->setValue($data['timezone']);
	$theme_select->addOptions(CSelect::createOptionsFromArray(APP::getThemes()));
}

$user_form_list
	->addRow(new CLabel(_('Language'), $lang_select->getFocusableElementId()), [$lang_select, $language_error])
	->addRow(new CLabel(_('Time zone'), $timezone_select->getFocusableElementId()), $timezone_select)
	->addRow(new CLabel(_('Theme'), $theme_select->getFocusableElementId()), $theme_select);

// Append auto-login & auto-logout to form list.
if ($data['action'] === 'userprofile.edit' || $data['db_user']['username'] !== ZBX_GUEST_USER) {
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
		(new CTextBox('autologout', $autologout, false, DB::getFieldLength('users', 'autologout')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	]);
}

$user_form_list
	->addRow((new CLabel(_('Refresh'), 'refresh'))->setAsteriskMark(),
		(new CTextBox('refresh', $data['refresh'], false, DB::getFieldLength('users', 'refresh')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Rows per page'), 'rows_per_page'))->setAsteriskMark(),
		(new CNumericBox('rows_per_page', $data['rows_per_page'], 6, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('URL (after login)'),
		(new CTextBox('url', $data['url'], false, DB::getFieldLength('users', 'url')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$tabs->addTab('userTab', _('User'), $user_form_list);

// Media tab.
if ($data['action'] === 'user.edit' || CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
	$media_form_list = new CFormList('userMediaFormList');
	$user_form->addVar('medias', $data['medias']);

	$media_table_info = (new CTable())
		->setId('media-table')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), _('Status'), _('Actions')]);

	foreach ($data['medias'] as $index => $media) {
		if (!array_key_exists($media['mediatypeid'], $data['mediatypes'])) {
			$media_name = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_DISABLED);
			$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
		}
		elseif ($data['mediatypes'][$media['mediatypeid']]['status'] == MEDIA_TYPE_STATUS_ACTIVE) {
			$media_name = $media['name'];

			if ($media['active'] == MEDIA_STATUS_ACTIVE) {
				$status = (new CButtonLink(_('Enabled')))
					->onClick('return create_var("'.$user_form->getName().'","disable_media",'.$index.', true);')
					->addClass(ZBX_STYLE_GREEN);
			}
			else {
				$status = (new CButtonLink(_('Disabled')))
					->onClick('return create_var("'.$user_form->getName().'","enable_media",'.$index.', true);')
					->addClass(ZBX_STYLE_RED);
			}
		}
		else {
			$media_name = [
				new CSpan($media['name']),
				makeWarningIcon(_('Media type disabled by Administration.'))
			];
			$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
		}

		$parameters = [
			'dstfrm' => $user_form->getName(),
			'media' => $index,
			'mediatypeid' => $media['mediatypeid'],
			'period' => $media['period'],
			'severity' => $media['severity'],
			'active' => $media['active'],
			'userdirectory_mediaid' => $media['userdirectory_mediaid']
		];

		if ($media['mediatype'] === MEDIA_TYPE_EMAIL) {
			$parameters['sendto_emails'] = $media['sendto'];
		}
		else {
			if (is_array($media['sendto'])) {
				$media['sendto'] = implode(', ', $media['sendto']);
			}
			$parameters['sendto'] = $media['sendto'];
		}

		$media_severity = [];

		if (array_key_exists('mediaid', $media)) {
			$parameters['mediaid'] = $media['mediaid'];
		}

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_name = CSeverityHelper::getName($severity);

			$media_active = ($media['severity'] & (1 << $severity));

			$media_severity[$severity] = (new CSpan(mb_substr($severity_name, 0, 1)))
				->setHint($severity_name.' ('.($media_active ? _('on') : _('off')).')', '', false)
				->addClass($media_active
					? CSeverityHelper::getStatusStyle($severity)
					: ZBX_STYLE_STATUS_DISABLED
				);
		}

		if (is_array($media['sendto'])) {
			$media['sendto'] = implode(', ', $media['sendto']);
		}

		if (mb_strlen($media['sendto']) > 50) {
			$media['sendto'] = (new CSpan(mb_substr($media['sendto'], 0, 50).'...'))->setHint($media['sendto']);
		}

		$media_table_info->addRow(
			(new CRow([
				$media_name,
				$media['sendto'],
				(new CDiv($media['period']))
					->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
				(new CDiv($media_severity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
				$status,
				(new CCol(
					new CHorList([
						(new CButtonLink(_('Edit')))
							->setAttribute('data-parameters', json_encode($parameters))
							->onClick('PopUp("popup.media", JSON.parse(this.dataset.parameters),
								{dialogue_class: "modal-popup-generic"});'
							),
						(new CButtonLink(_('Remove')))
							->setEnabled(!$parameters['userdirectory_mediaid'])
							->onClick('removeMedia('.$index.');')
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('medias_'.$index)
		);
	}

	$media_form_list->addRow(_('Media'),
		(new CDiv([
			$media_table_info,
			(new CButtonLink(_('Add')))
				->onClick('PopUp("popup.media", '.
					json_encode(['dstfrm' => $user_form->getName()]).
					', {dialogue_class: "modal-popup-generic"});'
				)
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

	$tabs->addTab('mediaTab', _('Media'), $media_form_list, TAB_INDICATOR_MEDIA);
}

// Permissions tab.
if ($data['action'] === 'user.edit') {
	$permissions_form_list = new CFormList('permissionsFormList');

	$role_disabled = $data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0;
	$role_disabled |= $data['readonly'];

	$role_multiselect = (new CMultiSelect([
		'name' => 'roleid',
		'object_name' => 'roles',
		'data' => $data['role'],
		'multiple' => false,
		'readonly' => $role_disabled,
		'popup' => [
			'parameters' => [
				'srctbl' => 'roles',
				'srcfld1' => 'roleid',
				'dstfrm' => 'user_form',
				'dstfld1' => 'roleid'
			]
		]
	]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$permissions_form_list->addRow(new CLabel(_('Role')),
			(new CDiv([
				$role_multiselect,
				new CDiv(_('User cannot change own role.'))
			]))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->addClass('multiselect-description-container')
		);
	}
	else {
		$permissions_form_list->addRow((new CLabel(_('Role'), 'roleid_ms'))->setAsteriskMark($data['roleid_required']),
			$role_multiselect
		);
	}

	if ($data['roleid']) {
		$permissions_form_list->addRow(_('User type'),
			new CTextBox('user_type', user_type2str($data['user_type']), true)
		);

		$permissions_table = (new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader([_('Group'), _('Type'), _('Permissions')]);

		if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
			$permissions_table->addRow([italic(_('All groups')), _('Hosts'), permissionText(PERM_READ_WRITE)]);
			$permissions_table->addRow([italic(_('All groups')), _('Templates'), permissionText(PERM_READ_WRITE)]);
		}
		else {
			foreach ($data['groups_rights'] as $groupid => $group_rights) {
				if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
					$group_name = ($groupid == 0)
						? italic(_('All groups'))
						: [$group_rights['name'], NBSP(), italic('('._('including subgroups').')')];
				}
				else {
					$group_name = $group_rights['name'];
				}

				$permissions_table->addRow([$group_name, _('Hosts'), permissionText($group_rights['permission'])]);
			}

			foreach ($data['templategroups_rights'] as $groupid => $group_rights) {
				if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
					$group_name = ($groupid == 0)
						? italic(_('All groups'))
						: [$group_rights['name'], NBSP(), italic('('._('including subgroups').')')];
				}
				else {
					$group_name = $group_rights['name'];
				}

				$permissions_table->addRow([$group_name, _('Templates'), permissionText($group_rights['permission'])]);
			}
		}

		$permissions_form_list
			->addRow(_('Permissions'),
				(new CDiv($permissions_table))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			)
			->addInfo(_('Permissions can be assigned for user groups only.'));

		// UI elements section.

		$permissions_form_list
			->addRow((new CTag('h4', true, _('Access to UI elements')))->addClass('input-section-header'));

		foreach (CRoleHelper::getUiSectionsLabels($data['user_type']) as $section_name => $section_label) {
			$elements = [];

			foreach (CRoleHelper::getUiSectionRulesLabels($section_name, $data['user_type']) as $rule_name => $rule_label) {
				$elements[] = (new CSpan($rule_label))->addClass(
					CRoleHelper::checkAccess($rule_name, $data['roleid']) ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
				);
			}

			if ($elements) {
				$permissions_form_list->addRow($section_label, (new CDiv($elements))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
				);
			}
		}

		// Services section.

		$permissions_form_list->addRow(
			(new CTag('h4', true, _('Access to services')))->addClass('input-section-header')
		);

		if ($data['service_write_access'] == CRoleHelper::SERVICES_ACCESS_ALL) {
			$permissions_form_list->addRow(
				_('Read-write access to services'),
				(new CDiv((new CSpan(_('All')))->addClass(ZBX_STYLE_STATUS_GREEN)))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}
		elseif ($data['service_write_access'] == CRoleHelper::SERVICES_ACCESS_NONE) {
			$permissions_form_list->addRow(
				_('Read-write access to services'),
				(new CDiv((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREY)))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}
		elseif ($data['service_write_list']) {
			$service_list = [];

			foreach ($data['service_write_list'] as $service) {
				$service_list[] = (new CSpan($service['name']))->addClass(ZBX_STYLE_STATUS_GREEN);
			}

			$permissions_form_list->addRow(
				_('Read-write access to services'),
				(new CDiv($service_list))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}

		if ($data['service_write_tag']['tag'] !== '') {
			$permissions_form_list->addRow(
				_('Read-write access to services with tag'),
				(new CDiv(
					(new CSpan([
						$data['service_write_tag']['tag'],
						$data['service_write_tag']['value'] !== '' ? ': '.$data['service_write_tag']['value'] : null
					]))->addClass(ZBX_STYLE_TAG)
				))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}

		if ($data['service_read_access'] == CRoleHelper::SERVICES_ACCESS_ALL) {
			$permissions_form_list->addRow(
				_('Read-only access to services'),
				(new CDiv((new CSpan(_('All')))->addClass(ZBX_STYLE_STATUS_GREEN)))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}
		elseif ($data['service_read_access'] == CRoleHelper::SERVICES_ACCESS_NONE) {
			$permissions_form_list->addRow(
				_('Read-only access to services'),
				(new CDiv((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREY)))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}
		elseif ($data['service_read_list']) {
			$service_list = [];

			foreach ($data['service_read_list'] as $service) {
				$service_list[] = (new CSpan($service['name']))->addClass(ZBX_STYLE_STATUS_GREEN);
			}

			$permissions_form_list->addRow(
				_('Read-only access to services'),
				(new CDiv($service_list))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}

		if ($data['service_read_tag']['tag'] !== '') {
			$permissions_form_list->addRow(
				_('Read-only access to services with tag'),
				(new CDiv(
					(new CSpan([
						$data['service_read_tag']['tag'],
						$data['service_read_tag']['value'] !== '' ? ': '.$data['service_read_tag']['value'] : null
					]))->addClass(ZBX_STYLE_TAG)
				))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}

		// Modules section.

		$permissions_form_list->addRow(
			(new CTag('h4', true, _('Access to modules')))->addClass('input-section-header')
		);

		if (!$data['modules']) {
			$permissions_form_list->addRow(italic(_('No enabled modules found.')));
		}
		else {
			$elements = [];

			foreach ($data['modules'] as $moduleid => $module_name) {
				$elements[] = (new CSpan($module_name))->addClass(
					array_key_exists($moduleid, $data['disabled_moduleids'])
							|| $data['modules_rules'][$moduleid] == MODULE_STATUS_DISABLED
						? ZBX_STYLE_STATUS_GREY
						: ZBX_STYLE_STATUS_GREEN
				);
			}

			if ($elements) {
				$permissions_form_list->addRow((new CDiv($elements))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
				);
			}
		}

		// API section.

		$api_access_enabled = CRoleHelper::checkAccess('api.access', $data['roleid']);
		$permissions_form_list
			->addRow((new CTag('h4', true, _('Access to API')))->addClass('input-section-header'))
			->addRow((new CDiv((new CSpan($api_access_enabled ? _('Enabled') : _('Disabled')))->addClass(
					$api_access_enabled ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
				)))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->addClass('rules-status-container')
			);

		$api_methods = CRoleHelper::getRoleApiMethods($data['roleid']);

		if ($api_methods) {
			$api_access_mode_allowed = CRoleHelper::checkAccess('api.mode', $data['roleid']);
			$elements = [];

			foreach ($api_methods as $api_method) {
				$elements[] = (new CSpan($api_method))->addClass(
					$api_access_mode_allowed ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
				);
			}

			$permissions_form_list->addRow($api_access_mode_allowed ? _('Allowed methods') : _('Denied methods'),
				(new CDiv($elements))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->addClass('rules-status-container')
			);
		}

		// Actions section.

		$permissions_form_list->addRow((new CTag('h4', true, _('Access to actions')))->addClass('input-section-header'));
		$elements = [];

		foreach (CRoleHelper::getActionsLabels($data['user_type']) as $rule_name => $rule_label) {
			$elements[] = (new CSpan($rule_label))
				->addClass(CRoleHelper::checkAccess($rule_name, $data['roleid'])
					? ZBX_STYLE_STATUS_GREEN
					: ZBX_STYLE_STATUS_GREY
				);
		}

		$permissions_form_list->addRow((new CDiv($elements))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->addClass('rules-status-container')
		);
	}

	$tabs->addTab('permissionsTab', _('Permissions'), $permissions_form_list);
}

// Frontend notifications tab.
if ($data['action'] !== 'user.edit') {
	$messaging_form_list = (new CFormList())
		->addRow(_('Frontend notifications'),
			(new CCheckBox('messages[enabled]'))
				->setChecked($data['messages']['enabled'] == 1)
				->setUncheckedValue(0)
		)
		->addRow(_('Message timeout'),
			(new CTextBox('messages[timeout]', $data['messages']['timeout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
			'timeout_row'
		)
		->addRow(new CLabel(_('Play sound'), 'label-sounds'),
			(new CSelect('messages[sounds.repeat]'))
				->setId('messages_sounds.repeat')
				->setFocusableElementId('label-sounds')
				->setValue($data['messages']['sounds.repeat'])
				->addOptions(CSelect::createOptionsFromArray([
					1 => _('Once'),
					10 => _n('%1$s second', '%1$s seconds', 10),
					-1 => _('Message timeout')
				])),
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
				(new CSelect('messages[sounds.recovery]'))
					->setId('messages_sounds.recovery')
					->setValue($data['messages']['sounds.recovery'])
					->addOptions(CSelect::createOptionsFromArray($zbx_sounds)),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("testUserSound('messages_sounds.recovery');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('AudioControl.stop();')
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
				->setLabel(CSeverityHelper::getName($severity))
				->setChecked(array_key_exists($severity, $data['messages']['triggers.severities']))
				->setUncheckedValue(0),
			[
				(new CSelect('messages[sounds.'.$severity.']'))
					->setId('messages_sounds.'.$severity)
					->setValue($data['messages']['sounds.'.$severity])
					->addOptions(CSelect::createOptionsFromArray($zbx_sounds)),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("testUserSound('messages_sounds.".$severity."');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('AudioControl.stop();')
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

	$tabs->addTab('notificationsTab', _('Frontend notifications'), $messaging_form_list,
		TAB_INDICATOR_FRONTEND_NOTIFICATIONS
	);
}

// Append buttons to form.
if ($data['action'] === 'user.edit') {
	$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
		->setArgument('action', 'user.list')
		->setArgument('page', CPagerHelper::loadPage('user.list', null))
	))->setId('cancel');

	if ($data['userid'] != 0) {
		$tabs->setFooter(makeFormFooter(
			(new CSubmitButton(_('Update'), 'action', 'user.update'))->setId('update'),
			[
				(new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
					->setArgument('action', 'user.delete')
					->setArgument('userids', [$data['userid']])
					->setArgument(CSRF_TOKEN_NAME, $csrf_token),
					_('Delete selected user?')
				))
					->setEnabled(bccomp(CWebUser::$data['userid'], $data['userid']) != 0)
					->setId('delete'),
				$cancel_button
			]
		));
	}
	else {
		$tabs->setFooter(makeFormFooter(
			(new CSubmitButton(_('Add'), 'action', 'user.create'))->setId('add'),
			[
				$cancel_button
			]
		));
	}
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', 'userprofile.update'))->setId('update'),
		[(new CRedirectButton(_('Cancel'), CMenuHelper::getFirstUrl()))->setId('cancel')]
	));
}

// Append tab to form.
$user_form->addItem($tabs);
$html_page
	->addItem($user_form)
	->show();

if ($data['action'] === 'user.edit') {
	(new CScriptTag('view.init('.json_encode([
		'userid' => $data['userid'] ?: null
	]).');'))
		->setOnDocumentReady()
		->show();
}
else {
	(new CScriptTag('view.init();'))
		->setOnDocumentReady()
		->show();
}
