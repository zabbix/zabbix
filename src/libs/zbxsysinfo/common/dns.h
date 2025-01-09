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

#ifndef ZABBIX_SYSINFO_COMMON_DNS_H
#define ZABBIX_SYSINFO_COMMON_DNS_H

#include "module.h"
#include "config.h"

#if defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__)

#	if !defined(C_IN) && !defined(_WINDOWS) && !defined(__MINGW32__)
#		define C_IN	ns_c_in
#	endif

/* define DNS record types to use common names on all systems, see RFC1035 standard for the types */
#	ifndef T_ANY
#		define T_ANY	255
#	endif
#	ifndef T_A
#		define T_A	1
#	endif
#	ifndef T_NS
#		define T_NS	2
#	endif
#	ifndef T_MD
#		define T_MD	3
#	endif
#	ifndef T_MF
#		define T_MF	4
#	endif
#	ifndef T_CNAME
#		define T_CNAME	5
#	endif
#	ifndef T_SOA
#		define T_SOA	6
#	endif
#	ifndef T_MB
#		define T_MB	7
#	endif
#	ifndef T_MG
#		define T_MG	8
#	endif
#	ifndef T_MR
#		define T_MR	9
#	endif
#	ifndef T_NULL
#		define T_NULL	10
#	endif
#	ifndef T_WKS
#		define T_WKS	11
#	endif
#	ifndef T_PTR
#		define T_PTR	12
#	endif
#	ifndef T_HINFO
#		define T_HINFO	13
#	endif
#	ifndef T_MINFO
#		define T_MINFO	14
#	endif
#	ifndef T_MX
#		define T_MX	15
#	endif
#	ifndef T_TXT
#		define T_TXT	16
#	endif
#	ifndef T_AAAA
#		define T_AAAA	28
#	endif
#	ifndef T_SRV
#		define T_SRV	33
#	endif

#endif /* defined(HAVE_RES_QUERY) || defined(_WINDOWS) || defined(__MINGW32__) */

int	net_dns(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_dns_record(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_dns_perf(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_NET_H */
