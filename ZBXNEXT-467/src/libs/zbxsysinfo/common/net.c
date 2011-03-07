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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

#include "comms.h"
#include "log.h"
#include "cfg.h"

#include "net.h"

#ifdef _WINDOWS
#include <windns.h>

#include <winsock2.h>  //winsock
#include <stdio.h>    //standard i/o
#pragma comment(lib, "Ws2_32.lib")

#pragma comment(lib, "Dnsapi.lib")
#endif

/*
 * 0 - NOT OK
 * 1 - OK
 * */
int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		const char *expect, const char *sendtoclose, int *value_int)
{
	zbx_sock_t	s;
	char		*buf;
	int		net, val = SUCCEED;

	assert(value_int);

	*value_int = 0;

	if (SUCCEED == (net = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout)))
	{
		if (NULL != request)
		{
			net = zbx_tcp_send_raw(&s, request);
		}

		if (NULL != expect && SUCCEED == net)
		{
			if (SUCCEED == (net = zbx_tcp_recv(&s, &buf)))
			{
				if (0 != strncmp(buf, expect, strlen(expect)))
				{
					val = FAIL;
				}
			}
		}

		if (NULL != sendtoclose && SUCCEED == net && SUCCEED == val)
		{
			zbx_tcp_send_raw(&s, sendtoclose);
		}

		if (SUCCEED == net && SUCCEED == val)
		{
			*value_int = 1;
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == net)
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect network error: %s", zbx_tcp_strerror());

	if (FAIL == val)
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect content error: expected [%s] received [%s]", expect, buf);

	return SYSINFO_RET_OK;
}

int	NET_TCP_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	unsigned short	port;
	int		value_int, ret;
	char		ip[64], port_str[8];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, ip, sizeof(ip)))
		*ip = '\0';

	if ('\0' == *ip)
		strscpy(ip, "127.0.0.1");

	if (0 != get_param(param, 2, port_str, sizeof(port_str)))
		*port_str = '\0';

	if (SUCCEED != is_ushort(port_str, &port))
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK == (ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int)))
	{
		SET_UI64_RESULT(result, value_int);
	}

	return ret;
}

#if defined(HAVE_RES_QUERY) || defined(_WINDOWS)

#if !defined(C_IN) && !defined(_WINDOWS)
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_ANY
#	define T_ANY	255
#endif	/* T_ANY */
#ifndef T_A
#	define T_A	1
#endif	/* T_A */
#ifndef T_NS
#	define T_NS	2
#endif	/* T_NS */
#ifndef T_MD
#	define T_MD	3
#endif	/* T_MD */
#ifndef T_MF
#	define T_MF	4
#endif	/* T_MF */
#ifndef T_CNAME
#	define T_CNAME	5
#endif	/* T_CNAME */
#ifndef T_SOA
#	define T_SOA	6
#endif	/* T_SOA */
#ifndef T_MB
#	define T_MB	7
#endif	/* T_MB */
#ifndef T_MG
#	define T_MG	8
#endif	/* T_MG */
#ifndef T_MR
#	define T_MR	9
#endif	/* T_MR */
#ifndef T_NULL
#	define T_NULL	10
#endif	/* T_NULL */
#ifndef T_WKS
#	define T_WKS	11
#endif	/* T_WKS */
#ifndef T_PTR
#	define T_PTR	12
#endif	/* T_PTR */
#ifndef T_HINFO
#	define T_HINFO	13
#endif	/* T_HINFO */
#ifndef T_MINFO
#	define T_MINFO	14
#endif	/* T_MINFO */
#ifndef T_MX
#	define T_MX	15
#endif	/* T_MX */
#ifndef T_TXT
#	define T_TXT	16
#endif	/* T_TXT */

static char	*decode_type(int q_type)
{
	static char	buf[16];

	switch (q_type)
	{
		case T_A:	return "A";	/* "address"; */
		case T_NS:	return "NS";	/* "name server"; */
		case T_MD:	return "MD";	/* "mail forwarder"; */
		case T_MF:	return "MF";	/* "mail forwarder"; */
		case T_CNAME:	return "CNAME";	/* "canonical name"; */
		case T_SOA:	return "SOA";	/* "start of authority"; */
		case T_MB:	return "MB";	/* "mailbox"; */
		case T_MG:	return "MG";	/* "mail group member"; */
		case T_MR:	return "MR";	/* "mail rename"; */
		case T_NULL:	return "NULL";	/* "null"; */
		case T_WKS:	return "WKS";	/* "well-known service"; */
		case T_PTR:	return "PTR";	/* "domain name pointer"; */
		case T_HINFO:	return "HINFO";	/* "host information"; */
		case T_MINFO:	return "MINFO";	/* "mailbox information"; */
		case T_MX:	return "MX";	/* "mail exchanger"; */
		case T_TXT:	return "TXT";	/* "text"; */
		default:
			zbx_snprintf(buf, sizeof(buf), "T_%d", q_type);
			return buf;
	}
}

#ifndef _WINDOWS
static char	*get_name(unsigned char *msg, unsigned char *msg_end, unsigned char **msg_ptr)
{
	int		res;
	static char	buffer[MAX_STRING_LEN];

	if ((res = dn_expand(msg, msg_end, *msg_ptr, buffer, sizeof(buffer))) < 0)
		return NULL;

	*msg_ptr += res;

	return buffer;
}
#endif /* not _WINDOWS*/

#endif /* defined(HAVE_RES_QUERY) || defined(_WINDOWS) */


int	NET_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return dns_query(cmd, param, flags, result, 1);
}
int	NET_DNS_RECORD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return dns_query(cmd, param, flags, result, 0);
}

int	dns_query(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result, int shortAnswer)
{
#if defined(HAVE_RES_QUERY) || defined(_WINDOWS)

	int		res, type, retrans, retry, i, offset = 0;
	char		ip[MAX_STRING_LEN];
	char		zone[MAX_STRING_LEN];
	char		retransStr[MAX_STRING_LEN];
	char		retryStr[MAX_STRING_LEN];
	char		tmp[MAX_STRING_LEN];
	char		buffer[MAX_STRING_LEN];

	typedef struct resolv_querytype_s {
		char	*name;
		int	type;
	} resolv_querytype_t;
	static resolv_querytype_t qt[] = {
		{"ANY", T_ANY},
		{"A", T_A},
		{"NS", T_NS},
		{"MD", T_MD},
		{"MF", T_MF},
		{"CNAME", T_CNAME},
		{"SOA", T_SOA},
		{"MB", T_MB},
		{"MG", T_MG},
		{"MR", T_MR},
		{"NULL", T_NULL},
#ifndef _WINDOWS /* TODO: add T_WKS support for Windows*/
		{"WKS", T_WKS},
#endif
		{"PTR", T_PTR},
		{"HINFO", T_HINFO},
		{"MINFO", T_MINFO},
		{"MX", T_MX},
		{"TXT", T_TXT},
		{NULL}
	};

#ifdef _WINDOWS
	PDNS_RECORD	pDnsRecord;
	PIP4_ARRAY	pSrvList = NULL;
#else /* not _WINDOWS */
	char		*name;
	unsigned char	*msg_end, *msg_ptr, *p;
	int		num_answers, num_query, q_type, q_class, q_ttl, q_len, value, c, n;
	struct servent	*s;
	HEADER 		*hp;
	struct in_addr	inaddr, asddd;
	struct protoent	*pr;
#if PACKETSZ > 1024
	char 		buf[PACKETSZ];
#else
	char 		buf[1024];
#endif

	typedef union {
		HEADER		h;
#if defined(NS_PACKETSZ)
		unsigned char	buffer[NS_PACKETSZ];
#elif defined(PACKETSZ)
		unsigned char	buffer[PACKETSZ];
#else
		unsigned char	buffer[512];
#endif
	} answer_t;
	answer_t	answer;
#endif /* ifdef _WINDOWS */

	*buffer = '\0';

	if (num_param(param) > 5)
		return SYSINFO_RET_FAIL;

	if(0 != get_param(param, 1, ip, MAX_STRING_LEN))
		ip[0] = '\0';

	if(0 != get_param(param, 2, zone, MAX_STRING_LEN) || '\0' == zone[0])
		strscpy(zone, "zabbix.com");

	if (get_param(param, 3, tmp, sizeof(tmp)) != 0 || *tmp == ' ')
		type = T_SOA;
	else
	{
		for (i = 0; qt[i].name != NULL; i++)
		{
#ifdef _WINDOWS
			if (0 == lstrcmpiA(qt[i].name, tmp))
#else
			if (0 == strcasecmp(qt[i].name, tmp))
#endif
			{
				type = qt[i].type;
				break;
			}
		}
		if (qt[i].name == NULL)
			return SYSINFO_RET_FAIL;
	}

	if(0 != get_param(param, 4, retransStr, MAX_STRING_LEN) || '\0' == retransStr[0])
		retrans = 1;
	else
		retrans = atoi(retransStr);

	if(0 != get_param(param, 5, retryStr, MAX_STRING_LEN) || '\0' == retryStr[0])
		retry = 2;
	else
		retry = atoi(retryStr);

#ifdef _WINDOWS
	if('\0' != ip[0])
	{
		pSrvList = (PIP4_ARRAY) zbx_malloc(pSrvList,sizeof(IP4_ARRAY));
		pSrvList->AddrCount = 1;
		pSrvList->AddrArray[0] = inet_addr(ip);
		res = DnsQuery_A( zone, type, DNS_QUERY_BYPASS_CACHE, pSrvList, &pDnsRecord, NULL);
		zbx_free(pSrvList);
	} else
		res = DnsQuery_A( zone, type, DNS_QUERY_STANDARD, NULL, &pDnsRecord, NULL);

	if (1 == shortAnswer)
		SET_UI64_RESULT(result, res == DNS_RCODE_NOERROR ? 1 : 0);
		return SYSINFO_RET_OK;
	if (NULL == pDnsRecord)
		return SYSINFO_RET_FAIL;

	while( NULL != pDnsRecord)
	{
		if (T_ANY != type && type != pDnsRecord->wType)
		{
			pDnsRecord = pDnsRecord->pNext;
			continue;
		}
		if (NULL == pDnsRecord->pName)
			return SYSINFO_RET_FAIL;

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%-20s", pDnsRecord->pName);

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(pDnsRecord->wType));
		switch(pDnsRecord->wType)
		{
			case T_A:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", inet_ntop(AF_INET, &(pDnsRecord->Data.A.IpAddress), tmp, sizeof(tmp)));
				break;
			case T_NS:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.NS.pNameHost);
				break;
			case T_MD:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.MD.pNameHost);
				break;
			case T_MF:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.MF.pNameHost);
				break;
			case T_CNAME:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.CNAME.pNameHost);
				break;
			case T_SOA:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s %s %d %d %d %d %d",
							pDnsRecord->Data.SOA.pNamePrimaryServer,
							pDnsRecord->Data.SOA.pNameAdministrator,
							pDnsRecord->Data.SOA.dwSerialNo,
							pDnsRecord->Data.SOA.dwRefresh,
							pDnsRecord->Data.SOA.dwRetry,
							pDnsRecord->Data.SOA.dwExpire,
							pDnsRecord->Data.SOA.dwDefaultTtl);
				break;
			case T_MB:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.MB.pNameHost);
				break;
			case T_MG:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.MG.pNameHost);
				break;
			case T_MR:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.MR.pNameHost);
				break;
			case T_PTR:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.PTR.pNameHost);
				break;
			case T_HINFO:
				for (i=0; i < (int)(pDnsRecord->Data.HINFO.dwStringCount); i++)
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.HINFO.pStringArray[i]);
				break;
			case T_MINFO:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s %s", pDnsRecord->Data.MINFO.pNameMailbox, pDnsRecord->Data.MINFO.pNameErrorsMailbox);
				break;
			case T_MX:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d %s", pDnsRecord->Data.MX.wPreference, pDnsRecord->Data.MX.pNameExchange);
				break;
			case T_TXT:
				for (i=0; i < (int)(pDnsRecord->Data.TXT.dwStringCount); i++)
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pDnsRecord->Data.TXT.pStringArray[i]);
				break;
			default:
				break;
		}
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");
		pDnsRecord = pDnsRecord->pNext;
	}
#else /* not _WINDOWS*/

	if (!(_res.options & RES_INIT))
		res_init();

	if('\0' != ip[0])
	{
		if (1 != inet_aton(ip, &inaddr))
			return SYSINFO_RET_FAIL;
		_res.nsaddr_list[0].sin_addr = inaddr;
		_res.nsaddr_list[0].sin_family = AF_INET;
		_res.nsaddr_list[0].sin_port = htons(NS_DEFAULTPORT);
		_res.nscount = 1;
	}

	res = res_mkquery(QUERY, zone, C_IN, type, NULL, 0, NULL, buf, sizeof(buf));
	if (res <= 0)
		return SYSINFO_RET_FAIL;

	_res.retrans = retrans;
	_res.retry = retry;
	res = res_send(buf, res, answer.buffer, sizeof(answer.buffer));

	hp = (HEADER *) answer.buffer;
	if (1 == shortAnswer)
	{
		SET_UI64_RESULT(result, (NOERROR != hp->rcode || 0 == ntohs(hp->ancount) || -1 == res) ? 0 : 1);
		return SYSINFO_RET_OK;
	}
	if (NOERROR != hp->rcode || 0 == ntohs(hp->ancount) || -1 == res)
		return SYSINFO_RET_FAIL;

	*buffer = '\0';
	offset = 0;

	msg_end = answer.buffer + res;

	num_answers = ntohs(answer.h.ancount);
	num_query = ntohs(answer.h.qdcount);

	msg_ptr = answer.buffer + HFIXEDSZ;

	/* skipping query records*/
	for (; num_query > 0 && msg_ptr < msg_end; num_query--)
		msg_ptr += dn_skipname(msg_ptr, msg_end) + QFIXEDSZ;

	for (; num_answers > 0 && msg_ptr < msg_end; num_answers--)
	{
		if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
			return SYSINFO_RET_FAIL;

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%-20s", name);

		GETSHORT(q_type, msg_ptr);
		GETSHORT(q_class, msg_ptr);
		GETLONG(q_ttl, msg_ptr);
		GETSHORT(q_len, msg_ptr);

		switch (q_type)
		{
			case T_A:
				switch (q_class)
				{
					case C_IN:
					case C_HS:
						bcopy(msg_ptr, &inaddr, INADDRSZ);
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), inet_ntoa(inaddr));
						break;
					default:
						;
				}
				msg_ptr += q_len;
				break;
			case T_NS:
			case T_CNAME:
			case T_MB:
			case T_MG:
			case T_MR:
			case T_PTR:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), name);
				break;
			case T_MD:
			case T_MF:
			case T_MX:
				GETSHORT(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %d", decode_type(q_type), value);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);
				break;
			case T_SOA:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(q_type));

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);
				break;
			case T_NULL:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s len:%d", decode_type(q_type), q_len);
				msg_ptr += q_len;
				break;
			case T_WKS:
				if (q_len < INT32SZ + 1)
					return SYSINFO_RET_FAIL;

				p = msg_ptr + q_len;

				bcopy(msg_ptr, &inaddr, INADDRSZ);
				msg_ptr += INT32SZ;

				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), inet_ntoa(inaddr));

				if (NULL != (pr = getprotobynumber(*msg_ptr)))
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pr->p_name);
				else
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", (int)*msg_ptr);

				msg_ptr++;
				n = 0;

				while (msg_ptr < p)
				{
					c = *msg_ptr++;
					do {
						if (c & 0200)
						{
							s = getservbyport((int)htons(n), pr ? pr->p_name : NULL);
							if (s != NULL)
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", s->s_name);
							else
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " #%d", n);
						}
						c <<= 1;
					} while (++n & 07);
				}
				break;
			case T_HINFO:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(q_type));

				p = msg_ptr + q_len;
				c = *msg_ptr++;

				if (c != 0)
				{
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %.*s", c, msg_ptr);
					msg_ptr += c;
				}

				if (msg_ptr < p) {
					c = *msg_ptr++;

					if (c != 0)
					{
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %.*s", c, msg_ptr);
						msg_ptr += c;
					}
				}
				break;
			case T_MINFO:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);
				break;
			case T_TXT:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s \"", decode_type(q_type));

				p = msg_ptr + q_len;
				while (msg_ptr < p)
				{
					for (c = *msg_ptr++; c > 0 && msg_ptr < p; c--)
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%c", *msg_ptr++);
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\"");
				break;
			default:
				msg_ptr += q_len;
		}
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");
	}
#endif

	if (offset != 0)
		buffer[--offset] = '\0';
	SET_TEXT_RESULT(result, strdup(buffer));
	return SYSINFO_RET_OK;
#else /* Both HAVE_RES_QUERY and _WINDOWS not defined */
	return SYSINFO_RET_FAIL;
#endif /* defined(HAVE_RES_QUERY) || defined(_WINDOWS) */
}
