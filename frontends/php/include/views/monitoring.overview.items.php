<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			->addItem([
				new CLabel(_('Group'), 'groupid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$data['pageFilter']->getGroupsCB()
			])
			->addItem([
				new CLabel(_('Type'), 'type'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CComboBox('type', $data['type'], 'submit()', [
					SHOW_TRIGGERS => _('Triggers'),
					SHOW_DATA => _('Data')
				])
			])
			->addItem([
				new CLabel(_('Hosts location'), 'view_style'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CComboBox('view_style', $data['view_style'], 'submit()', [
					STYLE_TOP => _('Top'),
					STYLE_LEFT => _('Left')
				])
			])
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
			->addItem($help)
		)
	);

// filter
$filter = (new CFilter())
	->setProfile('web.overview.filter', 0)
	->addVar('fullscreen', $data['fullscreen'] ? '1' : null)
	->addFilterTab(_('Filter'), [(new CFormList())->addRow([
		(new CTextBox('application', $data['filter']['application']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('application_name', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'applications',
					'srcfld1' => 'name',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'application',
					'real_hosts' => '1',
					'with_applications' => '1'
				]).', null, this);'
			)
		])
	]);

$widget->addItem($filter);

// data table
if ($data['pageFilter']->groupsSelected) {
	$groupids = ($data['pageFilter']->groupids !== null) ? $data['pageFilter']->groupids : [];
	$table = getItemsDataOverview($groupids, $data['filter']['application'], $data['view_style'], $data['fullscreen']);
}
else {
	$table = new CTableInfo();
}

$widget->addItem($table);

return $widget;
