<?php declare(strict_types = 0);
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
 * Graph (classic) widget view.
 *
 * @var CView $this
 * @var array $data
 */

$view = new CWidgetView($data);

if ($data['is_resource_available']) {
	$view
		->addItem(
			(new CDiv())
				->addClass('flickerfreescreen')
				->addItem(
					(new CLink(null, $data['widget']['graph_url'] ?? 'javascript:void(0)'))
					->addClass(ZBX_STYLE_DASHBOARD_WIDGET_GRAPH_LINK)
				)
		)
		->setVar('async_data', $data['widget']);

	if ($data['info']) {
		$view->setVar('info', $data['info']);
	}
}
else {
	$view->addItem(
		(new CTableInfo())->setNoDataMessage(_('No permissions to referred object or it does not exist!'))
	);
}

$view->show();
