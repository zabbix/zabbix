/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_API_H
#define ZABBIX_API_H

#include "db.h"

/* if set then filter matches if any item matches */
#define ZBX_API_FILTER_OPTION_ANY	1
/* if set then * wildcard is allowed for LIKE type filters */
#define ZBX_API_FILTER_OPTION_WILDCARD	2
/* if set then LIKE type filters drops starting % */
#define ZBX_API_FILTER_OPTION_START	4
/* if set then inverses LIKE filter */
#define ZBX_API_FILTER_OPTION_EXCLUDE	8

/* query not defined */
#define ZBX_API_QUERY_NONE		0
/* query the specified list of fields */
#define ZBX_API_QUERY_FIELDS		1
/* query all object fields */
#define ZBX_API_QUERY_ALL		2
/* query the number of selected objects */
#define ZBX_API_QUERY_COUNT		3

#define ZBX_API_PARAM_QUERY_EXTEND	"extend"
#define ZBX_API_PARAM_QUERY_COUNT	"count"

/* sort objects in ascending order */
#define ZBX_API_SORT_ASC		0
/* sort objects in descending order */
#define ZBX_API_SORT_DESC		1

#define ZBX_API_PARAM_NAME_SIZE		256

/* object field definition */
typedef struct
{
	/* field name */
	char		*name;
	/* ZBX_TYPE_ */
	unsigned char	type;
	/* see ZBX_API_FIELD_FLAG_* defines */
	unsigned int	flags;
}
zbx_api_field_t;

/* filter definition */
typedef struct
{
	/* (field,value) pairs to be used for exact matching */
	zbx_vector_ptr_pair_t	exact;

	/* (field,value) paris to be used for 'like' matching */
	zbx_vector_ptr_pair_t	like;

	/* filter options, see ZBX_API_FILTER_OPTION_* defines */
	unsigned char	options;

}
zbx_api_filter_t;

/* query parameter definition */
typedef struct
{
	/* a vector of output fields */
	zbx_vector_ptr_t	fields;

	/* the query type, see ZBX_API_QUERY_* defines */
	unsigned char		type;
}
zbx_api_query_t;

/* output sorting definition */
typedef struct
{
	/* sort field */
	const zbx_api_field_t	*field;

	/* sorting order, see ZBX_API_SORT_* defines */
	unsigned char		order;
}
zbx_api_sort_t;

/* common get request options */
typedef struct
{
	/* the configured parameter mask */
	unsigned int		parameters;

	/* preservekeys */
	unsigned char		output_indexed;

	/* editable */
	unsigned char		filter_editable;

	/* limit */
	int			limit;

	/* filter, search, excludeSearch, searchByAny, searchWildcardsEnabled, startSearch */
	/* (a vector of zbx_api_filter_t structures)                                       */
	zbx_api_filter_t	filter;

	/* output, countOutput */
	zbx_api_query_t		output;

	/* the number of output fields specified in query */
	int			output_field_count;

	/* sort, sortOrder (a vector of zbx_api_sort_t structures) */
	zbx_vector_ptr_t	sort;
}
zbx_api_get_options_t;

/* data returned by get request sub query (select<Object> parameter) */
typedef struct
{
	/* the sub query name */
	char			*name;

	/* the sub query */
	const zbx_api_query_t	*query;

	/* a vector of result sets matching main query rows by index */
	zbx_vector_ptr_t	rows;
}
zbx_api_query_result_t;

/* data retrieved by API get request */
/* TODO: create nice diagram illustrating result storage */
typedef struct
{
	/* the retrieved rows containing columns specified by get request output option */
	zbx_vector_ptr_t	rows;

	/* A vector of sub query results (zbx_api_query_result_t).      */
	/* Those sub queries are specified by select<Object> parameters */
	/* And performed for each row retrieved by the main query.      */
	zbx_vector_ptr_t	queries;
}
zbx_api_get_result_t;

/* object field flags */
#define ZBX_API_FIELD_FLAG_SORTABLE	1
#define ZBX_API_FIELD_FLAG_REQUIRED	2
#define ZBX_API_FIELD_FLAG_CALCULATED	4



void	zbx_api_get_init(zbx_api_get_options_t *self);
int	zbx_api_get_parse(zbx_api_get_options_t *self, const zbx_api_field_t *fields, const char *parameter,
		struct zbx_json_parse *json, const char **next, char **error);

int	zbx_api_get_finalize( zbx_api_get_options_t *self, const zbx_api_field_t *fields, char **error);
int	zbx_api_get_add_output_field(zbx_api_get_options_t *self, const zbx_api_field_t *fields, const char *name,
		char **error);
void	zbx_api_get_free(zbx_api_get_options_t *self);

void	zbx_api_query_init(zbx_api_query_t *self);
void	zbx_api_query_free(zbx_api_query_t *self);

int	zbx_api_get_param_query(const char *param, const char **next, const zbx_api_field_t *fields,
		zbx_api_query_t *value, char **error);
int	zbx_api_get_param_flag(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_bool(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_int(const char *param, const char **next, int *value, char **error);
int	zbx_api_get_param_object(const char *param, const char **next, zbx_vector_ptr_pair_t *value, char **error);
int	zbx_api_get_param_string_or_array(const char *param, const char **next, zbx_vector_str_t *value, char **error);
int	zbx_api_get_param_idarray(const char *param, const char **next, zbx_vector_uint64_t *value, char **error);

void	zbx_api_sql_add_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_query_t *query,
		const char *table, const char *alias);
void	zbx_api_sql_add_filter(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_filter_t *filter,
		const char *alias, const char **sql_condition);
void	zbx_api_sql_add_sort(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_vector_ptr_t *sort,
		const char *alias);

void	zbx_api_db_clean_rows(zbx_vector_ptr_t *rows);
void	zbx_api_db_free_rows(zbx_vector_ptr_t *rows);

void	zbx_api_db_fetch_rows(const char *sql, int fields_num, int rows_num, zbx_vector_ptr_t *resultset);
void	zbx_api_db_fetch_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *column_name,
		const zbx_api_query_t *query, zbx_api_get_result_t *result, int key_index);

void	zbx_api_get_result_init(zbx_api_get_result_t *self);
void	zbx_api_get_result_clean(zbx_api_get_result_t *self);


const zbx_api_field_t	*zbx_api_field_get(const zbx_api_field_t *fields, const char *name);
int	zbx_api_query_field_index(const zbx_api_query_t *query, const char *name);

/* TODO: investigate if it's possible to reuse dbschema definition */
extern const zbx_api_field_t zbx_api_class_mediatype[];

extern const zbx_api_field_t zbx_api_class_user[];

#endif
