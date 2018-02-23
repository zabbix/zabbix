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


define('ZBX_PAGE_NO_HEADER', 1);
define('ZBX_PAGE_NO_FOOTER', 1);

$message = CHtml::encode(getRequest('message', '')) ;
// remove debug code for login form message, trimming not in regex to relay only on [ ] in debug message.
$message = trim(preg_replace('/\[.*\]/', '', $message));

require_once dirname(__FILE__).'/../page_header.php';

$error = ($message !== '') ? (new CDiv($message))->addClass(ZBX_STYLE_RED) : null;
$guest = (CWebUser::$data['userid'] > 0)
	? (new CListItem(['or ', new CLink('sign in as guest', ZBX_DEFAULT_URL)]))
		->addClass(ZBX_STYLE_SIGN_IN_TXT)
	: null;

global $ZBX_SERVER_NAME;

(new CTag('main', true, [
	(isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '')
		? (new CDiv($ZBX_SERVER_NAME))->addClass(ZBX_STYLE_SERVER_NAME)
		: null,
	(new CDiv([
		(new CDiv())->addClass(ZBX_STYLE_SIGNIN_LOGO),
		(new CForm())
			->cleanItems()
			->setAttribute('aria-label', _('Sign in'))
			->addItem(hasRequest('request') ? new CVar('request', getRequest('request')) : null)
			->addItem(
				(new CList())
					->addItem([
						new CLabel(_('Username'), 'name'),
						(new CTextBox('name'))->setAttribute('autofocus', 'autofocus'),
						$error
					])
					->addItem([new CLabel(_('Password'), 'password'), (new CTextBox('password'))->setType('password')])
					->addItem(
						(new CCheckBox('autologin'))
							->setLabel(_('Remember me for 30 days'))
							->setChecked(getRequest('autologin', 1) == 1)
					)
					->addItem(new CSubmit('enter', _('Sign in')))
					->addItem($guest)
			)
	]))->addClass(ZBX_STYLE_SIGNIN_CONTAINER),
	(new CDiv([
		(new CLink(_('Help'), 'http://www.zabbix.com/documentation/4.0/'))
			->setTarget('_blank')
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT),
		'&nbsp;&nbsp;•&nbsp;&nbsp;',
		(new CLink(_('Support'), 'http://www.zabbix.com/support.php'))
			->setTarget('_blank')
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT)
	]))->addClass(ZBX_STYLE_SIGNIN_LINKS)
]))->show();

makePageFooter(false)->show();
?>
</body>
