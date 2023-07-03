<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$this->includeJsFile('configuration.discovery.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Discovery rules'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_DISCOVERY_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create discovery rule')))->setId('js-create')
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'discovery.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				]),
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Status')),
					new CFormField(
						(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
							->addValue(_('Any'), -1)
							->addValue(_('Enabled'), DRULE_STATUS_ACTIVE)
							->addValue(_('Disabled'), DRULE_STATUS_DISABLED)
							->setModern()
					)
				])
		])
		->addVar('action', 'discovery.list')
	);

$discoveryForm = (new CForm())->setName('druleForm');

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_drules'))
				->onClick("checkAll('".$discoveryForm->getName()."', 'all_drules', 'druleids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], (new CUrl('zabbix.php'))
			->setArgument('action', 'discovery.list')
			->getUrl()
		),
		_('IP range'),
		_('Proxy'),
		_('Interval'),
		_('Checks'),
		_('Status')
	]);

foreach ($data['drules'] as $drule) {
	$status = $drule['status'] == DRULE_STATUS_ACTIVE
		? (new CLink(_('Enabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable-drule')
			->setAttribute('data-druleid', (int) $drule['druleid'])
		: (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable-drule')
			->setAttribute('data-druleid', (int) $drule['druleid']);

	$discoveryTable->addRow([
		new CCheckBox('druleids['.$drule['druleid'].']', $drule['druleid']),
		(new CLink($drule['name']))
			->addClass('js-discovery-edit')
			->setAttribute('data-druleid', $drule['druleid']),
		$drule['iprange'],
		$drule['proxy'],
		$drule['delay'],
		!empty($drule['checks']) ? implode(', ', $drule['checks']) : '',
		$status
	]);
}

$discoveryForm->addItem([
	$discoveryTable,
	$this->data['paging'],
	new CActionButtonList('action', 'druleids', [
		'discovery.enable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massenable')
				->addClass('js-no-chkbxrange')
		],
		'discovery.disable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdisable')
				->addClass('js-no-chkbxrange')
		],
		'discovery.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
				->addClass('js-no-chkbxrange')
		]
	], 'discovery')
]);

$html_page
	->addItem($discoveryForm)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
