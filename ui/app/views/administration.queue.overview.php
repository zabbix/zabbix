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
	->setTitle(_('Queue overview'))
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
	_('Items'),
	_('5 seconds'),
	_('10 seconds'),
	_('30 seconds'),
	_('1 minute'),
	_('5 minutes'),
	_('More than 10 minutes')
]);

foreach ($data['item_types'] as $item_type) {
	$item_type_queue = array_key_exists($item_type, $data['queue_data'])
		? $data['queue_data'][$item_type]
		: [
			'delay5' => 0,
			'delay10' => 0,
			'delay30' => 0,
			'delay60' => 0,
			'delay300' => 0,
			'delay600' => 0
		];

	$table->addRow([
		item_type2str($item_type),
		($item_type_queue['delay5'] == 0) ? 0 : (new CCol($item_type_queue['delay5']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_NOT_CLASSIFIED)),
		($item_type_queue['delay10'] == 0) ? 0 : (new CCol($item_type_queue['delay10']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_INFORMATION)),
		($item_type_queue['delay30'] == 0) ? 0 : (new CCol($item_type_queue['delay30']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_WARNING)),
		($item_type_queue['delay60'] == 0) ? 0 : (new CCol($item_type_queue['delay60']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_AVERAGE)),
		($item_type_queue['delay300'] == 0) ? 0 : (new CCol($item_type_queue['delay300']))
			->addClass(CSeverityHelper::getStyle(TRIGGER_SEVERITY_HIGH)),
		($item_type_queue['delay600'] == 0) ? 0 : (new CCol($item_type_queue['delay600']))
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
	->show();
