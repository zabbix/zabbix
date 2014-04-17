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

#include "common.h"
#include "cfg.h"
#include "log.h"

char	*CONFIG_FILE		= NULL;

char	*CONFIG_LOG_FILE	= NULL;
int	CONFIG_LOG_FILE_SIZE	= 1;
int	CONFIG_ALLOW_ROOT	= 0;
int	CONFIG_TIMEOUT		= 3;

#if defined(_WINDOWS)
#	define PATH_SEPARATOR_STRING "\\"
#else
#	define PATH_SEPARATOR_STRING "/"
#endif

static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional, int strict);

int	parse_string_to_tokens(char *string_to_parse, char *delimiter, char ***tokens, int *tokens_cnt)
{
	char    *token_tmp;

	if (NULL == (token_tmp = strchr(string_to_parse, *delimiter)))
		return FALSE;

	*tokens = zbx_malloc(NULL, sizeof(char*));

	if (string_to_parse == token_tmp)
	{
		zbx_error("*<> is first");
		**tokens = token_tmp + 1;
	}
	else
	{
		zbx_error("<>*<> is first");
		**tokens = string_to_parse;
		*tokens_cnt = 1;
		*tokens = zbx_realloc(*tokens, sizeof(char*) * 2);
		*(*tokens + 1) = token_tmp + 1;
	}
	*token_tmp = '\0';

	while (NULL != (token_tmp = strchr(token_tmp + 1, *delimiter)))
	{
		*tokens_cnt = *tokens_cnt + 1;
		*tokens = zbx_realloc(*tokens, sizeof(char*) * (*tokens_cnt + 1));
		*(*tokens + *tokens_cnt) = token_tmp + 1;

		if (0 == **(*tokens + *tokens_cnt))
			*tokens_cnt = *tokens_cnt - 1;

		*token_tmp = '\0';
	}

	return SUCCEED;
}

int	parse_file_name_to_tokens(char *string_to_parse, char **extension_full, char ***tokens, int *tokens_cnt)
{
	char	*extension, *delimiter = "*";

	if (NULL == (*extension_full = strrchr(string_to_parse, PATH_SEPARATOR)))
		return FAIL;

	if (NULL == (extension = strrchr(string_to_parse, delimiter[0])))
	{
		zbx_rtrim(string_to_parse, PATH_SEPARATOR_STRING);
		return SUCCEED;
	}

	if (*extension_full > extension)
	{
		zbx_error("%s: Wrong usage of '*' wildcard", string_to_parse);
		return FAIL;
	}

	parse_string_to_tokens(*extension_full + 1, delimiter, tokens, tokens_cnt);

	**extension_full = '\0';

	return SUCCEED;
}


int	check_tokens_in_file_name(const char *cfg_file, char *path_tmp, char *file_name, char *extension_full, char **tokens, int tokens_cnt)
{
	char	*ex_carret, *delimiter = "*";
	int	tokens_cnt_tmp = 0;

	if (NULL == tokens[0])
		return SUCCEED;

	if (NULL == (ex_carret = strstr(file_name, tokens[0])))
		return FAIL;

	if (0 != strcmp(ex_carret,file_name))
	{
		if ('*' != *(strrchr(cfg_file, PATH_SEPARATOR) + 1))
			return FAIL;
	}

	for (; tokens_cnt >= tokens_cnt_tmp; tokens_cnt_tmp++)
	{
zbx_error("0 ex_carret: %s, %i, %i, %s", ex_carret, tokens_cnt, tokens_cnt_tmp, tokens[tokens_cnt_tmp]);

		if (NULL == tokens[tokens_cnt_tmp])
			return FAIL;

		if (NULL == (ex_carret = strstr(ex_carret, tokens[tokens_cnt_tmp])))
			return FAIL;

		ex_carret += strlen(tokens[tokens_cnt_tmp]);

		if ('\0' == *ex_carret)
			break;

		if (tokens_cnt == tokens_cnt_tmp)
		{
zbx_error("1 ex_carret: %s, %i, %i, %s", ex_carret, tokens_cnt, tokens_cnt_tmp, tokens[tokens_cnt_tmp]);

				char	*carret_tmp = NULL;

				while (1)
				{
					carret_tmp = ex_carret;
zbx_error("2 ex_carret: %s, %i, %i, %s", ex_carret, tokens_cnt, tokens_cnt_tmp, tokens[tokens_cnt_tmp]);
					if (NULL == (ex_carret = strstr(ex_carret, tokens[tokens_cnt_tmp])))
						break;

					ex_carret += strlen(tokens[tokens_cnt_tmp]);
					/*if (0 == strlen(ex_carret))
						break;*/

				}

				ex_carret = carret_tmp;

			if (0 != strlen(ex_carret))
			{
					zbx_error("3 ex_carret: %s, %s, %i", ex_carret, carret_tmp, tokens_cnt_tmp);

				if ('\0' != *(strrchr(cfg_file, delimiter[0]) + 1))
				{
					zbx_error("4 ex_carret: %s, %s, %i", ex_carret, carret_tmp, tokens_cnt_tmp);
					return FAIL;
				}
			}
		}
	}

	if (tokens_cnt > tokens_cnt_tmp)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_cfg_object                                                 *
 *                                                                            *
 * Purpose: parse "Include=..." line in configuration file                    *
 *                                                                            *
 * Parameters: cfg_file - full name of config file                            *
 *             cfg      - pointer to configuration parameter structure        *
 *             level    - a level of included file                            *
 *             strict   - treat unknown parameters as error                   *
 *                                                                            *
 * Return value: SUCCEED - parsed successfully                                *
 *               FAIL - error processing object                               *
 *                                                                            *
 ******************************************************************************/
static int	parse_cfg_object(const char *cfg_file, struct cfg_line *cfg, int level, int strict)
{
	const char		*__function_name = "parse_cfg_object";
	char 			*path_tmp = NULL, *file_name;
	char			*extension_full = NULL, **tokens_tmp = NULL;
	int			tokens_cnt = 0, ret;

#ifdef _WINDOWS
	WIN32_FIND_DATAW	find_file_data;
	HANDLE			h_find;
	char 			*path = NULL;
	wchar_t			*wpath;
	struct _stat	sb;

	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	path = zbx_strdup(path, cfg_file);

	if (FAIL == parse_file_name_to_tokens(path, &extension_full, &tokens_tmp, &tokens_cnt))
		goto out;

	wpath = zbx_utf8_to_unicode(path);

	if (0 != _wstat(wpath, &sb))
	{
		zbx_error("%s: %s\n", path, zbx_strerror(errno));
		goto out;
	}
	zbx_free(wpath);

	if (0 == S_ISDIR(sb.st_mode))
	{
		ret = __parse_cfg_file(path, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);
		goto out;
	}

	path_tmp = zbx_dsprintf(path_tmp, "%s\\*", path);

	wpath = zbx_utf8_to_unicode(path_tmp);

	if (INVALID_HANDLE_VALUE == (h_find = FindFirstFileW(wpath, &find_file_data)))
		goto out;

	while (0 != FindNextFileW(h_find, &find_file_data))
	{
		if (0 != (find_file_data.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY))
			continue;

		file_name = zbx_unicode_to_utf8(find_file_data.cFileName);

		if (FAIL == check_tokens_in_file_name(cfg_file, path_tmp, file_name, extension_full, tokens_tmp, tokens_cnt))
			continue;

		path_tmp = zbx_dsprintf(path_tmp, "%s\\%s", path, file_name);

		zbx_free(file_name);

		if (FAIL == __parse_cfg_file(path_tmp, cfg, level, ZBX_CFG_FILE_REQUIRED, strict))
			goto out;
	}

	ret = SUCCEED;
out:
	FindClose(h_find);
	zbx_free(wpath);
	zbx_free(path);

#else
	DIR		*dir;
	zbx_stat_t	sb;
	struct dirent	*d;
	char		*incl_file = NULL;
	char		*extension, *ex_carret;
	size_t		alloc_len = MAX_STRING_LEN, offset = 0;

	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	path_tmp = zbx_strdup(path_tmp, cfg_file);

	if (FAIL == parse_file_name_to_tokens(path_tmp, &extension_full, &tokens_tmp, &tokens_cnt))
	{
		ret = FAIL;
		goto out;
	}
	zbx_error("token count %i", tokens_cnt);
/*
	for (; 0 <= tokens_cnt; tokens_cnt--)
		zbx_error("token %i: %s", tokens_cnt, tokens_tmp[tokens_cnt]);
*/
	if (-1 == zbx_stat(path_tmp, &sb))
	{
		zbx_error("%s: %s", path_tmp, zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}

	if (!S_ISDIR(sb.st_mode))
		return __parse_cfg_file(path_tmp, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);

	if (NULL == (dir = opendir(path_tmp)))
	{
		zbx_error("%s: %s", path_tmp, zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}

	while (NULL != (d = readdir(dir)))
	{
		zbx_error("CHECKING file %s", d->d_name);
		if (FAIL == check_tokens_in_file_name(cfg_file, path_tmp, d->d_name, extension_full, tokens_tmp, tokens_cnt))
			continue;

		incl_file = zbx_dsprintf(incl_file, "%s/%s", path_tmp, d->d_name);

		if (-1 == zbx_stat(incl_file, &sb) || !S_ISREG(sb.st_mode))
			continue;

		if (FAIL == __parse_cfg_file(incl_file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict))
		{
			ret = FAIL;
			break;
		}
		zbx_error("file parsed: %s", incl_file);
	}

	if (-1 == closedir(dir))
	{
		zbx_error("%s: %s\n", path_tmp, zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}
out:
	zbx_free(incl_file);
#endif
	zbx_free(path_tmp);
	zbx_free(tokens_tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_cfg_file                                                   *
 *                                                                            *
 * Purpose: parse configuration file                                          *
 *                                                                            *
 * Parameters: cfg_file - full name of config file                            *
 *             cfg      - pointer to configuration parameter structure        *
 *             level    - a level of included file                            *
 *             optional - do not treat missing configuration file as error    *
 *             strict   - treat unknown parameters as error                   *
 *                                                                            *
 * Return value: SUCCEED - parsed successfully                                *
 *               FAIL - error processing config file                          *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional, int strict)
{
#define ZBX_MAX_INCLUDE_LEVEL	10

#define ZBX_CFG_LTRIM_CHARS	"\t "
#define ZBX_CFG_RTRIM_CHARS	ZBX_CFG_LTRIM_CHARS "\r\n"

	FILE		*file;
	int		i, lineno, param_valid;
	char		line[MAX_STRING_LEN], *parameter, *value;
	zbx_uint64_t	var;
#ifdef _WINDOWS
	wchar_t		*wcfg_file;
#endif
	if (++level > ZBX_MAX_INCLUDE_LEVEL)
	{
		zbx_error("Recursion detected! Skipped processing of '%s'.", cfg_file);
		return FAIL;
	}

	if (NULL != cfg_file)
	{
#ifdef _WINDOWS
		wcfg_file = zbx_utf8_to_unicode(cfg_file);
		file = _wfopen(wcfg_file, L"r");
		zbx_free(wcfg_file);

		if (NULL == file)
			goto cannot_open;
#else
		if (NULL == (file = fopen(cfg_file, "r")))
			goto cannot_open;
#endif
		for (lineno = 1; NULL != fgets(line, sizeof(line), file); lineno++)
		{
			zbx_ltrim(line, ZBX_CFG_LTRIM_CHARS);
			zbx_rtrim(line, ZBX_CFG_RTRIM_CHARS);

			if ('#' == *line || '\0' == *line)
				continue;

			/* we only support UTF-8 characters in the config file */
			if (SUCCEED != zbx_is_utf8(line))
				goto non_utf8;

			parameter = line;
			if (NULL == (value = strchr(line, '=')))
				goto non_key_value;

			*value++ = '\0';

			zbx_rtrim(parameter, ZBX_CFG_RTRIM_CHARS);
			zbx_ltrim(value, ZBX_CFG_LTRIM_CHARS);

			zabbix_log(LOG_LEVEL_DEBUG, "cfg: para: [%s] val [%s]", parameter, value);

			if (0 == strcmp(parameter, "Include"))
			{
				if (FAIL == parse_cfg_object(value, cfg, level, strict))
				{
					fclose(file);
					goto error;
				}

				continue;
			}

			param_valid = 0;

			for (i = 0; NULL != cfg[i].parameter; i++)
			{
				if (0 != strcmp(cfg[i].parameter, parameter))
					continue;

				param_valid = 1;

				zabbix_log(LOG_LEVEL_DEBUG, "accepted configuration parameter: '%s' = '%s'",
						parameter, value);

				switch (cfg[i].type)
				{
					case TYPE_INT:
						if (FAIL == str2uint64(value, "KMGT", &var))
							goto incorrect_config;

						if (cfg[i].min > var || (0 != cfg[i].max && var > cfg[i].max))
							goto incorrect_config;

						*((int *)cfg[i].variable) = (int)var;
						break;
					case TYPE_STRING_LIST:
						zbx_trim_str_list(value, ',');
						/* break; is not missing here */
					case TYPE_STRING:
						*((char **)cfg[i].variable) =
								zbx_strdup(*((char **)cfg[i].variable), value);
						break;
					case TYPE_MULTISTRING:
						zbx_strarr_add(cfg[i].variable, value);
						break;
					case TYPE_UINT64:
						if (FAIL == str2uint64(value, "KMGT", &var))
							goto incorrect_config;

						if (cfg[i].min > var || (0 != cfg[i].max && var > cfg[i].max))
							goto incorrect_config;

						*((zbx_uint64_t *)cfg[i].variable) = var;
						break;
					default:
						assert(0);
				}
			}

			if (0 == param_valid && ZBX_CFG_STRICT == strict)
				goto unknown_parameter;
		}
		fclose(file);
	}

	if (1 != level)	/* skip mandatory parameters check for included files */
		return SUCCEED;

	for (i = 0; NULL != cfg[i].parameter; i++) /* check for mandatory parameters */
	{
		if (PARM_MAND != cfg[i].mandatory)
			continue;

		switch (cfg[i].type)
		{
			case TYPE_INT:
				if (0 == *((int *)cfg[i].variable))
					goto missing_mandatory;
				break;
			case TYPE_STRING:
			case TYPE_STRING_LIST:
				if (NULL == (*(char **)cfg[i].variable))
					goto missing_mandatory;
				break;
			default:
				assert(0);
		}
	}

	return SUCCEED;
cannot_open:
	if (0 != optional)
		return SUCCEED;
	zbx_error("cannot open config file [%s]: %s", cfg_file, zbx_strerror(errno));
	goto error;
non_utf8:
	fclose(file);
	zbx_error("non-UTF-8 character at line %d (%s) in config file [%s]", lineno, line, cfg_file);
	goto error;
non_key_value:
	fclose(file);
	zbx_error("invalid entry [%s] (not following \"parameter=value\" notation) in config file [%s], line %d",
			line, cfg_file, lineno);
	goto error;
incorrect_config:
	fclose(file);
	zbx_error("wrong value of [%s] in config file [%s], line %d", cfg[i].parameter, cfg_file, lineno);
	goto error;
unknown_parameter:
	fclose(file);
	zbx_error("unknown parameter [%s] in config file [%s], line %d", parameter, cfg_file, lineno);
	goto error;
missing_mandatory:
	zbx_error("missing mandatory parameter [%s] in config file [%s]", cfg[i].parameter, cfg_file);
error:
	exit(EXIT_FAILURE);
}

int	parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int optional, int strict)
{
	return __parse_cfg_file(cfg_file, cfg, 0, optional, strict);
}
