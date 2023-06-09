/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_PROXYDATACACHE_H
#define ZABBIX_PROXYDATACACHE_H

#include "zbxproxydatacache.h"
#include "proxydatacache.h"
#include "zbxmutexs.h"
#include "zbxtime.h"

typedef enum
{
	PDC_DATABASE_ONLY,
	PDC_DATABASE,
	PDC_DATABASE_MEMORY,
	PDC_MEMORY,
	PDC_MEMORY_DATABASE,
}
zbx_pdc_state_t;

extern zbx_pdc_state_t	pdc_dst[];

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	druleid;
	zbx_uint64_t	dcheckid;
	char		*dns;
	char		*ip;
	char		*value;
	int		port;
	int		clock;
	int		status;
}
zbx_pdc_discovery_t;

ZBX_PTR_VECTOR_DECL(pdc_discovery_ptr, zbx_pdc_discovery_t *)

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	itemid;
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;		/* clock + ns */
	const char	*source;
	const char	*value;
	int		timestamp;
	int		severity;
	int		logeventid;
	int		state;
	int		mtime;
	int		flags;
	int		write_clock;
}
zbx_pdc_history_t;

ZBX_PTR_VECTOR_DECL(pdc_history_ptr, zbx_pdc_history_t *)

typedef struct
{
	zbx_uint64_t	id;
	char		*host;
	char		*listen_ip;
	char		*listen_port;
	char		*listen_dns;
	char		*host_metadata;
	int		clock;
	int		flags;
	int		tls_accepted;
}
zbx_pdc_autoreg_t;

ZBX_PTR_VECTOR_DECL(pdc_autoreg_ptr, zbx_pdc_autoreg_t *)

typedef struct
{
	zbx_vector_pdc_history_ptr_t	history;
	zbx_vector_pdc_discovery_ptr_t	discovery;
	zbx_vector_pdc_autoreg_ptr_t	autoreg;

	zbx_pdc_state_t			state;
	int				max_age;

	zbx_mutex_t			mutex;
}
zbx_pdc_t;

extern zbx_pdc_t	*pdc_cache;


#endif
