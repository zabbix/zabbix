<?php declare(strict_types = 0);
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
	->setTitle(_('Queue overview by proxy'))
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
	->setDocUrl(CDocHelper::getUrl(CDocHelper::QUEUE_OVERVIEW_PROXY));

$table = (new CTableInfo())->setHeader([
	_('Proxy'),
	_('5 seconds'),
	_('10 seconds'),
	_('30 seconds'),
	_('1 minute'),
	_('5 minutes'),
	_('More than 10 minutes')
]);

foreach ($data['proxies'] as $proxyid => $proxy) {
	$proxy_queue = array_key_exists($proxyid, $data['queue_data'])
		? $data['queue_data'][$proxyid]
		: [
			'delay5' => 0,
			'delay10' => 0,
			'delay30' => 0,
			'delay60' => 0,
			'delay300' => 0,
			'delay600' => 0
		];

	$table->addRow([
		$proxy['host'],
		($proxy_queue['delay5'] == 0) ? 0 : (new CCol($proxy_queue['delay5']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_NOT_CLASSIFIED)),
		($proxy_queue['delay10'] == 0) ? 0 : (new CCol($proxy_queue['delay10']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_INFORMATION)),
		($proxy_queue['delay30'] == 0) ? 0 : (new CCol($proxy_queue['delay30']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_WARNING)),
		($proxy_queue['delay60'] == 0) ? 0 : (new CCol($proxy_queue['delay60']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_AVERAGE)),
		($proxy_queue['delay300'] == 0) ? 0 : (new CCol($proxy_queue['delay300']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_HIGH)),
		($proxy_queue['delay600'] == 0) ? 0 : (new CCol($proxy_queue['delay600']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_DISASTER))
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
				->addItem(_('Total').': '.$table->getNumRows())
			)
		)
	)
	->show();
