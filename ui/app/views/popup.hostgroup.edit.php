<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

$popup_url = (new CUrl('zabbix.php'))->setArgument('action', 'hostgroup.edit');

if ($data['groupid'] !== null) {
	$popup_url->setArgument('groupid', $data['groupid']);
	$title = _('Host group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'enabled' => CWebUser::getType() == USER_TYPE_SUPER_ADMIN,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected host group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New host group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOSTGROUPS_EDIT),
	'body' => (new CPartial('configuration.hostgroup.edit.html', $data))->getOutput(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.hostgroup.edit.js.php').
		'hostgroup_edit_popup.init('.json_encode([
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
