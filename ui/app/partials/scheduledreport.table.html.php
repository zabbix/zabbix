<?php
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
 * @var CPartial $this
 */

$table = (new CTableInfo())
	->setHeader([
		($data['source'] === 'scheduledreport-form')
			? (new CColHeader((new CCheckBox('all_scheduledreports'))
				->onClick("checkAll('".$data['source']."', 'all_scheduledreports', 'reportids');")
			))->addClass(ZBX_STYLE_CELL_WIDTH)
			: null,
		($data['source'] === 'scheduledreport-form')
			? make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'scheduledreport.list')
					->getUrl()
			)
			: [[_('Name'), (new CSpan())->addClass(ZBX_STYLE_ARROW_UP)]],
		_('Owner'),
		_('Repeats'),
		_('Period'),
		_('Last sent'),
		_('Status'),
		_('Info')
	]);

$cycles = [
	ZBX_REPORT_CYCLE_DAILY => _('Daily'),
	ZBX_REPORT_CYCLE_WEEKLY => _('Weekly'),
	ZBX_REPORT_CYCLE_MONTHLY => _('Monthly'),
	ZBX_REPORT_CYCLE_YEARLY => _('Yearly')
];

$periods = [
	ZBX_REPORT_PERIOD_DAY => _('Previous day'),
	ZBX_REPORT_PERIOD_WEEK => _('Previous week'),
	ZBX_REPORT_PERIOD_MONTH => _('Previous month'),
	ZBX_REPORT_PERIOD_YEAR => _('Previous year')
];

$now = time();

foreach ($data['reports'] as $report) {
	$name = new CLink($report['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'scheduledreport.edit')
		->setArgument('reportid', $report['reportid'])
	);

	$info_icons = [];

	if (($report['state'] == ZBX_REPORT_STATE_ERROR || $report['state'] == ZBX_REPORT_STATE_SUCCESS_INFO)
			&& $report['info'] !== '') {
		$info_icons[] = ($report['state'] == ZBX_REPORT_STATE_ERROR)
			? makeErrorIcon($report['info'])
			: makeWarningIcon($report['info']);
	}

	if ($report['status'] == ZBX_REPORT_STATUS_DISABLED) {
		$status_name = _('Disabled');
		$status_class = ZBX_STYLE_RED;
	}
	else {
		$status_name = _('Enabled');
		$status_class = ZBX_STYLE_GREEN;

		if ($report['active_till'] !== '') {
			$active_till = (DateTime::createFromFormat(ZBX_DATE, $report['active_till'], new DateTimeZone('UTC')))
				->setTime(23, 59, 59);

			if ($active_till->getTimestamp() < $now) {
				$status_name = _('Expired');
				$status_class = ZBX_STYLE_GREY;

				$info_icons[] = makeWarningIcon(_s('Expired on %1$s.', $active_till->format(DATE_FORMAT)));
			}
		}
	}

	$status = ($data['source'] === 'scheduledreport-form' && $data['allowed_edit'])
		? (new CLink($status_name, (new CUrl('zabbix.php'))
			->setArgument('action', ($report['status'] == ZBX_REPORT_STATUS_DISABLED)
				? 'scheduledreport.enable'
				: 'scheduledreport.disable'
			)
			->setArgument('reportids', [$report['reportid']])
			->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addSID()
		: new CSpan($status_name);
	$status->addClass($status_class);

	$table->addRow([
		($data['source'] === 'scheduledreport-form')
			? new CCheckBox('reportids['.$report['reportid'].']', $report['reportid'])
			: null,
		(new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
		$report['owner'],
		$cycles[$report['cycle']],
		$periods[$report['period']],
		zbx_date2str(DATE_TIME_FORMAT, $report['lastsent']),
		$status,
		makeInformationList($info_icons)
	]);
}

$table->show();
