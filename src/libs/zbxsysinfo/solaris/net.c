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

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "../common/zbxsysinfo_common.h"
#include "zbx_sysinfo_kstat.h"

#include "zbxjson.h"
#include "zbxnum.h"

static int	get_kstat_named_field(const char *name, const char *field, zbx_uint64_t *field_value, char **error)
{
	int		ret = FAIL, min_instance = -1;
	kstat_ctl_t	*kc;
	kstat_t		*kp, *min_kp;
	kstat_named_t	*kn;

	if (NULL == (kc = kstat_open()))
	{
		*error = zbx_dsprintf(NULL, "Cannot open kernel statistics facility: %s", zbx_strerror(errno));
		return FAIL;
	}

	for (kp = kc->kc_chain; NULL != kp; kp = kp->ks_next)	/* traverse all kstat chain */
	{
		if (0 != strcmp(name, kp->ks_name))		/* network interface name */
			continue;

		if (0 != strcmp("net", kp->ks_class))
			continue;

		/* find instance with the smallest number */

		if (-1 == min_instance || kp->ks_instance < min_instance)
		{
			min_instance = kp->ks_instance;
			min_kp = kp;
		}

		if (0 == min_instance)
			break;
	}

	if (-1 != min_instance)
		kp = min_kp;

	if (NULL == kp)
	{
		*error = zbx_dsprintf(NULL, "Cannot look up interface \"%s\" in kernel statistics facility", name);
		goto clean;
	}

	if (-1 == kstat_read(kc, kp, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot read from kernel statistics facility: %s", zbx_strerror(errno));
		goto clean;
	}

	if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(kp, (char *)field)))
	{
		*error = zbx_dsprintf(NULL, "Cannot look up data in kernel statistics facility: %s",
				zbx_strerror(errno));
		goto clean;
	}

	*field_value = get_kstat_numeric_value(kn);

	ret = SUCCEED;
clean:
	kstat_close(kc);

	return ret;
}

static int	net_if_in_bytes(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "rbytes64", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if (0 == value && SUCCEED != get_kstat_named_field(if_name, "rbytes", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_in_packets(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "ipackets64", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if (0 == value && SUCCEED != get_kstat_named_field(if_name, "ipackets", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_in_errors(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "ierrors", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_out_bytes(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "obytes64", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if (0 == value && SUCCEED != get_kstat_named_field(if_name, "obytes", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_out_packets(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "opackets64", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if (0 == value && SUCCEED != get_kstat_named_field(if_name, "opackets", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_out_errors(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "oerrors", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	net_if_total_bytes(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "rbytes64", &value_in, &error)
			|| SUCCEED != get_kstat_named_field(if_name, "obytes64", &value_out, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if ((0 == value_in && SUCCEED != get_kstat_named_field(if_name, "rbytes", &value_in, &error)) ||
			(0 == value_out && SUCCEED != get_kstat_named_field(if_name, "obytes", &value_out, &error)))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value_in + value_out);

	return SYSINFO_RET_OK;
}

static int	net_if_total_packets(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "ipackets64", &value_in, &error)
			|| SUCCEED != get_kstat_named_field(if_name, "opackets64", &value_out, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}
	else if ((0 == value_in && SUCCEED != get_kstat_named_field(if_name, "ipackets", &value_in, &error)) ||
			(0 == value_out && SUCCEED != get_kstat_named_field(if_name, "opackets", &value_out, &error)))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value_in + value_out);

	return SYSINFO_RET_OK;
}

static int	net_if_total_errors(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value_in, value_out;
	char		*error;

	if (SUCCEED != get_kstat_named_field(if_name, "ierrors", &value_in, &error) ||
			SUCCEED != get_kstat_named_field(if_name, "oerrors", &value_out, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value_in + value_out);

	return SYSINFO_RET_OK;
}

int	net_if_collisions(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*if_name, *error;

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

	if (SUCCEED != get_kstat_named_field(if_name, "collisions", &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (NULL == port_str || SUCCEED != zbx_is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an -P tcp | grep '\\.%hu[^.].*LISTEN' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = execute_int(command, result, request->timeout)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (NULL == port_str || SUCCEED != zbx_is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an -P udp | grep '\\.%hu[^.].*Idle' | wc -l", port);

	if (SYSINFO_RET_FAIL == (res = execute_int(command, result, request->timeout)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	net_if_in(AGENT_REQUEST *request, AGENT_RESULT *result)
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
		ret = net_if_in_bytes(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = net_if_in_packets(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = net_if_in_errors(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	net_if_out(AGENT_REQUEST *request, AGENT_RESULT *result)
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
		ret = net_if_out_bytes(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = net_if_out_packets(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = net_if_out_errors(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	net_if_total(AGENT_REQUEST *request, AGENT_RESULT *result)
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
		ret = net_if_total_bytes(if_name, result);
	else if (0 == strcmp(mode, "packets"))
		ret = net_if_total_packets(if_name, result);
	else if (0 == strcmp(mode, "errors"))
		ret = net_if_total_errors(if_name, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	net_if_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct if_nameindex	*ni;
	struct zbx_json		j;
	int			i;

	if (NULL == (ni = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

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
