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

#include "jsonpath.h"

#include "json.h"

#include "zbxregexp.h"
#include "zbxvariant.h"
#include "zbxnum.h"
#include "zbxexpr.h"
#include "jsonobj.h"
#include "zbxalgo.h"
#include "zbxstr.h"

typedef struct
{
	zbx_jsonpath_token_group_t	group;
	int				precedence;
}

zbx_jsonpath_token_def_t;

typedef struct
{
	const zbx_jsonobj_t	*obj;
	char			*path;
	zbx_hashset_t		index;
}
zbx_jsonobj_index_t;

ZBX_PTR_VECTOR_DECL(jsonobj_index_ptr, zbx_jsonobj_index_t *)
ZBX_PTR_VECTOR_IMPL(jsonobj_index_ptr, zbx_jsonobj_index_t *)

#if !defined(_WINDOWS) && !defined(__MINGW32__)
struct zbx_jsonpath_index
{
	zbx_vector_jsonobj_index_ptr_t	indexes;
	pthread_mutex_t			lock;
};

static zbx_hashset_t	*jsonpath_index_get(zbx_jsonpath_index_t *index, const zbx_jsonobj_t *obj,
		zbx_jsonpath_token_t *token);

#endif

static int	jsonpath_query_object(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *obj, int path_depth);
static int	jsonpath_query_array(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *array, int path_depth);
static int	jsonpath_str_copy_value(char **str, size_t *str_alloc, size_t *str_offset, const zbx_jsonobj_t *obj);
static void	jsonpath_ctx_clear(zbx_jsonpath_context_t *ctx);

/* define token groups and precedence */
static zbx_jsonpath_token_def_t	jsonpath_tokens[] = {
	{0, 0},
	{ZBX_JSONPATH_TOKEN_GROUP_OPERAND, 0},		/* ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERAND, 0},		/* ZBX_JSONPATH_TOKEN_PATH_RELATIVE */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERAND, 0},		/* ZBX_JSONPATH_TOKEN_CONST_STR */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERAND, 0},		/* ZBX_JSONPATH_TOKEN_CONST_NUM */
	{ZBX_JSONPATH_TOKEN_GROUP_NONE, 0},		/* ZBX_JSONPATH_TOKEN_PAREN_LEFT */
	{ZBX_JSONPATH_TOKEN_GROUP_NONE, 0},		/* ZBX_JSONPATH_TOKEN_PAREN_RIGHT */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 4},	/* ZBX_JSONPATH_TOKEN_OP_PLUS */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 4},	/* ZBX_JSONPATH_TOKEN_OP_MINUS */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 3},	/* ZBX_JSONPATH_TOKEN_OP_MULT */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 3},	/* ZBX_JSONPATH_TOKEN_OP_DIV */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 7},	/* ZBX_JSONPATH_TOKEN_OP_EQ */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 7},	/* ZBX_JSONPATH_TOKEN_OP_NE */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 6},	/* ZBX_JSONPATH_TOKEN_OP_GT */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 6},	/* ZBX_JSONPATH_TOKEN_OP_GE */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 6},	/* ZBX_JSONPATH_TOKEN_OP_LT */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 6},	/* ZBX_JSONPATH_TOKEN_OP_LE */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR1, 2},	/* ZBX_JSONPATH_TOKEN_OP_NOT */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 11},	/* ZBX_JSONPATH_TOKEN_OP_AND */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 12},	/* ZBX_JSONPATH_TOKEN_OP_OR */
	{ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2, 7}		/* ZBX_JSONPATH_TOKEN_OP_REGEXP */
};


static int	jsonpath_token_precedence(int type)
{
	return jsonpath_tokens[type].precedence;
}

static int	jsonpath_token_group(int type)
{
	return jsonpath_tokens[type].group;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add external json object reference to a vector                    *
 *                                                                            *
 * Parameters: refs  - [IN/OUT] the json object reference vector              *
 *             name  - [IN] the json object name or array index               *
 *             value - [IN] the json object                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_jsonobj_ref_add_object(zbx_vector_jsonobj_ref_t *refs, const char *name,
		const zbx_jsonobj_t *value)
{
	zbx_jsonobj_ref_t	ref;

	ref.name = zbx_strdup(NULL, name);
	ref.internal = NULL;
	ref.value = value;
	zbx_vector_jsonobj_ref_append(refs, ref);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add internal json object reference to a vector                    *
 *                                                                            *
 * Parameters: refs  - [IN/OUT] the json object reference vector              *
 *             name  - [IN] the json object name or array index               *
 *             str   - [IN] the string value of the object                    *
 *                                                                            *
 * Comments: This function will create json object and add internal reference,*
 *           meaning the object will be destroyed together with its reference.*
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_jsonobj_ref_add_string(zbx_vector_jsonobj_ref_t *refs, const char *name,
		const char *str)
{
	zbx_jsonobj_ref_t	ref;

	ref.name = zbx_strdup(NULL, name);
	ref.internal = (zbx_jsonobj_t *)zbx_malloc(NULL, sizeof(zbx_jsonobj_t));
	jsonobj_init(ref.internal, ZBX_JSON_TYPE_UNKNOWN);
	jsonobj_set_string(ref.internal, zbx_strdup(NULL, str));
	ref.value = ref.internal;

	zbx_vector_jsonobj_ref_append(refs, ref);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a copy of json object reference to a vector                   *
 *                                                                            *
 * Parameters: refs  - [IN/OUT] the json object reference vector              *
 *             ref   - [IN] the json object reference                         *
 *                                                                            *
 * Comments: For internal references a new internal json object will be       *
 *           created.                                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_jsonobj_ref_add(zbx_vector_jsonobj_ref_t *refs, zbx_jsonobj_ref_t *ref)
{
	if (ref->value != ref->internal)
		zbx_vector_jsonobj_ref_add_object(refs, ref->name, ref->value);
	else
		zbx_vector_jsonobj_ref_add_string(refs, ref->name, ref->value->data.string);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy json object references from one vector to other              *
 *                                                                            *
 * Parameters: dst - [IN/OUT] the destination json object reference vector    *
 *             src - [IN] the source json object reference vector             *
 *                                                                            *
 * Comments: For internal references a new internal json object will be       *
 *           created.                                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_jsonobj_ref_copy(zbx_vector_jsonobj_ref_t *dst, const zbx_vector_jsonobj_ref_t *src)
{
	int	i;

	for (i = 0; i < src->values_num; i++)
	{
		if (src->values[i].value != src->values[i].internal)
			zbx_vector_jsonobj_ref_add_object(dst, src->values[i].name, src->values[i].value);
		else
			zbx_vector_jsonobj_ref_add_string(dst, src->values[i].name, src->values[i].value->data.string);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set json error message and return FAIL                            *
 *                                                                            *
 * Comments: This function is used to return from json path parsing functions *
 *           in the case of failure.                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_jsonpath_error(const char *path)
{
	if ('\0' != *path)
		zbx_set_json_strerror("unsupported construct in jsonpath starting with: \"%s\"", path);
	else
		zbx_set_json_strerror("jsonpath was unexpectedly terminated");

	return FAIL;
}

static char	*jsonpath_strndup(const char *source, size_t len)
{
	char	*str;

	str = (char *)zbx_malloc(NULL, len + 1);
	memcpy(str, source, len);
	str[len] = '\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquote single or double quoted string by stripping               *
 *          leading/trailing quotes and unescaping backslash sequences        *
 *                                                                            *
 * Parameters: value - [OUT] the output value, must have at least len bytes   *
 *             start - [IN] a single or double quoted string to unquote       *
 *             len   - [IN] the length of the input string                    *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_unquote(char *value, const char *start, size_t len)
{
	const char	*end = start + len - 1;

	for (start++; start != end; start++)
	{
		if ('\\' == *start)
			start++;

		*value++ = *start;
	}

	*value = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquote string stripping leading/trailing quotes and unescaping   *
 *          backspace sequences                                               *
 *                                                                            *
 * Parameters: start - [IN] the string to unquote including leading and       *
 *                          trailing quotes                                   *
 *             len   - [IN] the length of the input string                    *
 *                                                                            *
 * Return value: The unescaped string (must be freed by the caller).          *
 *                                                                            *
 ******************************************************************************/
static char	*jsonpath_unquote_dyn(const char *start, size_t len)
{
	char	*value;

	value = (char *)zbx_malloc(NULL, len + 1);
	jsonpath_unquote(value, start, len);

	return value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create jsonpath list item of the specified size                   *
 *                                                                            *
 ******************************************************************************/
static zbx_jsonpath_list_node_t	*jsonpath_list_create_node(size_t size)
{
	return (zbx_jsonpath_list_node_t *)zbx_malloc(NULL, offsetof(zbx_jsonpath_list_node_t, data) + size);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free jsonpath list                                                *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_list_free(zbx_jsonpath_list_node_t *list)
{
	while (NULL != list)
	{
		zbx_jsonpath_list_node_t	*item = list;

		list = list->next;
		zbx_free(item);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: append array index to list                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_jsonpath_list_node_t	*jsonpath_list_append_index(zbx_jsonpath_list_node_t *head, int index,
		int check_duplicate)
{
	zbx_jsonpath_list_node_t	*node;

	if (0 != check_duplicate)
	{
		for (node = head; NULL != node; node = node->next)
		{
			int	query_index;

			memcpy(&query_index, node->data, sizeof(query_index));
			if (query_index == index)
				return head;
		}
	}

	node = jsonpath_list_create_node(sizeof(int));
	node->next = head;
	memcpy(node->data, &index, sizeof(int));

	return node;
}

/******************************************************************************
 *                                                                            *
 * Purpose: append name to list                                               *
 *                                                                            *
 ******************************************************************************/
static zbx_jsonpath_list_node_t	*jsonpath_list_append_name(zbx_jsonpath_list_node_t *head, const char *name, size_t len)
{
	zbx_jsonpath_list_node_t	*node, *new_node;

	new_node = jsonpath_list_create_node(len + 1);
	jsonpath_unquote(new_node->data, name, len + 1);

	for (node = head; NULL != node; node = node->next)
	{
		if (0 == strcmp((char *)new_node->data, (char *)node->data))
		{
			zbx_free(new_node);
			return head;
		}
	}

	new_node->next = head;

	return new_node;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create jsonpath structure and compile json path                   *
 *                                                                            *
 ******************************************************************************/
static zbx_jsonpath_t	*jsonpath_create_token_jsonpath(const char *text, size_t len)
{
	zbx_jsonpath_t	*path;
	char		*tmp_text;

	tmp_text = jsonpath_strndup(text, len);

	if ('@' == *tmp_text)
		*tmp_text = '$';

	path = (zbx_jsonpath_t *)zbx_malloc(NULL, sizeof(zbx_jsonpath_t));

	if (FAIL == zbx_jsonpath_compile(tmp_text, path))
	{
		zbx_free(path);
		goto out;
	}

	if (1 != path->definite)
	{
		zbx_set_json_strerror("only simple path are supported in jsonpath expression: \"%s\"", text);
		zbx_jsonpath_clear(path);
		zbx_free(path);
		goto out;
	}

	if (ZBX_JSONPATH_SEGMENT_FUNCTION == path->segments[path->segments_num - 1].type)
	{
		zbx_set_json_strerror("functions are not supported in jsonpath expression: \"%s\"", text);
		zbx_jsonpath_clear(path);
		zbx_free(path);
	}
out:
	zbx_free(tmp_text);

	return path;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create jsonpath expression token                                  *
 *                                                                            *
 * Parameters: type       - [IN] the token type                               *
 *             expression - [IN] the expression                               *
 *             loc        - [IN] the token location in the expression         *
 *                                                                            *
 * Return value: The created token (must be freed by the caller) or           *
 *               NULL in the case of error.                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_jsonpath_token_t	*jsonpath_create_token(unsigned char type, const char *expression,
		const zbx_strloc_t *loc)
{
	zbx_jsonpath_token_t	*token;

	token = (zbx_jsonpath_token_t *)zbx_malloc(NULL, sizeof(zbx_jsonpath_token_t));
	token->type = type;

	switch (token->type)
	{
		case ZBX_JSONPATH_TOKEN_CONST_STR:
			token->text = jsonpath_unquote_dyn(expression + loc->l, loc->r - loc->l + 1);
			token->path = NULL;
			break;
		case ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE:
		case ZBX_JSONPATH_TOKEN_PATH_RELATIVE:
			if (NULL == (token->path = jsonpath_create_token_jsonpath(expression + loc->l,
					loc->r - loc->l + 1)))
			{
				zbx_free(token);
			}
			else
				token->text = jsonpath_strndup(expression + loc->l, loc->r - loc->l + 1);
			break;
		case ZBX_JSONPATH_TOKEN_CONST_NUM:
			token->text = jsonpath_strndup(expression + loc->l, loc->r - loc->l + 1);
			token->path = NULL;
			break;
		default:
			token->text = NULL;
			token->path = NULL;
	}

	return token;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free jsonpath expression token                                    *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_token_free(zbx_jsonpath_token_t *token)
{
	zbx_free(token->text);

	if (NULL != token->path)
	{
		zbx_jsonpath_clear(token->path);
		zbx_free(token->path);
	}

	zbx_free(token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reserve space in jsonpath segments array for more segments        *
 *                                                                            *
 * Parameters: jsonpath - [IN] the jsonpath data                              *
 *             num      - [IN] the number of segments to reserve              *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_reserve(zbx_jsonpath_t *jsonpath, int num)
{
	if (jsonpath->segments_num + num > jsonpath->segments_alloc)
	{
		int	old_alloc = jsonpath->segments_alloc;

		if (jsonpath->segments_alloc < num)
			jsonpath->segments_alloc = jsonpath->segments_num + num;
		else
			jsonpath->segments_alloc *= 2;

		jsonpath->segments = (zbx_jsonpath_segment_t *)zbx_realloc(jsonpath->segments,
				sizeof(zbx_jsonpath_segment_t) * (size_t)jsonpath->segments_alloc);

		/* Initialize the memory allocated for new segments, as parser can set     */
		/* detached flag for the next segment, so the memory cannot be initialized */
		/* when creating a segment.                                                */
		memset(jsonpath->segments + old_alloc, 0,
				(size_t)(jsonpath->segments_alloc - old_alloc) * sizeof(zbx_jsonpath_segment_t));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resource allocated by jsonpath segment                       *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_segment_clear(zbx_jsonpath_segment_t *segment)
{
	switch (segment->type)
	{
		case ZBX_JSONPATH_SEGMENT_MATCH_LIST:
			jsonpath_list_free(segment->data.list.values);
			break;
		case ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION:
			zbx_vector_ptr_clear_ext(&segment->data.expression.tokens,
					(zbx_clean_func_t)jsonpath_token_free);
			zbx_vector_ptr_destroy(&segment->data.expression.tokens);
			break;
		default:
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: find next component of json path                                  *
 *                                                                            *
 * Parameters: pnext - [IN/OUT] the reference to the next path component      *
 *                                                                            *
 * Return value: SUCCEED - the json path component was parsed successfully    *
 *               FAIL    - json path parsing error                            *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_next(const char **pnext)
{
	const char	*next = *pnext, *start;

	/* process dot notation component */
	if ('.' == *next)
	{
		if ('\0' == *(++next))
			return zbx_jsonpath_error(*pnext);

		if ('[' != *next)
		{
			start = next;

			while (0 != isalnum((unsigned char)*next) || '_' == *next || '$' == *next)
				next++;

			if (start == next)
				return zbx_jsonpath_error(*pnext);

			*pnext = next;
			return SUCCEED;
		}
	}

	if ('[' != *next)
		return zbx_jsonpath_error(*pnext);

	SKIP_WHITESPACE_NEXT(next);

	/* process array index component */
	if (0 != isdigit((unsigned char)*next))
	{
		size_t	pos;

		for (pos = 1; 0 != isdigit((unsigned char)next[pos]); pos++)
			;

		next += pos;
		SKIP_WHITESPACE(next);
	}
	else
	{
		char	quotes;

		if ('\'' != *next && '"' != *next)
			return zbx_jsonpath_error(*pnext);

		start = next;

		for (quotes = *next++; quotes != *next; next++)
		{
			if ('\0' == *next)
				return zbx_jsonpath_error(*pnext);
		}

		if (start == next)
			return zbx_jsonpath_error(*pnext);

		SKIP_WHITESPACE_NEXT(next);
	}

	if (']' != *next++)
		return zbx_jsonpath_error(*pnext);

	*pnext = next;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse single or double quoted substring                           *
 *                                                                            *
 * Parameters: start - [IN] the substring start                               *
 *             len   - [OUT] the substring length                             *
 *                                                                            *
 * Return value: SUCCEED - the substring was parsed successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_substring(const char *start, size_t *len)
{
	const char	*ptr;
	char		quotes;

	for (quotes = *start, ptr = start + 1; '\0' != *ptr; ptr++)
	{
		if (*ptr == quotes)
		{
			*len = (size_t)(ptr - start + 1);
			return SUCCEED;
		}

		if ('\\' == *ptr)
		{
			if (quotes != ptr[1] && '\\' != ptr[1] )
				return FAIL;
			ptr++;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse jsonpath reference                                          *
 *                                                                            *
 * Parameters: start - [IN] the jsonpath start                                *
 *             len   - [OUT] the jsonpath length                              *
 *                                                                            *
 * Return value: SUCCEED - the jsonpath was parsed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function is used to parse jsonpath references used in       *
 *           jsonpath filter expressions.                                     *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_path(const char *start, size_t *len)
{
	const char	*ptr = start + 1;

	while ('[' == *ptr || '.' == *ptr)
	{
		if (FAIL == jsonpath_next(&ptr))
			return FAIL;
	}

	*len = (size_t)(ptr - start);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse number value                                                *
 *                                                                            *
 * Parameters: start - [IN] the number start                                  *
 *             len   - [OUT] the number length                                *
 *                                                                            *
 * Return value: SUCCEED - the number was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_number(const char *start, size_t *len)
{
	const char	*ptr = start;
	char		*end;
	int		size;
	double		tmp;

	if ('-' == *ptr || '+' == *ptr)
		ptr++;

	if (FAIL == zbx_number_parse(ptr, &size))
		return FAIL;

	ptr += size;
	errno = 0;
	tmp = strtod(start, &end);

	if (ptr != end || HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
		return FAIL;

	*len = (size_t)(ptr - start);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next token in jsonpath expression                             *
 *                                                                            *
 * Parameters: expression - [IN] the jsonpath expression                      *
 *             pos        - [IN] the position of token in the expression      *
 *             prev_group - [IN] the preceding token group, used to determine *
 *                               token type based on context if necessary     *
 *             type       - [OUT] the token type                              *
 *             loc        - [OUT] the token location in the expression        *
 *                                                                            *
 * Return value: SUCCEED - the token was parsed successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_expression_next_token(const char *expression, size_t pos, int prev_group,
		zbx_jsonpath_token_type_t *type, zbx_strloc_t *loc)
{
	size_t		len;
	const char	*ptr = expression + pos;

	SKIP_WHITESPACE(ptr);
	loc->l = (size_t)(ptr - expression);

	switch (*ptr)
	{
		case '(':
			*type = ZBX_JSONPATH_TOKEN_PAREN_LEFT;
			loc->r = loc->l;
			return SUCCEED;
		case ')':
			*type = ZBX_JSONPATH_TOKEN_PAREN_RIGHT;
			loc->r = loc->l;
			return SUCCEED;
		case '+':
			*type = ZBX_JSONPATH_TOKEN_OP_PLUS;
			loc->r = loc->l;
			return SUCCEED;
		case '-':
			if (ZBX_JSONPATH_TOKEN_GROUP_OPERAND == prev_group)
			{
				*type = ZBX_JSONPATH_TOKEN_OP_MINUS;
				loc->r = loc->l;
				return SUCCEED;
			}
			break;
		case '/':
			*type = ZBX_JSONPATH_TOKEN_OP_DIV;
			loc->r = loc->l;
			return SUCCEED;
		case '*':
			*type = ZBX_JSONPATH_TOKEN_OP_MULT;
			loc->r = loc->l;
			return SUCCEED;
		case '!':
			if ('=' == ptr[1])
			{
				*type = ZBX_JSONPATH_TOKEN_OP_NE;
				loc->r = loc->l + 1;
				return SUCCEED;
			}
			*type = ZBX_JSONPATH_TOKEN_OP_NOT;
			loc->r = loc->l;
			return SUCCEED;
		case '=':
			switch (ptr[1])
			{
				case '=':
					*type = ZBX_JSONPATH_TOKEN_OP_EQ;
					loc->r = loc->l + 1;
					return SUCCEED;
				case '~':
					*type = ZBX_JSONPATH_TOKEN_OP_REGEXP;
					loc->r = loc->l + 1;
					return SUCCEED;
			}
			goto out;
		case '<':
			if ('=' == ptr[1])
			{
				*type = ZBX_JSONPATH_TOKEN_OP_LE;
				loc->r = loc->l + 1;
				return SUCCEED;
			}
			*type = ZBX_JSONPATH_TOKEN_OP_LT;
			loc->r = loc->l;
			return SUCCEED;
		case '>':
			if ('=' == ptr[1])
			{
				*type = ZBX_JSONPATH_TOKEN_OP_GE;
				loc->r = loc->l + 1;
				return SUCCEED;
			}
			*type = ZBX_JSONPATH_TOKEN_OP_GT;
			loc->r = loc->l;
			return SUCCEED;
		case '|':
			if ('|' == ptr[1])
			{
				*type = ZBX_JSONPATH_TOKEN_OP_OR;
				loc->r = loc->l + 1;
				return SUCCEED;
			}
			goto out;
		case '&':
			if ('&' == ptr[1])
			{
				*type = ZBX_JSONPATH_TOKEN_OP_AND;
				loc->r = loc->l + 1;
				return SUCCEED;
			}
			goto out;
		case '@':
			if (SUCCEED == jsonpath_parse_path(ptr, &len))
			{
				*type = ZBX_JSONPATH_TOKEN_PATH_RELATIVE;
				loc->r = loc->l + len - 1;
				return SUCCEED;
			}
			goto out;

		case '$':
			if (SUCCEED == jsonpath_parse_path(ptr, &len))
			{
				*type = ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE;
				loc->r = loc->l + len - 1;
				return SUCCEED;
			}
			goto out;
		case '\'':
		case '"':
			if (SUCCEED == jsonpath_parse_substring(ptr, &len))
			{
				*type = ZBX_JSONPATH_TOKEN_CONST_STR;
				loc->r = loc->l + len - 1;
				return SUCCEED;
			}
			goto out;
	}

	if ('-' == *ptr || 0 != isdigit((unsigned char)*ptr))
	{
		if (SUCCEED == jsonpath_parse_number(ptr, &len))
		{
			*type = ZBX_JSONPATH_TOKEN_CONST_NUM;
			loc->r = loc->l + len - 1;
			return SUCCEED;
		}
	}
out:
	return zbx_jsonpath_error(ptr);
}

/* value types on index stack */
typedef enum
{
	ZBX_JSONPATH_CONST = 1,		/* constant value - string or number */
	ZBX_JSONPATH_VALUE,		/* result of an operation after which cannot be used in index */
	ZBX_JSONPATH_PATH,		/* relative jsonpath - @.a.b.c */
	ZBX_JSONPATH_PATH_OP		/* result of an operation with jsonpath which still can be used in index */
}
zbx_jsonpath_index_value_type_t;

typedef struct
{
	zbx_jsonpath_index_value_type_t	type;
	zbx_jsonpath_token_t		*index_token;
	zbx_jsonpath_token_t		*value_token;
}
zbx_jsonpath_index_value_t;

ZBX_VECTOR_DECL(jpi_value, zbx_jsonpath_index_value_t)
ZBX_VECTOR_IMPL(jpi_value, zbx_jsonpath_index_value_t)

/******************************************************************************
 *                                                                            *
 * Purpose: analyze expression and set indexing fields if possible            *
 *                                                                            *
 * Comments: Expression can be indexed if it contains relative json path      *
 *           comparison with constant that is used in and operations.         *
 *           This is tested by doing a pseudo evaluation by operand types     *
 *           and checking the result type.                                    *
 *                                                                            *
 *           So expressions like ?(@.a.b == 1), ?(@.a == "A" and @.b == "B")  *
 *           can be indexed (by @.a.b and by @.a) while expressions like      *
 *           ?(@.a == @.b), ?(@.a == "A" or @.b == "B") cannot.               *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_expression_prepare_index(zbx_jsonpath_expression_t *exp)
{
	int				i;
	zbx_vector_jpi_value_t		stack;
	zbx_jsonpath_index_value_t	*left, *right;

	zbx_vector_jpi_value_create(&stack);

	for (i = 0; i < exp->tokens.values_num; i++)
	{
		zbx_jsonpath_token_t		*token = (zbx_jsonpath_token_t *)exp->tokens.values[i];
		zbx_jsonpath_index_value_t	jpi = {0};

		switch (token->type)
		{
			case ZBX_JSONPATH_TOKEN_OP_NOT:
				if (1 > stack.values_num)
					goto out;
				stack.values[stack.values_num - 1].type = ZBX_JSONPATH_VALUE;
				stack.values[stack.values_num - 1].index_token = NULL;
				stack.values[stack.values_num - 1].value_token = NULL;
				continue;
			case ZBX_JSONPATH_TOKEN_PATH_RELATIVE:
				jpi.index_token = token;
				jpi.type = ZBX_JSONPATH_PATH;
				zbx_vector_jpi_value_append(&stack, jpi);
				continue;
			case ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE:
				jpi.type = ZBX_JSONPATH_VALUE;
				zbx_vector_jpi_value_append(&stack, jpi);
				continue;
			case ZBX_JSONPATH_TOKEN_CONST_STR:
			case ZBX_JSONPATH_TOKEN_CONST_NUM:
				jpi.value_token = token;
				jpi.type = ZBX_JSONPATH_CONST;
				zbx_vector_jpi_value_append(&stack, jpi);
				continue;
		}

		if (2 > stack.values_num)
			goto out;

		left = &stack.values[stack.values_num - 2];
		right = &stack.values[stack.values_num - 1];
		stack.values_num--;

		switch (token->type)
		{
			case ZBX_JSONPATH_TOKEN_OP_EQ:
				if ((ZBX_JSONPATH_PATH == left->type || ZBX_JSONPATH_PATH == right->type) &&
						(ZBX_JSONPATH_CONST == left->type || ZBX_JSONPATH_CONST == right->type))
				{
					left->type = ZBX_JSONPATH_PATH_OP;

					if (ZBX_JSONPATH_CONST == right->type)
						left->value_token = right->value_token;
					else
						left->index_token = right->index_token;
				}
				else
					left->type = ZBX_JSONPATH_VALUE;
				continue;
			case ZBX_JSONPATH_TOKEN_OP_AND:
				if (ZBX_JSONPATH_PATH == left->type)
					left->type = ZBX_JSONPATH_VALUE;

				if (ZBX_JSONPATH_PATH == right->type)
					right->type = ZBX_JSONPATH_VALUE;

				if (ZBX_JSONPATH_PATH_OP == left->type && ZBX_JSONPATH_PATH_OP == right->type)
					continue;

				if ((ZBX_JSONPATH_PATH_OP == left->type || ZBX_JSONPATH_PATH_OP == right->type) &&
						(ZBX_JSONPATH_VALUE == left->type || ZBX_JSONPATH_VALUE == right->type))
				{
					if (ZBX_JSONPATH_PATH_OP != left->type)
						*left = *right;
				}
				else
					left->type = ZBX_JSONPATH_VALUE;
				continue;
			default:
				left->type = ZBX_JSONPATH_VALUE;
				left->index_token = NULL;
				left->value_token = NULL;
				break;
		}
	}

	if (1 == stack.values_num && ZBX_JSONPATH_PATH_OP == stack.values[0].type)
	{
		exp->index_token = stack.values[0].index_token;
		exp->value_token = stack.values[0].value_token;
	}
out:
	zbx_vector_jpi_value_destroy(&stack);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse jsonpath filter expression in format                        *
 *                                                                            *
 * Parameters: expression - [IN] the expression, including opening and        *
 *                               closing parenthesis                          *
 *             jsonpath   - [IN/OUT] the jsonpath                             *
 *             next       - [OUT] a pointer to the next character after       *
 *                                parsed expression                           *
 *                                                                            *
 * Return value: SUCCEED - the expression was parsed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function uses shunting-yard algorithm to store parsed       *
 *           tokens in postfix notation for evaluation.                       *
 *                                                                            *
 *  The following token precedence rules are enforced:                        *
 *   1) binary operator must follow an operand                                *
 *   2) operand must follow an operator                                       *
 *   3) unary operator must follow an operator                                *
 *   4) ')' must follow an operand                                            *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_expression(const char *expression, zbx_jsonpath_t *jsonpath, const char **next)
{
	int				nesting = 1, ret = FAIL;
	zbx_jsonpath_token_t		*optoken, *token;
	zbx_vector_ptr_t		output, operators;
	zbx_strloc_t			loc = {0, 0};
	zbx_jsonpath_token_type_t	token_type;
	zbx_jsonpath_token_group_t	prev_group = ZBX_JSONPATH_TOKEN_GROUP_NONE;

	if ('(' != *expression)
		return zbx_jsonpath_error(expression);

	zbx_vector_ptr_create(&output);
	zbx_vector_ptr_create(&operators);

	while (SUCCEED == jsonpath_expression_next_token(expression, loc.r + 1, prev_group, &token_type, &loc))
	{
		switch (token_type)
		{
			case ZBX_JSONPATH_TOKEN_PAREN_LEFT:
				nesting++;
				break;

			case ZBX_JSONPATH_TOKEN_PAREN_RIGHT:
				if (ZBX_JSONPATH_TOKEN_GROUP_OPERAND != prev_group)
				{
					zbx_jsonpath_error(expression + loc.l);
					goto out;
				}

				if (0 == --nesting)
				{
					*next = expression + loc.r + 1;
					ret = SUCCEED;
					goto out;
				}
				break;
			default:
				break;
		}

		if (ZBX_JSONPATH_TOKEN_GROUP_OPERAND == jsonpath_token_group(token_type))
		{
			/* expression cannot have two consequent operands */
			if (ZBX_JSONPATH_TOKEN_GROUP_OPERAND == prev_group)
			{
				zbx_jsonpath_error(expression + loc.l);
				goto out;
			}

			if (NULL == (token = jsonpath_create_token(token_type, expression, &loc)))
				goto cleanup;

			zbx_vector_ptr_append(&operators, token);
			prev_group = jsonpath_token_group(token_type);
			continue;
		}

		if (ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2 == jsonpath_token_group(token_type) ||
				ZBX_JSONPATH_TOKEN_GROUP_OPERATOR1 == jsonpath_token_group(token_type))
		{
			/* binary operator must follow an operand  */
			if (ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2 == jsonpath_token_group(token_type) &&
					ZBX_JSONPATH_TOKEN_GROUP_OPERAND != prev_group)
			{
				zbx_jsonpath_error(expression + loc.l);
				goto cleanup;
			}

			/* negation ! operator cannot follow an operand */
			if (ZBX_JSONPATH_TOKEN_OP_NOT == token_type &&
					ZBX_JSONPATH_TOKEN_GROUP_OPERAND == prev_group)
			{
				zbx_jsonpath_error(expression + loc.l);
				goto cleanup;
			}

			for (; 0 < operators.values_num; operators.values_num--)
			{
				optoken = operators.values[operators.values_num - 1];

				if (jsonpath_token_precedence(optoken->type) >
						jsonpath_token_precedence(token_type))
				{
					break;
				}

				if (ZBX_JSONPATH_TOKEN_PAREN_LEFT == optoken->type)
					break;

				zbx_vector_ptr_append(&output, optoken);
			}

			if (NULL == (token = jsonpath_create_token(token_type, expression, &loc)))
				goto cleanup;

			zbx_vector_ptr_append(&operators, token);
			prev_group = jsonpath_token_group(token_type);
			continue;
		}

		if (ZBX_JSONPATH_TOKEN_PAREN_LEFT == token_type)
		{
			if (NULL == (token = jsonpath_create_token(token_type, expression, &loc)))
				goto cleanup;

			zbx_vector_ptr_append(&operators, token);
			prev_group = ZBX_JSONPATH_TOKEN_GROUP_NONE;
			continue;
		}

		if (ZBX_JSONPATH_TOKEN_PAREN_RIGHT == token_type)
		{
			/* right parenthesis must follow and operand or right parenthesis */
			if (ZBX_JSONPATH_TOKEN_GROUP_OPERAND != prev_group)
			{
				zbx_jsonpath_error(expression + loc.l);
				goto cleanup;
			}

			for (optoken = 0; 0 < operators.values_num; operators.values_num--)
			{
				optoken = operators.values[operators.values_num - 1];

				if (ZBX_JSONPATH_TOKEN_PAREN_LEFT == optoken->type)
				{
					operators.values_num--;
					break;
				}

				zbx_vector_ptr_append(&output, optoken);
			}

			if (NULL == optoken)
			{
				zbx_jsonpath_error(expression + loc.l);
				goto cleanup;
			}
			jsonpath_token_free(optoken);

			prev_group = ZBX_JSONPATH_TOKEN_GROUP_OPERAND;
			continue;
		}
	}
out:
	if (SUCCEED == ret)
	{
		zbx_jsonpath_segment_t	*segment;

		for (optoken = 0; 0 < operators.values_num; operators.values_num--)
		{
			optoken = operators.values[operators.values_num - 1];

			if (ZBX_JSONPATH_TOKEN_PAREN_LEFT == optoken->type)
			{
				zbx_set_json_strerror("mismatched () brackets in expression: %s", expression);
				ret = FAIL;
				goto cleanup;
			}

			zbx_vector_ptr_append(&output, optoken);
		}

		jsonpath_reserve(jsonpath, 1);
		segment = &jsonpath->segments[jsonpath->segments_num++];
		segment->type = ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION;
		zbx_vector_ptr_create(&segment->data.expression.tokens);
		zbx_vector_ptr_append_array(&segment->data.expression.tokens, output.values, output.values_num);

		/* index only json path that has been definite until this point */
		if (0 != jsonpath->definite)
			jsonpath_expression_prepare_index(&segment->data.expression);

		jsonpath->definite = 0;
	}
cleanup:
	if (SUCCEED != ret)
	{
		zbx_vector_ptr_clear_ext(&operators, (zbx_clean_func_t)jsonpath_token_free);
		zbx_vector_ptr_clear_ext(&output, (zbx_clean_func_t)jsonpath_token_free);
	}

	zbx_vector_ptr_destroy(&operators);
	zbx_vector_ptr_destroy(&output);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse a list of single or double quoted names, including trivial  *
 *          case when a single name is used                                   *
 *                                                                            *
 * Parameters: list     - [IN] the name list                                  *
 *             jsonpath - [IN/OUT] the jsonpath                               *
 *             next     - [OUT] a pointer to the next character after parsed  *
 *                              list                                          *
 *                                                                            *
 * Return value: SUCCEED - the list was parsed successfully                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the trivial case (when list contains one name) the name is    *
 *           stored into zbx_jsonpath_list_t:value field and later its        *
 *           address is stored into zbx_jsonpath_list_t:values to reduce      *
 *           allocations in trivial cases.                                    *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_names(const char *list, zbx_jsonpath_t *jsonpath, const char **next)
{
	zbx_jsonpath_segment_t		*segment;
	int				ret = FAIL, parsed_name = 0;
	const char			*end, *start = NULL;
	zbx_jsonpath_list_node_t	*head = NULL;

	for (end = list; ']' != *end || NULL != start; end++)
	{
		switch (*end)
		{
			case '\'':
			case '"':
				if (NULL == start)
				{
					start = end;
				}
				else if (*start == *end)
				{
					if (start + 1 == end)
					{
						ret = zbx_jsonpath_error(start);
						goto out;
					}

					head = jsonpath_list_append_name(head, start, (size_t)(end - start));
					parsed_name = 1;
					start = NULL;
				}
				break;
			case '\\':
				if (NULL == start || ('\\' != end[1] && *start != end[1]))
				{
					ret = zbx_jsonpath_error(end);
					goto out;
				}
				end++;
				break;
			case ' ':
			case '\t':
				break;
			case ',':
				if (NULL != start)
					break;

				if (0 == parsed_name)
				{
					ret = zbx_jsonpath_error(end);
					goto out;
				}
				parsed_name = 0;
				break;
			case '\0':
				ret = zbx_jsonpath_error(end);
				goto out;
			default:
				if (NULL == start)
				{
					ret = zbx_jsonpath_error(end);
					goto out;
				}
		}
	}

	if (0 == parsed_name)
	{
		ret = zbx_jsonpath_error(end);
		goto out;
	}

	segment = &jsonpath->segments[jsonpath->segments_num++];
	segment->type = ZBX_JSONPATH_SEGMENT_MATCH_LIST;
	segment->data.list.type = ZBX_JSONPATH_LIST_NAME;
	segment->data.list.values = head;

	if (NULL != head->next)
		jsonpath->definite = 0;

	head = NULL;
	*next = end;
	ret = SUCCEED;
out:
	if (NULL != head)
		jsonpath_list_free(head);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse a list of array indexes or range start:end values           *
 *          case when a single name is used                                   *
 *                                                                            *
 * Parameters: list     - [IN] the index list                                 *
 *             jsonpath - [IN/OUT] the jsonpath                               *
 *             next     - [OUT] a pointer to the next character after parsed  *
 *                              list                                          *
 *                                                                            *
 * Return value: SUCCEED - the list was parsed successfully                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_indexes(const char *list, zbx_jsonpath_t *jsonpath, const char **next)
{
	zbx_jsonpath_segment_t		*segment;
	const char			*end, *start = NULL;
	int				ret = FAIL, type = ZBX_JSONPATH_SEGMENT_UNKNOWN;
	unsigned int			flags = 0, parsed_index = 0;
	zbx_jsonpath_list_node_t	*head = NULL, *node;

	for (end = list; ; end++)
	{
		if (0 != isdigit((unsigned char)*end))
		{
			if (NULL == start)
				start = end;
			continue;
		}

		if ('-' == *end)
		{
			if (NULL != start)
			{
				ret = zbx_jsonpath_error(end);
				goto out;
			}
			start = end;
			continue;
		}

		if (NULL != start)
		{
			if ('-' == *start && end == start + 1)
			{
				ret = zbx_jsonpath_error(start);
				goto out;
			}

			head = jsonpath_list_append_index(head, atoi(start), type == ZBX_JSONPATH_SEGMENT_MATCH_LIST);
			start = NULL;
			parsed_index = 1;
		}

		if (']' == *end)
		{
			if (ZBX_JSONPATH_SEGMENT_MATCH_RANGE != type)
			{
				if (0 == parsed_index)
				{
					ret = zbx_jsonpath_error(end);
					goto out;
				}
			}
			else
				flags |= (parsed_index << 1);
			break;
		}

		if (':' == *end)
		{
			if (ZBX_JSONPATH_SEGMENT_UNKNOWN != type)
			{
				ret = zbx_jsonpath_error(end);
				goto out;
			}
			type = ZBX_JSONPATH_SEGMENT_MATCH_RANGE;
			flags |= parsed_index;
			parsed_index = 0;
		}
		else if (',' == *end)
		{
			if (ZBX_JSONPATH_SEGMENT_MATCH_RANGE == type || 0 == parsed_index)
			{
				ret = zbx_jsonpath_error(end);
				goto out;
			}
			type = ZBX_JSONPATH_SEGMENT_MATCH_LIST;
			parsed_index = 0;
		}
		else if (' ' != *end && '\t' != *end)
		{
			ret = zbx_jsonpath_error(end);
			goto out;
		}
	}

	segment = &jsonpath->segments[jsonpath->segments_num++];

	if (ZBX_JSONPATH_SEGMENT_MATCH_RANGE == type)
	{
		node = head;

		segment->type = ZBX_JSONPATH_SEGMENT_MATCH_RANGE;
		segment->data.range.flags = flags;
		if (0 != (flags & 0x02))
		{
			memcpy(&segment->data.range.end, node->data, sizeof(int));
			node = node->next;
		}
		else
			segment->data.range.end = 0;

		if (0 != (flags & 0x01))
			memcpy(&segment->data.range.start, node->data, sizeof(int));
		else
			segment->data.range.start = 0;

		jsonpath->definite = 0;
	}
	else
	{
		segment->type = ZBX_JSONPATH_SEGMENT_MATCH_LIST;
		segment->data.list.type = ZBX_JSONPATH_LIST_INDEX;
		segment->data.list.values = head;

		if (NULL != head->next)
			jsonpath->definite = 0;

		head = NULL;
	}

	*next = end;
	ret = SUCCEED;
out:
	jsonpath_list_free(head);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse jsonpath bracket notation segment                           *
 *                                                                            *
 * Parameters: start     - [IN] the segment start                             *
 *             jsonpath  - [IN/OUT] the jsonpath                              *
 *             next      - [OUT] a pointer to the next character after parsed *
 *                               segment                                      *
 *                                                                            *
 * Return value: SUCCEED - the segment was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_bracket_segment(const char *start, zbx_jsonpath_t *jsonpath, const char **next)
{
	const char	*ptr = start;
	int		ret;

	SKIP_WHITESPACE(ptr);

	if ('?' == *ptr)
	{
		ret = jsonpath_parse_expression(ptr + 1, jsonpath, next);
	}
	else if ('*' == *ptr)
	{
		jsonpath->segments[jsonpath->segments_num++].type = ZBX_JSONPATH_SEGMENT_MATCH_ALL;
		jsonpath->definite = 0;
		*next = ptr + 1;
		ret = SUCCEED;
	}
	else if ('\'' == *ptr || '"' == *ptr)
	{
		ret = jsonpath_parse_names(ptr, jsonpath, next);
	}
	else if (0 != isdigit((unsigned char)*ptr) || ':' == *ptr || '-' == *ptr)
	{
		ret = jsonpath_parse_indexes(ptr, jsonpath, next);
	}
	else
		ret = zbx_jsonpath_error(ptr);

	if (SUCCEED == ret)
	{
		ptr = *next;
		SKIP_WHITESPACE(ptr);

		if (']' != *ptr)
			return zbx_jsonpath_error(ptr);

		*next = ptr + 1;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse jsonpath dot notation segment                               *
 *                                                                            *
 * Parameters: start     - [IN] the segment start                             *
 *             jsonpath  - [IN/OUT] the jsonpath                              *
 *             next      - [OUT] a pointer to the next character after parsed *
 *                               segment                                      *
 *                                                                            *
 * Return value: SUCCEED - the segment was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_dot_segment(const char *start, zbx_jsonpath_t *jsonpath, const char **next)
{
	zbx_jsonpath_segment_t	*segment;
	const char		*ptr;
	size_t			len;

	segment = &jsonpath->segments[jsonpath->segments_num];
	jsonpath->segments_num++;

	if ('*' == *start)
	{
		jsonpath->definite = 0;
		segment->type = ZBX_JSONPATH_SEGMENT_MATCH_ALL;
		*next = start + 1;
		return SUCCEED;
	}

	for (ptr = start; 0 != isalnum((unsigned char)*ptr) || '_' == *ptr || '$' == *ptr;)
		ptr++;

	len = (size_t)(ptr - start);

	if ('(' == *ptr)
	{
		const char	*end = ptr + 1;

		SKIP_WHITESPACE(end);
		if (')' == *end)
		{
			if (ZBX_CONST_STRLEN("min") == len && 0 == strncmp(start, "min", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_MIN;
			}
			else if (ZBX_CONST_STRLEN("max") == len && 0 == strncmp(start, "max", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_MAX;
			}
			else if (ZBX_CONST_STRLEN("avg") == len && 0 == strncmp(start, "avg", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_AVG;
			}
			else if (ZBX_CONST_STRLEN("length") == len && 0 == strncmp(start, "length", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_LENGTH;
			}
			else if (ZBX_CONST_STRLEN("first") == len && 0 == strncmp(start, "first", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_FIRST;
				jsonpath->first_match = 1;
			}
			else if (ZBX_CONST_STRLEN("sum") == len && 0 == strncmp(start, "sum", len))
			{
				segment->data.function.type = ZBX_JSONPATH_FUNCTION_SUM;
			}
			else
				return zbx_jsonpath_error(start);

			segment->type = ZBX_JSONPATH_SEGMENT_FUNCTION;
			*next = end + 1;
			return SUCCEED;
		}
	}

	if (0 < len)
	{
		segment->type = ZBX_JSONPATH_SEGMENT_MATCH_LIST;
		segment->data.list.type = ZBX_JSONPATH_LIST_NAME;
		segment->data.list.values = jsonpath_list_create_node(len + 1);
		zbx_strlcpy(segment->data.list.values->data, start, len + 1);
		segment->data.list.values->next = NULL;
		*next = start + len;
		return SUCCEED;
	}

	return zbx_jsonpath_error(start);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse jsonpath name reference ~                                   *
 *                                                                            *
 * Parameters: start     - [IN] the segment start                             *
 *             jsonpath  - [IN/OUT] the jsonpath                              *
 *             next      - [OUT] a pointer to the next character after parsed *
 *                               segment                                      *
 *                                                                            *
 * Return value: SUCCEED - the name reference was parsed                      *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_parse_name_reference(const char *start, zbx_jsonpath_t *jsonpath, const char **next)
{
	zbx_jsonpath_segment_t	*segment;

	segment = &jsonpath->segments[jsonpath->segments_num];
	jsonpath->segments_num++;
	segment->type = ZBX_JSONPATH_SEGMENT_FUNCTION;
	segment->data.function.type = ZBX_JSONPATH_FUNCTION_NAME;
	*next = start + 1;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform the rest of jsonpath query on json data                   *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             obj        - [IN] the json object                              *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - the data were queried successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_query_contents(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *obj, int path_depth)
{
	int	ret;

	switch (obj->type)
	{
		case ZBX_JSON_TYPE_OBJECT:
			ret = jsonpath_query_object(ctx, obj, path_depth);
			break;
		case ZBX_JSON_TYPE_ARRAY:
			ret = jsonpath_query_array(ctx, obj, path_depth);
			break;
		default:
			ret = SUCCEED;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: query next segment                                                *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             name       - [IN] name or index of the next json element       *
 *             obj        - [IN] the current json object                      *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - the segment was queried successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_query_next_segment(zbx_jsonpath_context_t *ctx, const char *name, const zbx_jsonobj_t *obj,
		int path_depth)
{
	/* check if jsonpath end has been reached, so we have found matching data */
	/* (functions are processed afterwards)                                   */
	if (++path_depth == ctx->path->segments_num ||
			ZBX_JSONPATH_SEGMENT_FUNCTION == ctx->path->segments[path_depth].type)
	{
		if (1 == ctx->path->first_match)
			ctx->found = 1;

		zbx_vector_jsonobj_ref_add_object(&ctx->objects, name, obj);
		return SUCCEED;
	}

	/* continue by matching found object against the rest of jsonpath segments */
	return jsonpath_query_contents(ctx, obj, path_depth);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match object contents against jsonpath segment name list          *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             parent     - [IN] parent json object                           *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - no errors, failed match is not an error            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_match_name(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *parent, int path_depth)
{
	const zbx_jsonpath_segment_t	*segment = &ctx->path->segments[path_depth];
	zbx_jsonpath_list_node_t	*node;
	zbx_jsonobj_el_t		el_local, *el;

	/* object contents can match only name list */
	if (ZBX_JSONPATH_LIST_NAME != segment->data.list.type)
		return SUCCEED;

	for (node = segment->data.list.values; NULL != node; node = node->next)
	{
		el_local.name = node->data;
		if (NULL != (el = (zbx_jsonobj_el_t *)zbx_hashset_search(&parent->data.object, &el_local)))
		{
			if (FAIL == jsonpath_query_next_segment(ctx, el->name, &el->value, path_depth))
				return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract value from json data by the specified path                *
 *                                                                            *
 * Parameters: obj   - [IN] the parent object                                 *
 *             path  - [IN] the jsonpath                                      *
 *             value - [OUT] the extracted value                              *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - in the case of errors or if there was no value to  *
 *                         extract                                            *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_extract_value(const zbx_jsonobj_t *obj, zbx_jsonpath_t *path, zbx_variant_t *value)
{
	int				ret = FAIL;
	zbx_jsonpath_context_t		ctx;

	ctx.path = path;
	ctx.found = 0;
	ctx.root = obj;
	zbx_vector_jsonobj_ref_create(&ctx.objects);
	ctx.index = NULL;

	if (SUCCEED == jsonpath_query_contents(&ctx, obj, 0) && 0 != ctx.objects.values_num)
	{
		char	*str = NULL;
		size_t	str_alloc = 0, str_offset = 0;

		if (ZBX_JSON_TYPE_NULL != ctx.objects.values[0].value->type)
		{
			jsonpath_str_copy_value(&str, &str_alloc, &str_offset, ctx.objects.values[0].value);
			zbx_variant_set_str(value, str);
		}
		else
			zbx_variant_set_none(value);

		ret = SUCCEED;
	}

	jsonpath_ctx_clear(&ctx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert jsonpath expression to text format                        *
 *                                                                            *
 * Parameters: expression - [IN] the jsonpath expression                      *
 *                                                                            *
 * Return value: The converted expression, must be freed by the caller.       *
 *                                                                            *
 * Comments: This function is used to include expression in error message.    *
 *                                                                            *
 ******************************************************************************/
static char	*jsonpath_expression_to_str(zbx_jsonpath_expression_t *expression)
{
	int			i;
	char			*str = NULL;
	size_t			str_alloc = 0, str_offset = 0;

	for (i = 0; i < expression->tokens.values_num; i++)
	{
		zbx_jsonpath_token_t	*token = (zbx_jsonpath_token_t *)expression->tokens.values[i];

		if (0 != i)
			zbx_strcpy_alloc(&str, &str_alloc, &str_offset, ",");

		switch (token->type)
		{
			case ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE:
			case ZBX_JSONPATH_TOKEN_PATH_RELATIVE:
			case ZBX_JSONPATH_TOKEN_CONST_STR:
			case ZBX_JSONPATH_TOKEN_CONST_NUM:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, token->text);
				break;
			case ZBX_JSONPATH_TOKEN_PAREN_LEFT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "(");
				break;
			case ZBX_JSONPATH_TOKEN_PAREN_RIGHT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, ")");
				break;
			case ZBX_JSONPATH_TOKEN_OP_PLUS:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "+");
				break;
			case ZBX_JSONPATH_TOKEN_OP_MINUS:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "-");
				break;
			case ZBX_JSONPATH_TOKEN_OP_MULT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "*");
				break;
			case ZBX_JSONPATH_TOKEN_OP_DIV:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "/");
				break;
			case ZBX_JSONPATH_TOKEN_OP_EQ:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "==");
				break;
			case ZBX_JSONPATH_TOKEN_OP_NE:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "!=");
				break;
			case ZBX_JSONPATH_TOKEN_OP_GT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, ">");
				break;
			case ZBX_JSONPATH_TOKEN_OP_GE:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, ">=");
				break;
			case ZBX_JSONPATH_TOKEN_OP_LT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "<");
				break;
			case ZBX_JSONPATH_TOKEN_OP_LE:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "<=");
				break;
			case ZBX_JSONPATH_TOKEN_OP_NOT:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "!");
				break;
			case ZBX_JSONPATH_TOKEN_OP_AND:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "&&");
				break;
			case ZBX_JSONPATH_TOKEN_OP_OR:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "||");
				break;
			case ZBX_JSONPATH_TOKEN_OP_REGEXP:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "=~");
				break;
			default:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "?");
				break;
		}
	}

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set jsonpath expression error message                             *
 *                                                                            *
 * Parameters: expression - [IN] the jsonpath expression                      *
 *                                                                            *
 * Comments: This function is used to set error message when expression       *
 *           evaluation fails                                                 *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_set_expression_error(zbx_jsonpath_expression_t *expression)
{
	char	*text;

	text = jsonpath_expression_to_str(expression);

	if (NULL != text)
		zbx_set_json_strerror("invalid compiled expression: %s", text);
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_free(text);
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert variant value to 'boolean' (1, 0)                         *
 *                                                                            *
 * Parameters: value - [IN/OUT] the value                                     *
 *                                                                            *
 * Comments: This function is used to cast operand to boolean value for       *
 *           boolean functions (and, or, negation).                           *
 *                                                                            *
 ******************************************************************************/
static void	jsonpath_variant_to_boolean(zbx_variant_t *value)
{
	double	res;

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			res = (0 != value->data.ui64 ? 1 : 0);
			break;
		case ZBX_VARIANT_DBL:
			res = (SUCCEED != zbx_double_compare(value->data.dbl, 0.0) ? 1 : 0);
			break;
		case ZBX_VARIANT_STR:
			res = ('\0' != *value->data.str ? 1 : 0);
			break;
		case ZBX_VARIANT_NONE:
			res = 0;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			res = 0;
			break;
	}

	zbx_variant_clear(value);
	zbx_variant_set_dbl(value, res);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match text against regular expression                             *
 *                                                                            *
 * Parameters: text    - [IN] the text to match                               *
 *             pattern - [IN] the regular expression                          *
 *             result  - [OUT] 1.0 if match succeeded, 0.0 otherwise          *
 *                                                                            *
 * Return value: SUCCEED - regular expression match was performed             *
 *               FAIL    - regular expression error                           *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_regexp_match(const char *text, const char *pattern, double *result)
{
	zbx_regexp_t	*rxp;
	char		*error = NULL;

	if (FAIL == zbx_regexp_compile(pattern, &rxp, &error))
	{
		zbx_set_json_strerror("invalid regular expression in JSON path: %s", error);
		zbx_free(error);
		return FAIL;
	}
	*result = (0 == zbx_regexp_match_precompiled(text, rxp) ? 1.0 : 0.0);
	zbx_regexp_free(rxp);

	return SUCCEED;
}

static int	jsonpath_prepare_numeric_arg(zbx_variant_t *var)
{
	if (SUCCEED != zbx_variant_convert(var, ZBX_VARIANT_DBL))
	{
		zbx_set_json_strerror("invalid operand '%s' of type '%s'", zbx_variant_value_desc(var),
				zbx_variant_type_desc(var));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match json array element/object value against jsonpath expression *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             name       - [IN] name or index of the next json element       *
 *             obj        - [IN] the jsonobject to match                      *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - no errors, failed match is not an error            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_match_expression(zbx_jsonpath_context_t *ctx, const char *name, const zbx_jsonobj_t *obj,
		int path_depth)
{
	zbx_vector_var_t	stack;
	int			i, ret = SUCCEED;
	zbx_jsonpath_segment_t	*segment;
	zbx_variant_t		value, *right;
	double			res;

	zbx_vector_var_create(&stack);

	segment = &ctx->path->segments[path_depth];

	for (i = 0; i < segment->data.expression.tokens.values_num; i++)
	{
		zbx_variant_t		*left;
		zbx_jsonpath_token_t	*token = (zbx_jsonpath_token_t *)segment->data.expression.tokens.values[i];

		if (ZBX_JSONPATH_TOKEN_GROUP_OPERATOR2 == jsonpath_token_group(token->type))
		{
			if (2 > stack.values_num)
			{
				jsonpath_set_expression_error(&segment->data.expression);
				ret = FAIL;
				goto out;
			}

			left = &stack.values[stack.values_num - 2];
			right = &stack.values[stack.values_num - 1];

			switch (token->type)
			{
				case ZBX_JSONPATH_TOKEN_OP_PLUS:
					if (SUCCEED != jsonpath_prepare_numeric_arg(left) ||
							SUCCEED != jsonpath_prepare_numeric_arg(right))
					{
						ret = FAIL;
						goto out;
					}
					left->data.dbl += right->data.dbl;
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_MINUS:
					if (SUCCEED != jsonpath_prepare_numeric_arg(left) ||
							SUCCEED != jsonpath_prepare_numeric_arg(right))
					{
						ret = FAIL;
						goto out;
					}
					left->data.dbl -= right->data.dbl;
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_MULT:
					if (SUCCEED != jsonpath_prepare_numeric_arg(left) ||
							SUCCEED != jsonpath_prepare_numeric_arg(right))
					{
						ret = FAIL;
						goto out;
					}
					left->data.dbl *= right->data.dbl;
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_DIV:
					if (SUCCEED != jsonpath_prepare_numeric_arg(left) ||
							SUCCEED != jsonpath_prepare_numeric_arg(right))
					{
						ret = FAIL;
						goto out;
					}
					left->data.dbl /= right->data.dbl;
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_EQ:
					res = (0 == zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_NE:
					res = (0 != zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_GT:
					res = (0 < zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_GE:
					res = (0 <= zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_LT:
					res = (0 > zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_LE:
					res = (0 >= zbx_variant_compare(left, right) ? 1.0 : 0.0);
					zbx_variant_clear(left);
					zbx_variant_clear(right);
					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_AND:
					jsonpath_variant_to_boolean(left);
					jsonpath_variant_to_boolean(right);
					if (SUCCEED != zbx_double_compare(left->data.dbl, 0.0) &&
							SUCCEED != zbx_double_compare(right->data.dbl, 0.0))
					{
						res = 1.0;
					}
					else
						res = 0.0;
					zbx_variant_set_dbl(left, res);
					zbx_variant_clear(right);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_OR:
					jsonpath_variant_to_boolean(left);
					jsonpath_variant_to_boolean(right);
					if (SUCCEED != zbx_double_compare(left->data.dbl, 0.0) ||
							SUCCEED != zbx_double_compare(right->data.dbl, 0.0))
					{
						res = 1.0;
					}
					else
					{
						res = 0.0;
					}
					zbx_variant_set_dbl(left, res);
					zbx_variant_clear(right);
					stack.values_num--;
					break;
				case ZBX_JSONPATH_TOKEN_OP_REGEXP:
					if (FAIL == zbx_variant_convert(left, ZBX_VARIANT_STR) ||
							FAIL == zbx_variant_convert(right, ZBX_VARIANT_STR))
					{
						res = 0.0;
						ret = SUCCEED;
					}
					else
					{
						ret = jsonpath_regexp_match(left->data.str, right->data.str, &res);
					}

					zbx_variant_clear(left);
					zbx_variant_clear(right);

					if (FAIL == ret)
						goto out;

					zbx_variant_set_dbl(left, res);
					stack.values_num--;
					break;
				default:
					break;
			}
			continue;
		}

		switch (token->type)
		{
			case ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE:
				if (FAIL == jsonpath_extract_value(ctx->root, token->path, &value))
					zbx_variant_set_none(&value);
				zbx_vector_var_append_ptr(&stack, &value);
				break;
			case ZBX_JSONPATH_TOKEN_PATH_RELATIVE:
				/* relative path can be applied only to array or object */
				if (ZBX_JSON_TYPE_ARRAY != obj->type && ZBX_JSON_TYPE_OBJECT != obj->type)
					goto out;

				if (FAIL == jsonpath_extract_value(obj, token->path, &value))
					zbx_variant_set_none(&value);
				zbx_vector_var_append_ptr(&stack, &value);
				break;
			case ZBX_JSONPATH_TOKEN_CONST_STR:
				zbx_variant_set_str(&value, zbx_strdup(NULL, token->text));
				zbx_vector_var_append_ptr(&stack, &value);
				break;
			case ZBX_JSONPATH_TOKEN_CONST_NUM:
				zbx_variant_set_dbl(&value, atof(token->text));
				zbx_vector_var_append_ptr(&stack, &value);
				break;
			case ZBX_JSONPATH_TOKEN_OP_NOT:
				if (1 > stack.values_num)
				{
					jsonpath_set_expression_error(&segment->data.expression);
					ret = FAIL;
					goto out;
				}
				right = &stack.values[stack.values_num - 1];
				jsonpath_variant_to_boolean(right);
				right->data.dbl = 1 - right->data.dbl;
				break;
			default:
				break;
		}
	}

	if (1 != stack.values_num)
	{
		jsonpath_set_expression_error(&segment->data.expression);
		goto out;
	}

	jsonpath_variant_to_boolean(&stack.values[0]);
	if (SUCCEED != zbx_double_compare(stack.values[0].data.dbl, 0.0))
		ret = jsonpath_query_next_segment(ctx, name, obj, path_depth);
out:
	for (i = 0; i < stack.values_num; i++)
		zbx_variant_clear(&stack.values[i]);
	zbx_vector_var_destroy(&stack);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: query indexed object fields for jsonpath segment match            *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             index      - [IN] the indexing hashset                         *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - the object was queried successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_match_indexed_expression(zbx_jsonpath_context_t *ctx, zbx_hashset_t *index, int path_depth)
{
	zbx_jsonpath_segment_t	*segment = &ctx->path->segments[path_depth];
	zbx_jsonobj_index_el_t	index_local, *el;

	index_local.value = segment->data.expression.value_token->text;

	if (NULL != (el = (zbx_jsonobj_index_el_t *)zbx_hashset_search(index, &index_local)))
	{
		int	i;

		for (i = 0; i < el->objects.values_num; i++)
		{
			jsonpath_match_expression(ctx, el->objects.values[i].name, el->objects.values[i].value,
					path_depth);
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: query object fields for jsonpath segment match                    *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             obj        - [IN] the json object to query                     *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - the object was queried successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_query_object(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *obj, int path_depth)
{
	const zbx_jsonpath_segment_t	*segment;
	int				ret = SUCCEED;
	zbx_hashset_const_iter_t	iter;
	const zbx_jsonobj_el_t		*el;

	segment = &ctx->path->segments[path_depth];

	if (ZBX_JSONPATH_SEGMENT_MATCH_LIST == segment->type)
	{
		ret = jsonpath_match_name(ctx, obj, path_depth);
		if (FAIL == ret || 1 != segment->detached)
			return ret;
	}
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	else if (ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION == segment->type && NULL != segment->data.expression.index_token)
	{
		zbx_hashset_t	*index;

		if (NULL != (index = jsonpath_index_get(ctx->index, obj, segment->data.expression.index_token)))
			return jsonpath_match_indexed_expression(ctx, index, path_depth);

	}
#endif
	zbx_hashset_const_iter_reset(&obj->data.object, &iter);
	while (NULL != (el = (const zbx_jsonobj_el_t *)zbx_hashset_const_iter_next(&iter)) && SUCCEED == ret &&
			0 == ctx->found)
	{
		switch (segment->type)
		{
			case ZBX_JSONPATH_SEGMENT_MATCH_ALL:
				ret = jsonpath_query_next_segment(ctx, el->name, &el->value, path_depth);
				break;
			case ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION:
				ret = jsonpath_match_expression(ctx, el->name, &el->value, path_depth);
				break;
			default:
				break;
		}

		if (1 == segment->detached)
			ret = jsonpath_query_contents(ctx, &el->value, path_depth);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: array elements against segment index list                         *
 *                                                                            *
 * Parameters: ctx          - [IN] the jsonpath query context                 *
 *             parent       - [IN] the json array                             *
 *             path_depth   - [IN] the jsonpath segment to match              *
 *                                                                            *
 * Return value: SUCCEED - no errors, failed match is not an error            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_match_index(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *parent, int path_depth)
{
	const zbx_jsonpath_segment_t	*segment = &ctx->path->segments[path_depth];
	const zbx_jsonpath_list_node_t	*node;

	/* array contents can match only index list */
	if (ZBX_JSONPATH_LIST_INDEX != segment->data.list.type)
		return SUCCEED;

	for (node = segment->data.list.values; NULL != node; node = node->next)
	{
		int	query_index;

		memcpy(&query_index, node->data, sizeof(query_index));

		if (0 > query_index)
			query_index += parent->data.array.values_num;

		if (query_index >= 0 && query_index < parent->data.array.values_num)
		{
			char	name[MAX_ID_LEN + 1];

			zbx_snprintf(name, sizeof(name), "%d", query_index);

			if (FAIL == jsonpath_query_next_segment(ctx, name, parent->data.array.values[query_index],
					path_depth))
			{
				return FAIL;
			}
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match array elements against segment index range                  *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             parent     - [IN] the json array                               *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - no errors, failed match is not an error            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_match_range(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *parent, int path_depth)
{
	int				i, start_index, end_index, values_num;
	const zbx_jsonpath_segment_t	*segment = &ctx->path->segments[path_depth];

	values_num = parent->data.array.values_num;
	start_index = (0 != (segment->data.range.flags & 0x01) ? segment->data.range.start : 0);
	end_index = (0 != (segment->data.range.flags & 0x02) ? MIN(segment->data.range.end, values_num) : values_num);

	if (0 > start_index)
	{
		if (0 > (start_index = start_index + values_num))
			start_index = 0;
	}
	if (0 > end_index)
	{
		if (0 > (end_index = end_index + values_num))
			return SUCCEED;
	}

	for (i = start_index; i < end_index; i++)
	{
		char	name[MAX_ID_LEN + 1];

		zbx_snprintf(name, sizeof(name), "%d", i);

		if (FAIL == jsonpath_query_next_segment(ctx, name, parent->data.array.values[i], path_depth))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: query json array for jsonpath segment match                       *
 *                                                                            *
 * Parameters: ctx        - [IN] the jsonpath query context                   *
 *             array      - [IN] the json array to query                      *
 *             path_depth - [IN] the jsonpath segment to match                *
 *                                                                            *
 * Return value: SUCCEED - the array was queried successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_query_array(zbx_jsonpath_context_t *ctx, const zbx_jsonobj_t *array, int path_depth)
{
	int			ret = SUCCEED, i;
	zbx_jsonpath_segment_t	*segment;

	segment = &ctx->path->segments[path_depth];

	switch (segment->type)
	{
		case ZBX_JSONPATH_SEGMENT_MATCH_LIST:
			ret = jsonpath_match_index(ctx, array, path_depth);
			if (FAIL == ret || 1 != segment->detached)
				return ret;
			break;
		case ZBX_JSONPATH_SEGMENT_MATCH_RANGE:
			ret = jsonpath_match_range(ctx, array, path_depth);
			if (FAIL == ret || 1 != segment->detached)
				return ret;
			break;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
		case ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION:
			if (NULL != segment->data.expression.index_token)
			{
				zbx_hashset_t	*index;

				if (NULL != (index = jsonpath_index_get(ctx->index, array,
						segment->data.expression.index_token)))
				{
					return jsonpath_match_indexed_expression(ctx, index, path_depth);
				}
			}
			break;
#endif
		default:
			break;
	}

	for (i = 0; i < array->data.array.values_num && SUCCEED == ret && 0 == ctx->found; i++)
	{
		char	name[MAX_ID_LEN + 1];

		zbx_snprintf(name, sizeof(name), "%d", i);

		switch (segment->type)
		{
			case ZBX_JSONPATH_SEGMENT_MATCH_ALL:
				ret = jsonpath_query_next_segment(ctx, name, array->data.array.values[i], path_depth);
				break;
			case ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION:
				ret = jsonpath_match_expression(ctx, name, array->data.array.values[i], path_depth);
				break;
			default:
				break;
		}

		if (1 == segment->detached)
			ret = jsonpath_query_contents(ctx, array->data.array.values[i], path_depth);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get numeric value from json data                                  *
 *                                                                            *
 * Parameters: obj   - [IN] json object                                       *
 *             value - [OUT] the extracted value                              *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - the pointer was not pointing at numeric value      *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_get_numeric_value(const zbx_jsonobj_t *obj, double *value)
{
	switch (obj->type)
	{
		case ZBX_JSON_TYPE_NUMBER:
			*value = obj->data.number;
			return SUCCEED;
		case ZBX_JSON_TYPE_STRING:
			if (SUCCEED == zbx_is_double(obj->data.string, value))
				return SUCCEED;
			zbx_set_json_strerror("array value is not a number or out of range: %s", obj->data.string);
			return FAIL;
		default:
			zbx_set_json_strerror("array value type is not a number or string");
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get value from json data                                          *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - the pointer was not pointing at numeric value      *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_str_copy_value(char **str, size_t *str_alloc, size_t *str_offset, const zbx_jsonobj_t *obj)
{
	switch (obj->type)
	{
		case ZBX_JSON_TYPE_STRING:
			zbx_strcpy_alloc(str, str_alloc, str_offset, obj->data.string);
			return SUCCEED;
			break;
		default:
			return zbx_jsonobj_to_string(str, str_alloc, str_offset, obj);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply jsonpath function to the extracted object list              *
 *                                                                            *
 * Parameters: in            - [IN] the matched objects                       *
 *             type          - [IN] the function type                         *
 *             definite_path - [IN/OUT] 1 - if the path is definite (pointing *
 *                                          at single object)                 *
 *                                      0 - otherwise                         *
 *             out           - [OUT] the result objects                       *
 *                                                                            *
 * Return value: SUCCEED - the function was applied successfully              *
 *               FAIL    - invalid input data for the function or internal    *
 *                         json error                                         *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_apply_function(const zbx_vector_jsonobj_ref_t *in, zbx_jsonpath_function_type_t type,
		int *definite_path, zbx_vector_jsonobj_ref_t *out)
{
	int				i, ret = FAIL;
	double				result;
	zbx_vector_jsonobj_ref_t	tmp;
	char				buffer[64];

	zbx_vector_jsonobj_ref_create(&tmp);

	if (ZBX_JSONPATH_FUNCTION_NAME == type)
	{
		if (0 == in->values_num)
		{
			zbx_set_json_strerror("cannot extract name from empty result");
			goto out;
		}

		for (i = 0; i < in->values_num; i++)
			zbx_vector_jsonobj_ref_add_string(out, "", in->values[i].name);

		ret = SUCCEED;
		goto out;
	}

	/* convert definite path result to object array if possible */
	if (0 != *definite_path)
	{
		if (0 == in->values_num || ZBX_JSON_TYPE_ARRAY != in->values[0].value->type)
		{
			/* all functions can be applied only to arrays        */
			/* attempt to apply a function to non-array will fail */
			zbx_set_json_strerror("cannot apply function to non-array JSON element");
			goto out;
		}

		for (i = 0; i < in->values[0].value->data.array.values_num; i++)
		{
			char	name[MAX_ID_LEN + 1];

			zbx_snprintf(name, sizeof(name), "%d", i);
			zbx_vector_jsonobj_ref_add_object(&tmp, name, in->values[0].value->data.array.values[i]);
		}

		in = &tmp;
		*definite_path = 0;
	}

	if (ZBX_JSONPATH_FUNCTION_LENGTH == type)
	{
		zbx_snprintf(buffer, sizeof(buffer), "%d", in->values_num);
		zbx_vector_jsonobj_ref_add_string(out, "", buffer);
		*definite_path = 1;
		ret = SUCCEED;
		goto out;
	}

	if (ZBX_JSONPATH_FUNCTION_FIRST == type)
	{
		if (0 < in->values_num)
			zbx_vector_jsonobj_ref_add(out, &in->values[0]);

		*definite_path = 1;
		ret = SUCCEED;
		goto out;
	}

	if (0 == in->values_num)
	{
		zbx_set_json_strerror("cannot apply aggregation function to empty array");
		goto out;
	}

	if (FAIL == jsonpath_get_numeric_value(in->values[0].value, &result))
		goto out;

	for (i = 1; i < in->values_num; i++)
	{
		double	value;

		if (FAIL == jsonpath_get_numeric_value(in->values[i].value, &value))
			goto out;

		switch (type)
		{
			case ZBX_JSONPATH_FUNCTION_MIN:
				if (value < result)
					result = value;
				break;
			case ZBX_JSONPATH_FUNCTION_MAX:
				if (value > result)
					result = value;
				break;
			case ZBX_JSONPATH_FUNCTION_AVG:
			case ZBX_JSONPATH_FUNCTION_SUM:
				result += value;
				break;
			default:
				break;
		}
	}

	if (ZBX_JSONPATH_FUNCTION_AVG == type)
		result /= in->values_num;

	zbx_print_double(buffer, sizeof(buffer), result);
	if (SUCCEED != zbx_is_double(buffer, NULL))
	{
		zbx_set_json_strerror("invalid function result: %s", buffer);
		goto out;
	}

	zbx_del_zeros(buffer);
	zbx_vector_jsonobj_ref_add_string(out, "", buffer);
	*definite_path = 1;
	ret = SUCCEED;
out:
	jsonobj_clear_ref_vector(&tmp);
	zbx_vector_jsonobj_ref_destroy(&tmp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply jsonpath function to the extracted object list              *
 *                                                                            *
 * Parameters: ctx           - [IN] the jsonpath query context                *
 *             path_depth    - [IN] the jsonpath segment to match             *
 *             definite_path - [IN/OUT]                                       *
 *             out           - [OUT] the result object                        *
 *                                                                            *
 * Return value: SUCCEED - the function was applied successfully              *
 *               FAIL    - invalid input data for the function or internal    *
 *                         json error                                         *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_apply_functions(zbx_jsonpath_context_t *ctx, int path_depth, int *definite_path,
		zbx_vector_jsonobj_ref_t *out)
{
	int				ret;
	zbx_vector_jsonobj_ref_t	in;

	zbx_vector_jsonobj_ref_create(&in);

	/* when functions are applied directly to the json document (at the start of the jsonpath ) */
	/* it makes all document as input object                                                    */
	if (0 == path_depth)
		zbx_vector_jsonobj_ref_add_object(&in, "", ctx->root);
	else
		zbx_vector_jsonobj_ref_copy(&in, &ctx->objects);

	for (;;)
	{
		ret = jsonpath_apply_function(&in, ctx->path->segments[path_depth++].data.function.type,
				definite_path, out);

		jsonobj_clear_ref_vector(&in);

		if (SUCCEED != ret || path_depth == ctx->path->segments_num)
			break;

		zbx_vector_jsonobj_ref_copy(&in, out);
		jsonobj_clear_ref_vector(out);
	}

	jsonobj_clear_ref_vector(&in);
	zbx_vector_jsonobj_ref_destroy(&in);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format query result, depending on jsonpath type                   *
 *                                                                            *
 * Parameters: objects       - [IN] the matched json refs (name, value)       *
 *             definite_path - [IN] the jsonpath definite flag                *
 *             output        - [OUT] the output value                         *
 *                                                                            *
 * Return value: SUCCEED - the result was formatted successfully              *
 *               FAIL    - invalid result data (internal json error)          *
 *                                                                            *
 ******************************************************************************/
static int	jsonpath_format_query_result(const zbx_vector_jsonobj_ref_t *objects, int definite_path, char **output)
{
	size_t	output_offset = 0, output_alloc;
	int	i;
	char	delim;

	if (0 == objects->values_num)
		return SUCCEED;

	if (1 == definite_path)
		return jsonpath_str_copy_value(output, &output_alloc, &output_offset, objects->values[0].value);

	/* reserve 32 bytes per returned object plus array start/end [] and terminating zero */
	output_alloc = (size_t)objects->values_num * 32 + 3;
	*output = (char *)zbx_malloc(NULL, output_alloc);

	delim = '[';

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_chrcpy_alloc(output, &output_alloc, &output_offset, delim);
		zbx_jsonobj_to_string(output, &output_alloc, &output_offset, objects->values[i].value);
		delim = ',';
	}

	zbx_chrcpy_alloc(output, &output_alloc, &output_offset, ']');

	return SUCCEED;
}

void	zbx_jsonpath_clear(zbx_jsonpath_t *jsonpath)
{
	int	i;

	for (i = 0; i < jsonpath->segments_num; i++)
		jsonpath_segment_clear(&jsonpath->segments[i]);

	zbx_free(jsonpath->segments);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compile jsonpath to be used in queries                            *
 *                                                                            *
 * Parameters: path     - [IN] the path to parse                              *
 *             jsonpath - [IN/OUT] the compiled jsonpath                      *
 *                                                                            *
 * Return value: SUCCEED - the segment was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_jsonpath_compile(const char *path, zbx_jsonpath_t *jsonpath)
{
	int				ret = FAIL;
	const char			*ptr = path, *next;
	zbx_jsonpath_segment_type_t	segment_type, last_segment_type = ZBX_JSONPATH_SEGMENT_UNKNOWN;
	zbx_jsonpath_t			jpquery;

	if ('$' != *ptr || '\0' == ptr[1])
	{
		zbx_set_json_strerror("JSONPath query must start with the root object/element $.");
		return FAIL;
	}

	memset(&jpquery, 0, sizeof(zbx_jsonpath_t));
	jsonpath_reserve(&jpquery, 4);
	jpquery.definite = 1;
	jpquery.first_match = 0;

	for (ptr++; '\0' != *ptr; ptr = next)
	{
		char	prefix;

		jsonpath_reserve(&jpquery, 1);

		if ('.' == (prefix = *ptr))
		{
			if ('.' == *(++ptr))
			{
				/* mark next segment as detached */
				zbx_jsonpath_segment_t	*segment = &jpquery.segments[jpquery.segments_num];

				if (1 != segment->detached)
				{
					segment->detached = 1;
					jpquery.definite = 0;
					ptr++;
				}
			}

			switch (*ptr)
			{
				case '[':
					prefix = *ptr;
					break;
				case '\0':
				case '.':
					prefix = 0;
					break;
			}
		}

		switch (prefix)
		{
			case '.':
				ret = jsonpath_parse_dot_segment(ptr, &jpquery, &next);
				break;
			case '[':
				ret = jsonpath_parse_bracket_segment(ptr + 1, &jpquery, &next);
				break;
			case '~':
				ret = jsonpath_parse_name_reference(ptr, &jpquery, &next);
				break;
			default:
				ret = zbx_jsonpath_error(ptr);
				break;
		}

		if (SUCCEED != ret)
			break;

		/* function segments can be followed only by function segments */
		segment_type = jpquery.segments[jpquery.segments_num - 1].type;
		if (ZBX_JSONPATH_SEGMENT_FUNCTION == last_segment_type && ZBX_JSONPATH_SEGMENT_FUNCTION != segment_type)
		{
			ret = zbx_jsonpath_error(ptr);
			break;
		}
		last_segment_type = segment_type;
	}

	if (SUCCEED == ret && 0 == jpquery.segments_num)
		ret = zbx_jsonpath_error(ptr);

	if (SUCCEED == ret)
	{
		jpquery.first_match |= jpquery.definite;
		*jsonpath = jpquery;
	}
	else
		zbx_jsonpath_clear(&jpquery);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform jsonpath query on the specified json data                 *
 *                                                                            *
 * Parameters: jp     - [IN] the json data                                    *
 *             path   - [IN] the jsonpath                                     *
 *             output - [OUT] the output value                                *
 *                                                                            *
 * Return value: SUCCEED - the query was performed successfully (empty result *
 *                         being counted as successful query)                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function is for compatibility purposes. Where possible the  *
 *           zbx_jsonobj_query() function must be used.                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_jsonpath_query(const struct zbx_json_parse *jp, const char *path, char **output)
{
	int		ret;
	zbx_jsonobj_t	obj;

	if (SUCCEED != zbx_jsonobj_open(jp->start, &obj))
		return FAIL;

	ret = zbx_jsonobj_query(&obj, path, output);

	zbx_jsonobj_clear(&obj);

	return ret;
}

static void	jsonpath_ctx_clear(zbx_jsonpath_context_t *ctx)
{
	jsonobj_clear_ref_vector(&ctx->objects);
	zbx_vector_jsonobj_ref_destroy(&ctx->objects);
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform jsonpath query on the specified json object               *
 *                                                                            *
 * Parameters: obj    - [IN] json object                                      *
 *             index  - [IN] jsonpath index (optional)                        *
 *             path   - [IN] jsonpath                                         *
 *             output - [OUT] output value                                    *
 *                                                                            *
 * Return value: SUCCEED - the query was performed successfully (empty result *
 *                         being counted as successful query)                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_jsonobj_query_ext(const zbx_jsonobj_t *obj, zbx_jsonpath_index_t *index, const char *path, char **output)
{
	zbx_jsonpath_context_t	ctx;
	zbx_jsonpath_t		jsonpath;
	int			ret = SUCCEED;

	if (FAIL == zbx_jsonpath_compile(path, &jsonpath))
		return FAIL;

	ctx.found = 0;
	ctx.root = obj;
	ctx.path = &jsonpath;
	zbx_vector_jsonobj_ref_create(&ctx.objects);
	ctx.index = index;

	switch (obj->type)
	{
		case ZBX_JSON_TYPE_OBJECT:
			ret = jsonpath_query_object(&ctx, obj, 0);
			break;
		case ZBX_JSON_TYPE_ARRAY:
			ret = jsonpath_query_array(&ctx, obj, 0);
			break;
		default:
			break;
	}

	if (SUCCEED == ret)
	{
		zbx_vector_jsonobj_ref_t	out;
		int				definite_path = jsonpath.definite, path_depth;

		zbx_vector_jsonobj_ref_create(&out);

		path_depth = jsonpath.segments_num;
		while (0 < path_depth && ZBX_JSONPATH_SEGMENT_FUNCTION == jsonpath.segments[path_depth - 1].type)
			path_depth--;

		if (path_depth < jsonpath.segments_num)
		{
			if (SUCCEED == (ret = jsonpath_apply_functions(&ctx, path_depth, &definite_path, &out)))
				ret = jsonpath_format_query_result(&out, definite_path, output);
		}
		else
			ret = jsonpath_format_query_result(&ctx.objects, definite_path, output);

		jsonobj_clear_ref_vector(&out);
		zbx_vector_jsonobj_ref_destroy(&out);
	}

	jsonpath_ctx_clear(&ctx);
	zbx_jsonpath_clear(&jsonpath);

	return ret;
}

int	zbx_jsonobj_query(const zbx_jsonobj_t *obj, const char *path, char **output)
{
	return zbx_jsonobj_query_ext(obj, NULL, path, output);
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
/* jsonobject index hashset support */

static zbx_hash_t	jsonobj_index_el_hash(const void *v)
{
	const zbx_jsonobj_index_el_t	*el = (const zbx_jsonobj_index_el_t *)v;

	return ZBX_DEFAULT_STRING_HASH_FUNC(el->value);
}

static int	jsonobj_index_el_compare(const void *v1, const void *v2)
{
	const zbx_jsonobj_index_el_t	*el1 = (const zbx_jsonobj_index_el_t *)v1;
	const zbx_jsonobj_index_el_t	*el2 = (const zbx_jsonobj_index_el_t *)v2;

	return strcmp(el1->value, el2->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by json object index element             *
 *                                                                            *
 * Parameters: v  - [IN] the json index element                               *
 *                                                                            *
 ******************************************************************************/
static void	jsonobj_index_el_clear(void *v)
{
	zbx_jsonobj_index_el_t	*el = (zbx_jsonobj_index_el_t *)v;
	int			i;

	zbx_free(el->value);
	for (i = 0; i < el->objects.values_num; i++)
	{
		zbx_free(el->objects.values[i].name);

		if (NULL != el->objects.values[i].internal)
		{
			zbx_jsonobj_clear(el->objects.values[i].internal);
			zbx_free(el->objects.values[i].internal);
		}
	}

	zbx_vector_jsonobj_ref_destroy(&el->objects);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add matched object to the index                                   *
 *                                                                            *
 * Parameters: index   - [IN] the parent object index                         *
 *             name    - [IN] the name of object to add to index              *
 *             obj     - [IN] the object to add to index                      *
 *             value   - [IN] the object matched by index path                *
 *                                                                            *
 ******************************************************************************/
static void	jsonobj_index_add_element(zbx_hashset_t *index, const char *name, const zbx_jsonobj_t *obj,
		const zbx_jsonobj_t *value)
{
#if defined(__hpux)
	/* fix for compiling with HP-UX bundled cc compiler */
	zbx_jsonobj_index_el_t	el_local = {NULL}, *el;
#else
	zbx_jsonobj_index_el_t	el_local = {.value = NULL}, *el;
#endif
	size_t			value_alloc = 0, value_offset = 0;
	zbx_jsonobj_ref_t	ref;

	jsonpath_str_copy_value(&el_local.value, &value_alloc, &value_offset, value);

	if (NULL == (el = (zbx_jsonobj_index_el_t *)zbx_hashset_search(index, &el_local)))
	{
		el = (zbx_jsonobj_index_el_t *)zbx_hashset_insert(index, &el_local, sizeof(el_local));
		zbx_vector_jsonobj_ref_create(&el->objects);
	}
	else
		zbx_free(el_local.value);

	ref.name = zbx_strdup(NULL, name);
	ref.value = obj;
	ref.internal = NULL;
	zbx_vector_jsonobj_ref_append(&el->objects, ref);
}


/******************************************************************************
 *                                                                            *
 * Purpose: add new json object index to jsopath index                        *
 *                                                                            *
 ******************************************************************************/
static zbx_hashset_t	*jsonpath_index_add(zbx_jsonpath_index_t *index, const zbx_jsonobj_t *obj,
		zbx_jsonpath_token_t *token)
{
	zbx_jsonobj_index_t	*obj_index;
	zbx_jsonpath_context_t	ctx;

	obj_index = (zbx_jsonobj_index_t *)zbx_malloc(NULL, sizeof(zbx_jsonobj_index_t));
	obj_index->obj = obj;
	obj_index->path = zbx_strdup(NULL, token->text);
	zbx_hashset_create_ext(&obj_index->index, 0, jsonobj_index_el_hash, jsonobj_index_el_compare,
			jsonobj_index_el_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	ctx.root = obj;
	ctx.path = token->path;
	zbx_vector_jsonobj_ref_create(&ctx.objects);
	ctx.index = NULL;

	if (ZBX_JSON_TYPE_OBJECT == obj->type)
	{
		zbx_hashset_const_iter_t	iter;
		const zbx_jsonobj_el_t		*el;

		zbx_hashset_const_iter_reset(&obj->data.object, &iter);
		while (NULL != (el = (const zbx_jsonobj_el_t *)zbx_hashset_const_iter_next(&iter)))
		{
			ctx.found = 0;
			if (SUCCEED == jsonpath_query_contents(&ctx, &el->value, 0) && 1 == ctx.objects.values_num)
			{
				jsonobj_index_add_element(&obj_index->index, el->name, &el->value,
						ctx.objects.values[0].value);
			}

			jsonobj_clear_ref_vector(&ctx.objects);
		}
	}
	else
	{
		int	i;

		for (i = 0; i < obj->data.array.values_num; i++)
		{
			char		name[MAX_ID_LEN + 1];
			zbx_jsonobj_t	*value = obj->data.array.values[i];

			zbx_snprintf(name, sizeof(name), "%d", i);

			ctx.found = 0;
			if (SUCCEED == jsonpath_query_contents(&ctx, value, 0) && 1 == ctx.objects.values_num)
				jsonobj_index_add_element(&obj_index->index, name, value, ctx.objects.values[0].value);

			jsonobj_clear_ref_vector(&ctx.objects);
		}
	}

	jsonpath_ctx_clear(&ctx);

	zbx_vector_jsonobj_index_ptr_append(&index->indexes, obj_index);

	return &obj_index->index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get json object index                                             *
 *                                                                            *
 * Parameters: index - [IN] jsonpath index, with 0-* json object indexes      *
 *             obj   - [IN] target object                                     *
 *             token - [IN] jsonpath token with index query                   *
 *                                                                            *
 * Return value: The indexed object contents by the specified query.          *
 *                                                                            *
 * Comments: If this object was not indexed by the specified query, then a    *
 *           new index will be created.                                       *
 *                                                                            *
 ******************************************************************************/
static zbx_hashset_t	*jsonpath_index_get(zbx_jsonpath_index_t *index, const zbx_jsonobj_t *obj,
		zbx_jsonpath_token_t *token)
{
	int		i;
	zbx_hashset_t	*objects = NULL;

	if (NULL == index)
		return NULL;

	pthread_mutex_lock(&index->lock);

	for (i = 0; i < index->indexes.values_num; i++)
	{
		if (index->indexes.values[i]->obj == obj && 0 == strcmp(index->indexes.values[i]->path, token->text))
		{
			objects = &index->indexes.values[i]->index;
			break;
		}
	}

	if (NULL == objects)
		objects = jsonpath_index_add(index, obj, token);

	pthread_mutex_unlock(&index->lock);

	return objects;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free json object index                                            *
 *                                                                            *
 ******************************************************************************/
static void	jsonobj_index_free(zbx_jsonobj_index_t *index)
{
	zbx_free(index->path);
	zbx_hashset_destroy(&index->index);
	zbx_free(index);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create jsonpath index                                             *
 *                                                                            *
 ******************************************************************************/
zbx_jsonpath_index_t	*zbx_jsonpath_index_create(char **error)
{
	zbx_jsonpath_index_t	*index;
	int			err;

	index = (zbx_jsonpath_index_t *)zbx_malloc(NULL, sizeof(zbx_jsonpath_index_t));

	if (0 != (err = pthread_mutex_init(&index->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize jsonpath index mutex: %s", zbx_strerror(err));
		zbx_free(index);
		return NULL;
	}

	zbx_vector_jsonobj_index_ptr_create(&index->indexes);

	return index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy jsonpath index                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_jsonpath_index_free(zbx_jsonpath_index_t *index)
{
	pthread_mutex_destroy(&index->lock);

	zbx_vector_jsonobj_index_ptr_clear_ext(&index->indexes, jsonobj_index_free);
	zbx_vector_jsonobj_index_ptr_destroy(&index->indexes);

	zbx_free(index);
}

#endif
