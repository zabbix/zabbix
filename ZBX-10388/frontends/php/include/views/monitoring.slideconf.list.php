<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->setTitle(_('Slide shows'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem(
				new CComboBox('config', 'slides.php', 'redirect(this.options[this.selectedIndex].value);', [
					'screens.php' => _('Screens'),
					'slides.php' => _('Slide shows')
				])
			)
			->addItem(new CSubmit('form', _('Create slide show')))
		)
	);

// filter
$widget->addItem(
	(new CFilter('web.slideconf.filter.state'))
		->addColumn((new CFormList())->addRow(_('Name like'),
			(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		))
);

// Create form.
$form = (new CForm())->setName('slideForm');

// create table
$slidesTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_shows'))->onClick("checkAll('".$form->getName()."', 'all_shows', 'shows');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Delay'), 'delay', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Number of slides'), 'cnt', $this->data['sort'], $this->data['sortorder']),
		_('Actions')
	]);

foreach ($this->data['slides'] as $slide) {
	$user_type = CWebUser::getType();

	if ($user_type == USER_TYPE_SUPER_ADMIN || $user_type == USER_TYPE_ZABBIX_ADMIN || $slide['editable']) {
		$checkbox = new CCheckBox('shows['.$slide['slideshowid'].']', $slide['slideshowid']);
		$properties = (new CLink(_('Properties'), '?form=update&slideshowid='.$slide['slideshowid']))
			->addClass('action');
	}
	else {
		$checkbox = (new CCheckBox('shows['.$slide['slideshowid'].']', $slide['slideshowid']))
			->setAttribute('disabled', 'disabled');
		$properties = '';
	}

	$slidesTable->addRow([
		$checkbox,
		(new CLink($slide['name'], 'slides.php?elementid='.$slide['slideshowid']))->addClass('action'),
		convertUnitsS($slide['delay']),
		$slide['cnt'],
		$properties
	]);
}

// append table to form
$form->addItem([
	$slidesTable,
	$this->data['paging'],
	new CActionButtonList('action', 'shows', [
		'slideshow.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected slide shows?')]
	])
]);

// append form to widget
$widget->addItem($form);

return $widget;
