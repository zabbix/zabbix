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
 * @var CView $this
 */

if ($data['uncheck']) {
	uncheckTableRows('discovery');
}

$widget = (new CWidget())
	->setTitle(_('Discovery rules'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_DISCOVERY_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('Create discovery rule'),
					(new CUrl('zabbix.php'))->setArgument('action', 'discovery.edit')
				))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'discovery.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), DRULE_STATUS_ACTIVE)
					->addValue(_('Disabled'), DRULE_STATUS_DISABLED)
					->setModern(true)
			)
		])
		->addVar('action', 'discovery.list')
	);

// create form
$discoveryForm = (new CForm())->setName('druleForm');

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_drules'))->onClick("checkAll('".$discoveryForm->getName()."', 'all_drules', 'druleids');")
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
	$status = new CCol(
		(new CLink(
			discovery_status2str($drule['status']),
			(new CUrl('zabbix.php'))
				->setArgument('druleids', (array) $drule['druleid'])
				->setArgument('action', $drule['status'] == DRULE_STATUS_ACTIVE
					? 'discovery.disable'
					: 'discovery.enable'
				)
				->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(discovery_status2style($drule['status']))
			->addSID()
	);

	$discoveryTable->addRow([
		new CCheckBox('druleids['.$drule['druleid'].']', $drule['druleid']),
		new CLink($drule['name'], (new CUrl('zabbix.php'))
			->setArgument('action', 'discovery.edit')
			->setArgument('druleid', $drule['druleid'])
		),
		$drule['iprange'],
		$drule['proxy'],
		$drule['delay'],
		!empty($drule['checks']) ? implode(', ', $drule['checks']) : '',
		$status
	]);
}

// append table to form
$discoveryForm->addItem([
	$discoveryTable,
	$this->data['paging'],
	new CActionButtonList('action', 'druleids', [
		'discovery.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected discovery rules?')],
		'discovery.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected discovery rules?')],
		'discovery.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected discovery rules?')]
	], 'discovery')
]);

// append form to widget
$widget->addItem($discoveryForm);

$widget->show();
