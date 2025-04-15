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

$this->includeJsFile('userprofile.notification.edit.js.php');

$html_page = new CHtmlPage();

$widget_name = _('Notifications');
$html_page->setTitleSubmenu(getUserSettingsSubmenu());
$doc_url = CDocHelper::USERS_USERPROFILE_NOTIFICATION_EDIT;
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
	->setName('userprofile_notification_form')
	->setId('userprofile-notification-form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('userid', $data['userid']);

// Media tab.
$media = new CPartial('user.edit.media.tab', [
	'form' => $form,
	'can_edit_media' => CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA)
] + $data);
$tabs->addTab('mediaTab', _('Media'), $media, TAB_INDICATOR_MEDIA);

// Frontend notifications tab.
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
				->addClass('js-test_sound')
				->setAttribute('data-message_sounds', 'recovery')
				->removeId(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('stop', _('Stop')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-audio_stop')
				->removeId()
		]
	]);

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
				->addClass('js-test_sound')
				->setAttribute('data-message_sounds', $severity)
				->removeId(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('stop', _('Stop')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-audio_stop')
				->removeId()
		]
	]);
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

// Append buttons to form.
$tabs->setFooter(makeFormFooter(
	(new CSubmitButton(_('Update'), 'action', 'userprofile.notification.update'))->setId('update'),
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
