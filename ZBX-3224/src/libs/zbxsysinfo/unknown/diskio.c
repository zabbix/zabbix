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

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifndef TODO
	return SYSINFO_RET_FAIL;
#else
	/* !!!TODO!!! */
	char devname[MAX_STRING_LEN];
	char type[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];

        assert(result);

        init_result(result);

        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, type, sizeof(type)) != 0)
        {
                type[0] = '\0';
        }
        if(type[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(type, sizeof(type), "bps");
	}

	if(get_param(param, 3, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }

        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}

	if ( !DISKDEV_COLLECTOR_STARTED(collector) )
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if( 0 == strcmp(type,"ops"))
	{
		if( 0 == strcmp(mode,"avg1"))		SET_DBL_RESULT(result, collector->diskdevices.XXX1);
		else if( 0 == strcmp(mode,"avg5"))	SET_DBL_RESULT(result, collector->diskdevices.XXX5);
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->diskdevices.XXX15);
		else return SYSINFO_RET_FAIL;

	}
	else if( 0 == strcmp(type,"bps"))
	{
		if( 0 == strcmp(mode,"avg1")) 		SET_DBL_RESULT(result, collector->diskdevices.XXX1);
		else if( 0 == strcmp(mode,"avg5")) 	SET_DBL_RESULT(result, collector->diskdevices.XXX5);
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->diskdevices.XXX15);
		else return SYSINFO_RET_FAIL;

	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
#endif /* TODO */
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifndef TODO
	return SYSINFO_RET_FAIL;
#else
	/* !!!TODO!!! */
	char devname[MAX_STRING_LEN];
	char type[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];

        assert(result);

        init_result(result);

        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, type, sizeof(type)) != 0)
        {
                type[0] = '\0';
        }
        if(type[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(type, sizeof(type), "bps");
	}

	if(get_param(param, 3, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }

        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}

	if ( !DISKDEV_COLLECTOR_STARTED(collector) )
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if( 0 == strcmp(type,"ops"))
	{
		if( 0 == strcmp(mode,"avg1"))		SET_DBL_RESULT(result, collector->diskdevices.XXX1);
		else if( 0 == strcmp(mode,"avg5"))	SET_DBL_RESULT(result, collector->diskdevices.XXX5);
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->diskdevices.XXX15);
		else return SYSINFO_RET_FAIL;

	}
	else if( 0 == strcmp(type,"bps"))
	{
		if( 0 == strcmp(mode,"avg1")) 		SET_DBL_RESULT(result, collector->diskdevices.XXX1);
		else if( 0 == strcmp(mode,"avg5")) 	SET_DBL_RESULT(result, collector->diskdevices.XXX5);
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->diskdevices.XXX15);
		else return SYSINFO_RET_FAIL;

	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
#endif /* TODO */
}
