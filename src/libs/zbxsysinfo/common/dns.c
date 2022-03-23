/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "dns.h"

#include "common.h"
#include "sysinfo.h"
#include "zbxcomms.h"
#include "log.h"
#include "zbxalgo.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#	include <windns.h>
#	pragma comment(lib, "Dnsapi.lib") /* add the library for DnsQuery function */
#endif

#if defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__)

static const char	*decode_type(int q_type)
{
	static ZBX_THREAD_LOCAL char	buf[16];

	switch (q_type)
	{
		case T_A:
			return "A";	/* address */
		case T_AAAA:
			return "AAAA";	/* v6 address */
		case T_NS:
			return "NS";	/* name server */
		case T_MD:
			return "MD";	/* mail destination */		/* obsolete */
		case T_MF:
			return "MF";	/* mail forwarder */		/* obsolete */
		case T_CNAME:
			return "CNAME";	/* canonical name */
		case T_SOA:
			return "SOA";	/* start of authority */
		case T_MB:
			return "MB";	/* mailbox */			/* experimental */
		case T_MG:
			return "MG";	/* mail group member */		/* experimental */
		case T_MR:
			return "MR";	/* mail rename */		/* experimental */
		case T_NULL:
			return "NULL";	/* null */			/* obsolete */
		case T_WKS:
			return "WKS";	/* well-known service */	/* obsolete */
		case T_PTR:
			return "PTR";	/* domain name pointer */
		case T_HINFO:
			return "HINFO";	/* host information */
		case T_MINFO:
			return "MINFO";	/* mailbox information */	/* experimental */
		case T_MX:
			return "MX";	/* mail exchanger */
		case T_TXT:
			return "TXT";	/* text */
		case T_SRV:
			return "SRV";	/* service locator */
		default:
			zbx_snprintf(buf, sizeof(buf), "T_%d", q_type);
			return buf;
	}
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
static char	*get_name(unsigned char *msg, unsigned char *msg_end, unsigned char **msg_ptr)
{
	int		res;
	static char	buffer[MAX_STRING_LEN];

	if (-1 == (res = dn_expand(msg, msg_end, *msg_ptr, buffer, sizeof(buffer))))
		return NULL;

	*msg_ptr += res;

	return buffer;
}
#endif	/* !defined(_WINDOWS) && !defined(__MINGW32__)*/

/* Replace zbx_inet_ntop with inet_ntop in case of drop Windows XP/W2k3 support */
#if defined(_WINDOWS) || defined(__MINGW32__)
const char *zbx_inet_ntop(int af, const void *src, char *dst, size_t size)
{
	struct sockaddr_storage ss;
	unsigned long s = size;

	memset(&ss, '\0', sizeof(ss));
	ss.ss_family = af;

	switch(af)
	{
		case AF_INET:
			((struct sockaddr_in *)&ss)->sin_addr = *(struct in_addr *)src;
			break;
		case AF_INET6:
			((struct sockaddr_in6 *)&ss)->sin6_addr = *(struct in6_addr *)src;
			break;
		default:
			return NULL;
	}

	return (0 == WSAAddressToStringA((struct sockaddr *)&ss, sizeof(ss), NULL, dst, &s))? dst : NULL;
}
#endif
#endif	/* defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__) */

static int	dns_query(AGENT_REQUEST *request, AGENT_RESULT *result, int short_answer)
{
#if defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__)

	size_t			offset = 0;
	int			res, type, retrans, retry, use_tcp, i, ret = SYSINFO_RET_FAIL, ip_type = AF_INET;
	char			*ip, zone[MAX_STRING_LEN], buffer[MAX_STRING_LEN], *zone_str, *param,
				tmp[MAX_STRING_LEN];
	struct in_addr		inaddr;
	struct in6_addr		in6addr;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
#if defined(HAVE_RES_NINIT) && !defined(_AIX)
	/* It seems that on some AIX systems with no updates installed res_ninit() can */
	/* corrupt stack (see ZBX-14559). Use res_init() on AIX. */
	struct __res_state	res_state_local;
#else	/* thread-unsafe resolver API */
	int			saved_retrans, saved_retry, saved_nscount = 0;
	unsigned long		saved_options;
	struct sockaddr_in	saved_ns;
#	if defined(HAVE_RES_U_EXT)		/* thread-unsafe resolver API /Linux/ */
	int			save_nssocks, saved_nscount6;
#	endif
#endif

#if defined(HAVE_RES_EXT_EXT)		/* AIX */
	union res_sockaddr_union	saved_ns6;
#elif defined(HAVE_RES_U_EXT_EXT)	/* BSD */
	struct sockaddr_in6		saved_ns6;
#else
	struct sockaddr_in6		*saved_ns6;
#endif
	struct sockaddr_in6	sockaddrin6;
	struct addrinfo		hint, *hres = NULL;
#endif
	typedef struct
	{
		const char	*name;
		int		type;
	}
	resolv_querytype_t;

	static const resolv_querytype_t	qt[] =
	{
		{"ANY",		T_ANY},
		{"A",		T_A},
		{"AAAA",	T_AAAA},
		{"NS",		T_NS},
		{"MD",		T_MD},
		{"MF",		T_MF},
		{"CNAME",	T_CNAME},
		{"SOA",		T_SOA},
		{"MB",		T_MB},
		{"MG",		T_MG},
		{"MR",		T_MR},
		{"NULL",	T_NULL},
#if !defined(_WINDOWS) && !defined(__MINGW32__)
		{"WKS",		T_WKS},
#endif
		{"PTR",		T_PTR},
		{"HINFO",	T_HINFO},
		{"MINFO",	T_MINFO},
		{"MX",		T_MX},
		{"TXT",		T_TXT},
		{"SRV",		T_SRV},
		{NULL}
	};

#if defined(_WINDOWS) || defined(__MINGW32__)
	PDNS_RECORD	pQueryResults, pDnsRecord;
	wchar_t		*wzone;
	char		tmp2[MAX_STRING_LEN];
	DWORD		options;
#else
	char		*name;
	unsigned char	*msg_end, *msg_ptr, *p;
	int		num_answers, num_query, q_type, q_class, q_len, value, c, n;
	struct servent	*s;
	HEADER		*hp;
	struct protoent	*pr;
#if PACKETSZ > 1024
	unsigned char	buf[PACKETSZ];
#else
	unsigned char	buf[1024];
#endif

	typedef union
	{
		HEADER		h;
#if defined(NS_PACKETSZ)
		unsigned char	buffer[NS_PACKETSZ];
#elif defined(PACKETSZ)
		unsigned char	buffer[PACKETSZ];
#else
		unsigned char	buffer[512];
#endif
	}
	answer_t;

	answer_t	answer;
#endif	/* defined(_WINDOWS) || defined(__MINGW32__) */
	zbx_vector_str_t	answers;

	*buffer = '\0';

	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	ip = get_rparam(request, 0);
	zone_str = get_rparam(request, 1);

#if !defined(_WINDOWS) && !defined(__MINGW32__)
	memset(&hint, '\0', sizeof(hint));
	hint.ai_family = PF_UNSPEC;
	hint.ai_flags = AI_NUMERICHOST;

	if (NULL != ip && '\0' != *ip && 0 == getaddrinfo(ip, NULL, &hint, &hres) && AF_INET6 == hres->ai_family)
		ip_type = hres->ai_family;

	if (NULL != hres)
		freeaddrinfo(hres);
#endif
	if (NULL == zone_str || '\0' == *zone_str)
		strscpy(zone, "zabbix.com");
	else
		strscpy(zone, zone_str);

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param)
		type = T_SOA;
	else
	{
		for (i = 0; NULL != qt[i].name; i++)
		{
			if (0 == strcasecmp(qt[i].name, param))
			{
				type = qt[i].type;
				break;
			}
		}

		if (NULL == qt[i].name)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	param = get_rparam(request, 3);

	if (NULL == param || '\0' == *param)
		retrans = 1;
	else if (SUCCEED != is_uint31(param, &retrans) || 0 == retrans)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	param = get_rparam(request, 4);

	if (NULL == param || '\0' == *param)
		retry = 2;
	else if (SUCCEED != is_uint31(param, &retry) || 0 == retry)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	param = get_rparam(request, 5);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "udp"))
		use_tcp = 0;
	else if (0 == strcmp(param, "tcp"))
		use_tcp = 1;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid sixth parameter."));
		return SYSINFO_RET_FAIL;
	}

#if defined(_WINDOWS) || defined(__MINGW32__)
	options = DNS_QUERY_STANDARD | DNS_QUERY_BYPASS_CACHE;
	if (0 != use_tcp)
		options |= DNS_QUERY_USE_TCP_ONLY;

	wzone = zbx_utf8_to_unicode(zone);
	res = DnsQuery(wzone, type, options, NULL, &pQueryResults, NULL);
	zbx_free(wzone);

	if (1 == short_answer)
	{
		SET_UI64_RESULT(result, DNS_RCODE_NOERROR != res ? 0 : 1);
		ret = SYSINFO_RET_OK;
		goto clean_dns;
	}

	if (DNS_RCODE_NOERROR != res)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot perform DNS query: [%d]", res));
		return SYSINFO_RET_FAIL;
	}

	pDnsRecord = pQueryResults;
	zbx_vector_str_create(&answers);

	while (NULL != pDnsRecord)
	{
		if (DnsSectionAnswer != pDnsRecord->Flags.S.Section)
		{
			pDnsRecord = pDnsRecord->pNext;
			continue;
		}

		if (NULL == pDnsRecord->pName)
			goto clean;

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%-20s",
				zbx_unicode_to_utf8_static(pDnsRecord->pName, tmp, sizeof(tmp)));
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s",
				decode_type(pDnsRecord->wType));

		switch (pDnsRecord->wType)
		{
			case T_A:
				inaddr.s_addr = pDnsRecord->Data.A.IpAddress;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						inet_ntoa(inaddr));
				break;
			case T_AAAA:
				memcpy(&in6addr.s6_addr, &(pDnsRecord->Data.AAAA.Ip6Address), sizeof(in6addr.s6_addr));
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_inet_ntop(AF_INET6, &in6addr, tmp, sizeof(tmp)));
				break;
			case T_NS:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.NS.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_MD:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MD.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_MF:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MF.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_CNAME:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.CNAME.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_SOA:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s %s %lu %lu %lu %lu %lu",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.SOA.pNamePrimaryServer, tmp, sizeof(tmp)),
						zbx_unicode_to_utf8_static(pDnsRecord->Data.SOA.pNameAdministrator, tmp2, sizeof(tmp2)),
						pDnsRecord->Data.SOA.dwSerialNo,
						pDnsRecord->Data.SOA.dwRefresh,
						pDnsRecord->Data.SOA.dwRetry,
						pDnsRecord->Data.SOA.dwExpire,
						pDnsRecord->Data.SOA.dwDefaultTtl);
				break;
			case T_MB:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MB.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_MG:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MG.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_MR:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MR.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_NULL:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " len:%lu",
						pDnsRecord->Data.Null.dwByteCount);
				break;
			case T_PTR:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.PTR.pNameHost, tmp, sizeof(tmp)));
				break;
			case T_HINFO:
				for (i = 0; i < (int)(pDnsRecord->Data.HINFO.dwStringCount); i++)
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " \"%s\"",
							zbx_unicode_to_utf8_static(pDnsRecord->Data.HINFO.pStringArray[i], tmp, sizeof(tmp)));
				break;
			case T_MINFO:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s %s",
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MINFO.pNameMailbox, tmp, sizeof(tmp)),
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MINFO.pNameErrorsMailbox, tmp2, sizeof(tmp2)));
				break;
			case T_MX:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %hu %s",
						pDnsRecord->Data.MX.wPreference,
						zbx_unicode_to_utf8_static(pDnsRecord->Data.MX.pNameExchange, tmp, sizeof(tmp)));
				break;
			case T_TXT:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " \"");

				for (i = 0; i < (int)(pDnsRecord->Data.TXT.dwStringCount); i++)
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s ",
							zbx_unicode_to_utf8_static(pDnsRecord->Data.TXT.pStringArray[i], tmp, sizeof(tmp)));

				if (0 < i)
					offset -= 1;	/* remove the trailing space */

				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\"");

				break;
			case T_SRV:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %hu %hu %hu %s",
						pDnsRecord->Data.SRV.wPriority,
						pDnsRecord->Data.SRV.wWeight,
						pDnsRecord->Data.SRV.wPort,
						zbx_unicode_to_utf8_static(pDnsRecord->Data.SRV.pNameTarget, tmp, sizeof(tmp)));
				break;
			default:
				break;
		}

		zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");

		pDnsRecord = pDnsRecord->pNext;
		zbx_vector_str_append(&answers, zbx_strdup(NULL, buffer));
		offset = 0;
		*buffer = '\0';
	}
#else	/* !defined(_WINDOWS) && !defined(__MINGW32__) */
#if defined(HAVE_RES_NINIT) && !defined(_AIX)
	memset(&res_state_local, 0, sizeof(res_state_local));
	if (-1 == res_ninit(&res_state_local))	/* initialize always, settings might have changed */
#else
	if (-1 == res_init())	/* initialize always, settings might have changed */
#endif
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot initialize DNS subsystem: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

#if defined(HAVE_RES_NINIT) && !defined(_AIX)
	if (-1 == (res = res_nmkquery(&res_state_local, QUERY, zone, C_IN, type, NULL, 0, NULL, buf, sizeof(buf))))
#else
	if (-1 == (res = res_mkquery(QUERY, zone, C_IN, type, NULL, 0, NULL, buf, sizeof(buf))))
#endif
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot create DNS query: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != ip && '\0' != *ip && AF_INET == ip_type)
	{
		if (0 == inet_aton(ip, &inaddr))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid IP address."));
			return SYSINFO_RET_FAIL;
		}

#if defined(HAVE_RES_NINIT) && !defined(_AIX)
		res_state_local.nsaddr_list[0].sin_addr = inaddr;
		res_state_local.nsaddr_list[0].sin_family = AF_INET;
		res_state_local.nsaddr_list[0].sin_port = htons(ZBX_DEFAULT_DNS_PORT);
		res_state_local.nscount = 1;
#else	/* thread-unsafe resolver API */
		memcpy(&saved_ns, &(_res.nsaddr_list[0]), sizeof(struct sockaddr_in));
		saved_nscount = _res.nscount;

		_res.nsaddr_list[0].sin_addr = inaddr;
		_res.nsaddr_list[0].sin_family = AF_INET;
		_res.nsaddr_list[0].sin_port = htons(ZBX_DEFAULT_DNS_PORT);
		_res.nscount = 1;
#endif
	}
	else if (NULL != ip && '\0' != *ip && AF_INET6 == ip_type)
	{
		if (0 == inet_pton(ip_type, ip, &in6addr))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid IPv6 address."));
			return SYSINFO_RET_FAIL;
		}

		memset(&sockaddrin6, '\0', sizeof(sockaddrin6));
#if defined(HAVE_RES_SIN6_LEN)
		sockaddrin6.sin6_len = sizeof(sockaddrin6);
#endif
		sockaddrin6.sin6_family = AF_INET6;
		sockaddrin6.sin6_addr = in6addr;
		sockaddrin6.sin6_port = htons(ZBX_DEFAULT_DNS_PORT);
#if defined(HAVE_RES_NINIT) && !defined(_AIX) && (defined(HAVE_RES_U_EXT) || defined(HAVE_RES_U_EXT_EXT))
		memset(&res_state_local.nsaddr_list[0], '\0', sizeof(res_state_local.nsaddr_list[0]));
#		ifdef HAVE_RES_U_EXT			/* Linux */
		saved_ns6 = res_state_local._u._ext.nsaddrs[0];
		res_state_local._u._ext.nsaddrs[0] = &sockaddrin6;
		res_state_local._u._ext.nssocks[0] = -1;
		res_state_local._u._ext.nscount6 = 1;	/* CentOS */
#		elif HAVE_RES_U_EXT_EXT			/* BSD */
		if (NULL != res_state_local._u._ext.ext)
			memcpy(res_state_local._u._ext.ext, &sockaddrin6, sizeof(sockaddrin6));

		res_state_local.nsaddr_list[0].sin_port = htons(ZBX_DEFAULT_DNS_PORT);
#		endif
		res_state_local.nscount = 1;
#else
		memcpy(&saved_ns, &(_res.nsaddr_list[0]), sizeof(struct sockaddr_in));
		saved_nscount = _res.nscount;
#		if defined(HAVE_RES_U_EXT) || defined(HAVE_RES_U_EXT_EXT) || defined(HAVE_RES_EXT_EXT)
		memset(&_res.nsaddr_list[0], '\0', sizeof(_res.nsaddr_list[0]));
		_res.nscount = 1;
#		endif
#		if defined(HAVE_RES_U_EXT)		/* thread-unsafe resolver API /Linux/ */
		saved_nscount6 = _res._u._ext.nscount6;
		saved_ns6 = _res._u._ext.nsaddrs[0];
		save_nssocks = _res._u._ext.nssocks[0];
		_res._u._ext.nsaddrs[0] = &sockaddrin6;
		_res._u._ext.nssocks[0] = -1;
		_res._u._ext.nscount6 = 1;
#		elif defined(HAVE_RES_U_EXT_EXT)	/* thread-unsafe resolver API /BSD/ */
		memcpy(&saved_ns6, _res._u._ext.ext, sizeof(saved_ns6));
		_res.nsaddr_list[0].sin_port = htons(ZBX_DEFAULT_DNS_PORT);

		if (NULL != _res._u._ext.ext)
			memcpy(_res._u._ext.ext, &sockaddrin6, sizeof(sockaddrin6));
#		elif defined(HAVE_RES_EXT_EXT)		/* thread-unsafe resolver API /AIX/ */
		memcpy(&saved_ns6, &(_res._ext.ext.nsaddrs[0]), sizeof(saved_ns6));
		memcpy(&_res._ext.ext.nsaddrs[0], &sockaddrin6, sizeof(sockaddrin6));
#		endif /* #if defined(HAVE_RES_U_EXT) */
#endif /* #if defined(HAVE_RES_NINIT) && !defined(_AIX) && (defined(HAVE_RES_U_EXT) || defined(HAVE_RES_U_EXT_EXT)) */
	}

#if defined(HAVE_RES_NINIT) && !defined(_AIX) && (defined(HAVE_RES_U_EXT) || defined(HAVE_RES_U_EXT_EXT))
	if (0 != use_tcp)
		res_state_local.options |= RES_USEVC;

	res_state_local.retrans = retrans;
	res_state_local.retry = retry;

	res = res_nsend(&res_state_local, buf, res, answer.buffer, sizeof(answer.buffer));

#	ifdef HAVE_RES_U_EXT	/* Linux */
	if (NULL != ip && '\0' != *ip && AF_INET6 == ip_type)
		res_state_local._u._ext.nsaddrs[0] = saved_ns6;
#	endif
#	ifdef HAVE_RES_NDESTROY
	res_ndestroy(&res_state_local);
#	else
	res_nclose(&res_state_local);
#	endif
#else	/* thread-unsafe resolver API */
	saved_options = _res.options;
	saved_retrans = _res.retrans;
	saved_retry = _res.retry;

	if (0 != use_tcp)
		_res.options |= RES_USEVC;

	_res.retrans = retrans;
	_res.retry = retry;

	res = res_send(buf, res, answer.buffer, sizeof(answer.buffer));

	_res.options = saved_options;
	_res.retrans = saved_retrans;
	_res.retry = saved_retry;

	if (NULL != ip && '\0' != *ip)
	{
		if (AF_INET6 == ip_type)
		{
#			if defined(HAVE_RES_U_EXT)		/* Linux */
			_res._u._ext.nsaddrs[0] = saved_ns6;
			_res._u._ext.nssocks[0] = save_nssocks;
			_res._u._ext.nscount6 = saved_nscount6;
#			elif defined(HAVE_RES_U_EXT_EXT)	/* BSD */
			if (NULL != _res._u._ext.ext)
				memcpy(_res._u._ext.ext, &saved_ns6, sizeof(saved_ns6));
#			elif defined(HAVE_RES_EXT_EXT)		/* AIX */
			memcpy(&_res._ext.ext.nsaddrs[0], &saved_ns6, sizeof(saved_ns6));
#			endif
		}

		memcpy(&(_res.nsaddr_list[0]), &saved_ns, sizeof(struct sockaddr_in));
		_res.nscount = saved_nscount;
	}
#endif
	hp = (HEADER *)answer.buffer;

	if (1 == short_answer)
	{
		SET_UI64_RESULT(result, -1 == res || NOERROR != hp->rcode || 0 == ntohs(hp->ancount) ? 0 : 1);
		return SYSINFO_RET_OK;
	}

	if (-1 == res || NOERROR != hp->rcode || 0 == ntohs(hp->ancount))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot perform DNS query."));
		return SYSINFO_RET_FAIL;
	}

	msg_end = answer.buffer + res;

	num_answers = ntohs(answer.h.ancount);
	num_query = ntohs(answer.h.qdcount);

	msg_ptr = answer.buffer + HFIXEDSZ;
	zbx_vector_str_create(&answers);

	/* skipping query records */
	for (; 0 < num_query && msg_ptr < msg_end; num_query--)
		msg_ptr += dn_skipname(msg_ptr, msg_end) + QFIXEDSZ;

	for (; 0 < num_answers && msg_ptr < msg_end; num_answers--)
	{
		if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%-20s", name);

		GETSHORT(q_type, msg_ptr);
		GETSHORT(q_class, msg_ptr);
		msg_ptr += INT32SZ;		/* skipping TTL */
		GETSHORT(q_len, msg_ptr);
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(q_type));

		switch (q_type)
		{
			case T_A:
				switch (q_class)
				{
					case C_IN:
					case C_HS:
						memcpy(&inaddr, msg_ptr, INADDRSZ);
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
								inet_ntoa(inaddr));
						break;
					default:
						;
				}

				msg_ptr += q_len;

				break;
			case T_AAAA:
				switch (q_class)
				{
					case C_IN:
					case C_HS:
						memcpy(&in6addr, msg_ptr, IN6ADDRSZ);
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s",
								inet_ntop(AF_INET6, &in6addr, tmp, sizeof(tmp)));
						break;
					default:
						;
				}

				msg_ptr += q_len;

				break;
			case T_NS:
			case T_CNAME:
			case T_MB:
			case T_MD:
			case T_MF:
			case T_MG:
			case T_MR:
			case T_PTR:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);
				break;
			case T_MX:
				GETSHORT(value, msg_ptr);	/* preference */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* exchange */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				break;
			case T_SOA:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* source host */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* administrator */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				GETLONG(value, msg_ptr);	/* serial number */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %u", (zbx_uint32_t)value);

				GETLONG(value, msg_ptr);	/* refresh time */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);	/* retry time */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);	/* expire time */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);	/* minimum TTL */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				break;
			case T_NULL:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " len:%d", q_len);
				msg_ptr += q_len;
				break;
			case T_WKS:
				if (INT32SZ + 1 > q_len)
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}

				p = msg_ptr + q_len;

				memcpy(&inaddr, msg_ptr, INADDRSZ);
				msg_ptr += INT32SZ;

				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", inet_ntoa(inaddr));

				if (NULL != (pr = getprotobynumber(*msg_ptr)))
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pr->p_name);
				else
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", (int)*msg_ptr);

				msg_ptr++;
				n = 0;

				while (msg_ptr < p)
				{
					c = *msg_ptr++;

					do
					{
						if (0 != (c & 0200))
						{
							s = getservbyport((int)htons(n), pr ? pr->p_name : NULL);

							if (NULL != s)
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", s->s_name);
							else
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " #%d", n);
						}

						c <<= 1;
					}
					while (0 != (++n & 07));
				}

				break;
			case T_HINFO:
				p = msg_ptr + q_len;
				c = *msg_ptr++;

				if (0 != c)
				{
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " \"%.*s\"", c, msg_ptr);
					msg_ptr += c;
				}

				if (msg_ptr < p)
				{
					c = *msg_ptr++;

					if (0 != c)
					{
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " \"%.*s\"", c, msg_ptr);
						msg_ptr += c;
					}
				}

				break;
			case T_MINFO:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* mailbox responsible for mailing lists */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* mailbox for error messages */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				break;
			case T_TXT:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " \"");
				p = msg_ptr + q_len;

				while (msg_ptr < p)
				{
					for (c = *msg_ptr++; 0 < c && msg_ptr < p; c--)
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%c", *msg_ptr++);
				}

				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\"");

				break;
			case T_SRV:
				GETSHORT(value, msg_ptr);       /* priority */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETSHORT(value, msg_ptr);       /* weight */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETSHORT(value, msg_ptr);       /* port */
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))	/* target */
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot decode DNS response."));
					return SYSINFO_RET_FAIL;
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				break;
			default:
				msg_ptr += q_len;
				break;
		}

		zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");

		zbx_vector_str_append(&answers, zbx_strdup(NULL, buffer));
		offset = 0;
		*buffer = '\0';
	}
#endif	/* defined(_WINDOWS) || defined(__MINGW32__) */

	zbx_vector_str_sort(&answers, ZBX_DEFAULT_STR_COMPARE_FUNC);

	for (i = 0; i < answers.values_num; i++)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s", answers.values[i]);

	if (0 != offset)
		buffer[--offset] = '\0';

	SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	ret = SYSINFO_RET_OK;

clean:
	zbx_vector_str_clear_ext(&answers, zbx_str_free);
	zbx_vector_str_destroy(&answers);
#if defined(_WINDOWS) || defined(__MINGW32__)
clean_dns:
	if (DNS_RCODE_NOERROR == res)
		DnsRecordListFree(pQueryResults, DnsFreeRecordList);
#endif

	return ret;

#else	/* all HAVE_RES_QUERY and _WINDOWS and __MINGW32__not defined */

	return SYSINFO_RET_FAIL;

#endif	/* defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__)*/
}

static int	dns_query_short(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return dns_query(request, result, 1);
}

static int	dns_query_long(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return dns_query(request, result, 0);
}

static int	dns_query_is_tcp(AGENT_REQUEST *request)
{
	char	*param;

	if (NULL != (param = get_rparam(request, 5)) && 0 == strcmp(param, "tcp"))
		return SUCCEED;

	return FAIL;
}

int	NET_DNS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	if (SUCCEED == dns_query_is_tcp(request))
		return zbx_execute_threaded_metric(dns_query_short, request, result);
#endif
	return dns_query_short(request, result);
}

int	NET_DNS_RECORD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	if (SUCCEED == dns_query_is_tcp(request))
		return zbx_execute_threaded_metric(dns_query_long, request, result);
#endif
	return dns_query_long(request, result);
}
