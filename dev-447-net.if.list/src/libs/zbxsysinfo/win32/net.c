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
#include "log.h"

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function KERNEL_MAXFILES!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function NET_IF_OUT!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function NET_IF_TOTAL!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int     NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function NET_TCP_LISTEN!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int     NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function NET_IF_COLLISIONS!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

static char	*get_if_type_string(DWORD type)
{
	switch (type) {
		case IF_TYPE_OTHER:			return "Other";
		case IF_TYPE_ETHERNET_CSMACD:		return "Ethernet";
		case IF_TYPE_ISO88025_TOKENRING:	return "Token Ring";
		case IF_TYPE_PPP:			return "PPP";
		case IF_TYPE_SOFTWARE_LOOPBACK:		return "Software Lookback";
		case IF_TYPE_ATM:			return "ATM";
		case IF_TYPE_IEEE80211:			return "IEEE 802.11 Wireless";
		case IF_TYPE_TUNNEL:			return "Tunnel type encapsulation";
		case IF_TYPE_IEEE1394:			return "IEEE 1394 Firewire";
		default:				return "unknown";
	}
}

static char	*get_if_adminstatus_string(DWORD status)
{
	switch (status) {
		case 0:		return "disabled";
		case 1:		return "enabled";
		default:	return "unknown";
	}
}

int	NET_IF_LIST(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD		dwSize, dwRetVal, i, j;
	char		*buf = NULL;
	int		buf_alloc = 512, buf_offset = 0, ret = SYSINFO_RET_FAIL;
	/* variables used for GetIfTable and GetIfEntry */
	MIB_IFTABLE	*pIfTable = NULL;
	MIB_IFROW	pIfRow;
	/* variables used for GetIpAddrTable */
	MIB_IPADDRTABLE	*pIPAddrTable = NULL;
	IN_ADDR		in_addr;

        assert(result);

        init_result(result);

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
			pIfRow.dwIndex = pIfTable->table[i].dwIndex;
			if (NO_ERROR != (dwRetVal = GetIfEntry(&pIfRow)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "GetIfEntry failed with error: %s",
						strerror_from_system(dwRetVal));
				continue;
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 32,
					"%-25s", get_if_type_string(pIfRow.dwType));

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 16,
					" %-8s", get_if_adminstatus_string(pIfRow.dwAdminStatus));

			for (j = 0; j < pIPAddrTable->dwNumEntries; j++)
				if (pIPAddrTable->table[j].dwIndex == pIfRow.dwIndex)
				{
					in_addr.S_un.S_addr = pIPAddrTable->table[j].dwAddr;
					zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 17,
							" %-15s", inet_ntoa(in_addr));
					break;
				}

			if (j == pIPAddrTable->dwNumEntries)
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 3, " -");

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 2, " ");
			for (j = 0; j < pIfRow.dwDescrLen; j++)
				if ('\0' != pIfRow.bDescr[j])
					zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 4,
							"%c", pIfRow.bDescr[j]);

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, 2, "\n");
		}
	}

	SET_TEXT_RESULT(result, buf);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free(pIfTable);
	zbx_free(pIPAddrTable);

	return ret;
}
