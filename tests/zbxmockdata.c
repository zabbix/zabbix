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

#include <yaml.h>

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"
#include "zbxalgo.h"

FILE	*__real_fopen(const char *path, const char *mode);
int	__real_fclose(FILE *stream);

static zbx_vector_ptr_t		handle_pool;		/* a place to store handles provided to mock data user */
static zbx_vector_str_t		string_pool;		/* a place to store strings provided to mock data user */
static yaml_document_t		test_case;		/* parsed YAML document with test case data */
static const yaml_node_t	*root;			/* the root document node */
static const yaml_node_t	*in = NULL;		/* pointer to "in" section of test case document */
static const yaml_node_t	*out = NULL;		/* pointer to "out" section of test case document */
static const yaml_node_t	*db_data = NULL;	/* pointer to "db data" section of test case document */
static const yaml_node_t	*files = NULL;		/* pointer to "files" section of test case document */
static const yaml_node_t	*exit_code = NULL;	/* pointer to "exit code" section of test case document */


typedef struct
{
	const yaml_node_t	*node;	/* node of test_case document handle is associated with */
	const yaml_node_item_t	*item;	/* current iterator position for vector handle */
}
zbx_mock_pool_handle_t;

typedef enum
{
	ZBX_MOCK_IN,		/* parameter from "in" section of test case data */
	ZBX_MOCK_OUT,		/* parameter from "out" section of test case data */
	ZBX_MOCK_DB_DATA,	/* data source from "db data" section of test case data */
	ZBX_MOCK_FILES		/* file contents from "files" section of test case data */
}
zbx_mock_parameter_t;

static const char	*zbx_yaml_error_string(yaml_error_type_t error)
{
	switch (error)
	{
		case YAML_NO_ERROR:
			return "No error is produced.";
		case YAML_MEMORY_ERROR:
			return "Cannot allocate or reallocate a block of memory.";
		case YAML_READER_ERROR:
			return "Cannot read or decode the input stream.";
		case YAML_SCANNER_ERROR:
			return "Cannot scan the input stream.";
		case YAML_PARSER_ERROR:
			return "Cannot parse the input stream.";
		case YAML_COMPOSER_ERROR:
			return "Cannot compose a YAML document.";
		case YAML_WRITER_ERROR:
			return "Cannot write to the output stream.";
		case YAML_EMITTER_ERROR:
			return "Cannot emit a YAML stream.";
		default:
			return "Unknown error.";
	}
}

static int	zbx_yaml_scalar_cmp(const char *str, const yaml_node_t *node)
{
	size_t	len;

	if (YAML_SCALAR_NODE != node->type)
		fail_msg("Internal error: scalar comparison of nonscalar node.");

	len = strlen(str);
	ZBX_RETURN_IF_NOT_EQUAL(len, node->data.scalar.length);
	return memcmp(str, node->data.scalar.value, len);
}

static int	zbx_yaml_scalar_ncmp(const char *str, size_t len, const yaml_node_t *node)
{
	if (YAML_SCALAR_NODE != node->type)
		fail_msg("Internal error: scalar comparison of nonscalar node.");

	if (len != node->data.scalar.length)
		return -1;

	return strncmp(str, (const char *)node->data.scalar.value, node->data.scalar.length);
}

static int	zbx_yaml_add_node(yaml_document_t *dst_doc, yaml_document_t *src_doc, yaml_node_t *src)
{
	int			new_node, key, value;
	yaml_node_pair_t	*pair;
	yaml_node_item_t	*item;

	switch (src->type)
	{
		case YAML_SCALAR_NODE:
			new_node = yaml_document_add_scalar(dst_doc, src->tag, src->data.scalar.value,
					src->data.scalar.length, src->data.scalar.style);
			break;

		case YAML_MAPPING_NODE:
			new_node = yaml_document_add_mapping(dst_doc, src->tag, src->data.mapping.style);
			for (pair = src->data.mapping.pairs.start; pair < src->data.mapping.pairs.top; pair++)
			{
				key = zbx_yaml_add_node(dst_doc, src_doc, yaml_document_get_node(src_doc, pair->key));
				value = zbx_yaml_add_node(dst_doc, src_doc, yaml_document_get_node(src_doc, pair->value));
				yaml_document_append_mapping_pair(dst_doc, new_node, key, value);
			}
			break;
		case YAML_SEQUENCE_NODE:
			new_node = yaml_document_add_sequence(dst_doc, src->tag, src->data.sequence.style);
			for (item = src->data.sequence.items.start; item < src->data.sequence.items.top; item++)
			{
				value = zbx_yaml_add_node(dst_doc, src_doc, yaml_document_get_node(src_doc, *item));
				yaml_document_append_sequence_item(dst_doc, new_node, value);
			}
			break;
		case YAML_NO_NODE:
			return -1;
	}

	return new_node;
}

static int	zbx_yaml_include(yaml_document_t *dst_doc, const char *filename)
{
	yaml_parser_t		parser;
	yaml_document_t		doc;
	yaml_node_t		*src_root;
	FILE			*fp;
	int			index = -1;

	if (NULL == (fp = __real_fopen(filename, "r")))
	{
		printf("Cannot open include file '%s': %s\n", filename, strerror(errno));
		goto out;
	}

	yaml_parser_initialize(&parser);
	yaml_parser_set_input_file(&parser, fp);

	if (0 != yaml_parser_load(&parser, &doc) &&  NULL != (src_root = yaml_document_get_root_node(&doc)))
	{
		index = zbx_yaml_add_node(dst_doc, &doc, src_root);
	}
	else
		printf("Cannot parse include file '%s'\n", filename);

	__real_fclose(fp);

	yaml_document_delete(&doc);
	yaml_parser_delete(&parser);
out:
	return index;
}

static void	zbx_yaml_replace_node_rec(yaml_document_t *doc, yaml_node_t *parent, const char *old_value,
		int new_index)
{
	yaml_node_t	*value_node;

	if (YAML_MAPPING_NODE == parent->type)
	{
		yaml_node_pair_t	*pair;

		for (pair = parent->data.mapping.pairs.start; pair < parent->data.mapping.pairs.top; pair++)
		{
			value_node = yaml_document_get_node(doc, pair->value);
			if (YAML_SCALAR_NODE == value_node->type && 0 == zbx_yaml_scalar_cmp(old_value, value_node))
				pair->value = new_index;
			else
				zbx_yaml_replace_node_rec(doc, value_node, old_value, new_index);
		}
	}
	else if (YAML_SEQUENCE_NODE == parent->type)
	{
		yaml_node_item_t	*item;

		for (item = parent->data.sequence.items.start; item < parent->data.sequence.items.top; item++)
		{
			value_node = yaml_document_get_node(doc, *item);
			if (YAML_SCALAR_NODE == value_node->type && 0 == zbx_yaml_scalar_cmp(old_value, value_node))
				*item = new_index;
			else
				zbx_yaml_replace_node_rec(doc, value_node, old_value, new_index);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces node occurrences in mappings and sequences with the new  *
 *          node index                                                        *
 *                                                                            *
 * Comments: When the test input data is converted by perl it loses anchor    *
 *           information. As workaround we try to find the occurrences not by *
 *           old node index, but by old node value. With including the        *
 *           possibility of other nodes having 'filename.inc.yaml' value is   *
 *           practically non-existent.                                        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_yaml_replace_node(yaml_document_t *doc, int old_index, int new_index)
{
	char		*value;
	yaml_node_t	*node, *parent;

	if (NULL == (node = yaml_document_get_node(doc, old_index)))
		return;

	if (NULL == (parent = yaml_document_get_root_node(doc)))
		return;

	value = zbx_malloc(NULL, node->data.scalar.length + 1);
	memcpy(value, node->data.scalar.value, node->data.scalar.length);
	value[node->data.scalar.length] = '\0';

	zbx_yaml_replace_node_rec(doc, parent, value, new_index);
	zbx_free(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: recursively include yaml documents from first level 'include'     *
 *          mapping scalar value or sequence                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_yaml_include_rec(yaml_document_t *doc, int *index)
{
	char			filename[MAX_STRING_LEN];
	const yaml_node_t	*node;
	int			new_index;

	node = yaml_document_get_node(doc, *index);

	if (YAML_SEQUENCE_NODE == node->type)
	{
		yaml_node_item_t	*item;

		for (item = node->data.sequence.items.start; item < node->data.sequence.items.top; item++)
			zbx_yaml_include_rec(doc, item);
		return;
	}

	if (YAML_SCALAR_NODE != node->type)
		return;

	memcpy(filename, node->data.scalar.value, node->data.scalar.length);
	filename[node->data.scalar.length] = '\0';

	if (-1 != (new_index = zbx_yaml_include(doc, filename)))
	{
		zbx_yaml_replace_node(doc, *index, new_index);

		/* re-acquire root node - after changes to the document */
		/* the previously acquired node pointers are not valid  */
		root = yaml_document_get_root_node(&test_case);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: includes another yaml document if include tag is set              *
 *                                                                            *
 * Comments: The document is included by recursively copying its contents     *
 *           under include tag, replacing its original value (file name).     *
 *           The file is included from the working directory.                 *
 *           After modifying document (so after include) the previously       *
 *           acquired yaml nodes are not guaranteed to be valid and must be   *
 *           reinitialized.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_yaml_check_include(yaml_document_t *doc)
{
	yaml_node_pair_t	*pair;

	root = yaml_document_get_root_node(doc);

	if (YAML_MAPPING_NODE != root->type)
		return -1;

	for (pair = root->data.mapping.pairs.start; pair < root->data.mapping.pairs.top; pair++)
	{
		if (0 == zbx_yaml_scalar_cmp("include", yaml_document_get_node(doc, pair->key)))
		{
			zbx_yaml_include_rec(doc, &pair->value);
			break;
		}
	}

	return 0;
}

static int	zbx_mock_data_load_test_case(void)
{
	const yaml_node_pair_t	*pair;

	if (-1 == zbx_yaml_check_include(&test_case))
		return -1;

	for (pair = root->data.mapping.pairs.start; pair < root->data.mapping.pairs.top; pair++)
	{
		const yaml_node_t	*key;

		key = yaml_document_get_node(&test_case, pair->key);

		if (YAML_SCALAR_NODE == key->type)
		{
			if (0 == zbx_yaml_scalar_cmp("in", key))
			{
				in = yaml_document_get_node(&test_case, pair->value);

				if (YAML_MAPPING_NODE != in->type)
				{
					printf("\"in\" is not a mapping.\n");
					break;
				}
			}
			else if (0 == zbx_yaml_scalar_cmp("out", key))
			{
				out = yaml_document_get_node(&test_case, pair->value);

				if (YAML_MAPPING_NODE != out->type)
				{
					printf("\"out\" is not a mapping.\n");
					break;
				}
			}
			else if (0 == zbx_yaml_scalar_cmp("db data", key))
			{
				db_data = yaml_document_get_node(&test_case, pair->value);

				if (YAML_MAPPING_NODE != db_data->type)
				{
					printf("\"db data\" is not a mapping.\n");
					break;
				}
			}
			else if (0 == zbx_yaml_scalar_cmp("files", key))
			{
				files = yaml_document_get_node(&test_case, pair->value);

				if (YAML_MAPPING_NODE != files->type)
				{
					printf("\"files\" is not a mapping.\n");
					break;
				}
			}
			else if (0 == zbx_yaml_scalar_cmp("exit code", key))
			{
				exit_code = yaml_document_get_node(&test_case, 	pair->value);

				if (YAML_SCALAR_NODE != exit_code->type)
				{
					printf("\"exit code\" is not a scalar.\n");
					break;
				}

				if (0 != zbx_yaml_scalar_cmp("success", exit_code) &&
						0 != zbx_yaml_scalar_cmp("failure", exit_code))
				{
					printf("Invalid value \"%.*s\" of"
							" \"exit code\".\n",
							(int)exit_code->data.scalar.length,
							exit_code->data.scalar.value);
					break;
				}
			}
			else if (0 != zbx_yaml_scalar_cmp("test case", key) &&
					0 != zbx_yaml_scalar_cmp("include", key))
			{
				printf("Unexpected key \"%.*s\" in mapping.\n",
						(int)key->data.scalar.length,
						key->data.scalar.value);
				break;
			}

			continue;
		}
		else
			printf("Non-scalar key in mapping.\n");

		break;
	}

	if (pair < root->data.mapping.pairs.top)
		return -1;

	zbx_vector_ptr_create(&handle_pool);
	zbx_vector_str_create(&string_pool);
	return 0;
}

static int	zbx_mock_data_load(yaml_parser_t *parser)
{
	yaml_document_t	tmp;
	int		ret = -1;

	if (NULL == (root = yaml_document_get_root_node(&test_case)))
	{
		printf("Stream contains no documents.\n");
		return -1;
	}

	if (YAML_MAPPING_NODE != root->type)
	{
		printf("Document is not a mapping.\n");
		return -1;
	}

	if (0 == yaml_parser_load(parser, &tmp))
	{
		printf("Cannot parse input: %s\n", zbx_yaml_error_string(parser->error));
		return -1;
	}

	if (NULL == yaml_document_get_root_node(&tmp))
		ret = zbx_mock_data_load_test_case();
	else
		printf("Stream contains multiple documents.\n");

	yaml_document_delete(&tmp);

	return ret;
}


/* TODO: validate that keys in "in", "out", "db data" are scalars; validate "db data" */
int	zbx_mock_data_init(void **state)
{
	yaml_parser_t	parser;
	int		ret;

	ZBX_UNUSED(state);

	yaml_parser_initialize(&parser);
	yaml_parser_set_input_file(&parser, stdin);

	if (0 == yaml_parser_load(&parser, &test_case))
	{
		printf("Cannot parse input: %s\n", zbx_yaml_error_string(parser.error));
		return -1;
	}

	ret = zbx_mock_data_load(&parser);
	yaml_parser_delete(&parser);

	return ret;
}

static zbx_mock_handle_t	zbx_mock_handle_alloc(const yaml_node_t *node)
{
	zbx_mock_handle_t	handleid;
	zbx_mock_pool_handle_t	*handle = NULL;

	handleid = (zbx_mock_handle_t)handle_pool.values_num;
	handle = zbx_malloc(handle, sizeof(zbx_mock_pool_handle_t));
	handle->node = node;
	handle->item = (YAML_SEQUENCE_NODE == node->type ? node->data.sequence.items.start : NULL);
	zbx_vector_ptr_append(&handle_pool, handle);

	return handleid;
}

int	zbx_mock_data_free(void **state)
{
	ZBX_UNUSED(state);

	zbx_vector_str_clear_ext(&string_pool, zbx_str_free);
	zbx_vector_ptr_clear_ext(&handle_pool, zbx_ptr_free);
	zbx_vector_str_destroy(&string_pool);
	zbx_vector_ptr_destroy(&handle_pool);
	yaml_document_delete(&test_case);

	return 0;
}

const char	*zbx_mock_error_string(zbx_mock_error_t error)
{
	switch (error)
	{
		case ZBX_MOCK_SUCCESS:
			return "No error, actually.";
		case ZBX_MOCK_INVALID_HANDLE:
			return "Provided handle wasn't created properly or its lifetime has expired.";
		case ZBX_MOCK_NO_PARAMETER:
			return "No parameter with a given name available in test case data.";
		case ZBX_MOCK_NO_EXIT_CODE:
			return "No exit code provided in test case data.";
		case ZBX_MOCK_NOT_AN_OBJECT:
			return "Provided handle is not an object handle.";
		case ZBX_MOCK_NO_SUCH_MEMBER:
			return "Object has no member associated with provided key.";
		case ZBX_MOCK_NOT_A_VECTOR:
			return "Provided handle is not a vector handle.";
		case ZBX_MOCK_END_OF_VECTOR:
			return "Vector iteration reached its end.";
		case ZBX_MOCK_NOT_A_STRING:
			return "Provided handle is not a string handle.";
		case ZBX_MOCK_INTERNAL_ERROR:
			return "Internal error, please report to maintainers.";
		case ZBX_MOCK_INVALID_YAML_PATH:
			return "Invalid YAML path syntax.";
		case ZBX_MOCK_NOT_A_BINARY:
			return "Provided handle is not a binary string.";
		case ZBX_MOCK_NOT_AN_UINT64:
			return "Provided handle is not an unsigned 64 bit integer handle.";
		case ZBX_MOCK_NOT_A_FLOAT:
			return "Provided handle is not a floating point number handle.";
		case ZBX_MOCK_NOT_A_TIMESTAMP:
			return "Invalid timestamp format.";
		case ZBX_MOCK_NOT_ENOUGH_MEMORY:
			return "Not enough space in output buffer.";
		case ZBX_MOCK_NOT_AN_INT:
			return "Provided handle is not a integer handle.";
		default:
			return "Unknown error.";
	}
}

static zbx_mock_error_t	zbx_mock_builtin_parameter(zbx_mock_parameter_t type, const char *name, zbx_mock_handle_t *parameter)
{
	const yaml_node_t	*source;
	const yaml_node_pair_t	*pair;

	switch (type)
	{
		case ZBX_MOCK_IN:
			source = in;
			break;
		case ZBX_MOCK_OUT:
			source = out;
			break;
		case ZBX_MOCK_DB_DATA:
			source = db_data;
			break;
		case ZBX_MOCK_FILES:
			source = files;
			break;
		default:
			return ZBX_MOCK_INTERNAL_ERROR;
	}

	if (NULL == source)
		return ZBX_MOCK_NO_PARAMETER;

	if (YAML_MAPPING_NODE != source->type)
		return ZBX_MOCK_INTERNAL_ERROR;

	for (pair = source->data.mapping.pairs.start; pair < source->data.mapping.pairs.top; pair++)
	{
		const yaml_node_t	*key;

		key = yaml_document_get_node(&test_case, pair->key);

		if (YAML_SCALAR_NODE != key->type)
			return ZBX_MOCK_INTERNAL_ERROR;

		if (0 == zbx_yaml_scalar_cmp(name, key))
		{
			*parameter = zbx_mock_handle_alloc(yaml_document_get_node(&test_case, pair->value));
			return ZBX_MOCK_SUCCESS;
		}
	}

	return ZBX_MOCK_NO_PARAMETER;
}

zbx_mock_error_t	zbx_mock_in_parameter(const char *name, zbx_mock_handle_t *parameter)
{
	return zbx_mock_builtin_parameter(ZBX_MOCK_IN, name, parameter);
}

zbx_mock_error_t	zbx_mock_out_parameter(const char *name, zbx_mock_handle_t *parameter)
{
	return zbx_mock_builtin_parameter(ZBX_MOCK_OUT, name, parameter);
}

zbx_mock_error_t	zbx_mock_db_rows(const char *data_source, zbx_mock_handle_t *rows)
{
	return zbx_mock_builtin_parameter(ZBX_MOCK_DB_DATA, data_source, rows);
}

zbx_mock_error_t	zbx_mock_file(const char *path, zbx_mock_handle_t *file)
{
	return zbx_mock_builtin_parameter(ZBX_MOCK_FILES, path, file);
}

zbx_mock_error_t	zbx_mock_exit_code(int *status)
{
	if (NULL == exit_code)
		return ZBX_MOCK_NO_EXIT_CODE;

	if (0 == zbx_yaml_scalar_cmp("success", exit_code))
		*status = EXIT_SUCCESS;
	else if (0 == zbx_yaml_scalar_cmp("failure", exit_code))
		*status = EXIT_FAILURE;
	else
		return ZBX_MOCK_INTERNAL_ERROR;

	return ZBX_MOCK_SUCCESS;
}

zbx_mock_error_t	zbx_mock_object_member(zbx_mock_handle_t object, const char *name, zbx_mock_handle_t *member)
{
	const zbx_mock_pool_handle_t	*handle;
	const yaml_node_pair_t		*pair;

	if (0 > object || object >= (zbx_mock_handle_t)handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[object];

	if (YAML_MAPPING_NODE != handle->node->type)
		return ZBX_MOCK_NOT_AN_OBJECT;

	for (pair = handle->node->data.mapping.pairs.start; pair < handle->node->data.mapping.pairs.top; pair++)
	{
		const yaml_node_t	*key;

		key = yaml_document_get_node(&test_case, pair->key);

		if (YAML_SCALAR_NODE != key->type)	/* deep validation that every key of every mapping in test */
			continue;			/* case document is scalar would be an overkill, just skip */

		if (0 == zbx_yaml_scalar_cmp(name, key))
		{
			*member = zbx_mock_handle_alloc(yaml_document_get_node(&test_case, pair->value));
			return ZBX_MOCK_SUCCESS;
		}
	}

	return ZBX_MOCK_NO_SUCH_MEMBER;
}

zbx_mock_error_t	zbx_mock_vector_element(zbx_mock_handle_t vector, zbx_mock_handle_t *element)
{
	zbx_mock_pool_handle_t	*handle;

	if (0 > vector || vector >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[vector];

	if (YAML_SEQUENCE_NODE != handle->node->type)
		return ZBX_MOCK_NOT_A_VECTOR;

	if (handle->item >= handle->node->data.sequence.items.top)
		return ZBX_MOCK_END_OF_VECTOR;

	*element = zbx_mock_handle_alloc(yaml_document_get_node(&test_case, *handle->item++));
	return ZBX_MOCK_SUCCESS;
}

zbx_mock_error_t	zbx_mock_string(zbx_mock_handle_t string, const char **value)
{
	const zbx_mock_pool_handle_t	*handle;
	char				*tmp = NULL;

	if (0 > string || string >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[string];

	if (YAML_SCALAR_NODE != handle->node->type ||
			NULL != memchr(handle->node->data.scalar.value, '\0', handle->node->data.scalar.length))
	{
		return ZBX_MOCK_NOT_A_STRING;
	}

	tmp = zbx_malloc(tmp, handle->node->data.scalar.length + 1);
	memcpy(tmp, handle->node->data.scalar.value, handle->node->data.scalar.length);
	tmp[handle->node->data.scalar.length] = '\0';
	zbx_vector_str_append(&string_pool, tmp);
	*value = tmp;
	return ZBX_MOCK_SUCCESS;
}

zbx_mock_error_t	zbx_mock_binary(zbx_mock_handle_t binary, const char **value, size_t *length)
{
	const zbx_mock_pool_handle_t	*handle;
	char				*tmp, *dst;
	const char			*src;
	size_t				i;

	if (0 > binary || binary >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[binary];

	if (YAML_SCALAR_NODE != handle->node->type)
		return ZBX_MOCK_NOT_A_BINARY;

	src = (char*)handle->node->data.scalar.value;
	dst = tmp = zbx_malloc(NULL, handle->node->data.scalar.length);

	for (i = 0; i < handle->node->data.scalar.length; i++)
	{
		if ('\\' == src[i])
		{
			if (i + 3 >= handle->node->data.scalar.length || 'x' != src[i + 1] ||
					SUCCEED != is_hex_n_range(&src[i + 2], 2, dst, sizeof(char), 0, 0xff))
			{
				zbx_free(tmp);
				return ZBX_MOCK_NOT_A_BINARY;
			}

			dst++;
			i += 3;
		}
		else
			*dst++ = src[i];
	}

	zbx_vector_str_append(&string_pool, tmp);
	*value = tmp;
	*length = dst - tmp;

	return ZBX_MOCK_SUCCESS;
}

static zbx_mock_error_t	zbx_yaml_path_next(const char **pnext, const char **key, int *key_len, int *index)
{
	const char	*next = *pnext;
	size_t		pos;
	char		quotes;

	while ('.' == *next)
		next++;

	/* process dot notation component */
	if ('[' != *next)
	{
		*key = next;

		while (0 != isalnum(*next) || '_' == *next)
			next++;

		if (*key == next)
			return ZBX_MOCK_INVALID_YAML_PATH;

		*key_len = next - *key;

		if ('\0' != *next && '.' != *next && '[' != *next)
			return ZBX_MOCK_INVALID_YAML_PATH;

		*pnext = next;
		*index = 0;

		return ZBX_MOCK_SUCCESS;
	}

	while (*(++next) == ' ')
		;

	/* process array index component */
	if (0 != isdigit(*next))
	{
		for (pos = 1; 0 != isdigit(next[pos]); pos++)
			;

		*key = next;
		*key_len = pos;

		next += pos;

		while (*next == ' ')
			next++;

		if (']' != *next++)
			return ZBX_MOCK_INVALID_YAML_PATH;

		*pnext = next;
		*index = 1;

		return ZBX_MOCK_SUCCESS;
	}

	/* process bracket notation component */

	if ('\'' != *next && '"' != *next)
		return ZBX_MOCK_INVALID_YAML_PATH;

	*key = next + 1;

	for (quotes = *next++; quotes != *next; next++)
	{
		if ('\0' == *next)
			return ZBX_MOCK_INVALID_YAML_PATH;
	}

	if (*key == next)
		return ZBX_MOCK_INVALID_YAML_PATH;

	*key_len = next - *key;

	while (*(++next) == ' ')
		;

	if (']' != *next++)
		return ZBX_MOCK_INVALID_YAML_PATH;

	*pnext = next;
	*index = 0;

	return ZBX_MOCK_SUCCESS;
}

static zbx_mock_error_t	zbx_mock_parameter_rec(const yaml_node_t *node, const char *path, zbx_mock_handle_t *parameter)
{
	const yaml_node_pair_t	*pair;
	const char		*pnext = path, *key_name;
	int			err, key_len, index;

	/* end of the path, return whatever has been found */
	if ('\0' == *pnext)
	{
		if (NULL != parameter)
			*parameter = zbx_mock_handle_alloc(node);

		return ZBX_MOCK_SUCCESS;
	}

	if (ZBX_MOCK_SUCCESS != (err = zbx_yaml_path_next(&pnext, &key_name, &key_len, &index)))
		return err;

	/* the path component is array index, attempt to extract sequence element */
	if (0 != index)
	{
		const yaml_node_t	*element;

		if (YAML_SEQUENCE_NODE != node->type)
			return ZBX_MOCK_NOT_A_VECTOR;

		index = atoi(key_name);

		if (0 > index || index >= (node->data.sequence.items.top - node->data.sequence.items.start))
			return ZBX_MOCK_END_OF_VECTOR;

		element = yaml_document_get_node(&test_case, node->data.sequence.items.start[index]);
		return zbx_mock_parameter_rec(element, pnext, parameter);
	}

	/* the patch component is object key, attempt to extract object member */

	if (YAML_MAPPING_NODE != node->type)
		return ZBX_MOCK_NOT_AN_OBJECT;

	for (pair = node->data.mapping.pairs.start; pair < node->data.mapping.pairs.top; pair++)
	{
		const yaml_node_t	*key, *value;

		key = yaml_document_get_node(&test_case, pair->key);

		if (0 == zbx_yaml_scalar_ncmp(key_name, key_len, key))
		{
			value = yaml_document_get_node(&test_case, pair->value);
			return zbx_mock_parameter_rec(value, pnext, parameter);
		}
	}

	return ZBX_MOCK_NO_SUCH_MEMBER;
}

zbx_mock_error_t	zbx_mock_parameter(const char *path, zbx_mock_handle_t *parameter)
{
	return zbx_mock_parameter_rec(root, path, parameter);
}

zbx_mock_error_t	zbx_mock_parameter_exists(const char *path)
{
	return zbx_mock_parameter_rec(root, path, NULL);
}

zbx_mock_error_t	zbx_mock_uint64(zbx_mock_handle_t object, zbx_uint64_t *value)
{
	const zbx_mock_pool_handle_t	*handle;

	if (0 > object || object >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[object];

	if (YAML_SCALAR_NODE != handle->node->type || ZBX_MAX_UINT64_LEN < handle->node->data.scalar.length)
		return ZBX_MOCK_NOT_AN_UINT64;

	if (SUCCEED != is_uint64_n((const char *)handle->node->data.scalar.value, handle->node->data.scalar.length,
			value))
	{
		return ZBX_MOCK_NOT_AN_UINT64;
	}

	return ZBX_MOCK_SUCCESS;
}

zbx_mock_error_t	zbx_mock_float(zbx_mock_handle_t object, double *value)
{
	const zbx_mock_pool_handle_t	*handle;
	char				*tmp = NULL;
	zbx_mock_error_t		res = ZBX_MOCK_SUCCESS;

	if (0 > object || object >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[object];

	if (YAML_SCALAR_NODE != handle->node->type)
		return ZBX_MOCK_NOT_A_FLOAT;

	tmp = zbx_malloc(tmp, handle->node->data.scalar.length + 1);
	memcpy(tmp, handle->node->data.scalar.value, handle->node->data.scalar.length);
	tmp[handle->node->data.scalar.length] = '\0';

	if (SUCCEED != is_double(tmp, value))
		res = ZBX_MOCK_NOT_A_FLOAT;

	zbx_free(tmp);

	return res;
}

zbx_mock_error_t	zbx_mock_int(zbx_mock_handle_t object, int *value)
{
	const zbx_mock_pool_handle_t	*handle;
	char				*tmp = NULL, *ptr;
	zbx_mock_error_t		res = ZBX_MOCK_SUCCESS;

	if (0 > object || object >= handle_pool.values_num)
		return ZBX_MOCK_INVALID_HANDLE;

	handle = handle_pool.values[object];

	if (YAML_SCALAR_NODE != handle->node->type)
		return ZBX_MOCK_NOT_AN_INT;

	tmp = zbx_malloc(tmp, handle->node->data.scalar.length + 1);
	memcpy(tmp, handle->node->data.scalar.value, handle->node->data.scalar.length);
	tmp[handle->node->data.scalar.length] = '\0';

	ptr = tmp;
	if ('-' == *ptr)
		ptr++;

	if (SUCCEED != is_uint31(ptr, value))
		res = ZBX_MOCK_NOT_AN_INT;

	if (ptr != tmp)
		*value = -(*value);

	zbx_free(tmp);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return string object contents                                     *
 *                                                                            *
 * Comments: The object can be either scalar value or a mapping. In the first *
 *           case the scalar value is returned. In the other case the string  *
 *           is assembled from the object properties:                         *
 *             <header> + <page> * <pages> + <footer>                         *
 *           (<header>, <page>, <pages>, <footer> properties are optional and *
 *           by default are empty except pages, which is 1 by default).       *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_mock_string_ex(zbx_mock_handle_t hobject, const char **value)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	char			*tmp = NULL;
	size_t			tmp_alloc = 0, tmp_offset = 0;

	/* if the object is string - return the value */
	if (ZBX_MOCK_SUCCESS == (err = zbx_mock_string(hobject, value)))
		return ZBX_MOCK_SUCCESS;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hobject, "header", &handle))
	{
		const char	*header;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &header)))
			fail_msg("Cannot read header property: %s", zbx_mock_error_string(err));
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, header);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hobject, "page", &handle))
	{
		const char	*page;
		int		i, pages_num = 1;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &page)))
			fail_msg("Cannot read page property: %s", zbx_mock_error_string(err));

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hobject, "pages", &handle))
		{
			const char	*pages;

			if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &pages)))
				fail_msg("Cannot read pages property: %s", zbx_mock_error_string(err));
			pages_num = atoi(pages);
		}

		for (i = 0; i < pages_num; i++)
			zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, page);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hobject, "footer", &handle))
	{
		const char	*footer;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &footer)))
			fail_msg("Cannot read footer property: %s", zbx_mock_error_string(err));
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, footer);
	}

	zbx_vector_str_append(&string_pool, tmp);
	*value = tmp;

	return ZBX_MOCK_SUCCESS;
}
