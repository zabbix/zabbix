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

static int	get_kstat_named_field(const char *name, const char *field, kstat_named_t *returned_data)
{
    int ret = SYSINFO_RET_FAIL;

    kstat_ctl_t	  *kc;
    kstat_t       *kp;
    kstat_named_t *kn;

    kc = kstat_open();
    if (kc)
    {
	kp = kstat_lookup(kc, NULL, -1, (char*) name);
        if ((kp) && (kstat_read(kc, kp, 0) != -1))
	{
	    kn = (kstat_named_t*) kstat_data_lookup(kp, (char*) field);
	    if(kn)
	    {
            	*returned_data = *kn;
            	ret = SYSINFO_RET_OK;
	    }
        }
	kstat_close(kc);
    }
    return ret;
}

static int	NET_IF_IN_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "rbytes64", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui64);
    }
    else if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "rbytes", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui32);
    }

    return ret;
}

static int	NET_IF_IN_PACKETS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ipackets64", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui64);
    }
    else if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ipackets", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui32);
    }

    return ret;
}

static int	NET_IF_IN_ERRORS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ierrors", &kn)) )
	SET_UI64_RESULT(result, kn.value.ui32);

    return ret;
}

static int	NET_IF_OUT_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "obytes64", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui64);
    }
    else if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "obytes", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui32);
    }

    return ret;
}

static int	NET_IF_OUT_PACKETS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "opackets64", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui64);
    }
    else if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "opackets", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui32);
    }

    return ret;
}

static int	NET_IF_OUT_ERRORS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "oerrors", &kn)) )
	SET_UI64_RESULT(result, kn.value.ui32);

    return ret;
}

static int	NET_IF_TOTAL_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t ikn;
    kstat_named_t okn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "rbytes64", &ikn)) &&
	SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "obytes64", &okn)) )
    {
	SET_UI64_RESULT(result, ikn.value.ui64 + okn.value.ui64);
    }
    else if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "rbytes", &ikn)) &&
	SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "obytes", &okn)) )
    {
	SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);
    }

    return ret;
}

static int	NET_IF_TOTAL_PACKETS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t ikn;
    kstat_named_t okn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ipackets64", &ikn)) &&
	SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "opackets64", &okn)) )
    {
	SET_UI64_RESULT(result, ikn.value.ui64 + okn.value.ui64);
    }
    else if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ipackets", &ikn)) &&
	SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "opackets", &okn)) )
    {
	SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);
    }

    return ret;
}

static int	NET_IF_TOTAL_ERRORS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t ikn;
    kstat_named_t okn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

	if ( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "ierrors", &ikn)) &&
		SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "oerrors", &okn)) )
			SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);

    return ret;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_named_t kn;
    char    if_name[MAX_STRING_LEN];
    int	    ret;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if( SYSINFO_RET_OK == (ret = get_kstat_named_field(if_name, "collisions", &kn)) )
    {
	SET_UI64_RESULT(result, kn.value.ui32);
    }

    return ret;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[8], command[64];
	unsigned short	port;
	int		res;

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (FAIL == is_ushort(tmp, &port))
		return SYSINFO_RET_FAIL;

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '*.%hu\\>' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(NULL, command, flags, result)))
		return res;

	if (result->ui64 > 1)
		result->ui64 = 1;

	return res;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes",   NET_IF_IN_BYTES},
		{"packets", NET_IF_IN_PACKETS},
		{"errors",  NET_IF_IN_ERRORS},
		{0,	    0}
	};

	char if_name[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
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
			return (fl[i].function)(cmd, if_name, flags, result);

	return SYSINFO_RET_FAIL;
}
int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes",   NET_IF_OUT_BYTES},
		{"packets", NET_IF_OUT_PACKETS},
		{"errors",  NET_IF_OUT_ERRORS},
		{0,	    0}
	};

	char if_name[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, if_name, sizeof(mode)) != 0)
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
			return (fl[i].function)(cmd, if_name, flags, result);

	return SYSINFO_RET_FAIL;
}
int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes",   NET_IF_TOTAL_BYTES},
		{"packets", NET_IF_TOTAL_PACKETS},
		{"errors",  NET_IF_TOTAL_ERRORS},
		{0,	    0}
	};

	char if_name[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
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
			return (fl[i].function)(cmd, if_name, flags, result);

	return SYSINFO_RET_FAIL;
}
