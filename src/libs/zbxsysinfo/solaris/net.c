/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxregexp.h"
#include "zbxcomms.h"
#include "zbxip.h"
#include <sys/socket.h>
#include <kstat.h>
#include <sys/sockio.h>
#include <net/if.h>
#include <sys/stropts.h>
#include <errno.h>
#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <ifaddrs.h>
#include <net/if.h>
#include <net/if_dl.h>

typedef struct
{
	char	*name;
	char	*json_name;
}
kstat_values_table_t;

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

static void	get_carrier(const char* interface, kstat_ctl_t *kc, struct zbx_json *j)
{
	kstat_t		*ksp;
	kstat_named_t	*knp;
	int		i;

	for (ksp = kc->kc_chain; NULL != ksp; ksp = ksp->ks_next)
	{
		if (0 == strcmp(ksp->ks_class, "net") && 0 == strcmp(ksp->ks_name, interface))
		{
			if (-1 == kstat_read(kc, ksp, NULL))
				break;

			if (KSTAT_TYPE_NAMED == ksp->ks_type)
			{
				knp = KSTAT_NAMED_PTR(ksp);
				for (i = 0; i < ksp->ks_ndata; i++, knp++)
				{
					if (0 == strcmp(knp->name, "link_state"))
					{
						int kstat = 0;

						if (KSTAT_DATA_UINT32 == knp->data_type && 1 == knp->value.ui32)
							kstat = 1;
						else if (KSTAT_DATA_INT32 == knp->data_type && 1 == knp->value.i32)
							kstat = 1;

						zbx_json_addint64(j, "carrier", kstat);
						break;
					}
				}
			}
			break;
		}
	}
}

static void	get_if_flags(const char* interface, struct zbx_json *j)
{
	int		sock;
	struct ifreq	ifr;

	sock = socket(AF_INET, SOCK_DGRAM, 0);
	if (0 > sock)
		return;

	memset(&ifr, 0, sizeof(ifr));
	zbx_strlcpy(ifr.ifr_name, interface, IFNAMSIZ);

	if (0 > ioctl(sock, SIOCGIFFLAGS, &ifr))
	{
		close(sock);
		return;
	}

	zbx_json_addstring(j, "administrative_state",
			(0 != (ifr.ifr_flags & IFF_UP)) ? "up" : "down", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "operational_state",
			(0 != (ifr.ifr_flags & IFF_RUNNING)) ? "up" : "down", ZBX_JSON_TYPE_STRING);

	close(sock);
}

static void	get_mac(const char* interface, struct zbx_json *j)
{
	int		sock;
	struct ifreq	ifr;
	char		*mac_addr_str;

	sock = socket(AF_INET, SOCK_DGRAM, 0);
	if (0 > sock)
		return;

	memset(&ifr, 0, sizeof(ifr));
	zbx_strlcpy(ifr.ifr_name, interface, IFNAMSIZ);

	if (0 == ioctl(sock, SIOCGIFHWADDR, &ifr))
	{
		unsigned char	*hwaddr = (unsigned char *)ifr.ifr_addr.sa_data;

		mac_addr_str = zbx_dsprintf(NULL, "%02x:%02x:%02x:%02x:%02x:%02x",
				hwaddr[0], hwaddr[1], hwaddr[2], hwaddr[3], hwaddr[4], hwaddr[5]);

		zbx_json_addstring(j, "mac", mac_addr_str, ZBX_JSON_TYPE_STRING);
		zbx_free(mac_addr_str);
	}
}

static void	get_if_statistics(const char *name, kstat_ctl_t *kc, struct zbx_json *j)
{
	int		min_instance = -1, i;
	kstat_t		*kp, *min_kp;
	kstat_named_t	*kn;

	static kstat_values_table_t	tr_table_in[] =
	{
		{"rbytes64", "bytes"},
		{"ipackets64", "packets"},
		{"ierrors", "errors"}
	};

	static kstat_values_table_t	tr_table_out[] =
	{
		{"obytes64", "bytes"},
		{"opackets64", "packets"},
		{"oerrors", "errors"}
	};

	for (kp = kc->kc_chain; NULL != kp; kp = kp->ks_next)
	{
		if (0 != strcmp(name, kp->ks_name))
			continue;

		if (0 != strcmp("net", kp->ks_class))
			continue;

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

	if (NULL == kp || -1 == kstat_read(kc, kp, 0))
	{
		return;
	}

	zbx_json_addobject(j, "in");

	for (i = 0; i < ARRSIZE(tr_table_in); i++)
	{
		if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(kp, tr_table_in[i].name)))
		{
			continue;
		}
		zbx_json_adduint64(j, tr_table_in[i].json_name, (uint64_t)get_kstat_numeric_value(kn));

	}
	zbx_json_close(j);

	zbx_json_addobject(j, "out");

	for (i = 0; i < ARRSIZE(tr_table_out); i++)
	{
		if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(kp, tr_table_out[i].name)))
		{
			continue;
		}
		zbx_json_adduint64(j, tr_table_out[i].json_name, (uint64_t)get_kstat_numeric_value(kn));

	}
	zbx_json_close(j);
}

int	net_if_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json		jcfg, jval, j;
	zbx_regexp_t		*rxp = NULL;
	char			*error = NULL, *p, *pattern;
	struct if_nameindex	*ni;
	int			i;
	kstat_ctl_t		*kc;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == request->nparam)
	{
		pattern = get_rparam(request, 0);

		if (FAIL == zbx_regexp_compile(pattern, &rxp, &error))
		{
			zbx_free(error);
			return SYSINFO_RET_FAIL;
		}
	}

	if (NULL == (ni = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		if (NULL != rxp)
			zbx_regexp_free(rxp);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (kc = kstat_open()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open kernel statistics facility: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&jval, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&jcfg, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; 0 != ni[i].if_index; i++)
	{
		p = ni[i].if_name;

		if (NULL != rxp && 0 != zbx_regexp_match_precompiled(p, rxp))
			continue;

		zbx_json_addobject(&jcfg, NULL);
		zbx_json_addobject(&jval, NULL);
		zbx_json_addstring(&jcfg, "name", p, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "name", p, ZBX_JSON_TYPE_STRING);
		get_if_flags(p, &jcfg);
		get_mac(p, &jcfg);
		get_mac(p, &jval);
		get_if_statistics(p, kc, &jval);

		get_carrier(p, kc, &jval);
		zbx_json_close(&jval);
		zbx_json_close(&jcfg);
	}

	if_freenameindex(ni);

	if (NULL != rxp)
		zbx_regexp_free(rxp);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addraw(&j, "config", jcfg.buffer);
	zbx_json_addraw(&j, "values", jval.buffer);
	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);
	zbx_json_free(&jval);
	zbx_json_free(&jcfg);
	kstat_close(kc);
	return SYSINFO_RET_OK;
}
