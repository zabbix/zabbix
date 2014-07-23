<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

error_reporting(E_ALL | E_STRICT); // because in some PHP versions E_ALL does not include E_STRICT

require_once __DIR__.'/../../include/defines.inc.php';
require_once __DIR__.'/../../include/func.inc.php';
require_once __DIR__.'/../../include/gettextwrapper.inc.php';
require_once __DIR__.'/../../include/triggers.inc.php';
require_once __DIR__.'/../../include/items.inc.php';
require_once __DIR__.'/../../include/discovery.inc.php';
require_once __DIR__.'/../../include/actions.inc.php';
require_once __DIR__.'/../../include/validate.inc.php';

// register autoloader
require_once __DIR__.'/../../include/classes/core/CAutoloader.php';

$autoloader = new CAutoloader(array(
	__DIR__.'/../../include/classes',
	__DIR__.'/../../include/classes/core',
	__DIR__.'/../../include/classes/helpers',
	__DIR__.'/../../include/classes/db',
	__DIR__.'/../../include/classes/api',
	__DIR__.'/../../include/classes/api/clients',
	__DIR__.'/../../include/classes/api/wrappers',
	__DIR__.'/../../include/classes/parsers',
	__DIR__.'/../../include/classes/parsers/results',
	__DIR__.'/../../include/classes/validators',
	__DIR__.'/../../include/classes/validators/action',
	__DIR__.'/../../include/classes/triggers',
	__DIR__.'/../../include/classes/import',
	__DIR__.'/../../include/classes/services',
	__DIR__.'/../../include/classes/helpers',
	__DIR__.'/../../api/classes',
	__DIR__.'/include/classes/validators',
	__DIR__.'/include/classes/parsers',
));
$autoloader->register();
