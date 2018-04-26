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


zbx_add_post_js('jqBlink.blink();');

// hint table
$help_hint = (new CList())
	->addClass(ZBX_STYLE_NOTIF_BODY)
	->addStyle('min-width: '.ZBX_OVERVIEW_HELP_MIN_WIDTH.'px')
	->addItem([
		(new CDiv())
			->addClass(ZBX_STYLE_NOTIF_INDIC)
			->addClass(getSeverityStyle(null, false)),
		new CTag('h4', true, _('OK')),
		(new CTag('p', true, ''))->addClass(ZBX_STYLE_GREY)
	]);
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$help_hint->addItem([
		(new CDiv())
			->addClass(ZBX_STYLE_NOTIF_INDIC)
			->addClass(getSeverityStyle($severity)),
		new CTag('h4', true, getSeverityName($severity, $data['config'])),
		(new CTag('p', true, _('PROBLEM')))->addClass(ZBX_STYLE_GREY)
	]);
}

// blinking preview in help popup (only if blinking is enabled)
$blink_period = timeUnitToSeconds($data['config']['blink_period']);
if ($blink_period > 0) {
	$indic_container = (new CDiv())
		->addClass(ZBX_STYLE_NOTIF_INDIC_CONTAINER)
		->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_NOTIF_INDIC)
				->addClass(getSeverityStyle(null, false))
				->addClass('blink')
				->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN)
		);
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$indic_container->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_NOTIF_INDIC)
				->addClass(getSeverityStyle($severity))
				->addClass('blink')
				->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN)
			);
	}
	$indic_container->addItem(
		(new CTag('p', true, _s('Age less than %s', convertUnitsS($blink_period))))->addClass(ZBX_STYLE_GREY)
	);

	$help_hint->addItem($indic_container);
}

// header right
$widget = (new CWidget())
	->setTitle(_('Overview'))
	->setControls(new CList([
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
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
			->addItem(get_icon('overviewhelp')->setHint($help_hint))
		))
			->setAttribute('aria-label', _('Content controls'))
	]));

// filter
$filter = $data['filter'];
$filterFormView = new CView('common.filter.trigger', [
	'filter' => [
		'filterid' => 'web.overview.filter.state',
		'showTriggers' => $filter['showTriggers'],
		'ackStatus' => $filter['ackStatus'],
		'showSeverity' => $filter['showSeverity'],
		'statusChange' => $filter['statusChange'],
		'statusChangeDays' => $filter['statusChangeDays'],
		'txtSelect' => $filter['txtSelect'],
		'application' => $filter['application'],
		'inventory' => $filter['inventory'],
		'showMaintenance' => $filter['showMaintenance'],
		'hostId' => $data['hostid'],
		'groupId' => $data['groupid'],
		'fullScreen' => $data['fullscreen']
	],
	'config' => $data['config']
]);
$filterForm = $filterFormView->render();

$widget->addItem($filterForm);

// data table
if ($data['pageFilter']->groupsSelected) {
	global $page;

	$dataTable = getTriggersOverview($data['hosts'], $data['triggers'], $page['file'], $data['view_style'], null,
		$data['fullscreen']
	);
}
else {
	$dataTable = new CTableInfo();
}

$widget->addItem($dataTable);

return $widget;
