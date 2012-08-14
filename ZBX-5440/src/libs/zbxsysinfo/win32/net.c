/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "zbxjson.h"

/*
 * returns interface statistics by IP address or interface name
 */
static int	get_if_stats(const char *if_name, MIB_IFROW *pIfRow)
{
	DWORD		dwSize, dwRetVal, i, j;
	int		ret = FAIL;
	char		ip[16];
	/* variables used for GetIfTable and GetIfEntry */
	MIB_IFTABLE	*pIfTable = NULL;
	/* variables used for GetIpAddrTable */
	MIB_IPADDRTABLE	*pIPAddrTable = NULL;
	IN_ADDR		in_addr;

	/* Allocate memory for our pointers. */
	dwSize = sizeof(MIB_IPADDRTABLE);
	pIPAddrTable = (MIB_IPADDRTABLE *)zbx_malloc(pIPAddrTable, sizeof(MIB_IPADDRTABLE));

	/* Make an initial call to GetIpAddrTable to get the
	   necessary size into the dwSize variable */
	if (ERROR_INSUFFICIENT_BUFFER == GetIpAddrTable(pIPAddrTable, &dwSize, 0))
		pIPAddrTable = (MIB_IPADDRTABLE *)zbx_realloc(pIPAddrTable, dwSize);

	/* Make a second call to GetIpAddrTable to get the
	   actual data we want */
	if (NO_ERROR != (dwRetVal = GetIpAddrTable(pIPAddrTable, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetIpAddrTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	/* Allocate memory for our pointers. */
	dwSize = sizeof(MIB_IFTABLE);
	pIfTable = (MIB_IFTABLE *)zbx_malloc(pIfTable, dwSize);

	/* Before calling GetIfEntry, we call GetIfTable to make
	   sure there are entries to get and retrieve the interface index.
	   Make an initial call to GetIfTable to get the necessary size into dwSize */
	if (ERROR_INSUFFICIENT_BUFFER == GetIfTable(pIfTable, &dwSize, 0))
		pIfTable = (MIB_IFTABLE *)zbx_realloc(pIfTable, dwSize);

	/* Make a second call to GetIfTable to get the actual data we want. */
	if (NO_ERROR != (dwRetVal = GetIfTable(pIfTable, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetIfTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	for (i = 0; i < pIfTable->dwNumEntries; i++)
	{
		LPTSTR	wdescr;
		LPSTR	utf8_descr;

		pIfRow->dwIndex = pIfTable->table[i].dwIndex;
		if (NO_ERROR != (dwRetVal = GetIfEntry(pIfRow)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "GetIfEntry failed with error: %s",
					strerror_from_system(dwRetVal));
			continue;
		}

		wdescr = zbx_acp_to_unicode(pIfRow->bDescr);
		utf8_descr = zbx_unicode_to_utf8(wdescr);
		if (0 == strcmp(if_name, utf8_descr))
			ret = SUCCEED;
		zbx_free(utf8_descr);
		zbx_free(wdescr);

		if (SUCCEED == ret)
			break;

		for (j = 0; j < pIPAddrTable->dwNumEntries; j++)
		{
			if (pIPAddrTable->table[j].dwIndex == pIfRow->dwIndex)
			{
				in_addr.S_un.S_addr = pIPAddrTable->table[j].dwAddr;
				zbx_snprintf(ip, sizeof(ip), "%s", inet_ntoa(in_addr));
				if (0 == strcmp(if_name, ip))
				{
					ret = SUCCEED;
					break;
				}
			}
		}

		if (SUCCEED == ret)
			break;
	}
clean:
	zbx_free(pIfTable);
	zbx_free(pIPAddrTable);

	return ret;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[32];
	MIB_IFROW	pIfRow;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (FAIL == get_if_stats(if_name, &pIfRow))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
	{
		SET_UI64_RESULT(result, pIfRow.dwInOctets);
	}
	else if (0 == strcmp(mode, "packets"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInUcastPkts + pIfRow.dwInNUcastPkts);
	}
	else if (0 == strcmp(mode, "errors"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInErrors);
	}
	else if (0 == strcmp(mode, "dropped"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInDiscards + pIfRow.dwInUnknownProtos);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[32];
	MIB_IFROW	pIfRow;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (FAIL == get_if_stats(if_name, &pIfRow))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
	{
		SET_UI64_RESULT(result, pIfRow.dwOutOctets);
	}
	else if (0 == strcmp(mode, "packets"))
	{
		SET_UI64_RESULT(result, pIfRow.dwOutUcastPkts + pIfRow.dwOutNUcastPkts);
	}
	else if (0 == strcmp(mode, "errors"))
	{
		SET_UI64_RESULT(result, pIfRow.dwOutErrors);
	}
	else if (0 == strcmp(mode, "dropped"))
	{
		SET_UI64_RESULT(result, pIfRow.dwOutDiscards);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[32];
	MIB_IFROW	pIfRow;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (FAIL == get_if_stats(if_name, &pIfRow))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
	{
		SET_UI64_RESULT(result, pIfRow.dwInOctets + pIfRow.dwOutOctets);
	}
	else if (0 == strcmp(mode, "packets"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInUcastPkts + pIfRow.dwInNUcastPkts +
				pIfRow.dwOutUcastPkts + pIfRow.dwOutNUcastPkts);
	}
	else if (0 == strcmp(mode, "errors"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInErrors + pIfRow.dwOutErrors);
	}
	else if (0 == strcmp(mode, "dropped"))
	{
		SET_UI64_RESULT(result, pIfRow.dwInDiscards + pIfRow.dwInUnknownProtos +
				pIfRow.dwOutDiscards);
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD		dwSize, dwRetVal, i;
	int		ret = SYSINFO_RET_FAIL;
	/* variables used for GetIfTable and GetIfEntry */
	MIB_IFTABLE	*pIfTable = NULL;
	MIB_IFROW	pIfRow;
	struct zbx_json	j;
	LPTSTR		wdescr;
	LPSTR		utf8_descr;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	/* Allocate memory for our pointers. */
	dwSize = sizeof(MIB_IFTABLE);
	pIfTable = (MIB_IFTABLE *)zbx_malloc(pIfTable, dwSize);

	/* Before calling GetIfEntry, we call GetIfTable to make
	   sure there are entries to get and retrieve the interface index.
	   Make an initial call to GetIfTable to get the necessary size into dwSize */
	if (ERROR_INSUFFICIENT_BUFFER == GetIfTable(pIfTable, &dwSize, 0))
		pIfTable = (MIB_IFTABLE *)zbx_realloc(pIfTable, dwSize);

	/* Make a second call to GetIfTable to get the actual data we want. */
	if (NO_ERROR != (dwRetVal = GetIfTable(pIfTable, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetIfTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	for (i = 0; i < pIfTable->dwNumEntries; i++)
	{
		pIfRow.dwIndex = pIfTable->table[i].dwIndex;
		if (NO_ERROR != (dwRetVal = GetIfEntry(&pIfRow)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "GetIfEntry failed with error: %s", strerror_from_system(dwRetVal));
			continue;
		}

		zbx_json_addobject(&j, NULL);

		wdescr = zbx_acp_to_unicode(pIfRow.bDescr);
		utf8_descr = zbx_unicode_to_utf8(wdescr);
		zbx_json_addstring(&j, "{#IFNAME}", utf8_descr, ZBX_JSON_TYPE_STRING);
		zbx_free(utf8_descr);
		zbx_free(wdescr);

		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free(pIfTable);

	return ret;
}

static char	*get_if_type_string(DWORD type)
{
	switch (type)
	{
		case IF_TYPE_OTHER:			return "Other";
		case IF_TYPE_ETHERNET_CSMACD:		return "Ethernet";
		case IF_TYPE_ISO88025_TOKENRING:	return "Token Ring";
		case IF_TYPE_PPP:			return "PPP";
		case IF_TYPE_SOFTWARE_LOOPBACK:		return "Software Loopback";
		case IF_TYPE_ATM:			return "ATM";
		case IF_TYPE_IEEE80211:			return "IEEE 802.11 Wireless";
		case IF_TYPE_TUNNEL:			return "Tunnel type encapsulation";
		case IF_TYPE_IEEE1394:			return "IEEE 1394 Firewire";
		default:				return "unknown";
	}
}

static char	*get_if_adminstatus_string(DWORD status)
{
	switch (status)
	{
		case 0:		return "disabled";
		case 1:		return "enabled";
		default:	return "unknown";
	}
}

int	NET_IF_LIST(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD		dwSize, dwRetVal, i, j;
	char		*buf = NULL;
	size_t		buf_alloc = 512, buf_offset = 0;
	int		ret = SYSINFO_RET_FAIL;
	/* variables used for GetIfTable and GetIfEntry */
	MIB_IFTABLE	*pIfTable = NULL;
	MIB_IFROW	pIfRow;
	/* variables used for GetIpAddrTable */
	MIB_IPADDRTABLE	*pIPAddrTable = NULL;
	IN_ADDR		in_addr;

	/* Allocate memory for our pointers. */
	dwSize = sizeof(MIB_IPADDRTABLE);
	pIPAddrTable = (MIB_IPADDRTABLE *)zbx_malloc(pIPAddrTable, sizeof(MIB_IPADDRTABLE));

	/* Make an initial call to GetIpAddrTable to get the
	   necessary size into the dwSize variable */
	if (ERROR_INSUFFICIENT_BUFFER == GetIpAddrTable(pIPAddrTable, &dwSize, 0))
		pIPAddrTable = (MIB_IPADDRTABLE *)zbx_realloc(pIPAddrTable, dwSize);

	/* Make a second call to GetIpAddrTable to get the
	   actual data we want */
	if (NO_ERROR != (dwRetVal = GetIpAddrTable(pIPAddrTable, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetIpAddrTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	/* Allocate memory for our pointers. */
	dwSize = sizeof(MIB_IFTABLE);
	pIfTable = (MIB_IFTABLE *)zbx_malloc(pIfTable, dwSize);

	/* Before calling GetIfEntry, we call GetIfTable to make
	   sure there are entries to get and retrieve the interface index.
	   Make an initial call to GetIfTable to get the necessary size into dwSize */
	if (ERROR_INSUFFICIENT_BUFFER == GetIfTable(pIfTable, &dwSize, 0))
		pIfTable = (MIB_IFTABLE *)zbx_realloc(pIfTable, dwSize);

	/* Make a second call to GetIfTable to get the actual data we want. */
	if (NO_ERROR != (dwRetVal = GetIfTable(pIfTable, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetIfTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	buf = (char *)zbx_malloc(buf, sizeof(char) * buf_alloc);

	if (pIfTable->dwNumEntries > 0)
	{
		for (i = 0; i < (int)pIfTable->dwNumEntries; i++)
		{
			LPTSTR	wdescr;
			LPSTR	utf8_descr;

			pIfRow.dwIndex = pIfTable->table[i].dwIndex;
			if (NO_ERROR != (dwRetVal = GetIfEntry(&pIfRow)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "GetIfEntry failed with error: %s",
						strerror_from_system(dwRetVal));
				continue;
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
					"%-25s", get_if_type_string(pIfRow.dwType));

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
					" %-8s", get_if_adminstatus_string(pIfRow.dwAdminStatus));

			for (j = 0; j < pIPAddrTable->dwNumEntries; j++)
				if (pIPAddrTable->table[j].dwIndex == pIfRow.dwIndex)
				{
					in_addr.S_un.S_addr = pIPAddrTable->table[j].dwAddr;
					zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
							" %-15s", inet_ntoa(in_addr));
					break;
				}

			if (j == pIPAddrTable->dwNumEntries)
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " -");

			wdescr = zbx_acp_to_unicode(pIfRow.bDescr);
			utf8_descr = zbx_unicode_to_utf8(wdescr);
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s\n", utf8_descr);
			zbx_free(utf8_descr);
			zbx_free(wdescr);
		}
	}

	SET_TEXT_RESULT(result, buf);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free(pIfTable);
	zbx_free(pIPAddrTable);

	return ret;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MIB_TCPTABLE	*pTcpTable = NULL;
	DWORD		dwSize, dwRetVal;
	int		i, ret = SYSINFO_RET_FAIL;
	unsigned short	port;
	char		tmp[8];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != is_ushort(tmp, &port))
		return SYSINFO_RET_FAIL;

	dwSize = sizeof(MIB_TCPTABLE);
	pTcpTable = (MIB_TCPTABLE *)zbx_malloc(pTcpTable, dwSize);

	/* Make an initial call to GetTcpTable to
	   get the necessary size into the dwSize variable */
	if (ERROR_INSUFFICIENT_BUFFER == (dwRetVal = GetTcpTable(pTcpTable, &dwSize, TRUE)))
		pTcpTable = (MIB_TCPTABLE *)zbx_realloc(pTcpTable, dwSize);

	/* Make a second call to GetTcpTable to get
	   the actual data we require */
	if (NO_ERROR == (dwRetVal = GetTcpTable(pTcpTable, &dwSize, TRUE)))
	{
		for (i = 0; i < (int)pTcpTable->dwNumEntries; i++)
		{
			if (MIB_TCP_STATE_LISTEN == pTcpTable->table[i].dwState &&
					port == ntohs((u_short)pTcpTable->table[i].dwLocalPort))
			{
				SET_UI64_RESULT(result, 1);
				break;
			}
		}
		ret = SYSINFO_RET_OK;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetTcpTable failed with error: %s", strerror_from_system(dwRetVal));
		goto clean;
	}

	if (!ISSET_UI64(result))
		SET_UI64_RESULT(result, 0);
clean:
	zbx_free(pTcpTable);

	return ret;
}
