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

#ifndef ZABBIX_ZBXVARIANT_H
#define ZABBIX_ZBXVARIANT_H

#include "zbxalgo.h"

typedef union
{
	zbx_uint64_t		ui64;
	double			dbl;

	/* null terminated string */
	char			*str;

	/* length prefixed (4 bytes) binary data */
	void			*bin;

	zbx_vector_dbl_t	*dbl_vector;

	/* null terminated error message */
	char			*err;
}
zbx_variant_data_t;

struct zbx_variant
{
	unsigned char		type;
	zbx_variant_data_t	data;
};

#define ZBX_VARIANT_NONE	0
#define ZBX_VARIANT_STR		1
#define ZBX_VARIANT_DBL		2
#define ZBX_VARIANT_UI64	3
#define ZBX_VARIANT_BIN		4
#define ZBX_VARIANT_DBL_VECTOR	5
#define ZBX_VARIANT_ERR	6

void	zbx_variant_clear(zbx_variant_t *value);
void	zbx_variant_set_none(zbx_variant_t *value);
void	zbx_variant_set_str(zbx_variant_t *value, char *text);
void	zbx_variant_set_dbl(zbx_variant_t *value, double value_dbl);
void	zbx_variant_set_ui64(zbx_variant_t *value, zbx_uint64_t value_ui64);
void	zbx_variant_set_bin(zbx_variant_t *value, void *value_bin);
void	zbx_variant_set_error(zbx_variant_t *value, char *error);
void	zbx_variant_set_dbl_vector(zbx_variant_t *value, zbx_vector_dbl_t *dbl_vector);

void	zbx_variant_copy(zbx_variant_t *value, const zbx_variant_t *source);
int	zbx_variant_set_numeric(zbx_variant_t *value, const char *text);

int	zbx_variant_convert(zbx_variant_t *value, int type);
const char	*zbx_get_variant_type_desc(unsigned char type);
const char	*zbx_variant_value_desc(const zbx_variant_t *value);
const char	*zbx_variant_type_desc(const zbx_variant_t *value);

int	zbx_variant_compare(const zbx_variant_t *value1, const zbx_variant_t *value2);

void	*zbx_variant_data_bin_copy(const void *bin);
void	*zbx_variant_data_bin_create(const void *data, zbx_uint32_t size);
zbx_uint32_t	zbx_variant_data_bin_get(const void *bin, void **data);

int	zbx_variant_to_value_type(zbx_variant_t *value, unsigned char value_type, int dbl_precision, char **errmsg);

ZBX_VECTOR_DECL(var, zbx_variant_t)

#endif
