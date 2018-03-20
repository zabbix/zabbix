/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

/* make sure that __wrap_*() prototypes match unwrapped counterparts */

#include "common.h"
#include "log.h"

void	__wrap___zbx_zabbix_log(int level, const char *fmt, ...)
{
	va_list		args;

	fprintf(stdout, "[ LOG (");

	switch (level)
	{
		case LOG_LEVEL_CRIT:
			fprintf(stdout, "CR");
			break;
		case LOG_LEVEL_ERR:
			fprintf(stdout, "ER");
			break;
		case LOG_LEVEL_WARNING:
			fprintf(stdout, "WR");
			break;
		case LOG_LEVEL_DEBUG:
			fprintf(stdout, "DB");
			break;
		case LOG_LEVEL_TRACE:
			fprintf(stdout, "TR");
			break;
		case LOG_LEVEL_INFORMATION:
			fprintf(stdout, "IN");
			break;
		default:
			fprintf(stdout, "NA");
			break;
	}

	fprintf(stdout, ") ] ");

	va_start(args, fmt);
	vfprintf(stdout, fmt, args);
	va_end(args);

	fprintf(stdout, "\n");
	fflush(stdout);
}
