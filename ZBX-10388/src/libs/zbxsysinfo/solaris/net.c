/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "log.h"

static int	get_kstat_named_field(const char *name, const char *field, zbx_uint64_t *field_value, char **error)
{
	int		ret = FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn;

	if (NULL == (kc = kstat_open()))
	{
		*error = zbx_dsprintf(*error, "Cannot open kernel statistics facility: %s", zbx_strerror(errno));
		return FAIL;
	}

	for (kp = kc->kc_chain; NULL != kp; kp = kp->ks_next)	/* traverse all kstat chain */
	{
		if (0 != strcmp(name, kp->ks_name))		/* network interface name */
			continue;

		/* Assume that interfaces in our zone have instance 0. If instance > 0 then it belongs to other zone */
		/* and should be monitored by Zabbix agent running in that zone where it will have instance number 0. */
		if (0 != kp->ks_instance)
			continue;

		if (0 == strcmp("net", kp->ks_class))
			break;
	}

	if (NULL == kp)
	{
		*error = zbx_dsprintf(*error, "Cannot look up interface \"%s\" in kernel statistics facility", name);
		goto clean;
	}

	if (-1 == kstat_read(kc, kp, 0))
	{
		*error = zbx_dsprintf(*error, "Cannot read from kernel statistics facility: %s", zbx_strerror(errno));
		goto clean;
	}

	if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(kp, (char *)field)))
	{
		*error = zbx_dsprintf(*error, "Cannot look up data in kernel statistics facility: %s",
				zbx_strerror(errno));
		goto clean;
	}

	*field_value = get_kstat_numeric_value(kn);

	ret = SUCCEED;
clean:
	kstat_close(kc);

	return ret;
}

static int	NET_IF_IN_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "rbytes64", &value, &error) ||
			SUCCEED == get_kstat_named_field(if_name, "rbytes", &value, &error))
	{
		SET_UI64_RESULT(result, value);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_IN_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "ipackets64", &value, &error) ||
			SUCCEED == get_kstat_named_field(if_name, "ipackets", &value, &error))
	{
		SET_UI64_RESULT(result, value);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_IN_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "ierrors", &value, &error))
	{
		SET_UI64_RESULT(result, value);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_OUT_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "obytes64", &value, &error) ||
			SUCCEED == get_kstat_named_field(if_name, "obytes", &value, &error))
	{
		SET_UI64_RESULT(result, value);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_OUT_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "opackets64", &value, &error) ||
			SUCCEED == get_kstat_named_field(if_name, "opackets", &value, &error))
	{
		SET_UI64_RESULT(result, value);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_OUT_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "oerrors", &value, &error))
	{
		SET_UI64_RESULT(result, value);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_TOTAL_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error = NULL;

	if ((SUCCEED == get_kstat_named_field(if_name, "rbytes64", &value_in, &error) &&
			SUCCEED == get_kstat_named_field(if_name, "obytes64", &value_out, &error)) ||
			(SUCCEED == get_kstat_named_field(if_name, "rbytes", &value_in, &error) &&
			SUCCEED == get_kstat_named_field(if_name, "obytes", &value_out, &error)))
	{
		SET_UI64_RESULT(result, value_in + value_out);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_TOTAL_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error = NULL;

	if ((SUCCEED == get_kstat_named_field(if_name, "ipackets64", &value_in, &error) &&
			SUCCEED == get_kstat_named_field(if_name, "opackets64", &value_out, &error)) ||
			(SUCCEED == get_kstat_named_field(if_name, "ipackets", &value_in, &error) &&
			SUCCEED == get_kstat_named_field(if_name, "opackets", &value_out, &error)))
	{
		SET_UI64_RESULT(result, value_in + value_out);
		zbx_free(error);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_TOTAL_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error = NULL;

	if (SUCCEED == get_kstat_named_field(if_name, "ierrors", &value_in, &error) &&
			SUCCEED == get_kstat_named_field(if_name, "oerrors", &value_out, &error))
	{
		SET_UI64_RESULT(result, value_in + value_out);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*if_name, *error = NULL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == get_kstat_named_field(if_name, "collisions", &value, &error))
	{
		SET_UI64_RESULT(result, value);

		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, error);

	return SYSINFO_RET_FAIL;
}

int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*port_str, command[64];
	unsigned short	port;
	int		res;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an -P tcp | grep '\\.%hu[^.].*LISTEN' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(command, result)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*port_str, command[64];
	unsigned short	port;
	int		res;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an -P udp | grep '\\.%hu[^.].*Idle' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = EXECUTE_INT(command, result)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		ret = NET_IF_IN_BYTES(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = NET_IF_IN_PACKETS(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = NET_IF_IN_ERRORS(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 ==strcmp(mode, "bytes"))
		ret = NET_IF_OUT_BYTES(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = NET_IF_OUT_PACKETS(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = NET_IF_OUT_ERRORS(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 ==strcmp(mode, "bytes"))
		ret = NET_IF_TOTAL_BYTES(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = NET_IF_TOTAL_PACKETS(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = NET_IF_TOTAL_ERRORS(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct if_nameindex	*ni;
	struct zbx_json		j;
	int			i;

	if (NULL == (ni = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; 0 != ni[i].if_index; i++)
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
