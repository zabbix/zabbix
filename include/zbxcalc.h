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

#ifndef ZABBIX_CALC_H
#define ZABBIX_CALC_H

#include "zbxcacheconfig.h"

#include "zbx_discoverer_constants.h"

#define ZBX_ITEM_QUERY_UNSET		0x0000

#define ZBX_ITEM_QUERY_HOST_SELF	0x0001
#define ZBX_ITEM_QUERY_HOST_ONE		0x0002
#define ZBX_ITEM_QUERY_HOST_ANY		0x0004

#define ZBX_ITEM_QUERY_KEY_ONE		0x0010
#define ZBX_ITEM_QUERY_KEY_SOME		0x0020
#define ZBX_ITEM_QUERY_KEY_ANY		0x0040
#define ZBX_ITEM_QUERY_FILTER		0x0100

#define ZBX_ITEM_QUERY_ERROR		0x8000

#define ZBX_ITEM_QUERY_MANY		(ZBX_ITEM_QUERY_HOST_ANY |\
					ZBX_ITEM_QUERY_KEY_SOME | ZBX_ITEM_QUERY_KEY_ANY |\
					ZBX_ITEM_QUERY_FILTER)

#define ZBX_ITEM_QUERY_ITEM_ANY		(ZBX_ITEM_QUERY_HOST_ANY | ZBX_ITEM_QUERY_KEY_ANY)

#define ZBX_EXPRESSION_NORMAL		0
#define ZBX_EXPRESSION_AGGREGATE	1

typedef struct
{
	int	num;
	char	*macro;
}
zbx_macro_index_t;

ZBX_PTR_VECTOR_DECL(macro_index, zbx_macro_index_t *)

void	zbx_macro_index_free(zbx_macro_index_t *index);
int	zbx_macro_index_compare(const void *d1, const void *d2);

/* group - hostids cache */
typedef struct
{
	char			*name;
	zbx_vector_uint64_t	hostids;
}
zbx_expression_group_t;

ZBX_PTR_VECTOR_DECL(expression_group_ptr, zbx_expression_group_t *)

/* item - tags cache */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_item_tag_t	tags;
}
zbx_expression_item_t;

ZBX_PTR_VECTOR_DECL(expression_item_ptr, zbx_expression_item_t *)

/* expression item query */
typedef struct
{
	/* query flags, see ZBX_ITEM_QUERY_* defines */
	zbx_uint32_t		flags;

	/* the item query /host/key?[filter] */
	zbx_item_query_t	ref;

	/* the query error */
	char			*error;

	/* the expression item query data, zbx_expression_query_one_t or zbx_expression_query_many_t */
	void			*data;
}
zbx_expression_query_t;

ZBX_PTR_VECTOR_DECL(expression_query_ptr, zbx_expression_query_t *)

typedef struct
{
	zbx_eval_context_t			*ctx;
	zbx_vector_expression_query_ptr_t	queries;
	int					mode;
	int					one_num;
	int					many_num;
	zbx_uint64_t				hostid;

	/* cache to resolve one item queries */
	zbx_host_key_t		*hostkeys;
	zbx_dc_item_t		*dcitems_hk;
	int			*errcodes_hk;

	/* cache to resolve many item queries */
	zbx_vector_expression_group_ptr_t	groups;
	zbx_vector_expression_item_ptr_t	itemtags;
	zbx_vector_dc_item_t			dcitem_refs;
	zbx_dc_item_t				*dcitems;
	int					*errcodes;
	int					dcitems_num;
}
zbx_expression_eval_t;

void	zbx_expression_eval_init(zbx_expression_eval_t *eval, int mode, zbx_eval_context_t *ctx);
void	zbx_expression_eval_clear(zbx_expression_eval_t *eval);
void	zbx_expression_eval_resolve_item_hosts(zbx_expression_eval_t *eval, const zbx_dc_item_t *item);
void	zbx_expression_eval_resolve_filter_macros(zbx_expression_eval_t *eval, const zbx_dc_item_t *item);
int	zbx_expression_eval_execute(zbx_expression_eval_t *eval, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error);

int	zbx_evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_str_t *unknown_msgs);
int	zbx_evaluate_unknown(const char *expression, double *value, char *error, size_t max_error_len);
int	zbx_evaluate_function(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *function,
		const char *parameter, const zbx_timespec_t *ts, char **error);

int	zbx_evaluable_for_notsupported(const char *fn);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid, const char *units,
		unsigned char value_type);

const char	*zbx_dservice_type_string(zbx_dservice_type_t service);

#endif
