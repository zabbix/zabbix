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
$this->includeJSfile('app/views/administration.userprofile.edit.js.php');
$this->addJsFile('multiselect.js');

$widget = ($data['name'] !== '' || $data['surname'] !== '')
	? (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['name'].' '.$data['surname'])
	: (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['alias']);
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

// Append common fields to form list.
$user_form_list = commonUserform($user_form, $user_form_list, $data, $form_autofocus);

// Media tab.
if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
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

if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
	$tabs->addTab('mediaTab', _('Media'), $user_media_form_list);
}

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

$repeat_sound = new CComboBox('messages[sounds.repeat]', $data['messages']['sounds.repeat'],
	'if (IE) { submit() }',
	[
		1 => _('Once'),
		10 => '10 '._('Seconds'),
		-1 => _('Message timeout')
	]
);
$user_messaging_form_list->addRow(_('Play sound'), $repeat_sound, 'repeat_row');

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

// Append buttons to form.
$buttons = [
	(new CRedirectButton(_('Cancel'), ZBX_DEFAULT_URL))->setId('cancel')
];

$tabs->setFooter(makeFormFooter(
	(new CSubmitButton(_('Update'), 'action', 'userprofile.update'))->setId('update'),
	$buttons
));

// Append tab to form.
$user_form->addItem($tabs);
$widget->addItem($user_form)->show();
