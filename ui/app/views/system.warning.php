<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

$page_header = (new CHtmlPageHeader(_('Fatal error, please report to the Zabbix team'), CWebUser::getLang()));

$page_header
	->setTheme($data['theme'])
	->addCssFile('assets/styles/'.$page_header->getTheme().'.css')
	->show();

if (CWebUser::isLoggedIn()) {
	$buttons = [
		(new CButton('back', _s('Go to "%1$s"', CMenuHelper::getFirstLabel())))
			->setAttribute('data-url', CMenuHelper::getFirstUrl())
			->onClick('document.location = this.dataset.url;')
	];
}
else {
	$buttons = [
		(new CButton('login', _s('Go to "%1$s"', _('Login'))))
			->setAttribute('data-url', 'index.php')
			->onClick('document.location = this.dataset.url;')
	];
}

echo '<body';

(new CDiv((new CTag('main', true,
	new CWarning(_('Fatal error, please report to the Zabbix team'), $data['messages'], $buttons)
))))
	->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
	->show();

echo '</body>';
echo '</html>';
