<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/js/monitoring.slideconf.edit.js.php';

$widget = (new CWidget())->setTitle(_('Slide shows'));

$tabs = new CTabView();

if (!$data['form_refresh']) {
	$tabs->setSelected(0);
}

// create form
$form = (new CForm())
	->setName('slideForm')
	->addVar('form', $data['form'])
	->addVar('slides', $data['slides_without_delay'])
	->addVar('current_user_userid', $data['current_user_userid'])
	->addVar('current_user_fullname', getUserFullname($data['users'][$data['current_user_userid']]));

if (!empty($data['slideshow']['slideshowid'])) {
	$form->addVar('slideshowid', $data['slideshow']['slideshowid']);
}

$user_type = CWebUser::getType();

// Create slide form list.
$slideshow_tab = (new CFormList());

// Slide show owner multiselect.
$multiselect_data = [
	'name' => 'userid',
	'selectedLimit' => 1,
	'objectName' => 'users',
	'disabled' => ($user_type != USER_TYPE_SUPER_ADMIN && $user_type != USER_TYPE_ZABBIX_ADMIN),
	'popup' => [
		'parameters' => [
			'srctbl' => 'users',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'userid',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname'
		]
	]
];

$slideshow_ownerid = $data['slideshow']['userid'];

// If slide show owner does not exist or is not allowed to display.
if ($slideshow_ownerid === '' || $slideshow_ownerid && array_key_exists($slideshow_ownerid, $data['users'])) {
	// Slide show owner data.
	if ($slideshow_ownerid) {
		$owner_data = [[
			'id' => $slideshow_ownerid,
			'name' => getUserFullname($data['users'][$slideshow_ownerid])
		]];
	}
	else {
		$owner_data = [];
	}

	$multiselect_data['data'] = $owner_data;

	// Append multiselect to slide show tab.
	$slideshow_tab->addRow(
		(new CLabel(_('Owner'), 'userid'))->setAsteriskMark(),
		(new CMultiSelect($multiselect_data))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}
else {
	$multiselect_userid = (new CMultiSelect($multiselect_data))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	// Administrators can change slide show owner, but cannot see users from other groups.
	if ($user_type == USER_TYPE_ZABBIX_ADMIN) {
		$slideshow_tab
			->addRow(_('Owner'), $multiselect_userid)
			->addRow('', _('Inaccessible user'), 'inaccessible_user');
	}
	else {
		// For regular users and guests, only information message is displayed without multiselect.
		$slideshow_tab->addRow(_('Owner'), [
			(new CSpan(_('Inaccessible user')))->setId('inaccessible_user'),
			(new CSpan($multiselect_userid))
				->addStyle('display: none;')
				->setId('multiselect_userid_wrapper')
		]);
	}
}

$slideshow_tab
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['slideshow']['name']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		(new CLabel(_('Default delay'), 'delay'))->setAsteriskMark(),
		(new CTextBox('delay', $data['slideshow']['delay']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);

// append slide table
$slideTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setId('slideTable')
	->setHeader([
		(new CColHeader())->setWidth(15),
		(new CColHeader())->setWidth(15),
		_('Screen'),
		(new CColHeader(_('Delay')))->setWidth(70),
		(new CColHeader(_('Action')))->setWidth(50)
	]);

$i = 1;

foreach ($data['slideshow']['slides'] as $key => $slides) {
	$slideTable->addRow(
		(new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan($i++.':'))
				->addClass('rowNum')
				->setId('current_slide_'.$key),
			$data['slideshow']['screens'][$slides['screenid']]['name'],
			(new CTextBox('slides['.$key.'][delay]', $slides['delay']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAttribute('placeholder', _('default')),
			(new CCol(
				(new CButton('remove_'.$key, _('Remove')))
					->onClick('javascript: removeSlide(this);')
					->addClass(ZBX_STYLE_BTN_LINK)
					->setAttribute('remove_slide', $key)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('slides_'.$key)
	);
}

$addButtonColumn = (new CCol(
		(new CButton('add', _('Add')))
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'screens',
					'srcfld1' => 'screenid',
					'dstfrm' => $form->getName(),
					'multiselect' => '1'
				]).', null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK)
	))->setColSpan(5);

$addButtonColumn->setAttribute('style', 'vertical-align: middle;');
$slideTable->addRow((new CRow($addButtonColumn))->setId('screenListFooter'));

$slideshow_tab->addRow(
	(new CLabel(_('Slides'), $slideTable->getId()))->setAsteriskMark(),
	(new CDiv($slideTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// Append tabs to form.
$tabs->addTab('slideTab', _('Slide'), $slideshow_tab);

// User group sharing table.
$user_group_shares_table = (new CTable())
	->setHeader([_('User groups'), _('Permissions'), _('Action')])
	->setAttribute('style', 'width: 100%;');

$add_user_group_btn = ([(new CButton(null, _('Add')))
	->onClick('return PopUp("popup.generic",'.
		CJs::encodeJson([
			'srctbl' => 'usrgrp',
			'srcfld1' => 'usrgrpid',
			'srcfld2' => 'name',
			'dstfrm' => $form->getName(),
			'multiselect' => '1'
		]).', null, this);'
	)
	->addClass(ZBX_STYLE_BTN_LINK)]);

$user_group_shares_table->addRow(
	(new CRow(
		(new CCol($add_user_group_btn))->setColSpan(3)
	))->setId('user_group_list_footer')
);

$user_groups = [];

foreach ($data['slideshow']['userGroups'] as $user_group) {
	$user_groupid = $user_group['usrgrpid'];
	if (array_key_exists($user_groupid, $data['user_groups'])) {
		$user_groups[$user_groupid] = [
			'usrgrpid' => $user_groupid,
			'name' => $data['user_groups'][$user_groupid]['name'],
			'permission' => $user_group['permission']
		];
	}
}

$js_insert = 'addPopupValues('.zbx_jsvalue(['object' => 'usrgrpid', 'values' => $user_groups]).');';

// User sharing table.
$user_shares_table = (new CTable())
	->setHeader([_('Users'), _('Permissions'), _('Action')])
	->setAttribute('style', 'width: 100%;');

$add_user_btn = ([(new CButton(null, _('Add')))
	->onClick('return PopUp("popup.generic",'.
		CJs::encodeJson([
			'srctbl' => 'users',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname',
			'dstfrm' => $form->getName(),
			'multiselect' => '1'
		]).', null, this);'
	)
	->addClass(ZBX_STYLE_BTN_LINK)]);

$user_shares_table->addRow(
	(new CRow(
		(new CCol($add_user_btn))->setColSpan(3)
	))->setId('user_list_footer')
);

$users = [];

foreach ($data['slideshow']['users'] as $user) {
	$userid = $user['userid'];
	if (array_key_exists($userid, $data['users'])) {
		$users[$userid] = [
			'id' => $userid,
			'name' => getUserFullname($data['users'][$userid]),
			'permission' => $user['permission']
		];
	}
}

$js_insert .= 'addPopupValues('.zbx_jsvalue(['object' => 'userid', 'values' => $users]).');';

zbx_add_post_js($js_insert);

$sharing_tab = (new CFormList('sharing_form'))
	->addRow(_('Type'),
	(new CRadioButtonList('private', (int) $data['slideshow']['private']))
		->addValue(_('Private'), PRIVATE_SHARING)
		->addValue(_('Public'), PUBLIC_SHARING)
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

// append buttons to form
if (isset($data['slideshow']['slideshowid'])) {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			new CButtonDelete(_('Delete slide show?'), url_params(['form', 'slideshowid'])),
			new CRedirectButton(_('Cancel'), 'slides.php')
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($tabs);
$widget->addItem($form);

return $widget;
