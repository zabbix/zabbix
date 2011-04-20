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

static int	get_kstat_io(const char *name, kstat_io_t *returned_data)
{
	int result = SYSINFO_RET_FAIL;
	kstat_ctl_t *kc;
	kstat_t *kt;

	kc = kstat_open();
	if (kc)
	{
		kt = kstat_lookup(kc, NULL, -1, (char *) name);
		if (kt)
		{
			if (kt->ks_type == KSTAT_TYPE_IO)
			{
				if(kstat_read(kc, kt, returned_data) != -1)
				{
					result = SYSINFO_RET_OK;
				}
			}
		}
		kstat_close(kc);
	}
	return result;
}

static int	VFS_DEV_READ_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_io_t	kio;
	int		ret;

	ret = get_kstat_io(param, &kio);

	if(ret == SYSINFO_RET_OK)
	{
		/* u_longlong_t nread;	number of bytes read */
		SET_UI64_RESULT(result, kio.nread);
	}

	return ret;
}

static int	VFS_DEV_READ_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_io_t	kio;
	int		ret;

	ret = get_kstat_io(param, &kio);

	if(ret == SYSINFO_RET_OK)
	{
		/* uint_t reads;    number of read operations */
		SET_UI64_RESULT(result, kio.reads);
	}

	return ret;
}

static int	VFS_DEV_WRITE_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_io_t	kio;
	int		ret;

	ret = get_kstat_io(param, &kio);

	if(ret == SYSINFO_RET_OK)
	{
		/* u_longlong_t nwritten;   number of bytes written */
		SET_UI64_RESULT(result, kio.nwritten);
	}

	return ret;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_io_t	kio;
	int		ret;

	ret = get_kstat_io(param, &kio);

	if(ret == SYSINFO_RET_OK)
	{
		/* uint_t   writes;    number of write operations */
		SET_UI64_RESULT(result, kio.writes);
	}

	return ret;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes", 	VFS_DEV_WRITE_BYTES},
		{"operations", 	VFS_DEV_WRITE_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(mode)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}

	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, devname, flags, result);

	return SYSINFO_RET_FAIL;
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes",	VFS_DEV_READ_BYTES},
		{"operations",	VFS_DEV_READ_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}
	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, devname, flags, result);

	return SYSINFO_RET_FAIL;
}
