<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * String that is used to substitute macro when it cannot be resolved.
 */
define('UNRESOLVED_MACRO_STRING', '*'._('UNKNOWN').'*');

/**
 * Date and time formats.
 */
define('DATE_TIME_FORMAT_SECONDS', _('Y-m-d H:i:s'));
define('DATE_TIME_FORMAT', _('Y-m-d H:i'));
define('DATE_TIME_FORMAT_SHORT', _('m-d H:i'));
define('DATE_FORMAT', _('Y-m-d'));
define('TIME_FORMAT_SECONDS', _('H:i:s'));
define('TIME_FORMAT', _('H:i'));
