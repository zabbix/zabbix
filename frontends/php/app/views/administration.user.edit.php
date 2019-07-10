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
$this->includeJSfile('app/views/administration.user.edit.js.php');
$this->addJsFile('multiselect.js');

$widget = (new CWidget())->setTitle(_('Users'));
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

$user_form_list->addRow(
	(new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
	(new CTextBox('alias', $data['alias']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
		->setAttribute('maxlength', DB::getFieldLength('users', 'alias'))
);
$form_autofocus = true;
$user_form_list
	->addRow(_x('Name', 'user first name'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
	)
	->addRow(_('Surname'),
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

// Append common fields to form list.
$user_form_list = commonUserform($user_form, $user_form_list, $data, $form_autofocus);

// Media tab.
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

// Append form lists to tab.
$tabs->addTab('userTab', _('User'), $user_form_list);
$tabs->addTab('mediaTab', _('Media'), $user_media_form_list);

// Permissions tab.
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

// Append buttons to form.
if ($data['userid'] != 0) {
	$buttons = [
		(new CRedirectButton(_('Cancel'), 'zabbix.php?action=user.list'))->setId('cancel')
	];

	$delete_btn = (new CRedirectButton(_('Delete'),
		'zabbix.php?action=user.delete&sid='.$data['sid'].'&group_userid[]='.$data['userid'],
		_('Delete selected user?')
	))->setId('delete');

	if (bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$delete_btn->setAttribute('disabled', 'disabled');
	}

	array_unshift($buttons, $delete_btn);

	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', 'user.update'))->setId('update'),
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
