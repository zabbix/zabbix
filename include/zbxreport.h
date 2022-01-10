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
#ifndef ZABBIX_ZBXREPORT_H
#define ZABBIX_ZBXREPORT_H

#include "zbxjson.h"

#define ZBX_REPORT_STATUS_ENABLED	0
#define ZBX_REPORT_STATUS_DISABLED	1

#define ZBX_REPORT_PERIOD_DAY		0
#define ZBX_REPORT_PERIOD_WEEK		0
#define ZBX_REPORT_PERIOD_MONTH		0
#define ZBX_REPORT_PERIOD_YEAR		0

void	zbx_report_test(const struct zbx_json_parse *jp, zbx_uint64_t userid, struct zbx_json *j);

#endif
