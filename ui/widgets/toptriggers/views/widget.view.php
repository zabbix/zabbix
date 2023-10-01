<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Top triggers widget view.
 *
 * @var CView $this
 * @var array $data
 */

$view = new CWidgetView($data);

$table = (new CTableInfo())
	->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Number of problems')])
	->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	foreach ($data['triggers'] as $triggerid => $trigger) {
		$hosts = [];

		foreach ($trigger['hosts'] as $host) {
			$hosts[] = (new CLinkAction($host['name']))
				->addClass(ZBX_STYLE_WORDBREAK)
				->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_COLOR_NEGATIVE : null)
				->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));
			$hosts[] = ', ';
		}

		array_pop($hosts);

		$table->addRow([
			$hosts,
			(new CLinkAction($trigger['description']))->setMenuPopup(
				CMenuPopupHelper::getTrigger([
					'triggerid' => $trigger['triggerid'],
					'backurl' => (new CUrl('zabbix.php'))
						->setArgument('action', 'dashboard.view')
						->getUrl()
				])
			),
			CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
			$trigger['problem_count']
		]);
	}
}

if ($data['info']) {
	$view->setVar('info', $data['info']);
}

$view
	->addItem($table)
	->show();
