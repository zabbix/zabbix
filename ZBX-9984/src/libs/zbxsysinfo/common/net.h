/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_SYSINFO_COMMON_NET_H
#define ZABBIX_SYSINFO_COMMON_NET_H

#include "sysinfo.h"

#if defined(HAVE_RES_QUERY) || defined(_WINDOWS)

#	if !defined(C_IN) && !defined(_WINDOWS)
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
#	ifndef T_SRV
#		define T_SRV	33
#	endif

#endif /* defined(HAVE_RES_QUERY) || defined(_WINDOWS) */

extern char	*CONFIG_SOURCE_IP;

#define ZBX_TCP_EXPECT_FAIL	-1
#define ZBX_TCP_EXPECT_OK	0
#define ZBX_TCP_EXPECT_IGNORE	1

int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		int(*validate_func)(const char *), const char *sendtoclose, int *value_int);

int	NET_DNS(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_DNS_RECORD(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_TCP_PORT(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_NET_H */
