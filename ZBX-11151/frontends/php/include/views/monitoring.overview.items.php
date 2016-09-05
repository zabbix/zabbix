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


// hint table
$help_hint = (new CList())
	->addClass(ZBX_STYLE_NOTIF_BODY)
	->addStyle('min-width: '.ZBX_OVERVIEW_HELP_MIN_WIDTH.'px');
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$help_hint->addItem([
		(new CDiv())
			->addClass(ZBX_STYLE_NOTIF_INDIC)
			->addClass(getSeverityStyle($severity)),
		new CTag('h4', true, getSeverityName($severity, $data['config'])),
		(new CTag('p', true, _('PROBLEM')))->addClass(ZBX_STYLE_GREY)
	]);
}

// header right
$help = get_icon('overviewhelp');
$help->setHint($help_hint);

$widget = (new CWidget())
	->setTitle(_('Overview'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem([_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()])
			->addItem([_('Type'), SPACE, new CComboBox('type', $this->data['type'], 'submit()', [
				SHOW_TRIGGERS => _('Triggers'),
				SHOW_DATA => _('Data')
			])])
			->addItem([_('Hosts location'), SPACE, new CComboBox('view_style', $this->data['view_style'], 'submit()', [
				STYLE_TOP => _('Top'),
				STYLE_LEFT => _('Left')
			])])
			->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]))
			->addItem($help)
		)
	);

// filter
$filter = (new CFilter('web.overview.filter.state'))
	->addVar('fullscreen', $this->data['fullscreen']);

$column = new CFormList();

// application
$column->addRow(_('Filter by application'), [
	(new CTextBox('application', $this->data['filter']['application']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('application_name', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application'.
			'&with_applications=1&dstfrm=zbx_filter");')
]);

$filter->addColumn($column);

$widget->addItem($filter);

// data table
if ($data['pageFilter']->groupsSelected) {
	$dataTable = getItemsDataOverview(array_keys($this->data['pageFilter']->hosts), $this->data['applicationIds'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo();
}

$widget->addItem($dataTable);

return $widget;
