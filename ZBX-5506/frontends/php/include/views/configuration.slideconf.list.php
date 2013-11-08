<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
$slideWidget = new CWidget();

// create new hostgroup button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CSubmit('form', _('Create slide show')));
$slideWidget->addPageHeader(_('CONFIGURATION OF SLIDE SHOWS'), $createForm);
$slideWidget->addHeader(_('Slide shows'));
$slideWidget->addHeaderRowNumber();

// create form
$slideForm = new CForm();
$slideForm->setName('slideForm');

// create table
$slidesTable = new CTableInfo(_('No slide shows defined.'));
$slidesTable->setHeader(array(
	new CCheckBox('all_shows', null, "checkAll('".$slideForm->getName()."', 'all_shows', 'shows');"),
	make_sorting_header(_('Name'), 'name'),
	make_sorting_header(_('Delay'), 'delay'),
	make_sorting_header(_('Count of slides'), 'cnt')
));

foreach ($this->data['slides'] as $slide) {
	if (!slideshow_accessible($slide['slideshowid'], PERM_READ_WRITE)) {
		continue;
	}
	$slidesTable->addRow(array(
		new CCheckBox('shows['.$slide['slideshowid'].']', null, null, $slide['slideshowid']),
		new CLink($slide['name'], '?config=1&form=update&slideshowid='.$slide['slideshowid'], 'action'),
		$slide['delay'],
		$slide['cnt']
	));
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected slide shows?'));
$goComboBox->addItem($goOption);
$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "shows";');

// append table to form
$slideForm->addItem(array($this->data['paging'], $slidesTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$slideWidget->addItem($slideForm);
return $slideWidget;
?>
