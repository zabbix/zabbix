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

#include "zbxtests.h"

#define ZBX_LTRIM_CHARS	"\t "
#define ZBX_RTRIM_CHARS	ZBX_LTRIM_CHARS "\r\n"

#define MAX_DBROWS	16

DB_ROW	get_db_data(const char *case_name, const char *data_suite)
{
	DB_ROW		row = NULL;
	int		lineno, found_case = 0, found_data_suite = 0;
	char		line[MAX_STRING_LEN], *tmp1, *tmp2, *values;
	FILE		*file;

	if (NULL != (file = fopen("data/db", "r")))
	{
		for (lineno = 0; NULL != fgets(line, sizeof(line), file); lineno++)
		{
			zbx_ltrim(line, ZBX_LTRIM_CHARS);
			zbx_rtrim(line, ZBX_RTRIM_CHARS);

			if ('#' == *line || '\0' == *line || '-' == *line)
				continue;

			tmp1 = line;

			if (NULL == (tmp2 = strchr(line, '|')))
				continue;

			*tmp2++ = '\0';

			zbx_rtrim(tmp1, ZBX_RTRIM_CHARS);
			zbx_ltrim(tmp2, ZBX_LTRIM_CHARS);

			if (0 == found_case)
			{
				found_case = (0 == strcmp(tmp1, "CASE_NAME") && 0 == strcmp(tmp2, case_name));
				continue;
			}

			if (0 == found_data_suite)
			{
				found_data_suite = (0 == strcmp(tmp1, "DATA_SUITE") && 0 == strcmp(tmp2, data_suite));
				continue;
			}

			if (0 == strcmp(tmp1, "FIELDS"))
				continue;

			if (0 == strcmp(tmp1, "ROW"))
			{
				int	i = 0;
				char 	*value = strtok(tmp2, "|");

				row = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));

				while (NULL != value)
				{
					row[i] = zbx_strdup(NULL, value);
					zbx_rtrim(row[i], ZBX_RTRIM_CHARS);
					value = strtok(NULL, "|");
					i++;
				}

				break;
			}
		}
		/* FIXME close(file); */
	}

	return row;
}
