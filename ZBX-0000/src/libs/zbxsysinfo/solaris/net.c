/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "zbxjson.h"
#include "../common/common.h"

static int	get_kstat_named_field(const char *name, const char *field, kstat_named_t *returned_data)
{
	int		ret = FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn;

	if (NULL != (kc = kstat_open()))
	{
		if (NULL != (kp = kstat_lookup(kc, NULL, -1, (char *)name)) && -1 != kstat_read(kc, kp, 0))
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

	if (SUCCEED == get_kstat_named_field(if_name, "rbytes64", &kn) ||
			SUCCEED == get_kstat_named_field(if_name, "rbytes", &kn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "ipackets64", &kn) ||
			SUCCEED == get_kstat_named_field(if_name, "ipackets", &kn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "ierrors", &kn))
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_BYTES(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "obytes64", &kn) ||
			SUCCEED == get_kstat_named_field(if_name, "obytes", &kn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "opackets64", &kn) ||
			SUCCEED == get_kstat_named_field(if_name, "opackets", &kn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	NET_IF_OUT_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	kstat_named_t	kn;

	if (SUCCEED == get_kstat_named_field(if_name, "oerrors", &kn))
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
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
		SET_UI64_RESULT(result, get_kstat_numeric_value(&ikn) + get_kstat_numeric_value(&okn));
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "rbytes", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "obytes", &okn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&ikn) + get_kstat_numeric_value(&okn));
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
		SET_UI64_RESULT(result, get_kstat_numeric_value(&ikn) + get_kstat_numeric_value(&okn));
	}
	else if (SUCCEED == get_kstat_named_field(if_name, "ipackets", &ikn) &&
			SUCCEED == get_kstat_named_field(if_name, "opackets", &okn))
	{
		SET_UI64_RESULT(result, get_kstat_numeric_value(&ikn) + get_kstat_numeric_value(&okn));
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
		SET_UI64_RESULT(result, get_kstat_numeric_value(&ikn) + get_kstat_numeric_value(&okn));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_named_t	kn;
	char		if_name[MAX_STRING_LEN];

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == get_kstat_named_field(if_name, "collisions", &kn))
		SET_UI64_RESULT(result, get_kstat_numeric_value(&kn));
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[8], command[64];
	unsigned short	port;
	int		res;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (FAIL == is_ushort(tmp, &port))
		return SYSINFO_RET_FAIL;

	zbx_snprintf(command, sizeof(command), "netstat -an -P tcp | grep '\\.%hu[^.].*LISTEN' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(NULL, command, flags, result)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	NET_UDP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[8], command[64];
	unsigned short	port;
	int		res;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (FAIL == is_ushort(tmp, &port))
		return SYSINFO_RET_FAIL;

	zbx_snprintf(command, sizeof(command), "netstat -an -P udp | grep '\\.%hu[^.].*Idle' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(NULL, command, flags, result)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"bytes",   NET_IF_IN_BYTES},
		{"packets", NET_IF_IN_PACKETS},
		{"errors",  NET_IF_IN_ERRORS},
		{NULL,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "bytes");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"bytes",   NET_IF_OUT_BYTES},
		{"packets", NET_IF_OUT_PACKETS},
		{"errors",  NET_IF_OUT_ERRORS},
		{NULL,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "bytes");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"bytes",   NET_IF_TOTAL_BYTES},
		{"packets", NET_IF_TOTAL_PACKETS},
		{"errors",  NET_IF_TOTAL_ERRORS},
		{NULL,	    0}
	};

	char	if_name[MAX_STRING_LEN], mode[16];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "bytes");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct if_nameindex	*ni;
	struct zbx_json		j;
	int			i;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

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
