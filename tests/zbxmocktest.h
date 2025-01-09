/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_MOCK_TEST_H
#define ZABBIX_MOCK_TEST_H

/* Mandatory headers needed by cmocka */
#include <stddef.h>
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <stdint.h>
#include <cmocka.h>
#include "zbxtypes.h"

/* hint to a compiler that cmocka _fail returns immediately, so it does not raise 'uninitialized variable' warnings */
#if defined(__GNUC__) || defined(__clang__)
#	define ZBX_NO_RETURN	__attribute__((noreturn))
#else
#	define ZBX_NO_RETURN
#endif

unsigned char get_program_type(void);
int	get_config_forks(unsigned char process_type);
void	set_config_forks(unsigned char process_type, int forks);

int	get_zbx_config_timeout(void);
const char	*get_zbx_config_source_ip(void);
int	get_zbx_config_enable_remote_commands(void);
zbx_uint64_t	get_zbx_config_value_cache_size(void);
void	set_zbx_config_value_cache_size(zbx_uint64_t cache_size);

void	zbx_mock_test_entry(void **state);

void	zbx_mock_log_impl(int level, const char *fmt, va_list args);

#endif	/* ZABBIX_MOCK_TEST_H */
