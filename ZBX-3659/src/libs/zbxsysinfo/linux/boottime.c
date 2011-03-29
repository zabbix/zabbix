/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

static int	getPROC2(char *file, char *param, int fieldno, unsigned flags, int type, AGENT_RESULT *result)
{

	FILE	*f;
	char	*res;
	char	buf[MAX_STRING_LEN];
	int	i;
	int	found;
	unsigned long uValue;
	double fValue;

	if (NULL == (f = fopen(file,"r")))
	{
		return SYSINFO_RET_FAIL;
	}

	/* find line */
	found = 0;
	while ( fgets(buf, MAX_STRING_LEN, f) != NULL )
	{
		if (strncmp(buf, "btime", 5) == 0)
		{
			found = 1;
			break;
		}
	}

	zbx_fclose(f);

	if (!found) return SYSINFO_RET_FAIL;

	/* find field */
	res = (char *)strtok(buf, " "); /* btime field1 field2 */
	for(i=1; i<=fieldno; i++)
	{
		res = (char *)strtok(NULL," ");
	}

	if ( res == NULL )
	{
		return SYSINFO_RET_FAIL;
	}

	/* convert field to right type */
	switch (type)
	{
	case AR_UINT64:
		sscanf(res, "%lu", &uValue);
		SET_UI64_RESULT(result, uValue);
		break;
	case AR_DOUBLE:
		sscanf(res, "%lf", &fValue);
		SET_DBL_RESULT(result, fValue);
		break;
	case AR_STRING: default:
		SET_STR_RESULT(result, strdup(buf));
		break;
	}

	return SYSINFO_RET_OK;
}

int	SYSTEM_BOOTTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return getPROC2("/proc/stat", "btime", 1, flags, AR_UINT64, result);
}
