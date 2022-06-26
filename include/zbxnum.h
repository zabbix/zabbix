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

#ifndef ZABBIX_NUM_H
#define ZABBIX_NUM_H

#include "common.h"

#define is_ushort(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned short), 0x0, 0xFFFF)

#define is_uint32(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0xFFFFFFFF)

#define is_uint64(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define is_uint64_n(str, n, value) \
	is_uint_n_range(str, n, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define is_uint31(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0x7FFFFFFF)

#define ZBX_MAX_UINT31_1	0x7FFFFFFE
#define is_uint31_1(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, ZBX_MAX_UINT31_1)

#define is_uint_range(str, value, min, max) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned int), min, max)
int	is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);

int	is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);

int	zbx_double_compare(double a, double b);

int	int_in_list(char *list, int value);

#define ZBX_UNIT_SYMBOLS	"KMGTsmhdw"

#define ZBX_FLAG_DOUBLE_PLAIN	0x00
#define ZBX_FLAG_DOUBLE_SUFFIX	0x01
int	zbx_is_double(const char *str, double *value);
int	zbx_is_double_suffix(const char *str, unsigned char flags);

#if defined(_WINDOWS) || defined(__MINGW32__)
int	zbx_wis_uint(const wchar_t *wide_string);
#endif

const char	*zbx_print_double(char *buffer, size_t size, double val);
int		zbx_number_parse(const char *number, int *len);
int		zbx_suffixed_number_parse(const char *number, int *len);


#define ZBX_STR2UINT64(uint, string) is_uint64(string, &uint)

double	zbx_get_double_epsilon(void);
void	zbx_update_epsilon_to_not_use_double_precision(void);
void	zbx_update_epsilon_to_python_compatible_precision(void);

int	str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value);
double	str2double(const char *str);

#endif /* ZABBIX_NUM_H */
