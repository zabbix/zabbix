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

#ifndef ZABBIX_ZBXALERT_H
#define ZABBIX_ZBXALERT_H

#include "db.h"
#include "zbxipcservice.h"

typedef struct
{
	int		source;
	int		object;
	zbx_uint64_t	objectid;
	int		alerts_num;
}
zbx_am_source_stats_t;

typedef struct
{
	char	*recipient;
	char	*info;
	int	status;
}
zbx_alerter_dispatch_result_t;

typedef struct
{
	zbx_ipc_async_socket_t	alerter;
	int			total_num;
	zbx_vector_ptr_t	results;
}
zbx_alerter_dispatch_t;

int	zbx_alerter_get_diag_stats(zbx_uint64_t *alerts_num, char **error);
int	zbx_alerter_get_top_mediatypes(int limit, zbx_vector_uint64_pair_t *mediatypes, char **error);
int	zbx_alerter_get_top_sources(int limit, zbx_vector_ptr_t *sources, char **error);

int	zbx_alerter_begin_dispatch(zbx_alerter_dispatch_t *dispatch, const char *subject, const char *message,
		const char *content_name, const char *content_type, const char *content, zbx_uint32_t content_size,
		char **error);
int	zbx_alerter_send_dispatch(zbx_alerter_dispatch_t *dispatch, const DB_MEDIATYPE *mediatype,
		const zbx_vector_str_t *recipients, char **error);
int	zbx_alerter_end_dispatch(zbx_alerter_dispatch_t *dispatch, char **error);
void	zbx_alerter_dispatch_result_free(zbx_alerter_dispatch_result_t *result);
void	zbx_alerter_clear_dispatch(zbx_alerter_dispatch_t *dispatch);

#endif
