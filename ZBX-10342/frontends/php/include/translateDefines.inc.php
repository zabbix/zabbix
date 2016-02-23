<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

// date formats
define('HISTORY_OF_ACTIONS_DATE_FORMAT', _('d M Y H:i:s'));
define('EVENT_ACTION_MESSAGES_DATE_FORMAT', _('d M Y H:i:s'));
define('EVENT_ACTION_CMDS_DATE_FORMAT', _('Y.M.d H:i:s'));
define('HISTORY_LOG_LOCALTIME_DATE_FORMAT', _('Y.M.d H:i:s'));
define('HISTORY_LOG_ITEM_PLAINTEXT', _('Y-m-d H:i:s'));
define('HISTORY_PLAINTEXT_DATE_FORMAT', _('Y-m-d H:i:s'));
define('HISTORY_ITEM_DATE_FORMAT', _('Y.M.d H:i:s'));
define('EVENTS_DISCOVERY_TIME_FORMAT', _('d M Y H:i:s'));
define('EVENTS_ACTION_TIME_FORMAT', _('d M Y H:i:s'));
define('QUEUE_NODES_DATE_FORMAT', _('d M Y H:i:s'));
define('CHARTBAR_HOURLY_DATE_FORMAT', _('Y.m.d H:i'));
define('CHARTBAR_DAILY_DATE_FORMAT', _('Y.m.d'));
// GETTEXT: Date format (year). Do not translate.
define('REPORT4_ANNUALLY_DATE_FORMAT', _x('Y', 'date format'));
define('REPORT4_MONTHLY_DATE_FORMAT', _('M Y'));
define('REPORT4_DAILY_DATE_FORMAT', _('d M Y'));
define('REPORT4_WEEKLY_DATE_FORMAT', _('d M Y H:i'));
define('FILTER_TIMEBAR_DATE_FORMAT', _('d M Y H:i'));
define('REPORTS_BAR_REPORT_DATE_FORMAT', _('d M Y H:i:s'));
define('POPUP_PERIOD_CAPTION_DATE_FORMAT', _('d M Y H:i:s'));
define('MAPS_DATE_FORMAT', _('Y.m.d H:i:s'));
define('SERVER_INFO_DATE_FORMAT', _('D, d M Y H:i:s O'));
define('XML_DATE_DATE_FORMAT', _('d.m.y'));
define('XML_TIME_DATE_FORMAT', _('H.i'));
