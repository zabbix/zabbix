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

#ifndef ZABBIX_MOCK_TEST_H
#define ZABBIX_MOCK_TEST_H

/* Mandatory headers needed by cmocka */
#include <stddef.h>
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <cmocka.h>

/* hint to a compiler that cmocka _fail returns immediately, so it does not raise 'uninitialized variable' warnings */
#if defined(__GNUC__) || defined(__clang__)
#	define ZBX_NO_RETURN	__attribute__((noreturn))
#else
#	define ZBX_NO_RETURN
#endif

void	zbx_mock_test_entry(void **state);

#endif	/* ZABBIX_MOCK_TEST_H */
