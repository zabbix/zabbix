<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$data['form_name'] = 'token_form';
$data['action_src'] = 'token.edit';
$popup_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'token.edit');

if ($data['tokenid'] != 0) {
	$popup_url->setArgument('tokenid', $data['tokenid']);

	$buttons = [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'token_edit_popup.submit();'
		],
		[
			'title' => _('Regenerate'),
			'confirmation' => _('Regenerate selected API token? Previously generated token will become invalid.'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'token_edit_popup.regenerate();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected API token?'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'token_edit_popup.delete('.json_encode($data['tokenid']).');'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'token_edit_popup.submit();'
		]
	];
}

$output = [
	'header' =>($data['tokenid'] == 0) ? _('New API token') : ('API token'),
	'body' => (new CPartial('administration.token.edit.html', $data))->getOutput(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.token.edit.js.php').
		'token_edit_popup.init('.json_encode([
			'popup_url' => $popup_url->getUrl(),
			'form_name' => $data['form_name']
		]).');',
	'buttons' => $buttons
];

echo json_encode($output);

