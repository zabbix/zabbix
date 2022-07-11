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
 */

$widget = (new CWidget())
	->setTitle(_('API tokens'))
	->setTitleSubmenu(getUserSettingsSubmenu());

$token_form = (new CForm())
	->setId('token_form')
	->setName('token')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

$token_from_list = (new CFormList())
	->addRow(_('Name').':', $data['name'])
	->addRow(_('Auth token').':', [
		$data['auth_token'],
		'&nbsp;',
		makeWarningIcon(
			_("Make sure to copy the auth token as you won't be able to view it after the page is closed.")
		),
		'&nbsp;',
		(new CLinkAction(_('Copy to clipboard')))
			->onClick('writeTextClipboard("'.$data['auth_token'].'")')
			->setAttribute('autofocus', 'autofocus')
	])
	->addRow(_('Expires at').':', [
		($data['expires_at'] == 0) ? '-' : date(DATE_TIME_FORMAT_SECONDS, (int) $data['expires_at']),
		($data['expires_at'] != 0 && time() > $data['expires_at'])
			? ['&nbsp;', makeErrorIcon(_('The token has expired. Please update the expiry date to use the token.'))]
			: null
	])
	->addRow(_('Description').':', (new CDiv($data['description']))->addClass(ZBX_STYLE_WORDBREAK))
	->addRow(new CLabel(_('Enabled').':', 'enabled'),
		(new CCheckBox('enabled'))
			->setChecked($data['status'] == ZBX_AUTH_TOKEN_ENABLED)
			->setEnabled(false)
	);

$token_view = (new CTabView())->addTab('token', '', $token_from_list);

$token_view->setFooter(makeFormFooter((new CRedirectButton(_('Close'), (new CUrl('zabbix.php'))
	->setArgument('action', 'user.token.list')
	->setArgument('page', CPagerHelper::loadPage('user.token.list', null))
))));

$token_form->addItem($token_view);
$widget
	->addItem($token_form)
	->show();
