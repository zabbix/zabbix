<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('layout.mode.js');
$this->includeJsFile('monitoring.map.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

(new CWidget())
	->setTitle(_('Maps'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setName('map.view')
			->addVar('action', 'map.view')
			->addVar('sysmapid', $data['map']['sysmapid'])
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Minimum severity'), 'label-severity-min'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CSelect('severity_min'))
						->setFocusableElementId('label-severity-min')
						->setValue($data['severity_min'])
						->addOptions(CSelect::createOptionsFromArray($data['severities']))
				])
			),
		(new CTag('nav', true, (new CList())
			->addItem($data['map']['editable']
				? (new CRedirectButton(_('Edit map'), (new CUrl('sysmap.php'))
					->setArgument('sysmapid', $data['map']['sysmapid'])
					->getUrl()
				))->setEnabled($data['allowed_edit'])
				: null
			)
			->addItem(get_icon('favourite', [
				'fav' => 'web.favorite.sysmapids',
				'elname' => 'sysmapid',
				'elid' => $data['map']['sysmapid']
			]))
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))
			->setAttribute('aria-label', _('Content controls'))
	]))
	->setNavigation(getSysmapNavigation($data['map']['sysmapid'], $data['map']['name'], $data['severity_min']))
	->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
			->addStyle('padding: 0;')
			->addItem(
				CScreenBuilder::getScreen([
					'resourcetype' => SCREEN_RESOURCE_MAP,
					'mode' => SCREEN_MODE_PREVIEW,
					'dataId' => 'mapimg',
					'screenitem' => [
						'screenitemid' => $data['map']['sysmapid'],
						'screenid' => null,
						'resourceid' => $data['map']['sysmapid'],
						'width' => null,
						'height' => null,
						'severity_min' => $data['severity_min']
					]
				])->get()
			)
	)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
