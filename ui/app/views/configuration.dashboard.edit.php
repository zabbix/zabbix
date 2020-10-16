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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('dashboard.grid.js');
$this->addJsFile('multiselect.js');
$this->addJsFile('class.cclock.js');

$this->includeJsFile('dashboard/class.template-dashboard.js.php');
$this->includeJsFile('configuration.dashboard.edit.js.php');

(new CWidget())
	->setTitle(_('Dashboards'))
	->setControls((new CList())
		->setId('dashbrd-control')
		->addItem((new CListItem([
			(new CTag('nav', true, [
				new CList([
					(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
					(new CButton('dashbrd-add-widget', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add widget')]))
						->addClass(ZBX_STYLE_BTN_ALT),
					(new CButton('dashbrd-paste-widget', _('Paste widget')))
						->addClass(ZBX_STYLE_BTN_ALT)
						->setEnabled(false),
					(new CButton('dashbrd-save', _('Save changes'))),
					(new CLink(_('Cancel'), '#'))->setId('dashbrd-cancel'),
					''
				])
			]))
				->setAttribute('aria-label', _('Content controls'))
				->addClass(ZBX_STYLE_DASHBRD_EDIT)
		])))
	)
	->addItem(get_header_host_table('dashboards', $data['dashboard']['templateid']))
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_CONTAINER))
	->show();

(new CScriptTag(
	'initializeTemplateDashboard('.
		json_encode($data['dashboard']).','.
		json_encode($data['widget_defaults']).','.
		json_encode($data['page']).
	');'
))
	->setOnDocumentReady()
	->show();
