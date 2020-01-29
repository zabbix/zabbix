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

error_reporting(E_ALL | E_STRICT); // because in some PHP versions E_ALL does not include E_STRICT

require_once __DIR__.'/../../include/defines.inc.php';
require_once __DIR__.'/../../include/func.inc.php';
require_once __DIR__.'/../../include/gettextwrapper.inc.php';
require_once __DIR__.'/../../include/triggers.inc.php';
require_once __DIR__.'/../../include/items.inc.php';
require_once __DIR__.'/../../include/discovery.inc.php';
require_once __DIR__.'/../../include/actions.inc.php';
require_once __DIR__.'/../../include/validate.inc.php';
require_once __DIR__.'/../../include/services.inc.php';
require_once __DIR__.'/../../include/db.inc.php';

// register autoloader
require_once __DIR__.'/../../include/classes/core/CAutoloader.php';

$autoloader = new CAutoloader;
$autoloader->addNamespace('', [
	'/../../include/classes',
	'/../../include/classes/api',
	'/../../include/classes/api/clients',
	'/../../include/classes/api/helpers',
	'/../../include/classes/api/wrappers',
	'/../../include/classes/core',
	'/../../include/classes/helpers',
	'/../../include/classes/db',
	'/../../include/classes/parsers',
	'/../../include/classes/parsers/results',
	'/../../include/classes/validators',
	'/../../include/classes/validators/action',
	'/../../include/classes/validators/object',
	'/../../include/classes/triggers',
	'/../../include/classes/import',
	'/../../include/classes/import/validators',
	'/../../include/classes/import/readers',
	'/../../include/classes/import/converters',
	'/../../include/classes/export',
	'/../../include/classes/export/writers',
	'/../../include/classes/services',
	'/../../include/classes/helpers',
	'/../../include/classes/regexp',
	'/../../include/classes/api/services',
	'/../../include/classes/api/managers',
	'/../../include/classes/html',
	'/../../include/classes/html/interfaces',
	'/../../include/classes/xml',
	'/include/classes/db',
	'/include/classes/html',
	'/include/classes/validators',
	'/include/classes/parsers',
	'/include/classes/import/converters'
]);
$autoloader->addNamespace('Core', [$this->rootDir.'/include/classes/core']);
$autoloader->register();
