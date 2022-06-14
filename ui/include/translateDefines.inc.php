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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * String that is used to substitute macro when it cannot be resolved.
 */
define('UNRESOLVED_MACRO_STRING', '*'._('UNKNOWN').'*');

/**
 * Date and time formats.
 * Date formats must be compatible with the CDate class (class.cdate.js).
 */
define('DATE_TIME_FORMAT_SECONDS', _('Y-m-d H:i:s'));
define('DATE_TIME_FORMAT', _('Y-m-d H:i'));
define('DATE_TIME_FORMAT_SHORT', _('m-d H:i'));
define('DATE_FORMAT', _('Y-m-d'));
define('DATE_FORMAT_SHORT', _('m-d'));
define('TIME_FORMAT_SECONDS', _('H:i:s'));
define('TIME_FORMAT', _('H:i'));

define('SVG_GRAPH_DATE_TIME_FORMAT_SHORT', _('n-d H:i'));
define('SVG_GRAPH_DATE_FORMAT', _('Y-n-d'));
define('SVG_GRAPH_DATE_FORMAT_SHORT', _('n-d'));

define('ZBX_SLA_PERIOD_DATE_FORMAT_DAILY', DATE_FORMAT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_FROM', DATE_FORMAT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_TO', DATE_FORMAT_SHORT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_MONTHLY', _x('Y-m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_FROM', _x('Y-m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_TO', _x('m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_ANNUALLY', _x('Y', DATE_FORMAT_CONTEXT));
