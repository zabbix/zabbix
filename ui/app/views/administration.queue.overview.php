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
 */

$html_page = (new CHtmlPage())
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
	])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::QUEUE_OVERVIEW));

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

$html_page
	->addItem($table)
	->show();
