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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.loader.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.js');
$this->addJsFile('class.dashboard.widget.iterator.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('multiselect.js');
$this->addJsFile('class.cclock.js');
$this->addJsFile('class.sortable.js');

$this->includeJsFile('configuration.dashboard.edit.js.php');

(new CWidget())
	->setTitle(_('Dashboards'))
	->setControls((new CList())
		->setId('dashbrd-control')
		->addItem((new CListItem([
			(new CTag('nav', true, [
				new CList([
					(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
					(new CList())
						->addClass(ZBX_STYLE_BTN_SPLIT)
						->addItem((new CButton('dashbrd-add-widget', _('Add')))->addClass(ZBX_STYLE_BTN_ALT))
						->addItem(
							(new CButton('dashbrd-add', '&#8203;'))
								->addClass(ZBX_STYLE_BTN_ALT)
								->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON)
						),
					(new CButton('dashbrd-save', _('Save changes'))),
					(new CLink(_('Cancel'), '#'))->setId('dashbrd-cancel'),
					''
				])
			]))
				->setAttribute('aria-label', _('Content controls'))
				->addClass(ZBX_STYLE_DASHBRD_EDIT)
		])))
	)
	->setNavigation(getHostNavigation('dashboards', $data['dashboard']['templateid']))
	->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_DASHBRD)
			->addItem(
				(new CDiv())
					->addClass(ZBX_STYLE_DASHBRD_NAVIGATION)
					->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_NAVIGATION_TABS))
					->addItem(
						(new CDiv())
							->addClass(ZBX_STYLE_DASHBRD_NAVIGATION_CONTROLS)
							->addItem([
								(new CSimpleButton())
									->addClass(ZBX_STYLE_DASHBRD_PREVIOUS_PAGE)
									->addClass('btn-iterator-page-previous')
									->setEnabled(false),
								(new CSimpleButton())
									->addClass(ZBX_STYLE_DASHBRD_NEXT_PAGE)
									->addClass('btn-iterator-page-next')
									->setEnabled(false)
							])
					)
			)
			->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID))
	)
	->show();

(new CScriptTag(
	'initializeView('.
		json_encode($data['dashboard']).','.
		json_encode($data['widget_defaults']).','.
		json_encode($data['page']).
	');'
))
	->setOnDocumentReady()
	->show();
