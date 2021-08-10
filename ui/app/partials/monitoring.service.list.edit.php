<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 */

$form = (new CForm())
	->setId('service-list')
	->setName('service-list')
	->addVar('back_url', $data['back_url']);

$header = [
	(new CColHeader(
		(new CCheckBox('all_services'))->onClick("checkAll('".$form->getName()."', 'all_services', 'serviceids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH)
];

if ($data['is_filtered']) {
	$path = null;

	$header[] = (new CColHeader(_('Parent services')))->addStyle('width: 15%');
	$header[] = (new CColHeader(_('Name')))->addStyle('width: 10%');
}
else {
	$path = $data['path'];
	if ($data['service'] !== null) {
		$path[] = $data['service']['serviceid'];
	}

	$header[] = (new CColHeader(_('Name')))->addStyle('width: 25%');
}

$table = (new CTableInfo())
	->setHeader(array_merge($header, [
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3),
		(new CColHeader())
	]));

foreach ($data['services'] as $serviceid => $service) {
	$row = [new CCheckBox('serviceids['.$serviceid.']', $serviceid)];

	if ($data['is_filtered']) {
		$parents = [];

		foreach (array_slice($service['parents'], 0, $data['max_in_table']) as $parent) {
			if ($parents) {
				$parents[] = ', ';
			}

			$parents[] = (new CLink($parent['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'service.list.edit')
					->setArgument('serviceid', $parent['serviceid'])
			))->setAttribute('data-serviceid', $parent['serviceid']);
		}

		$row[] = $parents;
	}

	$root_cause = [];

	foreach ($data['events'][$serviceid] as $event) {
		if ($root_cause) {
			$root_cause[] = ', ';
		}

		$root_cause[] = $data['can_monitor_problems']
			? new CLink($event['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('triggerids', [$event['objectid']])
			)
			: $event['name'];
	}

	$table->addRow(new CRow(array_merge($row, [
		($service['children'] > 0)
			? [
				(new CLink($service['name'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'service.list.edit')
						->setArgument('path', $path)
						->setArgument('serviceid', $serviceid)
				))->setAttribute('data-serviceid', $serviceid),
				CViewHelper::showNum($service['children'])
			]
			: $service['name'],
		in_array($service['status'], [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])
			? (new CCol(_('OK')))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(CSeverityHelper::getName((int) $service['status'])))
				->addClass(CSeverityHelper::getStyle((int) $service['status'])),
		$root_cause,
		($service['showsla'] == SERVICE_SHOW_SLA_ON) ? sprintf('%.4f', $service['goodsla']) : '',
		$data['tags'][$serviceid],
		(new CCol([
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_ADD)
				->addClass('js-add-child-service')
				->setAttribute('data-serviceid', $serviceid)
				->setTitle(_('Add child service')),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_EDIT)
				->addClass('js-edit-service')
				->setAttribute('data-serviceid', $serviceid)
				->setTitle(_('Edit')),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_REMOVE)
				->addClass('js-remove-service')
				->setAttribute('data-serviceid', $serviceid)
				->setTitle(_('Delete'))
		]))->addClass(ZBX_STYLE_LIST_TABLE_ACTIONS)
	])));
}

$action_buttons = new CActionButtonList('action', 'serviceids', [
	'popup.massupdate.service' => [
		'content' => (new CSimpleButton(_('Mass update')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massupdate-service')
	],
	'service.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected services?')]
], 'service');

$form
	->addItem([$table, $data['paging'], $action_buttons])
	->show();
