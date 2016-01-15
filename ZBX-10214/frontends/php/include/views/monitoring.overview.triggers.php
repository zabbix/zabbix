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
if ($data['config']['blink_period'] > 0) {
	$indic_container = (new CDiv())
		->addClass(ZBX_STYLE_NOTIF_INDIC_CONTAINER)
		->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_NOTIF_INDIC)
				->addClass(getSeverityStyle(null, false))
				->addClass('blink')
		);
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$indic_container->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_NOTIF_INDIC)
				->addClass(getSeverityStyle($severity))
				->addClass('blink')
			);
	}
	$indic_container->addItem(
		(new CTag('p', true, _s('Age less than %s', convertUnitsS($data['config']['blink_period']))))
			->addClass(ZBX_STYLE_GREY)
	);

	$help_hint->addItem($indic_container);
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
$filter = $this->data['filter'];
$filterFormView = new CView('common.filter.trigger', [
	'overview' => true,
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
		'hostId' => $this->data['hostid'],
		'groupId' => $this->data['groupid'],
		'fullScreen' => $this->data['fullscreen']
	],
	'config' => $data['config']
]);
$filterForm = $filterFormView->render();

$widget->addItem($filterForm);

// data table
if ($data['pageFilter']->groupsSelected) {
	global $page;

	$dataTable = getTriggersOverview($this->data['hosts'], $this->data['triggers'], $page['file'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo();
}

$widget->addItem($dataTable);

return $widget;
