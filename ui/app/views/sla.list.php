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
 * @var CView $this
 * @var array $data
 */

if ($data['uncheck']) {
	uncheckTableRows('sla', $data['keepids']);
}

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('sla.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$filter = null;

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter = (new CFilter())
		->addVar('action', 'sla.list')
		->setResetUrl($data['reset_curl'])
		->setProfile('web.sla.filter')
		->setActiveTab($data['active_tab']);

	$filter->addFilterTab(_('Filter'), [
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
				new CLabel(_('Tags')),
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
}

$form = (new CForm())
	->setId('sla-list')
	->setName('sla_list');

$header = [
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $data['filter_url'])
		->addStyle('width: 24%'),
	make_sorting_header(_('SLO'), 'slo', $data['sort'], $data['sortorder'], $data['filter_url']),
	make_sorting_header(_('Effective date'), 'effective_date', $data['sort'], $data['sortorder'], $data['filter_url'])
		->addStyle('width: 14%'),
	(new CColHeader(_('Reporting period')))->addStyle('width: 14%'),
	new CColHeader(_('Timezone')),
	new CColHeader(_('Schedule')),
	new CColHeader(_('SLA report')),
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $data['filter_url'])
];

if ($data['can_edit']) {
	array_unshift($header, (new CCheckBox('all_ids'))->onClick("checkAll('sla-list', 'all_ids', 'slaids');"));
}

$table = (new CTableInfo())
	->setHeader($header);

foreach ($data['slas'] as $slaid => $sla) {
	if ($data['can_edit']) {
		$name_element = (new CLink(CHtml::encode($sla['name']),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'sla.edit')
				->setArgument('slaid', $slaid)
		))
			->onClick('view.edit('.json_encode($slaid).')');

		if ($sla['status'] == CSlaHelper::SLA_STATUS_ENABLED) {
			$status_element = (new CLink(_('Enabled'),
				$data['status_toggle_curl']
					->setArgument('slaids', [$slaid])
					->setArgument('status', CSlaHelper::SLA_STATUS_DISABLED)
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addConfirmation(_('Disable SLA?'))
				->addSID();
		}
		else {
			$status_element = (new CLink(_('Disabled'),
				$data['status_toggle_curl']
					->setArgument('slaid', [$slaid])
					->setArgument('status', CSlaHelper::SLA_STATUS_ENABLED)
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addConfirmation(_('Enable SLA?'))
				->addSID();
		}
	}
	else {
		$name_element = CHtml::encode($sla['name']);
		$status_element = ($sla['status'] == CSlaHelper::SLA_STATUS_ENABLED) ? _('Enabled') : _('Disabled');
	}

	if (array_key_exists($slaid, $data['schedule_hints'])) {
		$schedule_mode = (new CCol(
			(new CLinkAction([
				CSlaHelper::scheduleModeToStr(CSlaHelper::SCHEDULE_MODE_CUSTOM),
				(new CSpan())->addClass('icon-description')
			]))
				->setHint($data['schedule_hints'][$slaid])
		))->addClass(ZBX_STYLE_NOWRAP);
	}
	else {
		$schedule_mode = CSlaHelper::scheduleModeToStr(CSlaHelper::SCHEDULE_MODE_NONSTOP);
	}

	$row = [
		$name_element,
		sprintf('%.4f', $sla['slo']),
		zbx_date2str(DATE_FORMAT, $sla['effective_date']),
		CSlaHelper::periodToStr((int) $sla['period']),
		$sla['timezone'],
		$schedule_mode,
		(new CLink(_('SLA report'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'sla.report')
				->setArgument('slaid', $slaid)
		))->setTarget('blank'),
		$status_element
	];

	if ($data['can_edit']) {
		array_unshift($row, new CCheckBox('slaids['.$slaid.']', $slaid));
	}

	$table->addRow(new CRow($row));
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'slaids', [
		'sla.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->setAttribute('confirm', _('Enable selected SLAs?'))
				->onClick('view.massEnable();')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		],
		'sla.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->setAttribute('confirm', _('Disable selected SLAs?'))
				->onClick('view.massDisable();')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		],
		'sla.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected SLAs?'))
				->onClick('view.massDelete();')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		]
	], 'slas')
]);

(new CWidget())
	->setTitle(_('SLA'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['can_edit']
						? (new CSimpleButton(_('Create SLA')))->onClick('view.edit()')
						: null
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'mode_switch_url' => $data['mode_switch_url'],
		'list_update_url' => $data['list_update_url'],
		'list_delete_url' => $data['list_delete_url']
	]).');
'))
	->setOnDocumentReady()
	->show();
