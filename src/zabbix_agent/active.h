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

#ifndef ZABBIX_ACTIVE_H
#define ZABBIX_ACTIVE_H

#include "zbxthreads.h"
#include "zbxalgo.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_HOST_METADATA;
extern char	*CONFIG_HOST_METADATA_ITEM;
extern char	*CONFIG_HOST_INTERFACE;
extern char	*CONFIG_HOST_INTERFACE_ITEM;
extern int	CONFIG_REFRESH_ACTIVE_CHECKS;
extern int	CONFIG_BUFFER_SEND;
extern int	CONFIG_BUFFER_SIZE;
extern int	CONFIG_MAX_LINES_PER_SECOND;
extern char	*CONFIG_LISTEN_IP;
extern int	CONFIG_LISTEN_PORT;

extern ZBX_THREAD_LOCAL char	*CONFIG_HOSTNAME;

#define HOST_METADATA_LEN	255	/* UTF-8 characters, not bytes */
#define HOST_INTERFACE_LEN	255	/* UTF-8 characters, not bytes */

typedef struct
{
	zbx_vector_ptr_t	addrs;
	char			*hostname;
}
ZBX_THREAD_ACTIVECHK_ARGS;

typedef struct
{
	char		*host;
	char		*key;
	char		*value;
	unsigned char	state;
	zbx_uint64_t	lastlogsize;
	int		timestamp;
	char		*source;
	int		severity;
	zbx_timespec_t	ts;
	int		logeventid;
	int		mtime;
	unsigned char	flags;
	zbx_uint64_t	id;
}
ZBX_ACTIVE_BUFFER_ELEMENT;

typedef struct
{
	ZBX_ACTIVE_BUFFER_ELEMENT	*data;
	int				count;
	int				pcount;
	int				lastsent;
	int				first_error;
}
ZBX_ACTIVE_BUFFER;

ZBX_THREAD_ENTRY(active_checks_thread, args);

#endif	/* ZABBIX_ACTIVE_H */
