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

$page_header = (new CHtmlPageHeader(_('Warning').' ['._s('refreshed every %1$s sec.', 30).']', CWebUser::getLang()));

$page_header
	->setTheme($data['theme'])
	->addCssFile('assets/styles/'.$page_header->getTheme().'.css')
	->show();

$buttons = array_key_exists('buttons', $data)
	? $data['buttons']
	: [(new CSimpleButton(_('Retry')))->onClick('document.location.reload();')];

echo '<body>';

(new CDiv((new CTag('main', true,
	new CWarning($data['header'], $data['messages'], $buttons)
))))
	->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
	->show();

echo get_js("setTimeout('document.location.reload();', 30000);");
echo '</body>';
echo '</html>';
