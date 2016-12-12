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
#include "log.h"
#include "zbxjson.h"

/* __stdcall calling convention is used for GetIfEntry2(). In order to declare a */
/* pointer to GetIfEntry2() we have to expand NETIOPAPI_API macro manually since */
/* part of it must be together with the pointer name in the parentheses.         */
typedef NETIO_STATUS (NETIOAPI_API_ *pGetIfEntry2_t)(PMIB_IF_ROW2 Row);

/* GetIfEntry2() is available since Windows Vista and Windows Server 2008. In    */
/* earlier Windows releases this pointer remains set to NULL and GetIfEntry() is */
/* used directly instead.                                                        */
static pGetIfEntry2_t	pGetIfEntry2 = NULL;

/* GetIfEntry2() and GetIfEntry() work with different MIB interface structures.  */
/* Use zbx_ifrow_t variables and zbx_ifrow_*() functions below instead of        */
/* version specific MIB interface API.                                           */
typedef struct
{
	MIB_IFROW	*ifRow;		/* 32-bit counters */
	MIB_IF_ROW2	*ifRow2;	/* 64-bit counters, supported since Windows Vista, Server 2008 */
}
zbx_ifrow_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_ifrow_init                                                   *
 *                                                                            *
 * Purpose: initialize the zbx_ifrow_t variable                               *
 *                                                                            *
 * Parameters:                                                                *
 *     pIfRow      - [IN/OUT] pointer to zbx_ifrow_t variable with all        *
 *                            members set to NULL                             *
 *                                                                            *
 * Comments: allocates memory, call zbx_ifrow_clean() with the same pointer   *
 *           to free it                                                       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ifrow_init(zbx_ifrow_t *pIfRow)
{

	HMODULE		module;
	static	char	check_done = FALSE;

	/* check (once) if GetIfEntry2() is available on this system */
	if (FALSE == check_done)
	{
		if (NULL != (module = GetModuleHandle(L"iphlpapi.dll")))
		{
			if (NULL == (pGetIfEntry2 = (pGetIfEntry2_t)GetProcAddress(module, "GetIfEntry2")))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "GetProcAddress failed with error: %s",
						strerror_from_system(GetLastError()));
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "GetModuleHandle failed with error: %s",
					strerror_from_system(GetLastError()));
		}

		check_done = TRUE;
	}

	/* allocate the relevant MIB interface structure */
	if (NULL != pGetIfEntry2)
		pIfRow->ifRow2 = zbx_malloc(pIfRow->ifRow2, sizeof(MIB_IF_ROW2));
	else
		pIfRow->ifRow = zbx_malloc(pIfRow->ifRow, sizeof(MIB_IFROW));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ifrow_clean                                                  *
 *                                                                            *
 * Purpose: clean the zbx_ifrow_t variable                                    *
 *                                                                            *
 * Parameters:                                                                *
 *     pIfRow      - [IN/OUT] pointer to initialized zbx_ifrow_t variable     *
 *                                                                            *
 * Comments: sets the members to NULL so the variable can be reused           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ifrow_clean(zbx_ifrow_t *pIfRow)
{
	zbx_free(pIfRow->ifRow);
	zbx_free(pIfRow->ifRow2);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ifrow_call_get_if_entry                                      *
 *                                                                            *
 * Purpose: call either GetIfEntry() or GetIfEntry2() based on the Windows    *
 *          release to fill the passed MIB interface structure.               *
 *                                                                            *
 * Parameters:                                                                *
 *     pIfRow      - [IN/OUT] pointer to initialized zbx_ifrow_t variable     *
 *                                                                            *
 * Comments: the index of the interface must be set with                      *
 *           zbx_ifrow_set_index(), otherwise this function will return error *
 *                                                                            *
 ******************************************************************************/
static DWORD	zbx_ifrow_call_get_if_entry(zbx_ifrow_t *pIfRow)
{
	/* on success both functions return 0 (NO_ERROR and STATUS_SUCCESS) */
	if (NULL != pIfRow->ifRow2)
		return pGetIfEntry2(pIfRow->ifRow2);
	else
		return GetIfEntry(pIfRow->ifRow);
}

/******************************************************************************
 *                                                                            *
 * Generic accessor functions for the release specific MIB interface          *
 * structure members. The return value type determined by the context in      *
 * which the functions are called.                                            *
 *                                                                            *
 ******************************************************************************/
static DWORD	zbx_ifrow_get_index(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InterfaceIndex;
	else
		return pIfRow->ifRow->dwIndex;
}

static void	zbx_ifrow_set_index(zbx_ifrow_t *pIfRow, DWORD index)
{
	if (NULL != pIfRow->ifRow2)
	{
		pIfRow->ifRow2->InterfaceLuid.Value = 0;
		pIfRow->ifRow2->InterfaceIndex = index;
	}
	else
		pIfRow->ifRow->dwIndex = index;
}

static DWORD	zbx_ifrow_get_type(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->Type;
	else
		return pIfRow->ifRow->dwType;
}

static DWORD	zbx_ifrow_get_admin_status(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->AdminStatus;
	else
		return pIfRow->ifRow->dwAdminStatus;
}

static ULONG64	zbx_ifrow_get_in_octets(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InOctets;
	else
		return pIfRow->ifRow->dwInOctets;
}

static ULONG64	zbx_ifrow_get_in_ucast_pkts(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InUcastPkts;
	else
		return pIfRow->ifRow->dwInUcastPkts;
}

static ULONG64	zbx_ifrow_get_in_nucast_pkts(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InNUcastPkts;
	else
		return pIfRow->ifRow->dwInNUcastPkts;
}

static ULONG64	zbx_ifrow_get_in_errors(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InErrors;
	else
		return pIfRow->ifRow->dwInErrors;
}

static ULONG64	zbx_ifrow_get_in_discards(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InDiscards;
	else
		return pIfRow->ifRow->dwInDiscards;
}

static ULONG64	zbx_ifrow_get_in_unknown_protos(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->InUnknownProtos;
	else
		return pIfRow->ifRow->dwInUnknownProtos;
}

static ULONG64	zbx_ifrow_get_out_octets(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->OutOctets;
	else
		return pIfRow->ifRow->dwOutOctets;
}

static ULONG64	zbx_ifrow_get_out_ucast_pkts(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->OutUcastPkts;
	else
		return pIfRow->ifRow->dwOutUcastPkts;
}

static ULONG64	zbx_ifrow_get_out_nucast_pkts(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->OutNUcastPkts;
	else
		return pIfRow->ifRow->dwOutNUcastPkts;
}

static ULONG64	zbx_ifrow_get_out_errors(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->OutErrors;
	else
		return pIfRow->ifRow->dwOutErrors;
}

static ULONG64	zbx_ifrow_get_out_discards(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return pIfRow->ifRow2->OutDiscards;
	else
		return pIfRow->ifRow->dwOutDiscards;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ifrow_get_utf8_description                                   *
 *                                                                            *
 * Purpose: returns interface description encoded in UTF-8 format             *
 *                                                                            *
 * Parameters:                                                                *
 *     pIfRow      - [IN] pointer to initialized zbx_ifrow_t variable         *
 *                                                                            *
 * Comments: returns pointer do dynamically-allocated memory, caller must     *
 *           free it                                                          *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_ifrow_get_utf8_description(const zbx_ifrow_t *pIfRow)
{
	if (NULL != pIfRow->ifRow2)
		return zbx_unicode_to_utf8(pIfRow->ifRow2->Description);
	else
	{
		static wchar_t *(*mb_to_unicode)(const char *) = NULL;
		wchar_t 	*wdescr;
		char		*utf8_descr;

		if (NULL == mb_to_unicode)
		{
			const OSVERSIONINFOEX	*vi;

			/* starting with Windows Vista (Windows Server 2008) the interface description */
			/* is encoded in OEM codepage while earlier versions used ANSI codepage */
			if (NULL != (vi = zbx_win_getversion()) && 6 <= vi->dwMajorVersion)
				mb_to_unicode = zbx_oemcp_to_unicode;
			else
				mb_to_unicode = zbx_acp_to_unicode;
		}
		wdescr = mb_to_unicode(pIfRow->ifRow->bDescr);
		utf8_descr = zbx_unicode_to_utf8(wdescr);
		zbx_free(wdescr);

		return utf8_descr;
	}
}

/*
 * returns interface statistics by IP address or interface name
 */
static int	get_if_stats(const char *if_name, zbx_ifrow_t *ifrow)
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
		char	*utf8_descr;

		zbx_ifrow_set_index(ifrow, pIfTable->table[i].dwIndex);
		if (NO_ERROR != (dwRetVal = zbx_ifrow_call_get_if_entry(ifrow)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "zbx_ifrow_call_get_if_entry failed with error: %s",
					strerror_from_system(dwRetVal));
			continue;
		}

		utf8_descr = zbx_ifrow_get_utf8_description(ifrow);
		if (0 == strcmp(if_name, utf8_descr))
			ret = SUCCEED;
		zbx_free(utf8_descr);

		if (SUCCEED == ret)
			break;

		for (j = 0; j < pIPAddrTable->dwNumEntries; j++)
		{
			if (pIPAddrTable->table[j].dwIndex == zbx_ifrow_get_index(ifrow))
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

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_ifrow_t	ifrow = {NULL, NULL};
	int		ret = SYSINFO_RET_FAIL;

	zbx_ifrow_init(&ifrow);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto clean;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto clean;
	}

	if (FAIL == get_if_stats(if_name, &ifrow))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		goto clean;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, zbx_ifrow_get_in_octets(&ifrow));
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_ucast_pkts(&ifrow) + zbx_ifrow_get_in_nucast_pkts(&ifrow));
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_errors(&ifrow));
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_discards(&ifrow) + zbx_ifrow_get_in_unknown_protos(&ifrow));
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto clean;
	}

	ret = SYSINFO_RET_OK;
clean:
	zbx_ifrow_clean(&ifrow);

	return ret;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_ifrow_t	ifrow = {NULL, NULL};
	int		ret = SYSINFO_RET_FAIL;

	zbx_ifrow_init(&ifrow);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto clean;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto clean;
	}

	if (FAIL == get_if_stats(if_name, &ifrow))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		goto clean;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, zbx_ifrow_get_out_octets(&ifrow));
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, zbx_ifrow_get_out_ucast_pkts(&ifrow) + zbx_ifrow_get_out_nucast_pkts(&ifrow));
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, zbx_ifrow_get_out_errors(&ifrow));
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, zbx_ifrow_get_out_discards(&ifrow));
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto clean;
	}

	ret = SYSINFO_RET_OK;
clean:
	zbx_ifrow_clean(&ifrow);

	return ret;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_ifrow_t	ifrow = {NULL, NULL};
	int		ret = SYSINFO_RET_FAIL;

	zbx_ifrow_init(&ifrow);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto clean;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == if_name || '\0' == *if_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto clean;
	}

	if (FAIL == get_if_stats(if_name, &ifrow))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		goto clean;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, zbx_ifrow_get_in_octets(&ifrow) + zbx_ifrow_get_out_octets(&ifrow));
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_ucast_pkts(&ifrow) + zbx_ifrow_get_in_nucast_pkts(&ifrow) +
				zbx_ifrow_get_out_ucast_pkts(&ifrow) + zbx_ifrow_get_out_nucast_pkts(&ifrow));
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_errors(&ifrow) + zbx_ifrow_get_out_errors(&ifrow));
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, zbx_ifrow_get_in_discards(&ifrow) + zbx_ifrow_get_in_unknown_protos(&ifrow) +
				zbx_ifrow_get_out_discards(&ifrow));
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto clean;
	}

	ret = SYSINFO_RET_OK;
clean:
	zbx_ifrow_clean(&ifrow);

	return ret;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DWORD		dwSize, dwRetVal, i;
	int		ret = SYSINFO_RET_FAIL;

	/* variables used for GetIfTable and GetIfEntry */
	MIB_IFTABLE	*pIfTable = NULL;
	zbx_ifrow_t	ifrow = {NULL, NULL};

	struct zbx_json	j;
	char 		*utf8_descr;

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
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s",
				strerror_from_system(dwRetVal)));
		goto clean;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	zbx_ifrow_init(&ifrow);

	for (i = 0; i < pIfTable->dwNumEntries; i++)
	{
		zbx_ifrow_set_index(&ifrow, pIfTable->table[i].dwIndex);
		if (NO_ERROR != (dwRetVal = zbx_ifrow_call_get_if_entry(&ifrow)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "zbx_ifrow_call_get_if_entry failed with error: %s",
					strerror_from_system(dwRetVal));
			continue;
		}

		zbx_json_addobject(&j, NULL);

		utf8_descr = zbx_ifrow_get_utf8_description(&ifrow);
		zbx_json_addstring(&j, "{#IFNAME}", utf8_descr, ZBX_JSON_TYPE_STRING);
		zbx_free(utf8_descr);

		zbx_json_close(&j);
	}

	zbx_ifrow_clean(&ifrow);

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

int	NET_IF_LIST(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DWORD		dwSize, dwRetVal, i, j;
	char		*buf = NULL;
	size_t		buf_alloc = 512, buf_offset = 0;
	int		ret = SYSINFO_RET_FAIL;
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
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain IP address information: %s",
				strerror_from_system(dwRetVal)));
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
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain network interface information: %s",
				strerror_from_system(dwRetVal)));
		goto clean;
	}

	buf = (char *)zbx_malloc(buf, sizeof(char) * buf_alloc);

	if (pIfTable->dwNumEntries > 0)
	{
		zbx_ifrow_t	ifrow = {NULL, NULL};

		zbx_ifrow_init(&ifrow);

		for (i = 0; i < (int)pIfTable->dwNumEntries; i++)
		{
			char		*utf8_descr;

			zbx_ifrow_set_index(&ifrow, pIfTable->table[i].dwIndex);
			if (NO_ERROR != (dwRetVal = zbx_ifrow_call_get_if_entry(&ifrow)))
			{
				zabbix_log(LOG_LEVEL_ERR, "zbx_ifrow_call_get_if_entry failed with error: %s",
						strerror_from_system(dwRetVal));
				continue;
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
					"%-25s", get_if_type_string(zbx_ifrow_get_type(&ifrow)));

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
					" %-8s", get_if_adminstatus_string(zbx_ifrow_get_admin_status(&ifrow)));

			for (j = 0; j < pIPAddrTable->dwNumEntries; j++)
				if (pIPAddrTable->table[j].dwIndex == zbx_ifrow_get_index(&ifrow))
				{
					in_addr.S_un.S_addr = pIPAddrTable->table[j].dwAddr;
					zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
							" %-15s", inet_ntoa(in_addr));
					break;
				}

			if (j == pIPAddrTable->dwNumEntries)
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " -");

			utf8_descr = zbx_ifrow_get_utf8_description(&ifrow);
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s\n", utf8_descr);
			zbx_free(utf8_descr);
		}

		zbx_ifrow_clean(&ifrow);
	}

	SET_TEXT_RESULT(result, buf);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free(pIfTable);
	zbx_free(pIPAddrTable);

	return ret;
}

int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	MIB_TCPTABLE	*pTcpTable = NULL;
	DWORD		dwSize, dwRetVal;
	int		i, ret = SYSINFO_RET_FAIL;
	unsigned short	port;
	char		*port_str;

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
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s",
				strerror_from_system(dwRetVal)));
		goto clean;
	}

	if (!ISSET_UI64(result))
		SET_UI64_RESULT(result, 0);
clean:
	zbx_free(pTcpTable);

	return ret;
}
