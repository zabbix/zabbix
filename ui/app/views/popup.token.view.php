<?php declare(strict_types = 0);
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
 * @var CPartial $this
 * @var array $data
 */

$token_form = (new CForm())
	->setId('token_form')
	->setName('token')
	->addItem(makeMessageBox(ZBX_STYLE_MSG_GOOD, [], $data['message']));

$token_from_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Name') . ':'),
		new CFormField($data['name'])
	])
	->addItem($data['admin_mode'] === '1'
		? [
			new CLabel(_('User') . ':'),
			new CFormField($data['user_name'])
		]
		: null
	)
	->addItem([
		new CLabel(_('Auth token') . ':'),
		new CFormField([
			$data['auth_token'],
			'&nbsp;',
			makeWarningIcon(
				_("Make sure to copy the auth token as you won't be able to view it after the page is closed.")
			),
			'&nbsp;',
			(new CLinkAction(_('Copy to clipboard')))
				->setAttribute('data-auth_token', $data['auth_token'])
				->onClick('writeTextClipboard(this.dataset.auth_token);')
				->setAttribute('autofocus', 'autofocus')
		])
	])
	->addItem([
		new CLabel(_('Expires at') . ':'),
		new CFormField([
			($data['expires_at'] == 0) ? '-' : date(DATE_TIME_FORMAT_SECONDS, (int) $data['expires_at']),
			($data['expires_at'] != 0 && time() > $data['expires_at'])
				? ['&nbsp;', makeErrorIcon(_('The token has expired. Please update the expiry date to use the token.'))]
				: null
		])
	])
	->addItem([
		new CLabel(_('Description') . ':'),
		(new CFormField($data['description']))->addClass(ZBX_STYLE_WORDBREAK)
	])
	->addItem([
		new CLabel(_('Enabled') . ':'),
		new CFormField((new CCheckBox('enabled'))
			->setChecked($data['status'] == ZBX_AUTH_TOKEN_ENABLED)
			->setEnabled(false)
		)
	]);

$token_form->addItem($token_from_grid);

$output = [
	'title' => ('API token'),
	'content' => $token_form->toString(),
	'buttons' => [[
		'title' => _('Close'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'token_edit_popup.close();'
	]]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
