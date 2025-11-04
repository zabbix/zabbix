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
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
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
	])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::QUEUE_DETAILS));

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
	$item_host = $data['hosts'][$item['hostid']];

	if (array_key_exists($item_host['proxyid'], $data['proxies'])) {
		$proxy_name = $data['proxies'][$item_host['proxyid']]['name'];
	}
	elseif (array_key_exists($item_host['assigned_proxyid'], $data['proxies'])) {
		$proxy_name = $data['proxies'][$item_host['assigned_proxyid']]['name'];
	}
	else {
		$proxy_name = (new CSpan(_('Proxy is not assigned yet.')))->addClass(ZBX_STYLE_GREY);
	}

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $item_queue_data['nextcheck']),
		zbx_date2age($item_queue_data['nextcheck']),
		$host['name'],
		$item['name'],
		$proxy_name
	]);
}

if (CWebUser::getRefresh()) {
	(new CScriptTag('PageRefresh.init('.(CWebUser::getRefresh() * 1000).');'))
		->setOnDocumentReady()
		->show();
}

$html_page->addItem($table);

if ($data['total_count'] != 0) {
	$html_page->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_TABLE_PAGING)
			->addItem((new CDiv())
				->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
				->addItem((new CDiv())
					->addClass(ZBX_STYLE_TABLE_STATS)
					->addItem(_s('Displaying %1$s of %2$s found', $table->getNumRows(), $data['total_count']))
				)
			)
	);
}

$html_page->show();
