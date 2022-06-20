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

$widget = (new CWidget())
	->setTitle(_('Maps'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_SYSMAP_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CForm('get'))
						->cleanItems()
						->addItem(
							(new CSubmit('form', _('Create map')))->setEnabled($data['allowed_edit'])
						)
				)
				->addItem(
					(new CButton('form', _('Import')))
						->onClick(
							'return PopUp("popup.import", {rules_preset: "map"},
								{dialogue_class: "modal-popup-generic"}
							);'
						)
						->setEnabled($data['allowed_edit'])
						->removeId()
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(
		(new CFilter())
			->setResetUrl(new CUrl('sysmaps.php'))
			->setProfile($data['profileIdx'])
			->setActiveTab($data['active_tab'])
			->addFilterTab(_('Filter'), [
				(new CFormList())->addRow(_('Name'),
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			])
	);

// create form
$sysmapForm = (new CForm())->setName('frm_maps');

// create table
$url = (new CUrl('sysmaps.php'))->getUrl();

$sysmapTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_maps'))->onClick("checkAll('".$sysmapForm->getName()."', 'all_maps', 'maps');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('Width'), 'width', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('Height'), 'height', $this->data['sort'], $this->data['sortorder'], $url),
		_('Actions')
	]);

foreach ($this->data['maps'] as $map) {
	$user_type = CWebUser::getType();
	if ($user_type == USER_TYPE_SUPER_ADMIN || $map['editable']) {
		$checkbox = new CCheckBox('maps['.$map['sysmapid'].']', $map['sysmapid']);
		$action = $data['allowed_edit']
			? new CLink(_('Properties'), 'sysmaps.php?form=update&sysmapid='.$map['sysmapid'])
			: _('Properties');
		$constructor = $data['allowed_edit']
			? new CLink(_('Constructor'), 'sysmap.php?sysmapid='.$map['sysmapid'])
			: _('Constructor');
	}
	else {
		$checkbox = (new CCheckBox('maps['.$map['sysmapid'].']', $map['sysmapid']))
			->setAttribute('disabled', 'disabled');
		$action = '';
		$constructor = '';
	}
	$sysmapTable->addRow([
		$checkbox,
		new CLink($map['name'], 'zabbix.php?action=map.view&sysmapid='.$map['sysmapid']),
		$map['width'],
		$map['height'],
		new CHorList([$action, $constructor])
	]);
}

// append table to form
$sysmapForm->addItem([
	$sysmapTable,
	$this->data['paging'],
	new CActionButtonList('action', 'maps', [
		'map.export' => [
			'content' => new CButtonExport('export.sysmaps',
				(new CUrl('sysmaps.php'))
					->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
					->getUrl()
			)
		],
		'map.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected maps?'),
			'disabled' => $data['allowed_edit'] ? null : 'disabled'
		]
	])
]);

// append form to widget
$widget->addItem($sysmapForm);

$widget->show();
