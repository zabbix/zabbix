<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * @var CView $this
 */

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
		(new CTag('p', true, _s('Age less than %1$s', convertUnitsS($blink_period))))->addClass(ZBX_STYLE_GREY)
	);

	$help_hint->addItem($indic_container);
}

// header right
$web_layout_mode = CViewHelper::loadLayoutMode();

$submenu_source = [
	SHOW_TRIGGERS => _('Trigger overview'),
	SHOW_DATA => _('Data overview')
];

$submenu = [];
foreach ($submenu_source as $value => $label) {
	$url = (new CUrl('overview.php'))
		->setArgument('type', $value)
		->getUrl();

	$submenu[$url] = $label;
}

$widget = (new CWidget())
	->setTitle(array_key_exists($this->data['type'], $submenu_source) ? $submenu_source[$this->data['type']] : null)
	->setTitleSubmenu([
		'main_section' => [
			'items' => $submenu
		]
	])
	->setWebLayoutMode($web_layout_mode)
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem(new CInput('hidden', 'type', $this->data['type']))
			->addItem((new CList())
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
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			->addItem(get_icon('overviewhelp')->setHint($help_hint))
		))
			->setAttribute('aria-label', _('Content controls'))
	]));

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	// filter
	$widget->addItem(new CPartial('common.filter.trigger', [
		'filter' => [
			'showTriggers' => $data['filter']['showTriggers'],
			'ackStatus' => $data['filter']['ackStatus'],
			'showSeverity' => $data['filter']['showSeverity'],
			'statusChange' => $data['filter']['statusChange'],
			'statusChangeDays' => $data['filter']['statusChangeDays'],
			'txtSelect' => $data['filter']['txtSelect'],
			'application' => $data['filter']['application'],
			'inventory' => $data['filter']['inventory'],
			'show_suppressed' => $data['filter']['show_suppressed'],
			'groups' => $data['filter']['groups'],
			'hosts' => $data['filter']['hosts']
		],
		'config' => $data['config'],
		'profileIdx' => $data['profileIdx'],
		'active_tab' => $data['active_tab']
	]));
}

if ($data['view_style'] == STYLE_TOP) {
	$table = new CPartial('trigoverview.table.top', $data);
}
else {
	$table = new CPartial('trigoverview.table.left', $data);
}

$widget->addItem($table);

$widget->show();
