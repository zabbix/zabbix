<?php declare(strict_types=1);
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
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

$success_message = $data['regenerate']
	? makeMessageBox(ZBX_STYLE_MSG_GOOD, [[
		'message' => _('API token updated')
	]])
	: makeMessageBox(ZBX_STYLE_MSG_GOOD, [[
		'message' => _('API token added')
	]]);
$token_form->addItem($success_message);

$token_from_grid = (new CFormGrid())
	->addItem([
		(new CDiv(_('Name') . ':'))->addClass(ZBX_STYLE_RIGHT),
		new CDiv($data['name'])
	]);

if ($data['action_dst'] === 'token.view') {
	$token_from_grid->addItem([
		(new CDiv(_('User') . ':'))->addClass(ZBX_STYLE_RIGHT),
		new CDiv($data['user'])
	]);
}

$token_from_grid->addItem([
		(new CDiv(_('Auth token') . ':'))->addClass(ZBX_STYLE_RIGHT),
		new CDiv([
			$data['auth_token'],
			'&nbsp;',
			makeWarningIcon(
				_("Make sure to copy the auth token as you won't be able to view it after the page is closed.")
			),
			'&nbsp;',
			(new CLinkAction(_('Copy to clipboard')))
				->onClick('writeTextClipboard("' . $data['auth_token'] . '")')
				->setAttribute('autofocus', 'autofocus')
		])
	])
	->addItem([
		(new CDiv(_('Expires at') . ':'))->addClass(ZBX_STYLE_RIGHT),
		new CDiv([
			($data['expires_at'] == 0) ? '-' : date(DATE_TIME_FORMAT_SECONDS, (int)$data['expires_at']),
			($data['expires_at'] != 0 && time() > $data['expires_at'])
				? ['&nbsp;', makeErrorIcon(_('The token has expired. Please update the expiry date to use the token.'))]
				: null
		])
	])
	->addItem([
		(new CDiv(_('Description') . ':'))->addClass(ZBX_STYLE_RIGHT),
		(new CDiv($data['description']))->addClass(ZBX_STYLE_WORDBREAK)
	])
	->addItem([
		(new CDiv(_('Enabled') . ':'))->addClass(ZBX_STYLE_RIGHT),
		new CDiv((new CCheckBox('enabled'))
			->setChecked($data['status'] == ZBX_AUTH_TOKEN_ENABLED)
			->setEnabled(false)
		)
	]);

$token_view = (new CTabView())->addTab('token', '', $token_from_grid);

$token_form->addItem($token_view);
$token_form
	->show();
