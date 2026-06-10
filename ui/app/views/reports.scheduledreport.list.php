<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$this->includeJsFile('reports.scheduledreport.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Scheduled reports'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_SCHEDULEDREPORT_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create report')))
						->addClass('js-create-scheduledreport')
						->setEnabled($data['allowed_edit'])
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
	->setId('scheduledreport-list-form')
	->setName('scheduledreport-list-form');

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
			'scheduledreport.massenable' => [
				'content' => (new CSimpleButton(_('Enable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massenable-scheduledreport')
					->addClass('js-no-chkbxrange')
					->setAttribute('data-disabled', !$data['allowed_edit'])
					->setEnabled($data['allowed_edit'])
			],
			'scheduledreport.massdisable' => [
				'content' => (new CSimpleButton(_('Disable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdisable-scheduledreport')
					->addClass('js-no-chkbxrange')
					->setAttribute('data-disabled', !$data['allowed_edit'])
					->setEnabled($data['allowed_edit'])
			],
			'scheduledreport.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdelete-scheduledreport')
					->addClass('js-no-chkbxrange')
					->setAttribute('data-disabled', !$data['allowed_edit'])
					->setEnabled($data['allowed_edit'])
			]
		], 'scheduledreport')
	]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init();
'))
	->setOnDocumentReady()
	->show();
