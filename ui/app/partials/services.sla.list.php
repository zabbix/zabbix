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
	->setId('sla-list')
	->setName('sla_list');

$header = [
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $data['filter_url'])
		->addStyle('width: 14%'),
	(new CColHeader(_('SLO'))),
	(new CColHeader(_('Effective date')))->addStyle('width: 24%'),
	(new CColHeader(_('Reporting period')))->addStyle('width: 14%'),
	(new CColHeader(_('Timezone'))),
	(new CColHeader(_('Schedule'))),
	(new CColHeader(_('SLA report'))),
	(new CColHeader(_('Status'))),
];

if ($data['can_edit']) {
	array_unshift($header,
		(new CCheckBox('all_ids'))->onClick("checkAll('sla-list', 'all_ids', 'ids');")
	);
}

$table = (new CTableInfo())
	->setHeader($header);

foreach ($data['records'] as $recordid => $record) {
	$row = [
		$record['name'],
		sprintf('%.4f', $record['slo']),
		zbx_date2str(DATE_FORMAT, $record['effective_date']),
	];

	if ($data['can_edit']) {
		array_unshift($row,
			new CCheckBox('ids['.$recordid.']', $recordid),
		);
	}

	$table->addRow(new CRow($row));
}


$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'ids', [
		'sla.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->setAttribute('confirm', _('Enable selected SLAs?'))
				->onClick('view.massEnable(this);')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		],
		'sla.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->setAttribute('confirm', _('Disable selected SLAs?'))
				->onClick('view.massDisable(this);')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		],
		'sla.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected SLAs?'))
				->onClick('view.massDeleteSlas(this);')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		]
	], 'slas')
])->show();
