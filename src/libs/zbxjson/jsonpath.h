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

#ifndef ZABBIX_JSONPATH_H
#define ZABBIX_JSONPATH_H

#include "zbxjson.h"
#include "jsonobj.h"

#include "zbxalgo.h"

typedef struct
{
	const zbx_jsonobj_t		*root;		/* the root object */
	zbx_jsonpath_t			*path;
	unsigned char			found;		/* set to 1 when one object was matched and */
							/* no more matches are required             */
	zbx_vector_jsonobj_ref_t	objects;	/* the matched objects */

	zbx_jsonpath_index_t		*index;
}
zbx_jsonpath_context_t;

typedef struct zbx_jsonpath_token zbx_jsonpath_token_t;

typedef enum
{
	ZBX_JSONPATH_SEGMENT_UNKNOWN,
	ZBX_JSONPATH_SEGMENT_MATCH_ALL,
	ZBX_JSONPATH_SEGMENT_MATCH_LIST,
	ZBX_JSONPATH_SEGMENT_MATCH_RANGE,
	ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION,
	ZBX_JSONPATH_SEGMENT_FUNCTION
}
zbx_jsonpath_segment_type_t;

/* specifies if the match list contains object property names or array indices */
typedef enum
{
	ZBX_JSONPATH_LIST_NAME = 1,
	ZBX_JSONPATH_LIST_INDEX
}
zbx_jsonpath_list_type_t;

typedef enum
{
	ZBX_JSONPATH_FUNCTION_MIN = 1,
	ZBX_JSONPATH_FUNCTION_MAX,
	ZBX_JSONPATH_FUNCTION_AVG,
	ZBX_JSONPATH_FUNCTION_LENGTH,
	ZBX_JSONPATH_FUNCTION_FIRST,
	ZBX_JSONPATH_FUNCTION_SUM,
	/* the element name suffix '~' internally is treated as a function */
	ZBX_JSONPATH_FUNCTION_NAME
}
zbx_jsonpath_function_type_t;

typedef struct zbx_jsonpath_list_item
{
	struct zbx_jsonpath_list_item	*next;
	/* the structure is always over-allocated so that either int */
	/* or a zero terminated string can be stored in data         */
	char				data[1];
}
zbx_jsonpath_list_node_t;

typedef struct
{
	zbx_jsonpath_list_node_t	*values;
	zbx_jsonpath_list_type_t	type;
}
zbx_jsonpath_list_t;

typedef struct
{
	int		start;
	int		end;
	unsigned int	flags;
}
zbx_jsonpath_range_t;

typedef enum
{
	ZBX_JSONPATH_EXPRESSION_INDEX_TRUE,
	ZBX_JSONPATH_EXPRESSION_INDEX_FALSE,
}
zbx_json_path_expression_index_t;

/* expression tokens in postfix notation */
typedef struct
{
	zbx_vector_ptr_t	tokens;
	zbx_jsonpath_token_t	*index_token;	/* relative path token that is used to index parent object */
	zbx_jsonpath_token_t	*value_token;	/* the index value token */
}
zbx_jsonpath_expression_t;

typedef struct
{
	zbx_jsonpath_function_type_t	type;
}
zbx_jsonpath_function_t;

typedef union
{
	zbx_jsonpath_list_t		list;
	zbx_jsonpath_range_t		range;
	zbx_jsonpath_expression_t	expression;
	zbx_jsonpath_function_t		function;
}
zbx_jsonpath_data_t;

struct zbx_jsonpath_segment
{
	zbx_jsonpath_segment_type_t	type;
	zbx_jsonpath_data_t		data;

	/* set to 1 if the segment is 'detached' and can be anywhere in parent node tree */
	unsigned char			detached;
};

/*                                                                            */
/* Token groups:                                                              */
/*   operand - constant value, jsonpath reference, result of () evaluation    */
/*   operator2 - binary operator (arithmetic or comparison)                   */
/*   operator1 - unary operator (negation !)                                  */
/*                                                                            */
typedef enum
{
	ZBX_JSONPATH_TOKEN_GROUP_NONE,
	ZBX_JSONPATH_TOKEN_GROUP_OPERAND,
	ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2,	/* binary operator */
	ZBX_JSONPATH_TOKEN_GROUP_OPERATOR1	/* unary operator */
}
zbx_jsonpath_token_group_t;

/* expression token types */
typedef enum
{
	ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE = 1,
	ZBX_JSONPATH_TOKEN_PATH_RELATIVE,
	ZBX_JSONPATH_TOKEN_CONST_STR,
	ZBX_JSONPATH_TOKEN_CONST_NUM,
	ZBX_JSONPATH_TOKEN_PAREN_LEFT,
	ZBX_JSONPATH_TOKEN_PAREN_RIGHT,
	ZBX_JSONPATH_TOKEN_OP_PLUS,
	ZBX_JSONPATH_TOKEN_OP_MINUS,
	ZBX_JSONPATH_TOKEN_OP_MULT,
	ZBX_JSONPATH_TOKEN_OP_DIV,
	ZBX_JSONPATH_TOKEN_OP_EQ,
	ZBX_JSONPATH_TOKEN_OP_NE,
	ZBX_JSONPATH_TOKEN_OP_GT,
	ZBX_JSONPATH_TOKEN_OP_GE,
	ZBX_JSONPATH_TOKEN_OP_LT,
	ZBX_JSONPATH_TOKEN_OP_LE,
	ZBX_JSONPATH_TOKEN_OP_NOT,
	ZBX_JSONPATH_TOKEN_OP_AND,
	ZBX_JSONPATH_TOKEN_OP_OR,
	ZBX_JSONPATH_TOKEN_OP_REGEXP
}
zbx_jsonpath_token_type_t;

struct zbx_jsonpath_token
{
	unsigned char	type;
	char		*text;
	zbx_jsonpath_t	*path;
};

typedef struct
{
	char				*value;		/* the value found at indexed path */
	zbx_vector_jsonobj_ref_t	objects;	/* the objects matching value at indexed path */
}
zbx_jsonobj_index_el_t;

#endif
