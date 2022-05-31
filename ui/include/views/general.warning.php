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

$pageHeader = (new CPageHeader(_('Warning').' ['._s('refreshed every %1$s sec.', 30).']', CWebUser::getLang()))
	->addCssFile('assets/styles/'.CHtml::encode($data['theme']).'.css')
	->display();

$buttons = array_key_exists('buttons', $data)
	? $data['buttons']
	: [(new CButton(null, _('Retry')))->onClick('document.location.reload();')];

echo '<body>';

(new CDiv((new CTag('main', true,
	new CWarning($data['header'], $data['messages'], $buttons)
))))
	->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
	->show();

echo get_js("setTimeout('document.location.reload();', 30000);");
echo '</body>';
echo '</html>';
