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
 */

if ($data['uncheck']) {
	uncheckTableRows('scheduledreport');
}

$widget = (new CWidget())
	->setTitle(_('Scheduled reports'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
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

$form->addItem([
		new CPartial('scheduledreport.table.html', [
			'source' => $form->getName(),
			'sort' => $data['sort'],
			'sortorder' => $data['sortorder'],
			'allowed_edit' => $data['allowed_edit'],
			'reports' => $data['reports']
		]),
		$data['paging'],
		new CActionButtonList('action', 'reportids', [
			'scheduledreport.enable' => [
				'name' => _('Enable'),
				'confirm' => _('Enable selected scheduled reports?'),
				'disabled' => !$data['allowed_edit']
			],
			'scheduledreport.disable' => [
				'name' => _('Disable'),
				'confirm' => _('Disable selected scheduled reports?'),
				'disabled' => !$data['allowed_edit']
			],
			'scheduledreport.delete' => [
				'name' => _('Delete'),
				'confirm' => _('Delete selected scheduled reports?'),
				'disabled' => !$data['allowed_edit']
			]
		], 'scheduledreport')
	]);

$widget
	->addItem($form)
	->show();
