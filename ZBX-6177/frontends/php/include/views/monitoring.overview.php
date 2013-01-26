<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/js/general.script.confirm.js.php';

$overviewWidget = new CWidget();

$typeComboBox = new CComboBox('type', $this->data['type'], 'submit()');
$typeComboBox->addItem(SHOW_TRIGGERS, _('Triggers'));
$typeComboBox->addItem(SHOW_DATA, _('Data'));

$headerForm = new CForm('get');
$headerForm->addItem(array(_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$headerForm->addItem(array(SPACE, _('Type'), SPACE, $typeComboBox));

$overviewWidget->addHeader(_('Overview'), $headerForm);

$hintTable = new CTableInfo();
$hintTable->setAttribute('style', 'width: 200px');

if ($this->data['type'] == SHOW_TRIGGERS) {
	$hintTable->addRow(array(new CCol(SPACE, 'normal'), _('Disabled')));
}

for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
	$hintTable->addRow(array(getSeverityCell($i), _('Enabled')));
}

$hintTable->addRow(array(new CCol(SPACE, 'trigger_unknown'), _('Unknown')));

if ($this->data['type'] == SHOW_TRIGGERS) {
	// blinking preview in help popup (only if blinking is enabled)
	$config = select_config();
	if ($config['blink_period'] > 0) {
		$col = new CCol(SPACE, 'not_classified');
		$col->setAttribute('style', 'background-image: url(images/gradients/blink.gif); background-position: top left; background-repeat: repeat;');
		$hintTable->addRow(array($col, _s('Age less than %s', convertUnitsS($config['blink_period']))));
	}

	$hintTable->addRow(array(new CCol(SPACE), _('No trigger')));
}
else {
	$hintTable->addRow(array(new CCol(SPACE), _('Disabled or no trigger')));
}

$help = new CHelp('web.view.php', 'right');
$help->setHint($hintTable, '', '', true, false);

// header right
$overviewWidget->addPageHeader(_('OVERVIEW'), array(
	get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])),
	SPACE,
	$help
));

// header left
$styleComboBox = new CComboBox('view_style', $this->data['view_style'], 'submit()');
$styleComboBox->addItem(STYLE_TOP, _('Top'));
$styleComboBox->addItem(STYLE_LEFT, _('Left'));

$hostLocationForm = new CForm('get');
$hostLocationForm->addVar('groupid', $this->data['groupid']);
$hostLocationForm->additem(array(_('Hosts location'), SPACE, $styleComboBox));

$overviewWidget->addHeader($hostLocationForm);

$dataTable = null;
if ($this->data['type'] == SHOW_DATA) {
	$dataTable = get_items_data_overview(array_keys($this->data['pageFilter']->hosts), $this->data['view_style']);
}
elseif ($this->data['type'] == SHOW_TRIGGERS) {
	$dataTable = get_triggers_overview(array_keys($this->data['pageFilter']->hosts), $this->data['view_style']);
}

$overviewWidget->addItem($dataTable);

return $overviewWidget;
