<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

$popup_url = (new CUrl('zabbix.php'))->setArgument('action', 'templategroup.edit');

if ($data['groupid'] !== null) {
	$popup_url->setArgument('groupid', $data['groupid']);
	$title = _('Template group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'templategroup_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'enabled' => CWebUser::getType() == USER_TYPE_SUPER_ADMIN,
			'isSubmit' => false,
			'action' => 'templategroup_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected template group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'templategroup_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New template group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'templategroup_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'body' => (new CPartial('configuration.templategroup.edit.html', $data))->getOutput(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.templategroup.edit.js.php').
		'templategroup_edit_popup.init('.json_encode([
			'popup_url' => $popup_url->getUrl(),
			'groupid' => $data['groupid'],
			'name' => $data['name']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
