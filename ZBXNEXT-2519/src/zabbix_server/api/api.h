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

#define ZBX_API_TRUE	1
#define ZBX_API_FALSE	0

/* if set then filter matches if any item matches */
#define ZBX_API_FILTER_OPTION_ANY	1
/* if set then * wildcard is allowed for LIKE type filters */
#define ZBX_API_FILTER_OPTION_WILDCARD	2
/* if set then LIKE type filters drops starting % */
#define ZBX_API_FILTER_OPTION_START	4
/* if set then inverses LIKE filter */
#define ZBX_API_FILTER_OPTION_EXCLUDE	8


#define ZBX_API_PARAM_QUERY_EXTEND	"extend"
#define ZBX_API_PARAM_QUERY_COUNT	"count"

/* sort objects in ascending order */
#define ZBX_API_SORT_ASC		0
/* sort objects in descending order */
#define ZBX_API_SORT_DESC		1

#define ZBX_API_PARAM_NAME_SIZE		256

#define ZBX_API_ACCESS_READ		0
#define ZBX_API_ACCESS_WRITE		1

/* json result tags */
#define ZBX_API_RESULT_TAG_JSONRPC	"jsonrpc"
#define ZBX_API_RESULT_TAG_ID		"id"
#define ZBX_API_RESULT_TAG_RESULT	"result"
#define ZBX_API_RESULT_TAG_ERROR	"error"
#define ZBX_API_RESULT_TAG_ERRCODE	"code"
#define ZBX_API_RESULT_TAG_ERRMESSAGE	"message"
#define ZBX_API_RESULT_TAG_ERRDATA	"data"
#define ZBX_API_RESULT_TAG_AUTH		"auth"
#define ZBX_API_RESULT_TAG_METHOD	"method"

/* api request data */
typedef struct
{
	zbx_uint64_t	id;
	int		type;
}
zbx_api_user_t;

/* class property definition */
typedef struct
{
	/* field name */
	char		*name;

	/* database schema field name, can be NULL */
	char		*field_name;

	/* the database schema field */
	const ZBX_FIELD	*field;

	/* see ZBX_API_FIELD_FLAG_* defines */
	unsigned int	flags;
}
zbx_api_property_t;

/* (property, value) pair definition */
typedef struct
{
	const zbx_api_property_t	*property;
	zbx_db_value_t			value;
}
zbx_api_property_value_t;

/* API object class definition */
typedef struct
{
	char			*name;

	/* corresponding table name */
	char			*table_name;

	/* object property list */
	zbx_api_property_t	*properties;
}
zbx_api_class_t;

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
	/* a vector of output properties, when empty query is interpreted as select count(*) */
	zbx_vector_ptr_t	properties;

	/* The number of properties specified by the request.                            */
	/* More fields might be added to query for internal processing. Use the          */
	/* properties_num value to determine the number of requested fields.             */
	int			properties_num;

	/* Index of the key field in fields list vector.                                  */
	/* For main query the key field is the object id field if preservekeys is set or  */
	/* 0 otherwise.                                                                   */
	/* For sub queries the key field is the main query result field, used to execute  */
	/* sub queries.                                                                   */
	int			key;

	/* 1 if the query is set, 0 otherwise */
	int			is_set;
}
zbx_api_query_t;

/* output sorting definition */
typedef struct
{
	/* sort field */
	const zbx_api_property_t	*field;

	/* sorting order, see ZBX_API_SORT_* defines */
	unsigned char			order;
}
zbx_api_sort_t;

/* common get request options */
typedef struct
{
	/* the configured parameter mask */
	unsigned int		parameters;

	/* preservekeys */
	unsigned char		preservekeys;

	/* editable */
	unsigned char		editable;

	/* limit */
	int			limit;

	/* filter, search, excludeSearch, searchByAny, searchWildcardsEnabled, startSearch */
	/* (a vector of zbx_api_filter_t structures)                                       */
	zbx_api_filter_t	filter;

	/* output, countOutput */
	zbx_api_query_t		output;

	/* sort, sortOrder (a vector of zbx_api_sort_t structures) */
	zbx_vector_ptr_t	sort;
}
zbx_api_getoptions_t;

/*
 * The result sets from database queries are usually stored as ptr vectors
 * of rows, each containing str vector of columns, as shown below:
 *
 *            .--------------------.
 *            |        rows        |
 *            | <zbx_vector_ptr_t> |
 *            |--------------------|
 *         .--| row1               |
 *      .--|--| row2               |
 *      |  |  | ...                |
 *   .--|--|--| rowN               |
 *   |  |  |  '--------------------'
 *   |  |  |
 *   |  |  |  .--------------------.
 *   |  |  '->|      columns       |
 *   |  |     | <zbx_vector_str_t> |
 *   |  |     |--------------------|
 *   |  |     | column1            |
 *   |  |     | column2            |
 *   |  |     | ...                |
 *   |  |     | columnK            |
 *   |  |     '--------------------'
 *   |  |
 *   |  |     .--------------------.
 *   |  '---->|      columns       |
 *   |        | <zbx_vector_str_t> |
 *   |        |--------------------|
 *   |        | column1            |
 *   |        | column2            |
 *   |        | ...                |
 *   |        | columnK            |
 *   |        '--------------------'
 *   |
 *   |                . . .
 *   |
 *   |        .--------------------.
 *   '------->|      columns       |
 *            | <zbx_vector_str_t> |
 *            |--------------------|
 *            | column1            |
 *            | column2            |
 *            | ...                |
 *            | columnK            |
 *            '--------------------'
 *
 * All values are stored as strings except nulls that have NULL value.
 *
 * The result set of the main request query is stored in zbx_api_get_result_t structure
 * rows vector. For example when executing mediatype.get request the rows vector will
 * contain the requested data from media_type table.
 *
 * If the request has sub queries (defined with select<Objects> request parameter) then
 * for every sub query a zbx_api_query_result_t structure is created and added to
 * zbx_api_get_result_t structure queries vector. This data basically is an additional
 * column to the main result set, with a result set per row.
 *
 * Then the sub queries are executed for each main result set (rows) row and the returned
 * result sets are stored corresponding zbx_api_query_result_t structure rows vector, matching
 * the main result set row by row.
 *
 *       .--------------------.
 *       |      queries       |
 *       | <zbx_vector_ptr_t> |
 *       |--------------------|
 *   .---| query1             |
 *   |   | ...                |
 *   | .-| queryQ             |
 *   | | '--------------------'
 *   | |
 *   | '------------------------------------------------.
 *   |                                                  |
 *   '-----------------.                                |
 *	               v                                v
 *       .--------------------------.     .--------------------------.
 *       |        columnK+1         |     |        columnK+Q         |
 *       | <zbx_api_query_result_t> |     | <zbx_api_query_result_t> |
 *       |--------------------------|     |--------------------------|
 *       | name                     |     | name                     |
 *       | query                    | ... | query                    |
 *       | rows[]                   |     | rows[]                   |
 *       |   resultset1             |     |   resultset1             |
 *       |   resultset2             |     |   resultset2             |
 *       |      ...                 |     |      ...                 |
 *       |   resultsetN             |     |   resultsetN             |
 *       '--------------------------'     '--------------------------'
 *
 * The resultsetX result sets stored in query rows matches the rows of the main result set:
 *   row1 -> resultset1
 *   row2 -> resultset2
 *      ...
 *   rowN -> resultsetN
 */


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
#define ZBX_API_PROPERTY_SORTABLE	1
#define ZBX_API_PROPERTY_REQUIRED	2
#define ZBX_API_FIELD_FLAG_CALCULATED	4


int	zbx_api_init(char **error);

void	zbx_api_getoptions_init(zbx_api_getoptions_t *self);
int	zbx_api_getoptions_parse(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass, const char *parameter,
		const char **next, char **error);

int	zbx_api_getoptions_finalize(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass, char **error);
int	zbx_api_getoptions_add_output_field(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass,
		const char *name, int *index, char **error);
void	zbx_api_getoptions_clean(zbx_api_getoptions_t *self);

void	zbx_api_query_init(zbx_api_query_t *self);
void	zbx_api_query_clean(zbx_api_query_t *self);

int	zbx_api_get_param_query(const char *param, const char **next, const zbx_api_class_t *objclass,
		zbx_api_query_t *value, char **error);
int	zbx_api_get_param_flag(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_bool(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_int(const char *param, const char **next, int *value, char **error);
int	zbx_api_get_param_map(const char *param, const char **next, zbx_vector_ptr_pair_t *value, char **error);
int	zbx_api_get_param_stringarray(const char *param, const char **next, zbx_vector_str_t *value, char **error);
int	zbx_api_get_param_idarray(const char *param, const char **next, zbx_vector_uint64_t *value, char **error);
int	zbx_api_get_param_objectarray(const char *param, const char **next, const zbx_api_class_t *objclass,
		zbx_vector_ptr_t *value, char **error);


void	zbx_api_sql_add_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_query_t *query,
		const char *table, const char *alias, int distinct);
void	zbx_api_sql_add_filter(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_filter_t *filter,
		const char *alias, const char **sql_condition);
void	zbx_api_sql_add_sort(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_vector_ptr_t *sort,
		const char *alias);
void	zbx_api_sql_add_field_value(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_FIELD *field,
		const zbx_db_value_t *value);


void	zbx_api_db_clean_rows(zbx_vector_ptr_t *rows);
void	zbx_api_db_free_rows(zbx_vector_ptr_t *rows);

int	zbx_api_db_fetch_rows(const char *sql, int fields_num, int rows_num, zbx_vector_ptr_t *resultset, char **error);
int	zbx_api_db_fetch_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *column_name,
		const zbx_api_query_t *query, zbx_api_get_result_t *result, char **error);

void	zbx_api_get_result_init(zbx_api_get_result_t *self);
void	zbx_api_get_result_clean(zbx_api_get_result_t *self);

int	zbx_api_property_from_string(const zbx_api_property_t *self, const char *value_str, zbx_db_value_t *value,
		char **error);
void	zbx_api_property_to_string(char **str, size_t *str_alloc, size_t *str_offset, const zbx_api_property_t *self,
		const zbx_db_value_t *value);

/* Object primary key property. It must be the first property in object class definition. */
#define zbx_api_object_pk(object)	(object->properties)

const zbx_api_property_t	*zbx_api_class_get_property(const zbx_api_class_t *objclass, const char *name);
void	zbx_api_object_free(zbx_vector_ptr_t *object);
void	zbx_api_objects_to_ids(const zbx_vector_ptr_t *objects, zbx_vector_uint64_t *ids);

int	zbx_api_prepare_objects_for_create(zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass, char **error);
int	zbx_api_prepare_objects_for_update(zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass, char **error);

int	zbx_api_create_objects(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		zbx_vector_uint64_t *outids, char **error);
int	zbx_api_delete_objects(const zbx_vector_uint64_t *ids, const zbx_api_class_t *objclass, char **error);
int	zbx_api_update_objects(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		zbx_vector_uint64_t *outids, char **error);

int	zbx_api_check_objects_for_unique_property(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		const char *propname, int update, char **error);
int	zbx_api_check_objectids(const zbx_vector_uint64_t *objectids, const zbx_api_class_t *objclass, char **error);

void	zbx_api_json_init(struct zbx_json *json, const char *id);
void	zbx_api_json_add_count(struct zbx_json *json, const char *name, const zbx_vector_ptr_t *rows);
void	zbx_api_json_add_result(struct zbx_json *json, const zbx_api_getoptions_t *options,
		const zbx_api_get_result_t *result);
void	zbx_api_json_add_row(struct zbx_json *json, const zbx_api_query_t *query, const zbx_vector_str_t *columns,
		const zbx_vector_ptr_t *queries, int row);
void	zbx_api_json_add_query(struct zbx_json *json, const char *name, const zbx_api_query_t *query,
		const zbx_vector_ptr_t *rows);
void	zbx_api_json_add_idarray(struct zbx_json *json, const char *name, const zbx_vector_uint64_t *ids);
void	zbx_api_json_add_error(struct zbx_json *json, const char *prefix, const char *error);

#endif
