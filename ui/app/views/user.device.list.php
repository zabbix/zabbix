<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$this->includeJsFile('user.device.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Linked devices'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USER_DEVICE_LIST))
	->setControls((new CList([
		(new CTag('nav', true,
			(new CList())->addItem((new CSimpleButton(_('Link a device')))->addClass('js-create-device'))
		))->setAttribute('aria-label', _('Content controls'))
	])))
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'user.device.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [

			(new CFormGrid())
				->addItem([(
				new CLabel(_('Users'), 'filter_users__ms')),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_userids[]',
							'object_name' => 'users',
							'data' => $data['filter']['ms_users'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'users',
									'srcfld1' => 'userid',
									'srcfld2' => 'fullname',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_userids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([(
					new CLabel(_('User roles'), 'filter_roles__ms')),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_roleids[]',
							'object_name' => 'roles',
							'data' => $data['filter']['ms_roles'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'roles',
									'srcfld1' => 'roleid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_roleids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('User groups'), 'filter_usrgrpids__ms'),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_usrgrpids[]',
							'object_name' => 'usersGroups',
							'data' => $data['filter']['ms_usrgrps'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'usrgrp',
									'srcfld1' => 'usrgrpid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_usrgrpids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
		])
		->addVar('action', 'user.device.list')
	);

$deviceForm = (new CForm())
	->setName('devices');

$deviceTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$deviceForm->getName()."', 'all_items', 'g_deviceid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Device ID'), 'uuid', $data['sort'], $data['sortorder'], $data['url']),
		_('User'),
		_('User role'),
		make_sorting_header(_('Linked on'), 'created_at', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Last active'), 'lastaccess', $data['sort'], $data['sortorder'], $data['url']),
		_('Action')
	])
	->setPageNavigation($data['paging']);

foreach ($data['devices'] as $device) {
	$deviceTable->addRow([
		(new CCheckBox('g_deviceid['.$device['deviceid'].']', $device['deviceid'])),
		$device['name'],
		$device['uuid'],
		$device['userid'],
		json_encode($device),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['created_at']),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['lastaccess']),
		(new CButton('', _('Unlink')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->removeId()
			// TODO: permission check
			->setEnabled(true)
			->setAttribute('data-deviceid', $device['deviceid'])
			->addClass('js-device-delete')
	]);
}

$buttons = [
	[
		'content' => (new CSimpleButton(_('Unlink')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdelete-device')
			->addClass('js-no-chkbxrange')
	]
];

$deviceForm->addItem([
	$deviceTable,
	new CActionButtonList('action', 'g_deviceid', $buttons, 'g_deviceid')
]);


$html_page
	->addItem($deviceForm)
	->show();

$confirm_messages = [
	'user.device.delete' => [_('Unlink selected device?'), _('Unlink selected devices?')]
];

(new CScriptTag('
	view.init('.json_encode([
		'confirm_messages' => $confirm_messages,
	]).');
'))
	->setOnDocumentReady()
	->show();
