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
 */

$html_page = (new CHtmlPage())
	->setTitle(_('Maps'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_SYSMAP_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CForm('get'))
						->addItem(
							(new CSubmit('form', _('Create map')))->setEnabled($data['allowed_edit'])
						)
				)
				->addItem(
					(new CButton('form', _('Import')))
						->onClick(
							'return PopUp("popup.import", '.
								json_encode([ 'rules_preset' => 'map',
									CCsrfTokenHelper::CSRF_TOKEN_NAME => CCsrfTokenHelper::get('import')
								]).
						', {
								dialogueid: "popup_import",
								dialogue_class: "modal-popup-generic"
							});'
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
		$properties_link = $data['allowed_edit']
			? new CLink(_('Properties'),
				(new CUrl('sysmaps.php'))
					->setArgument('form', 'update')
					->setArgument('sysmapid', $map['sysmapid'])
			)
			: _('Properties');
		$edit_link = $data['allowed_edit']
			? new CLink(_('Edit'),
				(new CUrl('sysmap.php'))->setArgument('sysmapid', $map['sysmapid'])
			)
			: _('Edit');
	}
	else {
		$checkbox = (new CCheckBox('maps['.$map['sysmapid'].']', $map['sysmapid']))
			->setAttribute('disabled', 'disabled');
		$properties_link = '';
		$edit_link = '';
	}
	$sysmapTable->addRow([
		$checkbox,
		(new CLink($map['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'map.view')
				->setArgument('sysmapid', $map['sysmapid'])
		)),
		$map['width'],
		$map['height'],
		new CHorList([$properties_link, $edit_link])
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
		'map.massdelete' => [
			'name' => _('Delete'),
			'confirm_singular' => _('Delete selected map?'),
			'confirm_plural' => _('Delete selected maps?'),
			'disabled' => $data['allowed_edit'] ? null : 'disabled',
			'csrf_token' => CCsrfTokenHelper::get('sysmaps.php')
		]
	])
]);

$html_page
	->addItem($sysmapForm)
	->show();
