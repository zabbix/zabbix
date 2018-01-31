/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

static zbx_vector_ptr_t		handle_pool;		/* a place to store handles provided to mock data user */
static zbx_vector_str_t		string_pool;		/* a place to store strings provided to mock data user */
static yaml_document_t		test_case;		/* parsed YAML document with test case data */
static const yaml_node_t	*root;                  /* the root document node */
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

/* TODO: validate that keys in "in", "out", "db data" are scalars; validate "db data" */
int	zbx_mock_data_init(void **state)
{
	yaml_parser_t	parser;

	ZBX_UNUSED(state);

	yaml_parser_initialize(&parser);
	yaml_parser_set_input_file(&parser, stdin);

	if (0 != yaml_parser_load(&parser, &test_case))
	{
		if (NULL != (root = yaml_document_get_root_node(&test_case)))
		{
			yaml_document_t	tmp;

			if (0 != yaml_parser_load(&parser, &tmp))
			{
				if (NULL == yaml_document_get_root_node(&tmp))
				{
					yaml_document_delete(&tmp);

					if (YAML_MAPPING_NODE == root->type)
					{
						const yaml_node_pair_t	*pair;

						for (pair = root->data.mapping.pairs.start;
								pair < root->data.mapping.pairs.top; pair++)
						{
							const yaml_node_t	*key;

							key = yaml_document_get_node(&test_case, pair->key);

							if (YAML_SCALAR_NODE == key->type)
							{
								if (0 == zbx_yaml_scalar_cmp("in", key))
								{
									in = yaml_document_get_node(&test_case,
											pair->value);

									if (YAML_MAPPING_NODE != in->type)
									{
										printf("\"in\" is not a mapping.\n");
										break;
									}
								}
								else if (0 == zbx_yaml_scalar_cmp("out", key))
								{
									out = yaml_document_get_node(&test_case,
											pair->value);

									if (YAML_MAPPING_NODE != out->type)
									{
										printf("\"out\" is not a mapping.\n");
										break;
									}
								}
								else if (0 == zbx_yaml_scalar_cmp("db data", key))
								{
									db_data = yaml_document_get_node(&test_case,
											pair->value);

									if (YAML_MAPPING_NODE != db_data->type)
									{
										printf("\"db data\" is not a mapping.\n");
										break;
									}
								}
								else if (0 == zbx_yaml_scalar_cmp("files", key))
								{
									files = yaml_document_get_node(&test_case,
											pair->value);

									if (YAML_MAPPING_NODE != files->type)
									{
										printf("\"files\" is not a mapping.\n");
										break;
									}
								}
								else if (0 == zbx_yaml_scalar_cmp("exit code", key))
								{
									exit_code = yaml_document_get_node(&test_case,
											pair->value);

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
								else if (0 != zbx_yaml_scalar_cmp("test case", key))
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

						if (pair >= root->data.mapping.pairs.top)
						{
							yaml_parser_delete(&parser);
							zbx_vector_ptr_create(&handle_pool);
							zbx_vector_str_create(&string_pool);
							return 0;
						}
					}
					else
						printf("Document is not a mapping.\n");
				}
				else
				{
					printf("Stream contains multiple documents.\n");
					yaml_document_delete(&tmp);
				}
			}
			else
				printf("Cannot parse input: %s\n", zbx_yaml_error_string(parser.error));

			yaml_document_delete(&test_case);
		}
		else
			printf("Stream contains no documents.\n");
	}
	else
		printf("Cannot parse input: %s\n", zbx_yaml_error_string(parser.error));

	yaml_parser_delete(&parser);
	*state = NULL;
	return -1;
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

	zbx_vector_str_clear_ext(&string_pool, zbx_ptr_free);
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
		for (pos = 0; 0 != isdigit(next[pos]); pos++)
			;

		if (0 == pos)
			return ZBX_MOCK_INVALID_YAML_PATH;

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
