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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$pageHeader = (new CPageHeader(_('Fatal error, please report to the Zabbix team'), CWebUser::getLang()))
	->addCssFile('assets/styles/'.CHtml::encode($data['theme']).'.css')
	->display();

$buttons = [
	(new CButton('back', _s('Go to "%1$s"', CMenuHelper::getFirstLabel())))
		->onClick('javascript: document.location = "'.CMenuHelper::getFirstUrl().'"'
)];

echo '<body';

(new CDiv((new CTag('main', true,
	new CWarning(_('Fatal error, please report to the Zabbix team'), $data['messages'], $buttons)
))))
	->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
	->show();

echo '</body>';
echo '</html>';
