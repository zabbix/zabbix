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

#include "zbxtests.h"

#define	TEST_MAX_CASE_NUM		512
#define	TEST_MAX_DATASOURCE_NUM		1024
#define	TEST_MAX_FUNCTION_NUM		1024
#define	TEST_MAX_ROW_NUM		512
#define	TEST_MAX_DATA_NUM		512

extern char	*curr_tested_function;
extern char	*curr_wrapped_function;
extern char	*curr_case_name;

char	*get_in_param_by_index(int idx)
{
	int	i;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			if (cases[i].in_params.data_num < idx)
				return cases[i].in_params.values[idx];
			else
				goto out;
		}
	}
out:
	return NULL;
}

char	*get_out_param_by_index(int idx)
{
	int	i;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			if (cases[i].out_params.data_num < idx)
				return cases[i].out_params.values[idx];
			else
				goto out;
		}
	}
out:
	return NULL;
}

char	*get_in_param_by_name(const char *name)
{
	int	i, n;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			for (n = 0; n < cases[i].in_params.data_num; n++)
			{
				if (0 == strcmp(cases[i].in_params.names[n], name))
					return cases[i].in_params.values[n];
			}
		}
	}

	return NULL;
}

char	*get_out_param_by_name(const char *name)
{
	int	i, n;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			for (n = 0; n < cases[i].out_params.data_num; n++)
			{
				if (0 == strcmp(cases[i].out_params.names[n], name))
					return cases[i].out_params.values[n];
			}
		}
	}

	return NULL;
}

char	*get_out_func_param_by_idx(int idx)
{
	int	i, n;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			for (n = 0; n < cases[i].function_num; n++)
			{
				if (0 == strcmp(cases[i].functions[n].name, curr_wrapped_function))
				{
					if (cases[i].functions[n].data.data_num < idx)
						goto out;

					return cases[i].functions[n].data.values[idx];
				}
			}
		}
	}
out:
	return NULL;
}

char	*get_out_func_param_by_name(const char *name)
{
	int	i, n, v;

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function) &&
				0 == strcmp(cases[i].case_name, curr_case_name))
		{
			for (n = 0; n < cases[i].function_num; n++)
			{
				if (0 == strcmp(cases[i].functions[n].name, curr_wrapped_function))
				{
					for (v = 0; v < cases[i].functions[n].data.data_num; v++)
					{
						if (0 == strcmp(cases[i].functions[n].data.names[v], name))
							return cases[i].functions[n].data.values[v];
					}
				}
			}
		}
	}

	return NULL;
}

int	load_data(char *file_name)
{
	int			res = FAIL, lineno, found_case, idx_str, line_type = 0;
	char			line[MAX_STRING_LEN], *tmp, *p;
	FILE			*file;
	zbx_test_case_t		*curr_case;
	zbx_test_datasource_t	*curr_ds;
	zbx_test_data_t		*curr_data;
	zbx_test_function_t	*curr_func;

	case_num = 0;
	cases = malloc(TEST_MAX_CASE_NUM * sizeof(zbx_test_case_t));
	curr_case = NULL;

	if (NULL != (file = fopen(file_name, "r")))
	{
		for (lineno = 0; NULL != fgets(line, sizeof(line), file); lineno++)
		{
			idx_str = 0;
			tmp = strdup(line);

			zbx_rtrim(tmp, "\r\n");

			p = strtok(tmp,"|");
			while (p != NULL)
			{
				if (0 == idx_str) /* data type identification */
				{
					if (0 == strcmp(p, "CASE"))
					{
						curr_case = &cases[case_num];

						curr_case->datasources = malloc(TEST_MAX_DATASOURCE_NUM * sizeof(zbx_test_datasource_t));
						curr_case->datasource_num = 0;

						curr_case->functions = malloc(TEST_MAX_FUNCTION_NUM * sizeof(zbx_test_function_t));
						curr_case->function_num = 0;

						curr_case->in_params.data_num = 0;
						curr_case->out_params.data_num = 0;

						case_num++;

						line_type = ZBX_TEST_DATA_TYPE_CASE;
					}
					else if (0 == strcmp(p, "TESTED_FUNCTION"))
					{
						line_type = ZBX_TEST_DATA_TYPE_TESTED_FUNC;
					}
					else if (0 == strcmp(p, "DB_DATA"))
					{
						curr_ds = &curr_case->datasources[curr_case->datasource_num];
						curr_ds->rows = malloc(TEST_MAX_ROW_NUM * sizeof(zbx_test_data_t));
						curr_ds->rows->data_num = 0;
						curr_ds->row_num = 0;
						curr_case->datasource_num++;

						line_type = ZBX_TEST_DATA_TYPE_DB_DATA;
					}
					else if (0 == strcmp(p, "FUNCTIONS"))
					{
						curr_func = &curr_case->functions[curr_case->function_num];
						curr_func->data.data_num = 0;
						curr_case->function_num++;

						line_type = ZBX_TEST_DATA_TYPE_FUNCTION;
					}
					else if (0 == strcmp(p, "IN"))
					{
						curr_data = &curr_case->in_params;

						line_type = ZBX_TEST_DATA_TYPE_IN_PARAM;
					}
					else if (0 == strcmp(p, "OUT"))
					{
						curr_data = &curr_case->out_params;

						line_type = ZBX_TEST_DATA_TYPE_OUT_PARAM;
					}
					else if (0 == strcmp(p, "IN_VALUES"))
					{
						curr_data = &curr_case->in_params;

						line_type = ZBX_TEST_DATA_TYPE_IN_VALUE;
					}
					else if (0 == strcmp(p, "OUT_VALUES"))
					{
						curr_data = &curr_case->out_params;

						line_type = ZBX_TEST_DATA_TYPE_OUT_VALUE;
					}
					else if (0 == strcmp(p, "FIELDS"))
					{
						curr_data = &curr_ds->rows[curr_ds->row_num];
						curr_data->data_num = 0;
						curr_ds->row_num++;

						line_type = ZBX_TEST_DATA_TYPE_DB_FIELD;
					}
					else if (0 == strcmp(p, "ROW"))
					{
						line_type = ZBX_TEST_DATA_TYPE_DB_ROW;
					}
					else if (0 == strcmp(p, "FUNC_OUT_PARAMS"))
					{
						curr_data = &curr_func->data;

						line_type = ZBX_TEST_DATA_TYPE_FUNC_OUT;
					}
					else if (0 == strcmp(p, "FUNC_OUT_VALUES"))
					{
						curr_data = &curr_func->data;

						line_type = ZBX_TEST_DATA_TYPE_FUNC_VALUE;
					}
					else
					{
						line_type = ZBX_TEST_DATA_TYPE_UNKNOWN;
					}
				}
				else if (0 < idx_str) /* filling in data */
				{
					switch (line_type)
					{
						case ZBX_TEST_DATA_TYPE_CASE:
							if (1 == idx_str)
								curr_case->case_name = strdup(p);
							break;
						case ZBX_TEST_DATA_TYPE_TESTED_FUNC:
							if (1 == idx_str)
								curr_case->tested_function = strdup(p);
							break;
						case ZBX_TEST_DATA_TYPE_DB_DATA:
							if (1 == idx_str)
								curr_ds->name = strdup(p);
							break;
						case ZBX_TEST_DATA_TYPE_FUNCTION:
							if (1 == idx_str)
								curr_func->name = strdup(p);
							break;
						case ZBX_TEST_DATA_TYPE_IN_PARAM:
						case ZBX_TEST_DATA_TYPE_OUT_PARAM:
						case ZBX_TEST_DATA_TYPE_DB_FIELD:
						case ZBX_TEST_DATA_TYPE_FUNC_OUT:
							if (1 <= idx_str)
							{
								if (1 == idx_str)
									curr_data->names = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));

								curr_data->names[idx_str - 1] = strdup(p);
							}
							break;
						case ZBX_TEST_DATA_TYPE_IN_VALUE:
						case ZBX_TEST_DATA_TYPE_OUT_VALUE:
						case ZBX_TEST_DATA_TYPE_DB_ROW:
						case ZBX_TEST_DATA_TYPE_FUNC_VALUE:
							if (1 <= idx_str)
							{
								if (1 == idx_str)
									curr_data->values = (char **)malloc(TEST_MAX_DATA_NUM * sizeof(char *));

								curr_data->values[idx_str - 1] = strdup(p);
								curr_data->data_num++;
							}
							break;
						default:
							break;
					}
				}

				p = strtok(NULL, "|");
				idx_str++;
			}
			free(tmp);
		}
		res = SUCCEED;
	}

	fclose(file);

	return res;
}

void	free_data()
{
	int	i, d, n, n2, f;

	for (i = 0; i < case_num; i++)
	{
		free(cases[i].case_name);
		free(cases[i].tested_function);

		for (d = 0; d < cases[i].datasource_num; d++)
		{
			free(cases[i].datasources[d].name);

			for (n = 0; n < cases[i].datasources[d].row_num; n++)
			{
				for (n2 = 0; n2 < cases[i].datasources[d].rows[n].data_num; n2++)
				{
					free(cases[i].datasources[d].rows[n].names[n2]);
					free(cases[i].datasources[d].rows[n].values[n2]);
				}

				free(cases[i].datasources[d].rows[n].names);
				free(cases[i].datasources[d].rows[n].values);
			}

			free(cases[i].datasources[d].rows);
		}

		free(cases[i].datasources);

		for (n = 0; n < cases[i].in_params.data_num; n++)
		{
			free(cases[i].in_params.names[n]);
			free(cases[i].in_params.values[n]);
		}

		free(cases[i].in_params.names);
		free(cases[i].in_params.values);

		for (n = 0; n < cases[i].out_params.data_num; n++)
		{
			free(cases[i].out_params.names[n]);
			free(cases[i].out_params.values[n]);
		}

		free(cases[i].out_params.names);
		free(cases[i].out_params.values);

		for (f = 0; f < cases[i].function_num; f++)
		{
			free(cases[i].functions[f].name);

			for (n = 0; n < cases[i].functions[f].data.data_num; n++)
			{
				free(cases[i].functions[f].data.names[n]);
				free(cases[i].functions[f].data.values[n]);
			}

			free(cases[i].functions[f].data.names);
			free(cases[i].functions[f].data.values);
		}

		free(cases[i].functions);
	}

	free(cases);
}

void	debug_print_cases()
{
	int i, n, n2;

	printf("case_num %d\n", case_num);

	for (i = 0; i < case_num; i++)
	{
		printf("in_params.data_num %d\n", cases[i].in_params.data_num);
		printf("out_params.data_num %d\n", cases[i].out_params.data_num);
		printf("datasource_num %d\n", cases[i].datasource_num);
		printf("function_num %d\n", cases[i].function_num);

		printf("case_name: %s\n", cases[i].case_name);

		for (n = 0; n < cases[i].in_params.data_num; n++)
		{
			printf(" in_param_names: %s\n", cases[i].in_params.names[n]);
			printf(" in_param_values: %s\n", cases[i].in_params.values[n]);
		}

		for (n = 0; n < cases[i].out_params.data_num; n++)
		{
			printf(" out_param_names: %s\n", cases[i].out_params.names[n]);
			printf(" out_param_values: %s\n", cases[i].out_params.values[n]);
		}

		for (n = 0; n < cases[i].datasource_num; n++)
		{
			printf(" datasource: %s (num %d)\n", cases[i].datasources[n].name,
					cases[i].datasources[n].rows->data_num);

			for (n2 = 0; n2 < cases[i].datasources[n].rows->data_num; n2++)
			{
				printf("  datasource field: %s\n", cases[i].datasources[n].rows->names[n2]);
				printf("  datasource values: %s\n", cases[i].datasources[n].rows->values[n2]);
			}
		}

		for (n = 0; n < cases[i].function_num; n++)
		{

			printf(" function: %s (num %d)\n", cases[i].functions[n].name,
					cases[i].functions[n].data.data_num);

			for (n2 = 0; n2 < cases[i].functions[n].data.data_num; n2++)
			{
				printf("  function param: %s\n", cases[i].functions[n].data.names[n2]);
				printf("  function values: %s\n", cases[i].functions[n].data.values[n2]);
			}
		}
	}
}
