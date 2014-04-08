<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

$userWidget = new CWidget();

if ($this->data['is_profile']) {
	$userWidget->addPageHeader(_('USER PROFILE').NAME_DELIMITER.$this->data['name'].' '.$this->data['surname']);
}
else {
	$userWidget->addPageHeader(_('CONFIGURATION OF USERS'));
}

// create form
$userForm = new CForm();
$userForm->setName('userForm');
$userForm->addVar('config', get_request('config', 0));
$userForm->addVar('form', $this->data['form']);
$userForm->addVar('form_refresh', $this->data['form_refresh'] + 1);

if (isset($_REQUEST['userid'])) {
	$userForm->addVar('userid', $this->data['userid']);
}

/*
 * User tab
 */
$userFormList = new CFormList('userFormList');

if (!$data['is_profile']) {
	$nameTextBox = new CTextBox('alias', $this->data['alias'], ZBX_TEXTBOX_STANDARD_SIZE);
	$nameTextBox->attr('autofocus', 'autofocus');
	$userFormList->addRow(_('Alias'), $nameTextBox);
	$userFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
	$userFormList->addRow(_('Surname'), new CTextBox('surname', $this->data['surname'], ZBX_TEXTBOX_STANDARD_SIZE));
}

// append password to form list
if ($data['auth_type'] == ZBX_AUTH_INTERNAL) {
	if (empty($this->data['userid']) || isset($this->data['change_password'])) {
		$userFormList->addRow(_('Password'), new CPassBox('password1', $this->data['password1'], ZBX_TEXTBOX_SMALL_SIZE));
		$userFormList->addRow(_('Password (once again)'), new CPassBox('password2', $this->data['password2'], ZBX_TEXTBOX_SMALL_SIZE));

		if (isset($this->data['change_password'])) {
			$userForm->addVar('change_password', $this->data['change_password']);
		}
	}
	else {
		$passwdButton = new CSubmit('change_password', _('Change password'), null, 'formlist');
		if ($this->data['alias'] == ZBX_GUEST_USER) {
			$passwdButton->setAttribute('disabled', 'disabled');
		}

		$userFormList->addRow(_('Password'), $passwdButton);
	}
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
		array(
			$lstGroups,
			new CButton('add_group', _('Add'),
				'return PopUp("popup_usrgrp.php?dstfrm='.$userForm->getName().'&list_name=user_groups_to_del[]&var_name=user_groups", 450, 450);', 'formlist'),
			BR(),
			(count($this->data['user_groups']) > 0)
				? new CSubmit('del_user_group', _('Delete selected'), null, 'formlist')
				: null
		)
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
	$languageComboBox->attr('disabled', 'disabled');
}
elseif (!$allLocalesAvailable) {
	$languageError = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

$userFormList->addRow(
	_('Language'),
	$languageError ? array($languageComboBox, SPACE, new CSpan($languageError, 'red wrap')) : $languageComboBox
);

// append themes to form list
$themes = array_merge(array(THEME_DEFAULT => _('System default')), Z::getThemes());
$themeComboBox = new CComboBox('theme', $this->data['theme'], null, $themes);
$userFormList->addRow(_('Theme'), $themeComboBox);

// append auto-login & auto-logout to form list
$autologoutCheckBox = new CCheckBox('autologout_visible', ($this->data['autologout'] == 0) ? 'no' : 'yes');
$autologoutTextBox = new CNumericBox('autologout', ($this->data['autologout'] == 0) ? '900' : $this->data['autologout'], 4);
if (!$this->data['autologout']) {
	$autologoutTextBox->setAttribute('disabled', 'disabled');
}
$userFormList->addRow(_('Auto-login'), new CCheckBox('autologin', $this->data['autologin'], null, 1));
$userFormList->addRow(_('Auto-logout (min 90 seconds)'), array($autologoutCheckBox, $autologoutTextBox));
$userFormList->addRow(_('Refresh (in seconds)'), new CNumericBox('refresh', $this->data['refresh'], 4));
$userFormList->addRow(_('Rows per page'), new CNumericBox('rows_per_page', $this->data['rows_per_page'], 6));
$userFormList->addRow(_('URL (after login)'), new CTextBox('url', $this->data['url'], ZBX_TEXTBOX_STANDARD_SIZE));

/*
 * Media tab
 */
if (uint_in_array(CWebUser::$data['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN))) {
	$userMediaFormList = new CFormList('userMediaFormList');
	$userForm->addVar('user_medias', $this->data['user_medias']);

	$mediaTableInfo = new CTableInfo(_('No media found.'));

	foreach ($this->data['user_medias'] as $id => $media) {
		if (!isset($media['active']) || !$media['active']) {
			$status = new CLink(_('Enabled'), '#', 'enabled');
			$status->onClick('return create_var("'.$userForm->getName().'","disable_media",'.$id.', true);');
		}
		else {
			$status = new CLink(_('Disabled'), '#', 'disabled');
			$status->onClick('return create_var("'.$userForm->getName().'","enable_media",'.$id.', true);');
		}

		$mediaUrl = '?dstfrm='.$userForm->getName().
						'&media='.$id.
						'&mediatypeid='.$media['mediatypeid'].
						'&sendto='.urlencode($media['sendto']).
						'&period='.$media['period'].
						'&severity='.$media['severity'].
						'&active='.$media['active'];

		foreach (getSeverityCaption() as $key => $caption) {
			$mediaActive = ($media['severity'] & (1 << $key));

			$mediaSeverity[$key] = new CSpan(zbx_substr($caption, 0, 1), $mediaActive ? 'enabled' : null);
			$mediaSeverity[$key]->setHint($caption.($mediaActive ? ' (on)' : ' (off)'));
		}

		$mediaTableInfo->addRow(array(
			new CCheckBox('user_medias_to_del['.$id.']', null, null, $id),
			new CSpan($media['description'], 'nowrap'),
			new CSpan($media['sendto'], 'nowrap'),
			new CSpan($media['period'], 'nowrap'),
			$mediaSeverity,
			$status,
			new CButton('edit_media', _('Edit'), 'return PopUp("popup_media.php'.$mediaUrl.'", 550, 400);', 'link_menu'))
		);
	}

	$userMediaFormList->addRow(_('Media'), array($mediaTableInfo,
		new CButton('add_media', _('Add'), 'return PopUp("popup_media.php?dstfrm='.$userForm->getName().'", 550, 400);', 'link_menu'),
		SPACE,
		SPACE,
		(count($this->data['user_medias']) > 0) ? new CSubmit('del_user_media', _('Delete selected'), null, 'link_menu') : null
	));
}

/*
 * Profile fields
 */
if ($this->data['is_profile']) {
	$zbxSounds = getSounds();

	$userMessagingFormList = new CFormList('userMessagingFormList');
	$userMessagingFormList->addRow(_('Frontend messaging'), new CCheckBox('messages[enabled]', $this->data['messages']['enabled'], null, 1));
	$userMessagingFormList->addRow(_('Message timeout (seconds)'), new CNumericBox('messages[timeout]', $this->data['messages']['timeout'], 5), false, 'timeout_row');

	$repeatSound = new CComboBox('messages[sounds.repeat]', $this->data['messages']['sounds.repeat'], 'javascript: if (IE) { submit(); }');
	$repeatSound->addItem(1, _('Once'));
	$repeatSound->addItem(10, '10 '._('Seconds'));
	$repeatSound->addItem(-1, _('Message timeout'));
	$userMessagingFormList->addRow(_('Play sound'), $repeatSound, false, 'repeat_row');

	$soundList = new CComboBox('messages[sounds.recovery]', $this->data['messages']['sounds.recovery']);
	foreach ($zbxSounds as $filename => $file) {
		$soundList->addItem($file, $filename);
	}

	$resolved = array(
		new CCheckBox('messages[triggers.recovery]', $this->data['messages']['triggers.recovery'], null, 1),
		_('Recovery'),
		SPACE,
		$soundList,
		new CButton('start', _('Play'), "javascript: testUserSound('messages_sounds.recovery');", 'formlist'),
		new CButton('stop', _('Stop'), 'javascript: AudioList.stopAll();', 'formlist')
	);

	$triggersTable = new CTable('', 'invisible');
	$triggersTable->addRow($resolved);

	$msgVisibility = array('1' => array(
		'messages[timeout]',
		'messages[sounds.repeat]',
		'messages[sounds.recovery]',
		'messages[triggers.recovery]',
		'timeout_row',
		'repeat_row',
		'triggers_row'
	));

	// trigger sounds
	$severities = array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED,
		TRIGGER_SEVERITY_INFORMATION,
		TRIGGER_SEVERITY_WARNING,
		TRIGGER_SEVERITY_AVERAGE,
		TRIGGER_SEVERITY_HIGH,
		TRIGGER_SEVERITY_DISASTER
	);
	foreach ($severities as $severity) {
		$soundList = new CComboBox('messages[sounds.'.$severity.']', $this->data['messages']['sounds.'.$severity]);
		foreach ($zbxSounds as $filename => $file) {
			$soundList->addItem($file, $filename);
		}

		$triggersTable->addRow(array(
			new CCheckBox('messages[triggers.severities]['.$severity.']', isset($this->data['messages']['triggers.severities'][$severity]), null, 1),
			getSeverityCaption($severity),
			SPACE,
			$soundList,
			new CButton('start', _('Play'), "javascript: testUserSound('messages_sounds.".$severity."');", 'formlist'),
			new CButton('stop', _('Stop'), 'javascript: AudioList.stopAll();', 'formlist')
		));

		zbx_subarray_push($msgVisibility, 1, 'messages[triggers.severities]['.$severity.']');
		zbx_subarray_push($msgVisibility, 1, 'messages[sounds.'.$severity.']');
	}

	$userMessagingFormList->addRow(_('Trigger severity'), $triggersTable, false, 'triggers_row');

	zbx_add_post_js("
		jQuery('#messages_enabled').bind('click', function() {
			if (this.checked
					&& !jQuery(\"input[id='messages_triggers.recovery']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_0']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_1']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_2']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_3']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_4']\").is(':checked')
					&& !jQuery(\"input[id='messages_triggers.severities_5']\").is(':checked')) {
				jQuery(\"input[id='messages_triggers.recovery']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_0']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_1']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_2']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_3']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_4']\").attr('checked', true);
				jQuery(\"input[id='messages_triggers.severities_5']\").attr('checked', true);
			}

			// enable/disable childs fields
			if (this.checked) {
				jQuery('#messagingTab input, #messagingTab select').removeAttr('disabled');
			}
			else {
				jQuery('#messagingTab input, #messagingTab select').attr('disabled', 'disabled');
				jQuery('#messages_enabled').removeAttr('disabled');
			}
		});

		// initial state: enable/disable childs fields
		if (jQuery('#messages_enabled').is(':checked')) {
			jQuery('#messagingTab input, #messagingTab select').removeAttr('disabled');
		}
		else {
			jQuery('#messagingTab input, #messagingTab select').attr('disabled', 'disabled');
			jQuery('#messages_enabled').removeAttr('disabled');
		}"
	);
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

	$userTypeComboBox = new CComboBox('user_type', $this->data['user_type'], 'submit();');
	$userTypeComboBox->addItem(USER_TYPE_ZABBIX_USER, user_type2str(USER_TYPE_ZABBIX_USER));
	$userTypeComboBox->addItem(USER_TYPE_ZABBIX_ADMIN, user_type2str(USER_TYPE_ZABBIX_ADMIN));
	$userTypeComboBox->addItem(USER_TYPE_SUPER_ADMIN, user_type2str(USER_TYPE_SUPER_ADMIN));

	if (isset($this->data['userid']) && bccomp(CWebUser::$data['userid'], $this->data['userid']) == 0) {
		$userTypeComboBox->setEnabled('disabled');
		$permissionsFormList->addRow(_('User type'), array($userTypeComboBox, SPACE, new CSpan(_('User can\'t change type for himself'))));
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

// append tab to form
$userForm->addItem($userTab);

// append buttons to form
if (empty($this->data['userid'])) {
	$userForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), new CButtonCancel(url_param('config'))));
}
else {
	if ($this->data['is_profile']) {
		$userForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), new CButtonCancel(url_param('config'))));
	}
	else {
		$deleteButton = new CButtonDelete(_('Delete selected user?'), url_param('form').url_param('userid').url_param('config'));

		if (bccomp(CWebUser::$data['userid'], $this->data['userid']) == 0) {
			$deleteButton->setAttribute('disabled', 'disabled');
		}

		$userForm->addItem(makeFormFooter(
			new CSubmit('save', _('Save')),
			array(
				$deleteButton,
				new CButtonCancel(url_param('config'))
			)
		));
	}
}

// append form to widget
$userWidget->addItem($userForm);

return $userWidget;
