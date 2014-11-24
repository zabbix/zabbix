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
	zbx_vector_str_t	fields;

	/* the query type, see ZBX_API_QUERY_* defines */
	unsigned char		type;
}
zbx_api_query_t;

/* output sorting definition */
typedef struct
{
	/* sort field */
	char		*field;

	/* sorting order, see ZBX_API_SORT_* defines */
	unsigned char	order;
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

	/* sort, sortOrder (a vector of zbx_api_sort_t structures) */
	zbx_vector_ptr_t	sort;
}
zbx_api_get_t;

/* object field flags */
#define ZBX_API_FIELD_FLAG_SORTABLE	1
#define ZBX_API_FIELD_FLAG_REQUIRED	2
#define ZBX_API_FIELD_FLAG_CALCULATED	4

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

void	zbx_api_get_init(zbx_api_get_t *self);
int	zbx_api_get_parse(zbx_api_get_t *self, const char *parameter, struct zbx_json_parse *json, const char **next,
		char **error);
int	zbx_api_get_validate(const zbx_api_get_t *self, char **error);
void	zbx_api_get_free(zbx_api_get_t *self);

void	zbx_api_query_init(zbx_api_query_t *self);
void	zbx_api_query_free(zbx_api_query_t *self);

int	zbx_api_get_param_query(const char *param, const char **next, zbx_api_query_t *value, char **error);
int	zbx_api_get_param_flag(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_bool(const char *param, const char **next, unsigned char *value, char **error);
int	zbx_api_get_param_int(const char *param, const char **next, int *value, char **error);
int	zbx_api_get_param_object(const char *param, const char **next, zbx_vector_ptr_pair_t *value, char **error);
int	zbx_api_get_param_string_or_array(const char *param, const char **next, zbx_vector_str_t *value, char **error);
int	zbx_api_get_param_idarray(const char *param, const char **next, zbx_vector_uint64_t *value, char **error);

const zbx_api_field_t	*zbx_api_field_get(const zbx_api_field_t *fields, const char *name);

#endif
