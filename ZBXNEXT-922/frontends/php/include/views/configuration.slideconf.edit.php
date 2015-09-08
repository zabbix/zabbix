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

$widget = (new CWidget())->setTitle(_('Slide shows'));

// create form
$slideForm = (new CForm())
	->setName('slideForm')
	->addVar('form', $this->data['form'])
	->addVar('slides', $this->data['slides_without_delay']);
if (!empty($this->data['slideshowid'])) {
	$slideForm->addVar('slideshowid', $this->data['slideshowid']);
}

// create slide form list
$slideFormList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Default delay (in seconds)'),
		(new CNumericBox('delay', $this->data['delay'], 5, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

// append slide table
$slideTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setId('slideTable')
	->setHeader([
		(new CColHeader())->setWidth(15),
		(new CColHeader())->setWidth(15),
		_('Screen'),
		(new CColHeader(_('Delay')))->setWidth(70),
		(new CColHeader(_('Action')))->setWidth(50)
	]);

$i = 1;
foreach ($this->data['slides'] as $key => $slides) {
	$name = '';
	if (!empty($slides['screenid'])) {
		$screen = get_screen_by_screenid($slides['screenid']);
		if ($screen) {
			$name = $screen['name'];
		}
	}

	$delay = (new CNumericBox('slides['.$key.'][delay]', !empty($slides['delay']) ? $slides['delay'] : '', 5, false, true, false))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		->setAttribute('placeholder', _('default'));

	$slideTable->addRow(
		(new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan($i++.':'))->addClass('rowNum')->setId('current_slide_'.$key),
			$name,
			$delay,
			(new CCol(
				(new CButton('remove_'.$key, _('Remove')))
					->onClick('javascript: removeSlide(this);')
					->addClass(ZBX_STYLE_BTN_LINK)
					->setAttribute('remove_slide', $key)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('slides_'.$key)
	);
}

$addButtonColumn = (new CCol(
	empty($this->data['work_slide'])
		? (new CButton('add', _('Add')))
			->onClick('return PopUp("popup.php?srctbl=screens&srcfld1=screenid&dstfrm='.$slideForm->getName().
					'&multiselect=1&writeonly=1")')
			->addClass(ZBX_STYLE_BTN_LINK)
		: null
	))->setColSpan(5);

$addButtonColumn->setAttribute('style', 'vertical-align: middle;');
$slideTable->addRow((new CRow($addButtonColumn))->setId('screenListFooter'));

$slideFormList->addRow(_('Slides'),
	(new CDiv($slideTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append tabs to form
$slideTab = new CTabView();
$slideTab->addTab('slideTab', _('Slide'), $slideFormList);

// append buttons to form
if (isset($this->data['slideshowid'])) {
	$slideTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete slide show?'), url_params(['form', 'slideshowid'])),
			new CButtonCancel()
		]
	));
}
else {
	$slideTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$slideForm->addItem($slideTab);
$widget->addItem($slideForm);

return $widget;
