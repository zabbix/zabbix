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

#include "../../include/common.h"
#include "../../include/zbxalgo.h"
#include "../../include/log.h"
#include "../../include/zbxserver.h"
#include "../../include/zbxtasks.h"

/* Mandatory headers needed by cmocka */
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <cmocka.h>

#ifndef ZABBIX_TESTS_H
#define ZABBIX_TESTS_H

#undef strcpy

#define ZBX_TEST_DATA_TYPE_UNKNOWN	0
#define	ZBX_TEST_DATA_TYPE_CASE		1
#define	ZBX_TEST_DATA_TYPE_PARAM_IN	2
#define	ZBX_TEST_DATA_TYPE_PARAM_OUT	3
#define	ZBX_TEST_DATA_TYPE_DB_DATA	4
#define	ZBX_TEST_DATA_TYPE_FUNCTION	5
#define	ZBX_TEST_DATA_TYPE_IN_PARAM	8
#define	ZBX_TEST_DATA_TYPE_IN_VALUE	9
#define	ZBX_TEST_DATA_TYPE_OUT_PARAM	10
#define	ZBX_TEST_DATA_TYPE_OUT_VALUE	11
#define	ZBX_TEST_DATA_TYPE_DB_FIELD	12
#define	ZBX_TEST_DATA_TYPE_DB_ROW	13
#define	ZBX_TEST_DATA_TYPE_FUNC_OUT	14
#define	ZBX_TEST_DATA_TYPE_FUNC_VALUE	15
#define	ZBX_TEST_DATA_TYPE_TESTED_FUNC	16

typedef struct
{
	char	**names;
	char	**values;
	int	data_num;
}
zbx_test_data_t;

typedef struct
{
	char			*name;
	zbx_test_data_t		*rows;
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
	char			*case_name;
	char			*tested_function;
	int			datasource_num;
	int			function_num;
	zbx_test_data_t		in_params;
	zbx_test_data_t		out_params;
	zbx_test_datasource_t	*datasources;
	zbx_test_function_t	*functions;

}
zbx_test_case_t;

zbx_test_case_t		*cases;
int			case_num;

int	load_data(char *file_name);
void	free_data();
void	debug_print_cases();

struct zbx_db_result
{
	char		*data_source;	/* "<table name>_" + "<table name>" + ... */
	char		*case_name;
	char		*sql;
	DB_ROW		*rows;
	int		rows_num;
	int		cur_row_idx;
};

void	test_try_task_closes_problem();
void	test_evaluate_function();
void	test_exception();
void	test_process_escalations();
void	test_successful_tm_get_remote_tasks();

#endif
