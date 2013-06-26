/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

static int	parse_cfg_object(const char *cfg_file, struct cfg_line *cfg, int level, int strict)
{
#ifdef _WINDOWS
	return __parse_cfg_file(cfg_file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);
#else
	DIR		*dir;
	struct stat	sb;
	struct dirent	*d;
	char		*incl_file = NULL;
	int		result = SUCCEED;

	if (-1 == stat(cfg_file, &sb))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		return FAIL;
	}

	if (!S_ISDIR(sb.st_mode))
		return __parse_cfg_file(cfg_file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict);

	if (NULL == (dir = opendir(cfg_file)))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		return FAIL;
	}

	while (NULL != (d = readdir(dir)))
	{
		incl_file = zbx_dsprintf(incl_file, "%s/%s", cfg_file, d->d_name);

		if (-1 == stat(incl_file, &sb) || !S_ISREG(sb.st_mode))
			continue;

		if (FAIL == __parse_cfg_file(incl_file, cfg, level, ZBX_CFG_FILE_REQUIRED, strict))
		{
			result = FAIL;
			break;
		}
	}
	zbx_free(incl_file);

	if (-1 == closedir(dir))
	{
		zbx_error("%s: %s\n", cfg_file, zbx_strerror(errno));
		return FAIL;
	}

	return result;
#endif
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
	int		i, lineno, result = SUCCEED, param_valid;
	char		line[MAX_STRING_LEN], *parameter, *value;
	zbx_uint64_t	var;

	if (++level > ZBX_MAX_INCLUDE_LEVEL)
	{
		zbx_error("Recursion detected! Skipped processing of '%s'.", cfg_file);
		return FAIL;
	}

	if (NULL != cfg_file)
	{
		if (NULL == (file = fopen(cfg_file, "r")))
			goto cannot_open;

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
				if (FAIL == (result = parse_cfg_object(value, cfg, level, strict)))
					break;

				continue;
			}

			for (i = 0; '\0' != value[i]; i++)
			{
				if ('\n' == value[i])
				{
					value[i] = '\0';
					break;
				}
			}

			param_valid = 0;
			for (i = 0; NULL != cfg[i].parameter; i++)
			{
				if (0 != strcmp(cfg[i].parameter, parameter))
					continue;

				param_valid = 1;

				zabbix_log(LOG_LEVEL_DEBUG, "accepted configuration parameter: '%s' = '%s'",parameter, value);

				switch (cfg[i].type)
				{
					case TYPE_INT:
						if (FAIL == str2uint64(value, "KMGT", &var))
							goto incorrect_config;

						/* usability: 2G converts to 0x7fffffff (2GB - 1 byte) */
						if ((zbx_uint64_t)2 * ZBX_GIBIBYTE == var)
							var--;

						if (cfg[i].min > var || (0 != cfg[i].max && var > cfg[i].max))
							goto incorrect_config;

						*((int *)cfg[i].variable) = (int)var;
						break;
					case TYPE_STRING:
						*((char **)cfg[i].variable) =
								zbx_strdup(*((char **)cfg[i].variable), value);
						break;
					case TYPE_MULTISTRING:
						zbx_strarr_add(cfg[i].variable, value);
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
		return result;

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
				if (NULL == (*(char **)cfg[i].variable))
					goto missing_mandatory;
				break;
			default:
				assert(0);
		}
	}

	return result;
cannot_open:
	if (0 != optional)
		return result;
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
