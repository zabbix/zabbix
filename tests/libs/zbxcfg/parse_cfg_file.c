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

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxcfg.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle, parameters, parameter;
	const char		*cfg_file, *validation, *tmp, **multi_string, *string_list;
	int			strict = 42, exit_code, parameter_count = 0, i;
	zbx_cfg_line_t		*cfg = NULL;
	void			**expected_values = NULL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("configuration file", &handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &cfg_file)))
	{
		fail_msg("Cannot get configuration file from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("validation", &handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &validation)))
	{
		fail_msg("Cannot get validation mode from test case data: %s", zbx_mock_error_string(error));
	}

	if (0 == strcmp(validation, "not strict"))
		strict = ZBX_CFG_NOT_STRICT;
	else if (0 == strcmp(validation, "strict"))
		strict = ZBX_CFG_STRICT;
	else
		fail_msg("Invalid validation mode \"%s\".", validation);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("parameters", &parameters)))
		fail_msg("Cannot get description of parameters from test case data: %s", zbx_mock_error_string(error));

	while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(parameters, &parameter)))
	{
		cfg = zbx_realloc(cfg, (parameter_count + 1) * sizeof(zbx_cfg_line_t));
		expected_values = zbx_realloc(expected_values, (parameter_count + 1) * sizeof(void *));

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_object_member(parameter, "name", &handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &tmp)))
		{
			fail_msg("Cannot get name of parameter #%d: %s", parameter_count + 1,
					zbx_mock_error_string(error));
		}

		cfg[parameter_count].parameter = tmp;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_object_member(parameter, "type", &handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &tmp)))
		{
			fail_msg("Cannot get type of parameter #%d: %s", parameter_count + 1,
					zbx_mock_error_string(error));
		}

		if (0 == strcmp(tmp, "numeric"))
		{
			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "expect", &handle)))
			{
				expected_values[parameter_count] = NULL;
			}
			else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle,
					&tmp)))
			{
				expected_values[parameter_count] = zbx_malloc(NULL, sizeof(zbx_uint64_t));

				if (SUCCEED != zbx_is_uint64(tmp, expected_values[parameter_count]))
				{
					fail_msg("Expected value \"%s\" of parameter #%d is not numeric.", tmp,
							parameter_count + 1);
				}
			}
			else
				break;

			cfg[parameter_count].variable = zbx_malloc(NULL, sizeof(zbx_uint64_t));
			*(zbx_uint64_t *)cfg[parameter_count].variable = (zbx_uint64_t)-1;

			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "min", &handle)))
			{
				cfg[parameter_count].min = 0;
			}
			else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle,
					&tmp)))
			{
				zbx_uint64_t	min;

				if (SUCCEED != zbx_is_uint64(tmp, &min))
				{
					fail_msg("Minimum allowed value \"%s\" of parameter #%d is not numeric.", tmp,
							parameter_count + 1);
				}

				cfg[parameter_count].min = min;
			}
			else
				break;

			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "max", &handle)))
			{
				cfg[parameter_count].max = 0;
			}
			else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle,
					&tmp)))
			{
				zbx_uint64_t	max;

				if (SUCCEED != zbx_is_uint64(tmp, &max))
				{
					fail_msg("Maximum allowed value \"%s\" of parameter #%d is not numeric.", tmp,
							parameter_count + 1);
				}

				cfg[parameter_count].max = max;
			}
			else
				break;

			cfg[parameter_count].type = ZBX_CFG_TYPE_UINT64;	/* no separate treatment for */
										/* ZBX_CFG_TYPE_INT          */
		}
		else if (0 == strcmp(tmp, "string"))
		{
			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "expect", &handle)))
			{
				expected_values[parameter_count] = NULL;
			}
			else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle,
					&tmp)))
			{
				expected_values[parameter_count] = zbx_malloc(NULL, sizeof(char *));
				*(const char **)expected_values[parameter_count] = tmp;
			}
			else
				break;

			cfg[parameter_count].variable = zbx_malloc(NULL, sizeof(char *));
			*(char **)cfg[parameter_count].variable = NULL;
			cfg[parameter_count].min = 0;
			cfg[parameter_count].max = 0;
			cfg[parameter_count].type = ZBX_CFG_TYPE_STRING;
		}
		else if (0 == strcmp(tmp, "string list"))
		{
			expected_values[parameter_count] = zbx_malloc(NULL, sizeof(zbx_mock_handle_t));

			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "expect",
					expected_values[parameter_count])))
			{
				fail_msg("Missing expected field for parameter #%d of string list type, use []"
						" instead.", parameter_count + 1);
			}

			if (ZBX_MOCK_SUCCESS != error)
				break;

			cfg[parameter_count].variable = zbx_malloc(NULL, sizeof(char *));
			*(char **)cfg[parameter_count].variable = NULL;
			cfg[parameter_count].min = 0;
			cfg[parameter_count].max = 0;
			cfg[parameter_count].type = ZBX_CFG_TYPE_STRING_LIST;
		}
		else if (0 == strcmp(tmp, "multi string"))
		{
			expected_values[parameter_count] = zbx_malloc(NULL, sizeof(zbx_mock_handle_t));

			if (ZBX_MOCK_NO_SUCH_MEMBER == (error = zbx_mock_object_member(parameter, "expect",
					expected_values[parameter_count])))
			{
				fail_msg("Missing expected field for parameter #%d of multi string type, use []"
						" instead.", parameter_count + 1);
			}

			if (ZBX_MOCK_SUCCESS != error)
				break;

			cfg[parameter_count].variable = zbx_malloc(NULL, sizeof(char **));
			*(char ***)cfg[parameter_count].variable = NULL;
			zbx_strarr_init((char ***)cfg[parameter_count].variable);
			cfg[parameter_count].min = 0;
			cfg[parameter_count].max = 0;
			cfg[parameter_count].type = ZBX_CFG_TYPE_MULTISTRING;
		}
		else
			fail_msg("Invalid type \"%s\" of parameter #%d.", tmp, parameter_count + 1);

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_object_member(parameter, "mandatory", &handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &tmp)))
		{
			fail_msg("Cannot get mandatory flag of parameter #%d: %s", parameter_count + 1,
					zbx_mock_error_string(error));
		}

		if (0 == strcmp(tmp, "yes"))
			cfg[parameter_count].mandatory = ZBX_CONF_PARM_MAND;
		else if (0 == strcmp(tmp, "no"))
			cfg[parameter_count].mandatory = ZBX_CONF_PARM_OPT;
		else
			fail_msg("Invalid mandatory flag \"%s\" of parameter #%d.", tmp, parameter_count + 1);

		parameter_count++;
	}

	if (ZBX_MOCK_END_OF_VECTOR != error)
	{
		fail_msg("Cannot get description of parameter #%d from test case data: %s", parameter_count + 1,
				zbx_mock_error_string(error));
	}

	cfg = zbx_realloc(cfg, (parameter_count + 1) * sizeof(zbx_cfg_line_t));
	cfg[parameter_count].parameter = NULL;

	zbx_init_library_cfg(ZBX_PROGRAM_TYPE_SERVER, cfg_file);

	zbx_parse_cfg_file(cfg_file, cfg, ZBX_CFG_FILE_REQUIRED, strict, ZBX_CFG_EXIT_FAILURE, ZBX_CFG_ENVVAR_USE);

	if (ZBX_MOCK_NO_EXIT_CODE != (error = zbx_mock_exit_code(&exit_code)))
	{
		if (ZBX_MOCK_SUCCESS == error)
			fail_msg("zbx_parse_cfg_file() was expected to call exit(%d), but has not.", exit_code);
		else
			fail_msg("Cannot get exit code from test case data: %s", zbx_mock_error_string(error));
	}

	for (i = 0; i < parameter_count; i++)
	{
		switch (cfg[i].type)
		{
			case ZBX_CFG_TYPE_MULTISTRING:
				multi_string = *(const char ***)cfg[i].variable;
				while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(
						*(zbx_mock_handle_t *)expected_values[i], &handle)) &&
						ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle, &tmp)))
				{
					if (NULL == *multi_string)
					{
						fail_msg("Values of multi string parameter \"%s\" ended while \"%s\""
								" was expected.", cfg[i].parameter, tmp);
					}

					if (0 != strcmp(*multi_string, tmp))
					{
						fail_msg("Value \"%s\" of multi string parameter \"%s\""
								" differs from expected \"%s\".", *multi_string,
								cfg[i].parameter, tmp);
					}

					multi_string++;
				}
				if (ZBX_MOCK_END_OF_VECTOR != error)
				{
					fail_msg("Cannot get expected values of multi string parameter \"%s\": %s",
							cfg[i].parameter, zbx_mock_error_string(error));
				}
				if (NULL != *multi_string)
				{
					fail_msg("Value of multi string parameter \"%s\" ends with unexpected \"%s\""
							" (and maybe more).", cfg[i].parameter, *multi_string);
				}
				break;
			case ZBX_CFG_TYPE_STRING_LIST:
				string_list = *(const char **)cfg[i].variable;
				while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(
						*(zbx_mock_handle_t *)expected_values[i], &handle)) &&
						ZBX_MOCK_SUCCESS == (error = zbx_mock_string(handle, &tmp)))
				{
					if ('\0' == *string_list)
					{
						fail_msg("Value of string list parameter \"%s\" ended when \"%s{,...}\""
								" was expected.", cfg[i].parameter, tmp);
					}

					if (0 != strncmp(string_list, tmp, strlen(tmp)))
					{
						fail_msg("Value of string list parameter \"%s\" starting with \"%s\""
								" differs from expected \"%s{,...}\".",
								cfg[i].parameter, string_list, tmp);
					}

					string_list += strlen(tmp);

					if (',' != *string_list)
					{
						if ('\0' != *string_list)
						{
							fail_msg("Value of string list parameter \"%s\" starting with"
								" \"%s\" differs from expected.", cfg[i].parameter,
								string_list);
						}
					}
					else
						string_list++;
				}
				if (ZBX_MOCK_END_OF_VECTOR != error)
				{
					fail_msg("Cannot get expected value of string list parameter \"%s\": %s",
							cfg[i].parameter, zbx_mock_error_string(error));
				}
				if ('\0' != *string_list)
				{
					fail_msg("Values of string list parameter \"%s\" ends with unexpected \"%s\".",
							cfg[i].parameter, string_list);
				}
				break;
			case ZBX_CFG_TYPE_STRING:
				if (NULL == *(char **)cfg[i].variable && NULL != *(char **)expected_values[i])
				{
					fail_msg("No value of string parameter \"%s\" while expected \"%s\".",
							cfg[i].parameter, *(char **)expected_values[i]);
				}
				else if (NULL != *(char **)cfg[i].variable && NULL == *(char **)expected_values[i])
				{
					fail_msg("Got value \"%s\" of string parameter \"%s\" none was expected.",
							*(char **)cfg[i].variable, cfg[i].parameter);
				}
				else if (NULL != *(char **)cfg[i].variable && NULL != *(char **)expected_values[i] &&
						0 != strcmp(*(char **)cfg[i].variable, *(char **)expected_values[i]))
				{
					fail_msg("Value \"%s\" of string parameter \"%s\" differs from expected"
							" \"%s\".", *(char **)cfg[i].variable, cfg[i].parameter,
							*(char **)expected_values[i]);
				}
				break;
			case ZBX_CFG_TYPE_UINT64:
				if (*(zbx_uint64_t *)cfg[i].variable != *(zbx_uint64_t *)expected_values[i])
				{
					fail_msg("Value " ZBX_FS_UI64 " of numeric parameter \"%s\""
							" differs from expected (" ZBX_FS_UI64 ").",
							*(zbx_uint64_t *)cfg[i].variable, cfg[i].parameter,
							*(zbx_uint64_t *)expected_values[i]);
				}
				break;
			default:
				fail_msg("Invalid type of parameter \"%s\" when doing validation.", cfg[i].parameter);
		}
	}

	for (i = 0; i < parameter_count; i++)
	{
		switch (cfg[i].type)
		{
			case ZBX_CFG_TYPE_MULTISTRING:
				zbx_strarr_free((char ***)cfg[i].variable);
				zbx_free(cfg[i].variable);
				zbx_free(expected_values[i]);
				break;
			case ZBX_CFG_TYPE_STRING_LIST:
			case ZBX_CFG_TYPE_STRING:
				zbx_free(*(char **)cfg[i].variable);
				zbx_free(cfg[i].variable);
				zbx_free(expected_values[i]);
				break;
			case ZBX_CFG_TYPE_UINT64:
				zbx_free(cfg[i].variable);
				zbx_free(expected_values[i]);
				break;
			default:
				fail_msg("Invalid type of parameter \"%s\" when doing cleanup.", cfg[i].parameter);
		}
	}

	zbx_free(expected_values);
	zbx_free(cfg);
}
