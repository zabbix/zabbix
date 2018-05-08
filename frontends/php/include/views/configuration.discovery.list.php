<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$widget = (new CWidget())
	->setTitle(_('Discovery rules'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())->addItem(new CSubmit('form', _('Create discovery rule'))))
	)
	->addItem((new CFilter())
		->setProfile('web.discoveryconf.filter', 0)
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(
				_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(
				_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), DRULE_STATUS_ACTIVE)
					->addValue(_('Disabled'), DRULE_STATUS_DISABLED)
					->setModern(true)
			)
		])
	);

// create form
$discoveryForm = (new CForm())->setName('druleForm');

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_drules'))->onClick("checkAll('".$discoveryForm->getName()."', 'all_drules', 'g_druleid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('IP range'),
		_('Interval'),
		_('Checks'),
		_('Status')
	]);

foreach ($data['drules'] as $drule) {
	array_push($drule['description'], new CLink($drule['name'], '?form=update&druleid='.$drule['druleid']));

	$status = new CCol(
		(new CLink(
			discovery_status2str($drule['status']),
			'?g_druleid[]='.$drule['druleid'].
			'&action='.($drule['status'] == DRULE_STATUS_ACTIVE ? 'drule.massdisable' : 'drule.massenable')
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(discovery_status2style($drule['status']))
			->addSID()
	);

	$discoveryTable->addRow([
		new CCheckBox('g_druleid['.$drule['druleid'].']', $drule['druleid']),
		$drule['description'],
		$drule['iprange'],
		$drule['delay'],
		!empty($drule['checks']) ? implode(', ', $drule['checks']) : '',
		$status
	]);
}

// append table to form
$discoveryForm->addItem([
	$discoveryTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_druleid', [
		'drule.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected discovery rules?')],
		'drule.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected discovery rules?')],
		'drule.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected discovery rules?')]
	])
]);

// append form to widget
$widget->addItem($discoveryForm);

return $widget;
