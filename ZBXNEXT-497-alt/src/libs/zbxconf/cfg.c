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

static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional, int strict);

/******************************************************************************
 *                                                                            *
 * Function: parse_glob                                                       *
 *                                                                            *
 * Purpose: parse a glob like "/usr/local/etc/zabbix_agentd.conf.d/p*.conf"   *
 *          into "/usr/local/etc/zabbix_agentd.conf.d" and "p*.conf" parts    *
 *                                                                            *
 * Parameters: glob    - [IN] glob as specified in Include directive          *
 *             path    - [OUT] parsed path, either directory or file          *
 *             pattern - [OUT] parsed pattern, if path is directory           *
 *                                                                            *
 * Return value: SUCCEED - glob is valid and was parsed successfully          *
 *               FAIL - glob is invalid                                       *
 *                                                                            *
 ******************************************************************************/
static int	parse_glob(const char *glob, char **path, char **pattern)
{
	const char	*p;

	if (NULL == (p = strchr(glob, '*')))
	{
		*path = zbx_strdup(NULL, glob);
		*pattern = NULL;

		return SUCCEED;
	}

	if (NULL != strchr(p + 1, PATH_SEPARATOR))
	{
		zbx_error("%s: glob pattern should be the last component of the path\n", glob);
		return FAIL;
	}

	do
	{
		if (glob == p)
		{
			zbx_error("%s: path should be absolute\n", glob);
			return FAIL;
		}

		p--;
	}
	while (PATH_SEPARATOR != *p);

	*path = zbx_strdup(NULL, glob);
#ifdef _WINDOWS
	(*path)[p - glob] = '\0';
#else
	(*path)[glob == p ? 1 : p - glob] = '\0';	/* root directory "/" should remain as is */
#endif
	*pattern = zbx_strdup(NULL, p + 1);

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
	int			ret = FAIL;
	char			*path = NULL, *pattern = NULL, *file = NULL;
	zbx_stat_t		sb;

#ifdef _WINDOWS
	WIN32_FIND_DATAW	find_file_data;
	HANDLE			h_find;
	char 			*find_path = NULL, *file_name;
	wchar_t			*wfind_path = NULL;

	if (SUCCEED != parse_glob(cfg_file, &path, &pattern))
		goto clean;

	zbx_rtrim(path, "\\");

	if (0 != zbx_stat(path, &sb))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		goto clean;
	}

	if (0 == S_ISDIR(sb.st_mode))
	{
		if (NULL == pattern)
		{
			ret = __parse_cfg_file(path, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);
			goto clean;
		}

		zbx_error("%s: path before pattern is not a directory\n", cfg_file);
		goto clean;
	}

	find_path = zbx_dsprintf(find_path, "%s\\*", path);
	wfind_path = zbx_utf8_to_unicode(find_path);

	if (INVALID_HANDLE_VALUE == (h_find = FindFirstFileW(wfind_path, &find_file_data)))
		goto clean;

	while (0 != FindNextFileW(h_find, &find_file_data))
	{
		if (0 != (find_file_data.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY))
			continue;

		file_name = zbx_unicode_to_utf8(find_file_data.cFileName);
		file = zbx_dsprintf(file, "%s\\%s", path, file_name);
		zbx_free(file_name);

		if (SUCCEED != __parse_cfg_file(file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict))
			goto close;
	}

	ret = SUCCEED;
close:
	FindClose(h_find);
clean:
	zbx_free(file);
	zbx_free(wfind_path);
	zbx_free(find_path);
	zbx_free(pattern);
	zbx_free(path);
#else
	DIR		*dir;
	struct dirent	*d;

	if (SUCCEED != parse_glob(cfg_file, &path, &pattern))
		goto clean;

	if (0 != zbx_stat(path, &sb))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		goto clean;
	}

	if (0 == S_ISDIR(sb.st_mode))
	{
		if (NULL == pattern)
		{
			ret = __parse_cfg_file(path, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);
			goto clean;
		}

		zbx_error("%s: path before pattern is not a directory\n", cfg_file);
		goto clean;
	}

	if (NULL == (dir = opendir(path)))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		goto clean;
	}

	while (NULL != (d = readdir(dir)))
	{
		file = zbx_dsprintf(file, "%s/%s", path, d->d_name);

		if (0 != zbx_stat(file, &sb) || 0 == S_ISREG(sb.st_mode))
			continue;

		if (SUCCEED != __parse_cfg_file(file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict))
			goto close;
	}

	ret = SUCCEED;
close:
	if (0 != closedir(dir))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		ret = FAIL;
	}
clean:
	zbx_free(file);
	zbx_free(pattern);
	zbx_free(path);
#endif
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
