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

$widget = (new CWidget())->setTitle(_('Screens'));

$createForm = (new CForm('get'))
	->cleanItems();

$controls = (new CList())
	->addItem(new CSubmit('form', _('Create screen')));
if (!empty($this->data['templateid'])) {
	$createForm->addVar('templateid', $this->data['templateid']);
	$widget->addItem(get_header_host_table('screens', $this->data['templateid']));
}
else {
	$controls->addItem((new CButton('form', _('Import')))->onClick('redirect("conf.import.php?rules_preset=screen")'));
}
$createForm->addItem($controls);
$widget->setControls($createForm);

// create form
$screenForm = (new CForm())
	->setName('screenForm')
	->addVar('templateid', $this->data['templateid']);

// create table
$screenTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_screens'))->onClick("checkAll('".$screenForm->getName()."', 'all_screens', 'screens');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Dimension (cols x rows)'),
		_('Screen')
	]);

foreach ($this->data['screens'] as $screen) {
	$screenTable->addRow([
		new CCheckBox('screens['.$screen['screenid'].']', $screen['screenid']),
		new CLink($screen['name'], '?form=update&screenid='.$screen['screenid'].url_param('templateid')),
		$screen['hsize'].' x '.$screen['vsize'],
		new CLink(_('Edit'), 'screenedit.php?screenid='.$screen['screenid'].url_param('templateid'))
	]);
}

// buttons
$buttonsArray = [];
if (!$this->data['templateid']) {
	$buttonsArray['screen.export'] = ['name' => _('Export')];
}
$buttonsArray['screen.massdelete'] = ['name' => _('Delete'), 'confirm' => _('Delete selected screens?')];

// append table to form
$screenForm->addItem([
	$screenTable,
	$this->data['paging'],
	new CActionButtonList('action', 'screens', $buttonsArray)
]);

// append form to widget
$widget->addItem($screenForm);

return $widget;
