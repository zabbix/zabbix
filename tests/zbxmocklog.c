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

#include "zbxmocktest.h"

#include "zbxcommon.h"

void	zbx_mock_log_impl(int level, const char *fmt, va_list args)
{
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

	vfprintf(stdout, fmt, args);

	fprintf(stdout, "\n");
	fflush(stdout);
}
