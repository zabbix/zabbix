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
 * @var array $data
 */

$this->addJsFile('class.calendar.js');
$this->includeJsFile('maintenance.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Maintenance periods'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_MAINTENANCE_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create maintenance period')))
						->setEnabled($data['allowed_edit'])
						->addClass('js-maintenance-create')
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

$action_url = (new CUrl('zabbix.php'))->setArgument('action', 'maintenance.list');

$filter = (new CFilter())
	->setResetUrl($action_url)
	->addVar('action', 'maintenance.list')
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addItem([
				new CLabel(_('Host groups'), 'filter_groups__ms'),
				(new CMultiSelect([
					'name' => 'filter_groups[]',
					'object_name' => 'hostGroup',
					'data' => $data['filter']['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_groups_',
							'editable' => 1
						]
					]
				]))
					->setId('filter_groups_')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			])
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			]),
		(new CFormGrid())->addItem([
			new CLabel(_('State')),
			new CFormField(
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_x('Active', 'maintenance status'), MAINTENANCE_STATUS_ACTIVE)
					->addValue(_x('Approaching', 'maintenance status'), MAINTENANCE_STATUS_APPROACH)
					->addValue(_x('Expired', 'maintenance status'), MAINTENANCE_STATUS_EXPIRED)
					->setModern(true)
			)
		])
	]);

$action_url->removeArgument('filter_rst');

$form = (new CForm())
	->setId('maintenance-list')
	->setName('maintenance_list');

$maintenance_list = (new CTableInfo())
	->setHeader([
		$data['allowed_edit']
			? (new CColHeader(
				(new CCheckBox('all_maintenances'))
					->onClick("checkAll('".$form->getName()."', 'all_maintenances', 'maintenanceids');")
			))->addClass(ZBX_STYLE_CELL_WIDTH)
			: null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $action_url->getUrl()),
		make_sorting_header(_('Type'), 'maintenance_type', $data['sort'], $data['sortorder'], $action_url->getUrl()),
		make_sorting_header(_('Active since'), 'active_since', $data['sort'], $data['sortorder'], $action_url->getUrl()),
		make_sorting_header(_('Active till'), 'active_till', $data['sort'], $data['sortorder'], $action_url->getUrl()),
		_('State'),
		_('Description')
	]);

if ($data['maintenances']) {
	foreach ($data['maintenances'] as $maintenance) {
		$maintenanceid = $maintenance['maintenanceid'];

		switch ($maintenance['status']) {
			case MAINTENANCE_STATUS_EXPIRED:
				$maintenance_status = (new CSpan(_x('Expired', 'maintenance status')))->addClass(ZBX_STYLE_RED);
				break;
			case MAINTENANCE_STATUS_APPROACH:
				$maintenance_status = (new CSpan(_x('Approaching', 'maintenance status')))->addClass(ZBX_STYLE_ORANGE);
				break;
			case MAINTENANCE_STATUS_ACTIVE:
				$maintenance_status = (new CSpan(_x('Active', 'maintenance status')))->addClass(ZBX_STYLE_GREEN);
				break;
		}

		$maintenance_list->addRow([
			$data['allowed_edit'] ? new CCheckBox('maintenanceids[' . $maintenanceid . ']', $maintenanceid) : null,
			(new CLink($maintenance['name']))
				->addClass('js-maintenance-edit')
				->setAttribute('data-maintenanceid', $maintenance['maintenanceid']),
			$maintenance['maintenance_type'] ? _('No data collection') : _('With data collection'),
			zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_since']),
			zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_till']),
			$maintenance_status,
			$maintenance['description']
		]);
	}
}

$form->addItem($maintenance_list);

if ($data['allowed_edit']) {
	$form->addItem([
		$data['paging'],
		new CActionButtonList('action', 'maintenanceids', [
			'maintenance.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdelete-maintenance')
					->addClass('no-chkbxrange')
			]
		], 'maintenance')
	]);
}

$html_page
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
