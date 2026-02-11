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

#ifndef ZABBIX_RESOLVER_H
#define ZABBIX_RESOLVER_H
#include "config.h"
#ifdef HAVE_ARES_QUERY_CACHE
#include <ares.h>
typedef struct ares_channeldata zbx_channel_t;

void	zbx_ares_library_init(void);
int	zbx_ares_getaddrinfo(const char *ip, const char *service, int ai_flags, int ai_family, int ai_socktype,
		struct ares_addrinfo **ares_ai, char *error, int max_error_len);
int	zbx_ares_getnameinfo(const struct sockaddr *sa, ares_socklen_t salen, char *host, size_t hostlen,
		char *error, int max_error_len);
#endif
#endif
