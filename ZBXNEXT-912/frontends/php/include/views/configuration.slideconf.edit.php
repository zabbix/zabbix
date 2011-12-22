<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/views/js/configuration.slideconf.edit.js.php');

$slideWidget = new CWidget();
$slideWidget->addPageHeader(_('CONFIGURATION OF SLIDE SHOWS'));

// create form
$slideForm = new CForm();
$slideForm->setName('slideForm');
$slideForm->addVar('form', $this->data['form']);
$slideForm->addVar('slides', $this->data['slides']);
if (!empty($this->data['slideshowid'])) {
	$slideForm->addVar('slideshowid', $this->data['slideshowid']);
}

// create slide form list
$slideFormList = new CFormList('slideFormList');
$slideFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
$slideFormList->addRow(_('Update interval (in sec)'), new CNumericBox('delay', $this->data['delay'], 5));

// append slide table
$slideTable = new CTableInfo(_('No slides defined.'), 'formElementTable');
$slideTable->setAttribute('style', 'min-width: 500px;');
$slideTable->setAttribute('id', 'slideTable');
$slideTable->setHeader(array(
	SPACE,
	SPACE,
	_('Screen'),
	_('Delay'),
	_('Action')
));

$i = 1;
foreach ($this->data['slides'] as $step => $slides) {
	$name = '';
	if (!empty($slides['screenid'])) {
		$screen = get_screen_by_screenid($slides['screenid']);
		if (!empty($screen['name'])) {
			$name = $screen['name'];
		}
	}
	$name = new CSpan($name, 'link');
	$name->onClick('return create_var(\''.$slideForm->getName().'\', \'edit_slide\', '.$slides['slideid'].', true);');

	$numSpan = new CSpan($i++.':');
	$numSpan->addClass('rowNum');
	$numSpan->setAttribute('id', 'current_slide_'.$step);

	$row = new CRow(array(
		new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
		$numSpan,
		$name,
		!empty($slides['delay']) ? bold($slides['delay']) : $this->data['delay'],
		new CButton('remove', _('Remove'), 'javascript: removeSlide(\''.$step.'\');', 'link_menu')
	), 'sortable');
	$row->setAttribute('id', 'slides_'.$step);
	$slideTable->addRow($row);
}
$tmpColumn = new CCol(
	empty($this->data['work_slide'])
		? new CSubmit('btn1', _('Add'), 'return create_var(\''.$slideForm->getName().'\', \'add_slide\', 1, true);', 'link_menu')
		: null,
	null,
	5
);
$tmpColumn->setAttribute('style', 'vertical-align: middle;');
$slideTable->addRow(new CRow($tmpColumn));

$slideFormList->addRow(_('Slides'), new CDiv($slideTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append slides window to form list
if (!empty($this->data['work_slide'])) {
	$slideForm->addVar('work_slide[slideid]', $this->data['work_slide']['slideid']);
	$slideForm->addVar('work_slide[screenid]', $this->data['work_slide']['screenid']);

	$editSlideTable = new CTableInfo();
	$editSlideTable->addRow(array(_('Delay'), new CNumericBox('work_slide[delay]', $this->data['work_slide']['delay'], 5)));
	$editSlideTable->addRow(array(_('Screen'), array(
		new CTextBox('screen_name', $this->data['work_slide_screen'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('select_screen', _('Select'),
			'return PopUp(\'popup.php?dstfrm='.$slideForm->getName().'&srctbl=screens'.
			'&dstfld1=screen_name&srcfld1=name'.
			'&dstfld2=work_slide_screenid&srcfld2=screenid\');',
			'formlist'
		)
	)));

	$column = new CCol(array(
		new CSubmit(
			!empty($this->data['work_slide']['screenid']) ? 'edit_slide' : 'add_slide',
			!empty($this->data['work_slide']['screenid']) ? _('Save') : _('Add')
		),
		SPACE,
		new CSubmit('cancel_slide', _('Cancel'))
	));
	$column->setAttribute('colspan', '2');
	$column->setAttribute('style', 'text-align: right;');
	$editSlideTable->setFooter($column);

	$slideFormList->addRow(
		SPACE,
		array(
			BR(),
			create_hat(
				!empty($this->data['work_slide']['screenid']) ? _('Edit slide') : _('New slide'),
				$editSlideTable
			)
		)
	);
}

// append tabs to form
$slideTab = new CTabView();
$slideTab->addTab('slideTab', _('Slide'), $slideFormList);
$slideForm->addItem($slideTab);

// append buttons to form
if (empty($this->data['slideshowid'])) {
	$slideForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(new CButtonCancel())
	));
}
else {
	$slideForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete slide show?'), url_param('form').url_param('slideshowid').url_param('config')),
			new CButtonCancel()
		)
	));
}

$slideWidget->addItem($slideForm);
return $slideWidget;
?>
