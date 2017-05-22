<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

$url_list = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.list');
if ($data['fullscreen']) {
	$url_list->setArgument('fullscreen', '1');
}

$breadcrumbs = [
	(new CSpan())->addItem(new CLink(_('All dashboards'), $url_list->getUrl()))
];


if (!$is_new) {
	$url_view = (new CUrl('zabbix.php'))
		->setArgument('action', 'dashboard.view')
		->setArgument('dashboardid', $data['dashboard']['dashboardid']);
	if ($data['fullscreen']) {
		$url_view->setArgument('fullscreen', '1');
	}
	$breadcrumbs[] = '/';
	$breadcrumbs[] = (new CSpan())
		->addItem(new CLink($data['dashboard']['name'], $url_view->getUrl()))
		->addClass(ZBX_STYLE_SELECTED);
}

return $breadcrumbs;
