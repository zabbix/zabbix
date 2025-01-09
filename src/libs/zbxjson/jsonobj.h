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

#ifndef ZABBIX_JSONOBJ_H
#define ZABBIX_JSONOBJ_H

#include "zbxjson.h"

#include "zbxalgo.h"

typedef struct
{
	char		*name;
	zbx_jsonobj_t	*value;
	unsigned char	external;	/* 1 - the reference is to an external object.                */
					/* 0 - the reference is to a local object which must be freed */
					/*     when reference is destroyed                            */
}
zbx_jsonobj_ref_t;

ZBX_VECTOR_DECL(jsonobj_ref, zbx_jsonobj_ref_t)

void	jsonobj_init(zbx_jsonobj_t *obj, zbx_json_type_t type);

void	jsonobj_el_init(zbx_jsonobj_el_t *el);
void	jsonobj_el_clear(zbx_jsonobj_el_t *el);

void	jsonobj_set_string(zbx_jsonobj_t *obj, char *str);
void	jsonobj_set_number(zbx_jsonobj_t *obj, double number);
void	jsonobj_set_true(zbx_jsonobj_t *obj);
void	jsonobj_set_false(zbx_jsonobj_t *obj);
void	jsonobj_set_null(zbx_jsonobj_t *obj);

void	jsonobj_clear_ref_vector(zbx_vector_jsonobj_ref_t *refs);

#endif
