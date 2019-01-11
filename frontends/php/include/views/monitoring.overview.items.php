<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
$web_layout_mode = CView::getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Overview'))
	->setWebLayoutMode($web_layout_mode)
	->setControls((new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Type'), 'type'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					new CComboBox('type', $this->data['type'], 'submit()', [
						SHOW_TRIGGERS => _('Triggers'),
						SHOW_DATA => _('Data')
					])
				])
				->addItem([
					new CLabel(_('Hosts location'), 'view_style'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					new CComboBox('view_style', $this->data['view_style'], 'submit()', [
						STYLE_TOP => _('Top'),
						STYLE_LEFT => _('Left')
					])
				])
		),
		(new CTag('nav', true, (new CList())
			->addItem(get_icon('fullscreen'))
			->addItem(get_icon('overviewhelp')->setHint($help_hint))
		))
			->setAttribute('aria-label', _('Content controls'))
	])));

if (in_array($web_layout_mode, [ZBX_LAYOUT_NORMAL, ZBX_LAYOUT_FULLSCREEN])) {
	// filter
	$widget->addItem((new CFilter((new CUrl('overview.php'))->setArgument('type', 1)))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(_('Application'), [
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
				->addRow(_('Show suppressed problems'),
					(new CCheckBox('show_suppressed'))->setChecked(
						$data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE
					)
				)
		])
	);
}

// data table
if ($data['pageFilter']->groupsSelected) {
	$groupids = ($data['pageFilter']->groupids !== null) ? $data['pageFilter']->groupids : [];
	$table = getItemsDataOverview($groupids, $data['filter']['application'], $data['view_style'],
		$data['filter']['show_suppressed']
	);
}
else {
	$table = new CTableInfo();
}

$widget->addItem($table);

return $widget;
