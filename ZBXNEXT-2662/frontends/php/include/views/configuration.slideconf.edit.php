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


require_once dirname(__FILE__).'/js/configuration.slideconf.edit.js.php';

$slideWidget = (new CWidget())->setTitle(_('Slide shows'));

// create form
$slideForm = new CForm();
$slideForm->setName('slideForm');
$slideForm->addVar('form', $this->data['form']);
$slideForm->addVar('slides', $this->data['slides_without_delay']);
if (!empty($this->data['slideshowid'])) {
	$slideForm->addVar('slideshowid', $this->data['slideshowid']);
}

// create slide form list
$slideFormList = new CFormList('slideFormList');
$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$slideFormList->addRow(_('Name'), $nameTextBox);
$slideFormList->addRow(_('Default delay (in seconds)'), new CNumericBox('delay', $this->data['delay'], 5, false, false, false));

// append slide table
$slideTable = new CTableInfo();
$slideTable->setAttribute('style', 'min-width: 312px;');
$slideTable->setAttribute('id', 'slideTable');
$slideTable->setHeader(array(
	new CColHeader(SPACE, null, null, '15'),
	new CColHeader(SPACE, null, null, '15'),
	_('Screen'),
	new CColHeader(_('Delay'), null, null, '70'),
	new CColHeader(_('Action'), null, null, '50')
));

$i = 1;
foreach ($this->data['slides'] as $key => $slides) {
	$name = '';
	if (!empty($slides['screenid'])) {
		$screen = get_screen_by_screenid($slides['screenid']);
		if ($screen) {
			$name = $screen['name'];
		}
	}

	$delay = new CNumericBox('slides['.$key.'][delay]', !empty($slides['delay']) ? $slides['delay'] : '', 5, false, true, false);
	$delay->setAttribute('placeholder', _('default'));

	$removeButton = new CButton('remove_'.$key, _('Remove'), 'javascript: removeSlide(this);', 'link_menu');
	$removeButton->setAttribute('remove_slide', $key);

	$row = new CRow(
		array(
			new CCol(new CDiv(null, 'drag-icon'), 'td-drag-icon'),
			new CSpan($i++.':', 'rowNum', 'current_slide_'.$key),
			$name,
			$delay,
			$removeButton
		),
		'sortable',
		'slides_'.$key
	);
	$slideTable->addRow($row);
}

$addButtonColumn = new CCol(
	empty($this->data['work_slide'])
		? new CButton('add', _('Add'),
			'return PopUp("popup.php?srctbl=screens&srcfld1=screenid&dstfrm='.$slideForm->getName().
				'&multiselect=1&writeonly=1")',
			'link_menu')
		: null,
	null,
	5
);
$addButtonColumn->setAttribute('style', 'vertical-align: middle;');
$slideTable->addRow(new CRow($addButtonColumn, null, 'screenListFooter'));

$slideFormList->addRow(_('Slides'), new CDiv($slideTable, 'objectgroup inlineblock border_dotted'));

// append tabs to form
$slideTab = new CTabView();
$slideTab->addTab('slideTab', _('Slide'), $slideFormList);

// append buttons to form
if (isset($this->data['slideshowid'])) {
	$slideTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete slide show?'), url_params(array('form', 'slideshowid'))),
			new CButtonCancel()
		)
	));
}
else {
	$slideTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		array(new CButtonCancel())
	));
}

$slideForm->addItem($slideTab);
$slideWidget->addItem($slideForm);

return $slideWidget;
