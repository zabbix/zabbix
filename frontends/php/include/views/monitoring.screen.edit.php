<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/js/monitoring.screen.edit.js.php';

$widget = (new CWidget())->setTitle(_('Screens'));

$tabs = new CTabView();

if (!$data['form_refresh']) {
	$tabs->setSelected(0);
}

if ($data['screen']['templateid']) {
	$widget->addItem(get_header_host_table('screens', $data['screen']['templateid']));
}

// create form
$form = (new CForm())
	->setName('screenForm')
	->addVar('form', $data['form']);

if ($data['screen']['templateid'] != 0) {
	$form->addVar('templateid', $data['screen']['templateid']);
}
else {
	$form->addVar('current_user_userid', $data['current_user_userid'])
		->addVar('current_user_fullname', getUserFullname($data['users'][$data['current_user_userid']]));
}

if ($data['screen']['screenid']) {
	$form->addVar('screenid', $data['screen']['screenid']);
}

$user_type = CWebUser::getType();

// Create screen form list.
$screen_tab = (new CFormList());

if (!$data['screen']['templateid']) {
	// Screen owner multiselect.
	$multiselect_data = [
		'name' => 'userid',
		'selectedLimit' => 1,
		'objectName' => 'users',
		'disabled' => ($user_type != USER_TYPE_SUPER_ADMIN && $user_type != USER_TYPE_ZABBIX_ADMIN),
		'popup' => [
			'parameters' => 'srctbl=users&dstfrm='.$form->getName().'&dstfld1=userid&srcfld1=userid&srcfld2=fullname'
		]
	];

	$screen_ownerid = $data['screen']['userid'];

	// If screen owner does not exist or is not allowed to display.
	if ($screen_ownerid === '' || $screen_ownerid && array_key_exists($screen_ownerid, $data['users'])) {
		// Screen owner data.
		if ($screen_ownerid) {
			$owner_data = [[
				'id' => $screen_ownerid,
				'name' => getUserFullname($data['users'][$screen_ownerid])
			]];
		}
		else {
			$owner_data = [];
		}

		$multiselect_data['data'] = $owner_data;

		// Append multiselect to screen tab.
		$screen_tab->addRow(_('Owner'),
			(new CMultiSelect($multiselect_data))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);
	}
	else {
		$multiselect_userid = (new CMultiSelect($multiselect_data))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

		// Administrators can change screen owner, but cannot see users from other groups.
		if ($user_type == USER_TYPE_ZABBIX_ADMIN) {
			$screen_tab->addRow(_('Owner'), $multiselect_userid)
				->addRow('', _('Inaccessible user'), 'inaccessible_user');
		}
		else {
			// For regular users and guests, only information message is displayed without multiselect.
			$screen_tab->addRow(_('Owner'), [
				(new CSpan(_('Inaccessible user')))->setId('inaccessible_user'),
				(new CSpan($multiselect_userid))
					->addStyle('display: none;')
					->setId('multiselect_userid_wrapper')
			]);
		}
	}
}

$screen_tab->addRow(_('Name'),
		(new CTextBox('name', $data['screen']['name']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Columns'),
		(new CNumericBox('hsize', $data['screen']['hsize'], 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Rows'),
		(new CNumericBox('vsize', $data['screen']['vsize'], 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

// append tab to form
$tabs->addTab('screen_tab', _('Screen'), $screen_tab);
if (!$data['screen']['templateid']) {
	// User group sharing table.
	$user_group_shares_table = (new CTable())
		->setHeader([_('User groups'), _('Permissions'), _('Action')])
		->setAttribute('style', 'width: 100%;');

	$add_user_group_btn = ([(new CButton(null, _('Add')))
		->onClick('return PopUp("popup.php?dstfrm='.$form->getName().
			'&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name&multiselect=1")'
		)
		->addClass(ZBX_STYLE_BTN_LINK)]);

	$user_group_shares_table->addRow(
		(new CRow(
			(new CCol($add_user_group_btn))->setColSpan(3)
		))->setId('user_group_list_footer')
	);

	$user_groups = [];

	foreach ($data['screen']['userGroups'] as $user_group) {
		$user_groupid = $user_group['usrgrpid'];
		$user_groups[$user_groupid] = [
			'usrgrpid' => $user_groupid,
			'name' => $data['user_groups'][$user_groupid]['name'],
			'permission' => $user_group['permission']
		];
	}

	$js_insert = 'addPopupValues('.zbx_jsvalue(['object' => 'usrgrpid', 'values' => $user_groups]).');';

	// User sharing table.
	$user_shares_table = (new CTable())
		->setHeader([_('Users'), _('Permissions'), _('Action')])
		->setAttribute('style', 'width: 100%;');

	$add_user_btn = ([(new CButton(null, _('Add')))
		->onClick('return PopUp("popup.php?dstfrm='.$form->getName().
			'&srctbl=users&srcfld1=userid&srcfld2=fullname&multiselect=1")'
		)
		->addClass(ZBX_STYLE_BTN_LINK)]);

	$user_shares_table->addRow(
		(new CRow(
			(new CCol($add_user_btn))->setColSpan(3)
		))->setId('user_list_footer')
	);

	$users = [];

	foreach ($data['screen']['users'] as $user) {
		$userid = $user['userid'];
		$users[$userid] = [
			'id' => $userid,
			'name' => getUserFullname($data['users'][$userid]),
			'permission' => $user['permission']
		];
	}

	$js_insert .= 'addPopupValues('.zbx_jsvalue(['object' => 'userid', 'values' => $users]).');';

	zbx_add_post_js($js_insert);

	$sharing_tab = (new CFormList('sharing_form'))
		->addRow(_('Type'),
		(new CRadioButtonList('private', (int) $data['screen']['private']))
			->addValue(_('Private'), PRIVATE_SHARING, 'private_' . PRIVATE_SHARING)
			->addValue(_('Public'), PUBLIC_SHARING, 'private_' . PUBLIC_SHARING)
			->setModern(true)
		)
		->addRow(_('List of user group shares'),
			(new CDiv($user_group_shares_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		)
		->addRow(_('List of user shares'),
			(new CDiv($user_shares_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		);

	// Append data to form.
	$tabs->addTab('sharing_tab', _('Sharing'), $sharing_tab);
}

if ($data['form'] === 'update') {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButton('clone', _('Clone')),
			new CButton('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete screen?'), url_params(['form', 'screenid', 'templateid'])),
			new CButtonCancel(url_param('templateid'))
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('templateid'))]
	));
}

$form->addItem($tabs);

$widget->addItem($form);

return $widget;
