<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * String that is used to substitute macro when it cannot be resolved.
 */
define('UNRESOLVED_MACRO_STRING', '*'._('UNKNOWN').'*');

/**
 * Date and time formats.
 * Date formats must be compatible with the CDate class (class.cdate.js).
 */
define('DATE_TIME_FORMAT_SECONDS', _('Y-m-d h:i:s A'));
define('DATE_TIME_FORMAT', _('Y-m-d h:i A'));
define('DATE_TIME_FORMAT_SHORT', _('m-d h:i A'));
define('DATE_FORMAT', _('Y-m-d'));
define('DATE_FORMAT_SHORT', _('m-d'));
define('TIME_FORMAT_SECONDS', _('h:i:s A'));
define('TIME_FORMAT', _('h:i A'));

define('SVG_GRAPH_DATE_TIME_FORMAT_SHORT', _('n-d h:i A'));
define('SVG_GRAPH_DATE_FORMAT', _('Y-n-d'));
define('SVG_GRAPH_DATE_FORMAT_SHORT', _('n-d'));

define('ZBX_SLA_PERIOD_DATE_FORMAT_DAILY', DATE_FORMAT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_FROM', DATE_FORMAT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_TO', DATE_FORMAT_SHORT);
define('ZBX_SLA_PERIOD_DATE_FORMAT_MONTHLY', _x('Y-m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_FROM', _x('Y-m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_TO', _x('m', DATE_FORMAT_CONTEXT));
define('ZBX_SLA_PERIOD_DATE_FORMAT_ANNUALLY', _x('Y', DATE_FORMAT_CONTEXT));
