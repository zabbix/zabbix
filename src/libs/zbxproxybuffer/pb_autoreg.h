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

#ifndef ZABBIX_PB_AUTOREG_H
#define ZABBIX_PB_AUTOREG_H

#include "zbxproxybuffer.h"
#include "proxybuffer.h"

void	pb_list_free_autoreg(zbx_list_t *list, zbx_pb_autoreg_t *row);
size_t	pb_autoreg_estimate_row_size(const char *host, const char *host_metadata, const char *ip, const char *dns);
void	pb_autoreg_clear(zbx_pb_t *pb, zbx_uint64_t lastid);
void	pb_autoreg_flush(zbx_pb_t *pb);
void	pb_autoreg_set_lastid(zbx_uint64_t lastid);
int	pb_autoreg_check_age(zbx_pb_t *pb);
int	pb_autoreg_has_mem_rows(zbx_pb_t *pb);

#endif
