/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "../include/common.h"
#include "../include/zbxalgo.h"
#include "../include/log.h"
#include "../include/zbxserver.h"
#include "../include/zbxtasks.h"

/* Mandatory headers needed by cmocka */
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <cmocka.h>

#ifndef ZABBIX_TESTS_H
#define ZABBIX_TESTS_H

#undef strcpy

#define ZBX_TEST_DATA_TYPE_UNKNOWN	0
#define	ZBX_TEST_DATA_TYPE_PARAM_IN	1
#define	ZBX_TEST_DATA_TYPE_PARAM_OUT	2
#define	ZBX_TEST_DATA_TYPE_DB_DATA	3
#define	ZBX_TEST_DATA_TYPE_FUNCTION	4
#define	ZBX_TEST_DATA_TYPE_IN_PARAM	5
#define	ZBX_TEST_DATA_TYPE_IN_VALUE	6
#define	ZBX_TEST_DATA_TYPE_OUT_PARAM	7
#define	ZBX_TEST_DATA_TYPE_OUT_VALUE	8
#define	ZBX_TEST_DATA_TYPE_DB_FIELD	9
#define	ZBX_TEST_DATA_TYPE_DB_ROW	10
#define	ZBX_TEST_DATA_TYPE_FUNC_OUT	11
#define	ZBX_TEST_DATA_TYPE_FUNC_VALUE	12

#define	TEST_MAX_CASE_NUM		512
#define	TEST_MAX_DATASOURCE_NUM		1024
#define	TEST_MAX_FUNCTION_NUM		1024
#define	TEST_MAX_ROW_NUM		512
#define	TEST_MAX_DATA_NUM		512

typedef struct
{
	char	**names;
	char	**values;
	int	data_num;
}
zbx_test_data_t;

typedef struct
{
	char	**values;
	int	value_num;
}
zbx_test_row_t;

typedef struct
{
	char			*source_name;
	char			**field_names;
	int			field_num;
	zbx_test_row_t		*rows;
	int			row_num;
}
zbx_test_datasource_t;

typedef struct
{
	char			*name;
	zbx_test_data_t		data;
}
zbx_test_function_t;

typedef struct
{
	int			datasource_num;
	int			function_num;
	zbx_test_data_t		in_params;
	zbx_test_data_t		out_params;
	zbx_test_datasource_t	*datasources;
	zbx_test_function_t	*functions;

}
zbx_test_case_t;

struct zbx_db_result
{
	char		*data_source;	/* "<table name>_" + "<table name>" + ... */
	char		*sql;
	DB_ROW		*rows;
	int		rows_num;
	int		cur_row_idx;
};

#endif
