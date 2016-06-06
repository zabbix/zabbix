<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


include('include/views/js/configuration.services.list.js.php');

$serviceWidget = new CWidget(null, 'service-list');
$serviceWidget->addPageHeader(_('CONFIGURATION OF IT SERVICES'), SPACE);
$serviceWidget->addHeader(_('IT services'));

// create form
$serviceForm = new CForm();
$serviceForm->setName('serviceForm');

$serviceWidget->addItem(BR());
$serviceWidget->addItem($this->data['tree']->getHTML());
return $serviceWidget;
