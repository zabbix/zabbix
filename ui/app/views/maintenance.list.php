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

$this->includeJsFile('maintenance.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'maintenance.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'maintenance.list'))
	->setProfile($data['filter_profile'])
	->setActiveTab($data['filter_active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addItem([
				new CLabel(_('Host groups'), 'filter_groups__ms'),
				new CFormField(
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
				)
			])
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
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

$form = (new CForm())
	->setId('maintenance-list')
	->setName('maintenance_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'maintenance.list')
	->getUrl();

$maintenance_list = (new CTableInfo())
	->setHeader([
		$data['allowed_edit']
			? (new CColHeader((new CCheckBox('all_maintenances'))
				->onClick("checkAll('".$form->getName()."', 'all_maintenances', 'maintenanceids');")
			))->addClass(ZBX_STYLE_CELL_WIDTH)
			: null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Type'), 'maintenance_type', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Active since'), 'active_since', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Active till'), 'active_till', $data['sort'], $data['sortorder'], $view_url),
		_('State'),
		_('Description')
	])
	->setPageNavigation($data['paging']);

foreach ($data['maintenances'] as $maintenanceid => $maintenance) {
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

	$maintenance_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'maintenance.edit')
		->setArgument('maintenanceid', $maintenanceid)
		->getUrl();

	$maintenance_list->addRow([
		$data['allowed_edit'] ? new CCheckBox('maintenanceids['.$maintenanceid.']', $maintenanceid) : null,
		(new CCol((new CLink($maintenance['name'], $maintenance_url))))->addClass(ZBX_STYLE_WORDBREAK),
		$maintenance['maintenance_type'] ? _('No data collection') : _('With data collection'),
		zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_since']),
		zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_till']),
		$maintenance_status,
		(new CCol($maintenance['description']))
			->addClass(ZBX_STYLE_WORDBREAK)
			->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);
}

$form->addItem($maintenance_list);

if ($data['allowed_edit']) {
	$form->addItem(
		new CActionButtonList('action', 'maintenanceids', [
			'maintenance.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdelete-maintenance')
					->addClass('js-no-chkbxrange')
			]
		], 'maintenance')
	);
}

(new CHtmlPage())
	->setTitle(_('Maintenance periods'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_MAINTENANCE_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create maintenance period')))
						->addClass('js-create-maintenance')
						->setEnabled($data['allowed_edit'])
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
