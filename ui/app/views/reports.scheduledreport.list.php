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

if ($data['uncheck']) {
	uncheckTableRows('scheduledreport');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Scheduled reports'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_SCHEDULEDREPORT_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CRedirectButton(_('Create report'),
						(new CUrl('zabbix.php'))->setArgument('action', 'scheduledreport.edit')
					))->setEnabled($data['allowed_edit'])
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'scheduledreport.list'))
		->addVar('action', 'scheduledreport.list')
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Show'),
				(new CRadioButtonList('filter_show', (int) $data['filter']['show']))
					->addValue(_('All'), ZBX_REPORT_FILTER_SHOW_ALL)
					->addValue(_('Created by me'), ZBX_REPORT_FILTER_SHOW_MY)
					->setModern(true)
			),
			(new CFormList())->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), ZBX_REPORT_STATUS_ENABLED)
					->addValue(_('Disabled'), ZBX_REPORT_STATUS_DISABLED)
					->addValue(_('Expired'), ZBX_REPORT_STATUS_EXPIRED)
					->setModern(true)
			)
		])
	);

$form = (new CForm())
	->setId('scheduledreport-form')
	->setName('scheduledreport-form');

$csrf_token = CCsrfTokenHelper::get('scheduledreport');

$form->addItem([
		new CPartial('scheduledreport.table.html', [
			'source' => $form->getName(),
			'sort' => $data['sort'],
			'sortorder' => $data['sortorder'],
			'allowed_edit' => $data['allowed_edit'],
			'reports' => $data['reports'],
			'paging' => $data['paging']
		]),
		new CActionButtonList('action', 'reportids', [
			'scheduledreport.enable' => [
				'name' => _('Enable'),
				'confirm_singular' => _('Enable selected scheduled report?'),
				'confirm_plural' => _('Enable selected scheduled reports?'),
				'disabled' => !$data['allowed_edit'],
				'csrf_token' => $csrf_token
			],
			'scheduledreport.disable' => [
				'name' => _('Disable'),
				'confirm_singular' => _('Disable selected scheduled report?'),
				'confirm_plural' => _('Disable selected scheduled reports?'),
				'disabled' => !$data['allowed_edit'],
				'csrf_token' => $csrf_token
			],
			'scheduledreport.delete' => [
				'name' => _('Delete'),
				'confirm_singular' => _('Delete selected scheduled report?'),
				'confirm_plural' => _('Delete selected scheduled reports?'),
				'disabled' => !$data['allowed_edit'],
				'csrf_token' => $csrf_token
			]
		], 'scheduledreport')
	]);

$html_page
	->addItem($form)
	->show();
