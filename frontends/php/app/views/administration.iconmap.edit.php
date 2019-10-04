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


$this->includeJSfile('app/views/administration.iconmap.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('iconmap.edit :data-demo: '.$data['demo']))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu((new CUrl('zabbix.php'))
					->setArgument('action', 'iconmap.edit')
					->getUrl()
				))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$form = (new CForm())
	->setId('autoreg-form')
	->setName('autoreg-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.edit')
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

$widget
	->addItem($form)
	->show();
