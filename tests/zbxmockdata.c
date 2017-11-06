/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxtests.h"

#include "common.h"

typedef enum
{
	ZBX_TEST_DATA_TYPE_UNKNOWN,
	ZBX_TEST_DATA_TYPE_PARAM_IN,
	ZBX_TEST_DATA_TYPE_PARAM_OUT,
	ZBX_TEST_DATA_TYPE_DB_DATA,
	ZBX_TEST_DATA_TYPE_FUNCTION,
	ZBX_TEST_DATA_TYPE_IN_PARAM,
	ZBX_TEST_DATA_TYPE_IN_VALUE,
	ZBX_TEST_DATA_TYPE_OUT_PARAM,
	ZBX_TEST_DATA_TYPE_OUT_VALUE,
	ZBX_TEST_DATA_TYPE_DB_FIELD,
	ZBX_TEST_DATA_TYPE_DB_ROW,
	ZBX_TEST_DATA_TYPE_FUNC_OUT,
	ZBX_TEST_DATA_TYPE_FUNC_VALUE
}
line_type_t;

#define	TEST_MAX_DATASOURCE_NUM	1024
#define	TEST_MAX_FUNCTION_NUM	1024
#define	TEST_MAX_ROW_NUM	512
#define	TEST_MAX_DATA_NUM	512

char		*curr_wrapped_function = NULL;
zbx_test_case_t	*test_case = NULL;

int	zbx_mock_data_init(void **state)
{
	char 			line[MAX_STRING_LEN], *tmp, *p;
	line_type_t		line_type = ZBX_TEST_DATA_TYPE_UNKNOWN;
	zbx_test_datasource_t	*curr_ds = NULL;
	zbx_test_data_t		*curr_data = NULL;
	zbx_test_function_t	*curr_func = NULL;
	zbx_test_row_t		*curr_row = NULL;

	ZBX_UNUSED(state);

	test_case = malloc(sizeof(zbx_test_case_t));

	test_case->datasources = malloc(TEST_MAX_DATASOURCE_NUM * sizeof(zbx_test_datasource_t));
	test_case->datasource_num = 0;

	test_case->functions = malloc(TEST_MAX_FUNCTION_NUM * sizeof(zbx_test_function_t));
	test_case->function_num = 0;

	test_case->in_params.data_num = 0;
	test_case->out_params.data_num = 0;

	while (fgets(line, MAX_STRING_LEN, stdin) != NULL)
	{
		int	idx_str = 0;

		tmp = strdup(line);
		zbx_rtrim(tmp, "\r\n");

		p = strtok(tmp,"|");
		while (p != NULL)
		{
			if (0 == idx_str)	/* data type identification */
			{
				if (0 == strcmp(p, "DB_DATA"))
				{
					curr_ds = &test_case->datasources[test_case->datasource_num];
					curr_ds->field_names = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_ds->field_num = 0;

					test_case->datasource_num++;

					line_type = ZBX_TEST_DATA_TYPE_DB_DATA;
				}
				else if (0 == strcmp(p, "FUNCTION"))
				{
					curr_func = &test_case->functions[test_case->function_num];
					curr_func->data.data_num = 0;
					test_case->function_num++;

					line_type = ZBX_TEST_DATA_TYPE_FUNCTION;
				}
				else if (0 == strcmp(p, "IN_NAMES"))
				{
					curr_data = &test_case->in_params;
					curr_data->names = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_data->data_num = 0;

					line_type = ZBX_TEST_DATA_TYPE_IN_PARAM;
				}
				else if (0 == strcmp(p, "OUT_NAMES"))
				{
					curr_data = &test_case->out_params;
					curr_data->names = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_data->data_num = 0;

					line_type = ZBX_TEST_DATA_TYPE_OUT_PARAM;
				}
				else if (0 == strcmp(p, "IN_VALUES"))
				{
					curr_data = &test_case->in_params;
					curr_data->values = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));

					line_type = ZBX_TEST_DATA_TYPE_IN_VALUE;
				}
				else if (0 == strcmp(p, "OUT_VALUES"))
				{
					curr_data = &test_case->out_params;
					curr_data->values = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));

					line_type = ZBX_TEST_DATA_TYPE_OUT_VALUE;
				}
				else if (0 == strcmp(p, "FIELDS"))
				{
					curr_ds->rows =  malloc(TEST_MAX_ROW_NUM * sizeof(zbx_test_row_t *));
					curr_ds->row_num = 0;

					line_type = ZBX_TEST_DATA_TYPE_DB_FIELD;
				}
				else if (0 == strcmp(p, "ROW"))
				{
					curr_row = &curr_ds->rows[curr_ds->row_num];
					curr_row->values = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_row->value_num = 0;

					curr_ds->row_num++;

					line_type = ZBX_TEST_DATA_TYPE_DB_ROW;
				}
				else if (0 == strcmp(p, "FUNC_OUT_PARAMS"))
				{
					curr_data = &curr_func->data;
					curr_data->names = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_data->data_num = 0;

					line_type = ZBX_TEST_DATA_TYPE_FUNC_OUT;
				}
				else if (0 == strcmp(p, "FUNC_OUT_VALUES"))
				{
					curr_data = &curr_func->data;
					curr_data->values = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));
					curr_data->data_num = 0;

					line_type = ZBX_TEST_DATA_TYPE_FUNC_VALUE;
				}
				else
					line_type = ZBX_TEST_DATA_TYPE_UNKNOWN;
			}
			else	/* filling in data */
			{
				switch (line_type)
				{
					case ZBX_TEST_DATA_TYPE_DB_DATA:
						if (1 == idx_str && NULL != curr_data)
							curr_ds->source_name = strdup(p);
						break;
					case ZBX_TEST_DATA_TYPE_FUNCTION:
						if (1 == idx_str && NULL != curr_func)
							curr_func->name = strdup(p);
						break;
					case ZBX_TEST_DATA_TYPE_IN_PARAM:
					case ZBX_TEST_DATA_TYPE_OUT_PARAM:
					case ZBX_TEST_DATA_TYPE_FUNC_OUT:
						if (NULL != curr_data && NULL != curr_data->names)
							curr_data->names[idx_str - 1] = strdup(p);
						break;
					case ZBX_TEST_DATA_TYPE_IN_VALUE:
					case ZBX_TEST_DATA_TYPE_OUT_VALUE:
					case ZBX_TEST_DATA_TYPE_FUNC_VALUE:
						if (NULL != curr_data && NULL != curr_data->values)
						{
							curr_data->values[idx_str - 1] = strdup(p);
							curr_data->data_num++;
						}
						break;
					case ZBX_TEST_DATA_TYPE_DB_FIELD:
						if (NULL != curr_ds && NULL != curr_ds->field_names)
						{
							curr_ds->field_names[idx_str - 1] = strdup(p);
							curr_ds->field_num++;
						}
						break;
					case ZBX_TEST_DATA_TYPE_DB_ROW:
						if (NULL != curr_row && NULL != curr_row->values)
						{
							curr_row->values[idx_str - 1] = strdup(p);
							curr_row->value_num++;
						}
				}
			}

			p = strtok(NULL, "|");
			idx_str++;
		}
		free(tmp);
	}

	return 0;
}

int	zbx_mock_data_free(void **state)
{
	int	n1, n2, n3;

	ZBX_UNUSED(state);

	for (n1 = 0; n1 < test_case->datasource_num; n1++)
	{
		free(test_case->datasources[n1].source_name);

		for (n2 = 0; n2 < test_case->datasources[n1].field_num; n2++)
			free(test_case->datasources[n1].field_names[n2]);

		for (n2 = 0; n2 < test_case->datasources[n1].row_num; n2++)
		{
			for (n3 = 0; n3 < test_case->datasources[n1].rows[n2].value_num; n3++)
				free(test_case->datasources[n1].rows[n2].values[n3]);
		}
	}

	free(test_case->datasources);

	for (n1 = 0; n1 < test_case->in_params.data_num; n1++)
	{
		free(test_case->in_params.names[n1]);
		free(test_case->in_params.values[n1]);
	}

	free(test_case->in_params.names);
	free(test_case->in_params.values);

	for (n1 = 0; n1 < test_case->out_params.data_num; n1++)
	{
		free(test_case->out_params.names[n1]);
		free(test_case->out_params.values[n1]);
	}

	free(test_case->out_params.names);
	free(test_case->out_params.values);

	for (n1 = 0; n1 < test_case->function_num; n1++)
	{
		free(test_case->functions[n1].name);

		for (n2 = 0; n2 < test_case->functions[n1].data.data_num; n2++)
		{
			free(test_case->functions[n1].data.names[n2]);
			free(test_case->functions[n1].data.values[n2]);
		}

		free(test_case->functions[n1].data.names);
		free(test_case->functions[n1].data.values);

		free(test_case->functions);
	}

	free(test_case);

	return 0;
}

void	debug_print_cases(zbx_test_case_t *test_case)
{
	int n1, n2, n3;

	printf("in_params.data_num %d\n", test_case->in_params.data_num);
	for (n1 = 0; n1 < test_case->in_params.data_num; n1++)
	{
		printf("\tin_params.name[%d]: %s\n", n1, test_case->in_params.names[n1]);
		printf("\tin_params.value[%d]: %s\n", n1, test_case->in_params.values[n1]);
	}

	printf("out_params.data_num %d\n", test_case->out_params.data_num);
	for (n1 = 0; n1 <  test_case->out_params.data_num; n1++)
	{
		printf("\tout_params.name[%d]: %s\n", n1, test_case->out_params.names[n1]);
		printf("\tout_params.value[%d]: %s\n", n1, test_case->out_params.values[n1]);
	}

	printf("datasource_num %d\n", test_case->datasource_num);
	for (n1 = 0; n1 < test_case->datasource_num; n1++)
	{
		printf("\tdatasources[%d].source_name: %s\n", n1, test_case->datasources[n1].source_name);
		printf("\tdatasources[%d].field_num: %d\n", n1, test_case->datasources[n1].field_num);

		for (n2 = 0; n2 <  test_case->datasources[n1].field_num; n2++)
			printf("\t\tdatasources.field_name[%d]: %s\n", n2, test_case->datasources[n1].field_names[n2]);

		for (n2 = 0; n2 < test_case->datasources[n1].row_num; n2++)
		{
			for (n3 = 0; n3 < test_case->datasources[n1].rows[n2].value_num; n3++)
			{
				printf("\t\tdatasources.row[%d].value[%d]: %s\n", n2, n3,
						test_case->datasources[n1].rows[n2].values[n3]);
			}
		}
	}

	printf("function_num %d\n", test_case->function_num);
	for (n1 = 0; n1 < test_case->function_num; n1++)
	{
		printf("\tfunctions[%d]: %s\n", n1, test_case->functions[n1].name);

		for (n2 = 0; n2 < test_case->functions[n1].data.data_num; n2++)
		{
			printf("\t\tfunctions.name[%d]: %s\n", n2, test_case->functions[n1].data.names[n2]);
			printf("\t\tfunctions.value[%d]: %s\n", n2, test_case->functions[n1].data.values[n2]);
		}
	}
}

char	*get_in_param_by_index(int idx)
{
	if (idx >= test_case->in_params.data_num)
	{
		fail_msg("Cannot find in_param by index=%d", idx);
		return NULL;
	}

	return test_case->in_params.values[idx];
}

char	*get_out_param_by_index(int idx)
{
	if (idx >= test_case->out_params.data_num)
	{
		fail_msg("Cannot find out_param by index=%d", idx);
		return NULL;
	}

	return test_case->out_params.values[idx];
}

char	*get_in_param_by_name(char *name)
{
	int	i;

	for (i = 0; i < test_case->in_params.data_num; i++)
	{
		if (0 == strcmp(test_case->in_params.names[i], name))
			return test_case->in_params.values[i];
	}

	fail_msg("Cannot find in_param by name='%s'", name);

	return NULL;
}

char	*get_out_param_by_name(char *name)
{
	int	i;

	for (i = 0; i < test_case->out_params.data_num; i++)
	{
		if (0 == strcmp(test_case->out_params.names[i], name))
			return test_case->out_params.values[i];
	}

	fail_msg("Cannot find out_param by name='%s'", name);

	return NULL;
}

char	*get_out_func_param_by_index(int idx)
{
	int	i;

	for (i = 0; i < test_case->function_num; i++)
	{
		if (0 == strcmp(test_case->functions[i].name, curr_wrapped_function))
		{
			if (test_case->functions[i].data.data_num > idx)
				break;

			return test_case->functions[i].data.values[idx];
		}
	}

	fail_msg("Cannot find out_func_param by index=%d", idx);

	return NULL;
}

char	*get_out_func_param_by_name(char *name)
{
	int	i, n;

	for (i = 0; i < test_case->function_num; i++)
	{
		if (0 == strcmp(test_case->functions[i].name, curr_wrapped_function))
		{
			for (n = 0; n < test_case->functions[i].data.data_num; n++)
			{
				if (0 == strcmp(test_case->functions[i].data.names[n], name))
					return test_case->functions[i].data.values[n];
			}
		}
	}

	fail_msg("Cannot find out_func_param by name='%s'", name);

	return NULL;
}
