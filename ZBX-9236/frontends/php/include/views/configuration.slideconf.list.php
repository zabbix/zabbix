<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$slideWidget = (new CWidget())->setTitle(_('Slide shows'));

// create new hostgroup button
$createForm = new CForm('get');
$createForm->cleanItems();
$controls = new CList();
$controls->addItem(new CSubmit('form', _('Create slide show')));
$createForm->addItem($controls);
$slideWidget->setControls($createForm);

// create form
$slideForm = new CForm();
$slideForm->setName('slideForm');

// create table
$slidesTable = new CTableInfo();
$slidesTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_shows', null, "checkAll('".$slideForm->getName()."', 'all_shows', 'shows');")))->
		addClass('cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Delay'), 'delay', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Number of slides'), 'cnt', $this->data['sort'], $this->data['sortorder'])
]);

foreach ($this->data['slides'] as $slide) {
	$slidesTable->addRow([
		new CCheckBox('shows['.$slide['slideshowid'].']', null, null, $slide['slideshowid']),
		new CLink($slide['name'], '?form=update&slideshowid='.$slide['slideshowid'], 'action'),
		convertUnitsS($slide['delay']),
		$slide['cnt']
	]);
}

// append table to form
$slideForm->addItem([
	$slidesTable,
	$this->data['paging'],
	new CActionButtonList('action', 'shows', [
		'slideshow.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected slide shows?')]
	])
]);

// append form to widget
$slideWidget->addItem($slideForm);

return $slideWidget;
