/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "cfg.h"
#include "log.h"

char	*CONFIG_FILE		= NULL;
int	CONFIG_ZABBIX_FORKS	= 3;

char	*CONFIG_LOG_FILE	= NULL;
int	CONFIG_LOG_FILE_SIZE	= 1;
char	CONFIG_ALLOW_ROOT	= 0;
int	CONFIG_TIMEOUT		= 3;

static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional);

static int	parse_cfg_object(const char *cfg_file, struct cfg_line *cfg, int level)
{
#ifdef _WINDOWS
	return __parse_cfg_file(cfg_file, cfg, level, 0);
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
		return __parse_cfg_file(cfg_file, cfg, level, 0);

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

		if (FAIL == __parse_cfg_file(incl_file, cfg, level, 0))
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
 *             cfg - pointer to configuration parameter structure             *
 *                                                                            *
 * Return value: SUCCEED - parsed successfully                                *
 *               FAIL - error processing config file                          *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional)
{
#define ZBX_MAX_INCLUDE_LEVEL	10

#define ZBX_CFG_LTRIM_CHARS	"\t "
#define ZBX_CFG_RTRIM_CHARS	ZBX_CFG_LTRIM_CHARS "\r\n"

	FILE		*file;
	int		i, lineno, result = SUCCEED;
	char		line[MAX_STRING_LEN], *parameter, *value;
	zbx_uint64_t	var;

	assert(cfg);

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

			if ('#' == *line)
				continue;
			if (strlen(line) < 3)
				continue;

			parameter = line;
			value = strstr(line, "=");

			if (NULL == value)
			{
				zbx_error("error in line [%d] \"%s\"", lineno, line);
				result = FAIL;
				break;
			}

			*value++ = '\0';

			zbx_rtrim(parameter, ZBX_CFG_RTRIM_CHARS);

			zbx_ltrim(value, ZBX_CFG_LTRIM_CHARS);
			zbx_rtrim(value, ZBX_CFG_RTRIM_CHARS);

			zabbix_log(LOG_LEVEL_DEBUG, "cfg: para: [%s] val [%s]", parameter, value);

			if (0 == strcmp(parameter, "Include"))
			{
				if (FAIL == (result = parse_cfg_object(value, cfg, level)))
					break;
			}

			for (i = 0; '\0' != value[i]; i++)
			{
				if ('\n' == value[i])
				{
					value[i] = '\0';
					break;
				}
			}

			for (i = 0; NULL != cfg[i].parameter; i++)
			{
				if (0 != strcmp(cfg[i].parameter, parameter))
					continue;

				zabbix_log(LOG_LEVEL_DEBUG, "accepted configuration parameter: '%s' = '%s'",parameter, value);

				if (NULL != cfg[i].function)
				{
					if (SUCCEED != cfg[i].function(value))
						goto incorrect_config;
				}
				else if (TYPE_INT == cfg[i].type)
				{
					if (FAIL == str2uint64(value, &var))
						goto incorrect_config;

					if ((cfg[i].min && var < cfg[i].min) || (cfg[i].max && var > cfg[i].max))
						goto incorrect_config;

					*((int *)cfg[i].variable) = (int)var;
				}
				else if (TYPE_STRING == cfg[i].type)
				{
					*((char **)cfg[i].variable) = strdup(value);
				}
				else
					assert(0);
			}
		}
		fclose(file);
	}

	if (1 != level)	/* skip mandatory parameters check for included files */
		return result;

	for (i = 0; NULL != cfg[i].parameter; i++) /* check for mandatory parameters */
	{
		if (PARM_MAND != cfg[i].mandatory)
			continue;

		if (TYPE_INT == cfg[i].type)
		{
			if (0 == *((int *)cfg[i].variable))
				goto missing_mandatory;
		}
		else if (TYPE_STRING == cfg[i].type)
		{
			if (NULL == (*(char **)cfg[i].variable))
				goto missing_mandatory;
		}
		else
			assert(0);
	}

	return result;

cannot_open:
	if (optional)
		return result;
	zbx_error("cannot open config file [%s]: %s", cfg_file, zbx_strerror(errno));
	exit(1);

missing_mandatory:
	zbx_error("missing mandatory parameter [%s]", cfg[i].parameter);
	exit(1);

incorrect_config:
	zbx_error("wrong value for [%s] in line %d", cfg[i].parameter, lineno);
	exit(1);
}

int	parse_cfg_file(const char *cfg_file, struct cfg_line *cfg)
{
	return __parse_cfg_file(cfg_file, cfg, 0, 0);
}

int	parse_opt_cfg_file(const char *cfg_file, struct cfg_line *cfg)
{
	return __parse_cfg_file(cfg_file, cfg, 0, 1);
}
