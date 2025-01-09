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
