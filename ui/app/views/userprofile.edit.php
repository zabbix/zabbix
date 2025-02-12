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

$this->includeJsFile('userprofile.edit.js.php');

$html_page = new CHtmlPage();

$widget_name = _('Profile');
$html_page->setTitleSubmenu(getUserSettingsSubmenu());
$doc_url = CDocHelper::USERS_USERPROFILE_EDIT;
$csrf_token = CCsrfTokenHelper::get('userprofile');

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
$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('userprofile-form')
	->setName('userprofile_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('userid', $data['userid']);

$form_list = new CFormList('user_form_list');

$user_full_name = ($data['name'] !== '' || $data['surname'] !== '')
	? $data['name'].' '.$data['surname']
	: $data['username'];

$form_list
	->addRow(_('Name'),
		(new CSpan($user_full_name))
	);

if ($data['change_password']) {
	$form->disablePasswordAutofill();

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

	$current_password->setAttribute('autofocus', 'autofocus');

	if (CWebUser::$data['userid'] == $data['userid']) {
		$form_list
			->addRow((new CLabel(_('Current password'), 'current_password'))->setAsteriskMark(), $current_password);
	}

	$form_list
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
	$hint = !$change_password_enabled
		? $hint = makeErrorIcon(_('Password can only be changed for users using the internal Zabbix authentication.'))
		: null;

	$form_list->addRow(_('Password'), [
		(new CSimpleButton(_('Change password')))
			->setEnabled($change_password_enabled)
			->setAttribute('autofocus', 'autofocus')
			->onClick('submitFormWithParam("'.$form->getName().'", "change_password", "1");')
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

$form_list
	->addRow(new CLabel(_('Language'), $lang_select->getFocusableElementId()), [$lang_select, $language_error])
	->addRow(new CLabel(_('Time zone'), $timezone_select->getFocusableElementId()), $timezone_select)
	->addRow(new CLabel(_('Theme'), $theme_select->getFocusableElementId()), $theme_select);

// Append auto-login & auto-logout to form list.
if ($data['username'] !== ZBX_GUEST_USER) {
	$autologout = ($data['autologout'] !== '0') ? $data['autologout'] : DB::getDefault('users', 'autologout');

	$form_list->addRow(_('Auto-login'),
		(new CCheckBox('autologin'))
			->setUncheckedValue('0')
			->setChecked($data['autologin'])
	);
	$form_list->addRow(_('Auto-logout'), [
		(new CCheckBox(null))
			->setId('autologout_visible')
			->setChecked($data['autologout'] !== '0'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('autologout', $autologout, false, DB::getFieldLength('users', 'autologout')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	]);
}

$form_list
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

$tabs->addTab('userTab', _('User'), $form_list);

// Append buttons to form.
$tabs->setFooter(makeFormFooter(
	(new CSubmitButton(_('Update'), 'action', 'userprofile.update'))->setId('update'),
	[(new CRedirectButton(_('Cancel'), CMenuHelper::getFirstUrl()))->setId('cancel')]
));

// Append tab to form.
$form->addItem($tabs);
$html_page
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
