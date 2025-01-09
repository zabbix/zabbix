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
 * @var CPartial $this
 * @var array    $data
 */

$form = (new CForm())
	->setId('service-list')
	->setName('service_list')
	->addVar('back_url', $data['back_url']);

$header = [
	(new CColHeader(
		(new CCheckBox('all_services'))->onClick("checkAll('".$form->getName()."', 'all_services', 'serviceids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH)
];

if ($data['is_filtered']) {
	$path = null;

	$header[] = (new CColHeader(_('Parent services')))->addStyle('width: 15%;');
	$header[] = (new CColHeader(_('Name')))->addStyle('width: 10%;');
}
else {
	$path = $data['path'];

	if ($data['service'] !== null) {
		$path[] = $data['service']['serviceid'];
	}

	$header[] = (new CColHeader(_('Name')))->addStyle('width: 25%;');
}

$table = (new CTableInfo())
	->setHeader(array_merge($header, [
		(new CColHeader(_('Status')))->addStyle('width: 14%;'),
		(new CColHeader(_('Root cause')))->addStyle('width: 25%;'),
		(new CColHeader(_('Created at')))->addStyle('width: 10%;'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3),
		(new CColHeader())
	]))
	->setPageNavigation($data['paging']);

foreach ($data['services'] as $serviceid => $service) {
	$row = [(new CCheckBox('serviceids['.$serviceid.']', $serviceid))->setEnabled(!$service['readonly'])];

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

	foreach (array_slice($service['problem_events'], 0, $data['max_in_table']) as $problem_event) {
		if ($root_cause) {
			$root_cause[] = ', ';
		}

		$root_cause[] = $data['can_monitor_problems'] && $problem_event['triggerid'] !== null
			? new CLink($problem_event['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_set', '1')
					->setArgument('triggerids', [$problem_event['triggerid']])
			)
			: $problem_event['name'];
	}

	$table->addRow(new CRow(array_merge($row, [
		(new CCol([
			(new CLink($service['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'service.list.edit')
					->setArgument('path', $path)
					->setArgument('serviceid', $serviceid)
			))->setAttribute('data-serviceid', $serviceid),
			CViewHelper::showNum($service['children'])
		]))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(CSeverityHelper::getName((int) $service['status'])))
			->addClass(CSeverityHelper::getStyle((int) $service['status'])),
		$root_cause,
		zbx_date2str(DATE_FORMAT, $service['created_at']),
		$data['tags'][$serviceid],
		(new CCol([
			(new CButtonIcon(ZBX_ICON_PLUS, _('Add child service')))
				->addClass('js-add-child-service')
				->setAttribute('data-serviceid', $serviceid)
				->setEnabled(!$service['readonly'] && $service['problem_tags'] == 0),
			(new CButtonIcon(ZBX_ICON_PENCIL, _('Edit')))
				->addClass('js-edit-service-list')
				->setAttribute('data-serviceid', $serviceid)
				->setEnabled(!$service['readonly']),
			(new CButtonIcon(ZBX_ICON_REMOVE_SMALL, _('Delete')))
				->addClass('js-delete-service')
				->setAttribute('data-serviceid', $serviceid)
				->setEnabled(!$service['readonly'])
		]))->addClass(ZBX_STYLE_LIST_TABLE_ACTIONS)
	])));
}

$action_buttons = new CActionButtonList('action', 'serviceids', [
	'popup.massupdate.service' => [
		'content' => (new CSimpleButton(_('Mass update')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massupdate-service')
			->addClass('js-no-chkbxrange')
	],
	'service.massdelete' => [
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdelete-service')
			->addClass('js-no-chkbxrange')
	]
], $path !== null ? 'service_'.implode('_', $path) : 'service');

$form
	->addItem([$table, $action_buttons])
	->show();
