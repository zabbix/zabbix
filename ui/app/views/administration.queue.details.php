<?php declare(strict_types=1);
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

$widget = (new CWidget())
	->setTitle(_('Queue details'))
	->setTitleSubmenu([
		'main_section' => [
			'items' => [
				(new CUrl('zabbix.php'))
					->setArgument('action', 'queue.overview')
					->getUrl() => _('Queue overview'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'queue.overview.proxy')
					->getUrl() => _('Queue overview by proxy'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'queue.details')
					->getUrl() => _('Queue details')
			]
		]
	]);

$table = (new CTableInfo())->setHeader([
	_('Scheduled check'),
	_('Delayed by'),
	_('Host'),
	_('Name'),
	_('Proxy')
]);

foreach ($data['queue_data'] as $itemid => $item_queue_data) {
	if (!array_key_exists($itemid, $data['items'])) {
		continue;
	}

	$item = $data['items'][$itemid];
	$host = reset($item['hosts']);

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $item_queue_data['nextcheck']),
		zbx_date2age($item_queue_data['nextcheck']),
		$host['name'],
		$item['name'],
		array_key_exists($data['hosts'][$item['hostid']]['proxy_hostid'], $data['proxies'])
			? $data['proxies'][$data['hosts'][$item['hostid']]['proxy_hostid']]['host']
			: ''
	]);
}

if (CWebUser::getRefresh()) {
	(new CScriptTag('PageRefresh.init('.(CWebUser::getRefresh() * 1000).');'))
		->setOnDocumentReady()
		->show();
}

$widget
	->addItem($table)
	->addItem((new CDiv())
		->addClass(ZBX_STYLE_TABLE_PAGING)
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
			->addItem((new CDiv())
				->addClass(ZBX_STYLE_TABLE_STATS)
				->addItem(_s('Displaying %1$s of %2$s found', $table->getNumRows(), $data['total_count']))
			)
		)
	)
	->show();
