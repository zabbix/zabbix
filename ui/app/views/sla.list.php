<?php declare(strict_types = 1);
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

$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('sla.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'sla.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'sla.list'))
	->setProfile('web.sla.list.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Status')),
				new CFormField(
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), CSlaHelper::SLA_STATUS_ANY)
						->addValue(_('Enabled'), CSlaHelper::SLA_STATUS_ENABLED)
						->addValue(_('Disabled'), CSlaHelper::SLA_STATUS_DISABLED)
						->setModern(true)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Service tags')),
				new CFormField(
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags'] ?: [
							['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]
						]
					])
				)
			])
	]);

$form = (new CForm())
	->setId('sla-list')
	->setName('sla_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'sla.list')
	->getUrl();

$header = [
	$data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]
		? (new CColHeader(
			(new CCheckBox('all_slas'))->onClick("checkAll('sla_list', 'all_slas', 'slaids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH)
		: null,
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url)
		->addStyle('width: 15%;'),
	make_sorting_header(_('SLO'), 'slo', $data['sort'], $data['sortorder'], $view_url),
	make_sorting_header(_('Effective date'), 'effective_date', $data['sort'], $data['sortorder'], $view_url),
	new CColHeader(_('Reporting period')),
	new CColHeader(_('Timezone')),
	new CColHeader(_('Schedule')),
	$data['has_access'][CRoleHelper::UI_SERVICES_SLA_REPORT] ? new CColHeader(_('SLA report')) : null,
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $view_url)
];

$sla_list = (new CTableInfo())->setHeader($header);

foreach ($data['slas'] as $slaid => $sla) {
	if ($data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]) {
		$status_tag = $sla['status'] == ZBX_SLA_STATUS_ENABLED
			? (new CLink(_('Enabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-disable-sla')
				->setAttribute('data-slaid', $slaid)
			: (new CLink(_('Disabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addClass('js-enable-sla')
				->setAttribute('data-slaid', $slaid);
	}
	else {
		$status_tag = $sla['status'] == ZBX_SLA_STATUS_ENABLED
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}

	if ($data['has_access'][CRoleHelper::UI_SERVICES_SLA_REPORT]) {
		$sla_report_tag = $sla['status'] == ZBX_SLA_STATUS_ENABLED
			? new CLink(_('SLA report'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'slareport.list')
					->setArgument('filter_slaid', $slaid)
					->setArgument('filter_set', 1)
			)
			: '';
	}
	else {
		$sla_report_tag = null;
	}

	$row = [
		$data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]
			? new CCheckBox('slaids['.$slaid.']', $slaid)
			: null,
		(new CCol($data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]
			? (new CLink($sla['name']))
				->addClass('js-edit-sla')
				->setAttribute('data-slaid', $slaid)
			: $sla['name']
		))->addClass(ZBX_STYLE_WORDBREAK),
		CSlaHelper::getSloTag((float) $sla['slo']),
		zbx_date2str(DATE_FORMAT, $sla['effective_date'], 'UTC'),
		CSlaHelper::getPeriodNames()[$sla['period']],
		$sla['timezone'] !== ZBX_DEFAULT_TIMEZONE
			? $sla['timezone']
			: CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(), _('System default')),
		CSlaHelper::getScheduleTag($sla['schedule']),
		$sla_report_tag,
		$status_tag
	];

	$sla_list->addRow($row);
}

$form->addItem([$sla_list, $data['paging']]);

if ($data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]) {
	$form->addItem(
		new CActionButtonList('action', 'slaids', [
			'sla.massenable' => [
				'content' => (new CSimpleButton(_('Enable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massenable-sla')
					->addClass('no-chkbxrange')
			],
			'sla.massdisable' => [
				'content' => (new CSimpleButton(_('Disable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdisable-sla')
					->addClass('no-chkbxrange')
			],
			'sla.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdelete-sla')
					->addClass('no-chkbxrange')
			]
		], 'sla')
	);
}

(new CWidget())
	->setTitle(_('SLA'))
	->setControls(
		(new CTag('nav', true,
			(new CSimpleButton(_('Create SLA')))
				->addClass('js-create-sla')
				->setEnabled($data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA])
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'enable_url' => (new CUrl('zabbix.php'))
			->setArgument('action', 'sla.enable')
			->setArgumentSID()
			->getUrl(),
		'disable_url' => (new CUrl('zabbix.php'))
			->setArgument('action', 'sla.disable')
			->setArgumentSID()
			->getUrl(),
		'delete_url' => (new CUrl('zabbix.php'))
			->setArgument('action', 'sla.delete')
			->setArgumentSID()
			->getUrl()
	]).');
'))
	->setOnDocumentReady()
	->show();
