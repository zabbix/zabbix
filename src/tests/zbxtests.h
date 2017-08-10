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

/* Mandatory headers needed by cmocka */
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <cmocka.h>

#ifndef ZABBIX_TESTS_H
#define ZABBIX_TESTS_H

void	test_successful_evaluate_function();
void	test_exception();
void	test_successful_process_escalations();

DB_ROW	get_db_data(const char *case_name, const char *data_suite);

#endif

