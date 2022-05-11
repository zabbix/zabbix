<?php
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

define('ZBX_PAGE_NO_HEADER', 1);
define('ZBX_PAGE_NO_FOOTER', 1);
define('ZBX_PAGE_NO_MENU', true);
define('ZBX_PAGE_NO_JSLOADER', true);

require_once dirname(__FILE__).'/../page_header.php';
$error = null;

if ($data['error']) {
	// remove debug code for login form message, trimming not in regex to relay only on [ ] in debug message.
	$message = trim(preg_replace('/\[.*\]/', '', $data['error']['message']));
	$error = (new CDiv($message))->addClass(ZBX_STYLE_RED);
}

$guest = $data['guest_login_url']
	? (new CListItem([_('or'), ' ', new CLink(_('sign in as guest'), $data['guest_login_url'])]))
		->addClass(ZBX_STYLE_SIGN_IN_TXT)
	: null;

$http_login_link = $data['http_login_url']
	? (new CListItem(new CLink(_('Sign in with HTTP'), $data['http_login_url'])))->addClass(ZBX_STYLE_SIGN_IN_TXT)
	: null;

$saml_login_link = $data['saml_login_url']
	? (new CListItem(new CLink(_('Sign in with Single Sign-On (SAML)'), $data['saml_login_url'])))
		->addClass(ZBX_STYLE_SIGN_IN_TXT)
	: null;

global $ZBX_SERVER_NAME;

(new CDiv([
	(new CTag('main', true, [
		(isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '')
			? (new CDiv($ZBX_SERVER_NAME))->addClass(ZBX_STYLE_SERVER_NAME)
			: null,
		(new CDiv([
			(new CDiv(makeLogo(LOGO_TYPE_NORMAL)))->addClass(ZBX_STYLE_SIGNIN_LOGO),
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
						->addItem([new CLabel(_('Password'), 'password'), new CPassBox('password')])
						->addItem(
							(new CCheckBox('autologin'))
								->setLabel(_('Remember me for 30 days'))
								->setChecked($data['autologin'])
						)
						->addItem(new CSubmit('enter', _('Sign in')))
						->addItem($guest)
						->addItem($http_login_link)
						->addItem($saml_login_link)
				)
		]))->addClass(ZBX_STYLE_SIGNIN_CONTAINER),
		(new CDiv([
			(new CLink(_('Help'), CBrandHelper::getHelpUrl()))
				->setTarget('_blank')
				->addClass(ZBX_STYLE_GREY)
				->addClass(ZBX_STYLE_LINK_ALT),
			CBrandHelper::isRebranded() ? null : '&nbsp;&nbsp;•&nbsp;&nbsp;',
			CBrandHelper::isRebranded()
				? null
				: (new CLink(_('Support'), getSupportUrl(CWebUser::getLang())))
					->setTarget('_blank')
					->addClass(ZBX_STYLE_GREY)
					->addClass(ZBX_STYLE_LINK_ALT)
		]))->addClass(ZBX_STYLE_SIGNIN_LINKS)
	])),
	makePageFooter(false)
]))
	->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
	->show();
?>
</body>
