/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"

typedef struct
{
	int		source;
	int		object;
	zbx_uint64_t	objectid;
	int		alerts_num;
}
zbx_am_source_stats_t;

int	zbx_alerter_get_diag_stats(zbx_uint64_t *alerts_num, char **error);

int	zbx_alerter_get_top_mediatypes(int limit, zbx_vector_uint64_pair_t *mediatypes, char **error);

int	zbx_alerter_get_top_sources(int limit, zbx_vector_ptr_t *sources, char **error);

#endif
