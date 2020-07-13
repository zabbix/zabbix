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

$this->addJsFile('multiselect.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tabfilter.js');
$this->addJsFile('class.tabfilteritem.js');

$this->includeJsFile('monitoring.host.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter = new CTabFilter();
	$containerid = 'tabcontent';

	foreach ($data['filter_tabs'] as $tab) {
		$container = null;
		$label = (new CLink($tab['label']))->setAttribute('data-target', '#'.$containerid);

		if ($tab['active']) {
			$container = (new CDiv(new CPartial($tab['template'], ['render_html' => true] + $tab)))->setId($containerid);
			$label->addClass('active');
		}

		$filter->addItem([$label, $container]);
		$filter->addTemplate(new CPartial($tab['template'], ['fields' => []] + $tab));
	}

	$filter->setData($data['filter_tabs']);

	$widget->addItem($filter);
}

$widget->addItem((new CForm())->setName('host_view')->addClass('is-loading'));

$widget->show();

(new CScriptTag('host_page.start();'))
	->setOnDocumentReady()
	->show();
