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
#include "zbxjson.h"

static int	get_kstat_named_field(const char *name, const char *field, kstat_named_t *returned_data)
{
	int		ret = FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn;

	if (NULL != (kc = kstat_open()))
	{
		if (NULL != (kp = kstat_lookup(kc, NULL, -1, (char *)name)) &&
				-1 != kstat_read(kc, kp, 0))
		{
			if (NULL != (kn = (kstat_named_t *)kstat_data_lookup(kp, (char *)field)))
			{
				*returned_data = *kn;
				ret = SUCCEED;
			}
		}
		kstat_close(kc);
	}

	return ret;
}

static int	NET_IF_IN_BYTES(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "rbytes64", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "rbytes", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "ipackets64", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "ipackets", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "ierrors", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_BYTES(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "obytes64", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "obytes", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "opackets64", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "opackets", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "oerrors", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_BYTES(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	ikn, okn;

	if (SUCCEED == get_kstat_named_field(if_name, "rbytes64", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "obytes64", &okn))
	{
		SET_UI64_RESULT(result, ikn.value.ui64 + okn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "rbytes", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "obytes", &okn))
	{
		SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	ikn, okn;

	if (SUCCEED == get_kstat_named_field(if_name, "ipackets64", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "opackets64", &okn))
	{
		SET_UI64_RESULT(result, ikn.value.ui64 + okn.value.ui64);
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "ipackets", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "opackets", &okn))
	{
		SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	ikn, okn;

	if (SUCCEED == get_kstat_named_field(if_name, "ierrors", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "oerrors", &okn))
	{
		SET_UI64_RESULT(result, ikn.value.ui32 + okn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_named_t	kn;
	char		if_name[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == get_kstat_named_field(if_name, "collisions", &kn))
	{
		SET_UI64_RESULT(result, kn.value.ui32);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[8], command[64];
	unsigned short	port;
	int		res;

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (FAIL == is_ushort(tmp, &port))
		return SYSINFO_RET_FAIL;

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '*.%d\\>' | wc -l", (int)port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(NULL, command, flags, result)))
		return res;

	if (NULL != GET_DBL_RESULT(result))
		if (result->dbl > 1)
			result->dbl = 1;

	return res;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",   NET_IF_IN_BYTES},
		{"packets", NET_IF_IN_PACKETS},
		{"errors",  NET_IF_IN_ERRORS},
		{0,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; 0 != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",   NET_IF_OUT_BYTES},
		{"packets", NET_IF_OUT_PACKETS},
		{"errors",  NET_IF_OUT_ERRORS},
		{0,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; 0 != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",   NET_IF_TOTAL_BYTES},
		{"packets", NET_IF_TOTAL_PACKETS},
		{"errors",  NET_IF_TOTAL_ERRORS},
		{0,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; 0 != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct if_nameindex	*ni;
	struct zbx_json		j;
	int			i;

	assert(result);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, cmd);

	for (ni = if_nameindex(), i = 0; 0 != ni[i].if_index; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", ni[i].if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	if_freenameindex(ni);

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
