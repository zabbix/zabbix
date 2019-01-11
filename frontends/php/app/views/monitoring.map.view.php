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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('layout.mode.js');

(new CWidget())
	->setTitle(_('Maps'))
	->setWebLayoutMode(CView::getLayoutMode())
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->addVar('action', 'map.view')
			->addVar('sysmapid', $data['map']['sysmapid'])
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Minimum severity'), 'severity_min'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getSeveritiesMinCB()
				])
			),
		(new CTag('nav', true, (new CList())
			->addItem($data['map']['editable']
				? new CRedirectButton(_('Edit map'), (new CUrl('sysmap.php'))
					->setArgument('sysmapid', $data['map']['sysmapid'])
					->getUrl()
				)
				: null
			)
			->addItem(get_icon('favourite', [
				'fav' => 'web.favorite.sysmapids',
				'elname' => 'sysmapid',
				'elid' => $data['map']['sysmapid']
			]))
			->addItem(get_icon('fullscreen'))
		))
			->setAttribute('aria-label', _('Content controls'))
	]))
	->setBreadcrumbs(
		get_header_sysmap_table($data['map']['sysmapid'], $data['map']['name'], $data['severity_min'])
	)
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
