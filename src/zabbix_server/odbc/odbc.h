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

#ifndef ZABBIX_ZBXODBC_H
#define ZABBIX_ZBXODBC_H

#include "config.h"

#ifdef HAVE_UNIXODBC

typedef struct zbx_odbc_data_source	zbx_odbc_data_source_t;
typedef struct zbx_odbc_query_result	zbx_odbc_query_result_t;

zbx_odbc_data_source_t	*zbx_odbc_connect(const char *dsn, const char *connection, const char *user, const char *pass,
		int timeout, char **error);
zbx_odbc_query_result_t	*zbx_odbc_select(const zbx_odbc_data_source_t *data_source, const char *query, char **error);

int	zbx_odbc_query_result_to_string(zbx_odbc_query_result_t *query_result, char **string, char **error);
int	zbx_odbc_query_result_to_lld_json(zbx_odbc_query_result_t *query_result, char **lld_json, char **error);
int	zbx_odbc_query_result_to_json(zbx_odbc_query_result_t *query_result, char **out_json, char **error);

void	zbx_odbc_query_result_free(zbx_odbc_query_result_t *query_result);
void	zbx_odbc_data_source_free(zbx_odbc_data_source_t *data_source);

#endif	/* HAVE_UNIXODBC */

#endif
