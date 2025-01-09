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

#ifndef ZABBIX_DBUPGRADE_MACROS_H
#define ZABBIX_DBUPGRADE_MACROS_H

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxexpr.h"

#define ZBX_DBPATCH_FUNCTION_UPDATE_NAME		0x01
#define ZBX_DBPATCH_FUNCTION_UPDATE_PARAM		0x02
#define ZBX_DBPATCH_FUNCTION_UPDATE			(ZBX_DBPATCH_FUNCTION_UPDATE_NAME | \
							ZBX_DBPATCH_FUNCTION_UPDATE_PARAM)

#define ZBX_DBPATCH_FUNCTION_CREATE			0x40
#define ZBX_DBPATCH_FUNCTION_DELETE			0x80

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	itemid;

	/* hostid for time based functions to track associated            */
	/* hosts when replacing with history function with common function */
	zbx_uint64_t	hostid;

	/* function location - expression|recovery expression */
	unsigned char	location;

	char		*name;
	char		*parameter;

	/* the first parameter (host:key) for calculated item */
	/* formula functions, NULL for trigger functions      */
	char		*arg0;

	unsigned char	flags;
}
zbx_dbpatch_function_t;

typedef struct
{
	const char	*field_name;
	size_t		max_len;
}
zbx_field_len_t;

ZBX_VECTOR_DECL(strloc, zbx_strloc_t)

int	db_rename_macro(zbx_db_result_t result, const char *table, const char *pkey, zbx_field_len_t *fields,
		int fields_num, const char *oldmacro, const char *newmacro);
void	dbpatch_convert_simple_macro(const char *expression, const zbx_token_simple_macro_t *data, int more,
		char **function);
int	dbpatch_is_time_function(const char *name, size_t len);
void	dbpatch_update_hist2common(zbx_dbpatch_function_t *function, int extended, char **expression);
void	dbpatch_convert_function(zbx_dbpatch_function_t *function, char **replace, zbx_vector_ptr_t *functions);
void	dbpatch_function_free(zbx_dbpatch_function_t *func);
int	dbpatch_is_composite_constant(const char *str);
void	dbpatch_strcpy_alloc_quoted_compat(char **str, size_t *str_alloc, size_t *str_offset, const char *source);
void	dbpatch_convert_params(char **out, const char *parameter, const zbx_vector_strloc_t *params, ...);

zbx_dbpatch_function_t	*dbpatch_new_function(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *name,
		const char *parameter, unsigned char flags);

#endif
