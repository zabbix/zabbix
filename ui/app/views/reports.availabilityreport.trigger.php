<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @var array $data
 */

(new CHtmlPage())
	->setTitle(_('Availability report graph'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_AVAILABILITYREPORT))
	->setNavigation((new CList())->addItem(new CBreadcrumbs([
		(new CSpan())->addItem(new CLink(_('Availability report'),
			(new CUrl('zabbix.php'))->setArgument('action', 'availabilityreport.list')
		)),
		(new CSpan())
			->addItem($data['host']['name'])
			->addClass('wide'),
		(new CSpan())
			->addItem($data['trigger']['description'])
			->addClass('wide')
	])))
	->addItem((new CTableInfo())
		->addRow(new CImg('chart4.php?triggerid='.$data['trigger']['triggerid']))
	)
	->show();
