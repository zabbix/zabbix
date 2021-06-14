<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

// hint table
$help_hint = (new CList())
	->addClass(ZBX_STYLE_NOTIF_BODY)
	->addStyle('min-width: '.ZBX_OVERVIEW_HELP_MIN_WIDTH.'px');
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$help_hint->addItem([
		(new CDiv())
			->addClass(ZBX_STYLE_NOTIF_INDIC)
			->addClass(getSeverityStyle($severity)),
		new CTag('h4', true, getSeverityName($severity)),
		(new CTag('p', true, _('PROBLEM')))->addClass(ZBX_STYLE_GREY)
	]);
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
	->setControls(
		(new CTag('nav', true, (new CList())
			->addItem((new CForm('get'))
				->cleanItems()
				->setName('main_filter')
				->setAttribute('aria-label', _('Main filter'))
				->addItem(new CInput('hidden', 'type', $data['type']))
				->addItem((new CList())
					->addItem([
						new CLabel(_('Hosts location'), 'label-view-style'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CSelect('view_style'))
							->setId('hosts-location')
							->setValue($data['view_style'])
							->setFocusableElementId('label-view-style')
							->addOption(new CSelectOption(STYLE_TOP, _('Top')))
							->addOption(new CSelectOption(STYLE_LEFT, _('Left')))
					])
				)
			)
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			->addItem(get_icon('overviewhelp')->setHint($help_hint))
		))
			->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	// filter
	$widget->addItem(new CPartial('common.filter.item', [
		'filter' => [
			'groups' => $data['filter']['groups'],
			'hosts' => $data['filter']['hosts'],
			'show_suppressed' => $data['filter']['show_suppressed'],
			'tags' => $data['filter']['tags'],
			'evaltype' => $data['filter']['evaltype']
		],
		'profileIdx' => $data['profileIdx'],
		'active_tab' => $data['active_tab']
	]));
}

$partial = ($data['view_style'] == STYLE_TOP) ? 'dataoverview.table.top' : 'dataoverview.table.left';
$table = new CPartial($partial, [
	'items' => $data['items'],
	'hosts' => $data['hosts'],
	'has_hidden_data' => $data['has_hidden_data']
]);

$widget->addItem($table);

$widget->show();
