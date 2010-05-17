/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

char	*CONFIG_FILE			= NULL;
int	CONFIG_ZABBIX_FORKS		= 3;

char	*CONFIG_LOG_FILE		= NULL;
int	CONFIG_LOG_FILE_SIZE		= 1;
char	CONFIG_ALLOW_ROOT		= 0;
int	CONFIG_TIMEOUT			= AGENT_TIMEOUT;

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

	if (stat(cfg_file, &sb) == -1) {
		zbx_error("%s: %s\n", cfg_file, strerror(errno));
		return FAIL;
	}

	if (!S_ISDIR(sb.st_mode))
		return __parse_cfg_file(cfg_file, cfg, level, 0);

	if (NULL == (dir = opendir(cfg_file))) {
		zbx_error("%s: %s\n", cfg_file, strerror(errno));
		return FAIL;
	}

	while (NULL != (d = readdir(dir))) {
		incl_file = zbx_dsprintf(incl_file, "%s/%s", cfg_file, d->d_name);

		if (stat(incl_file, &sb) == -1 || !S_ISREG(sb.st_mode))
			continue;

		if (__parse_cfg_file(incl_file, cfg, level, 0) == FAIL) {
			result = FAIL;
			break;
		}
	}
	zbx_free(incl_file);

	if (closedir(dir) == -1) {
		zbx_error("%s: %s\n", cfg_file, strerror(errno));
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
 * Author: Alexei Vladishev                                                   *
 *         Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	__parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int level, int optional)
{
	FILE	*file;

#define ZBX_MAX_INCLUDE_LEVEL 10

#define ZBX_CFG_LTRIM_CHARS "\t "
#define ZBX_CFG_RTRIM_CHARS ZBX_CFG_LTRIM_CHARS "\r\n\0"

	register int
		i, lineno;

	char
		line[MAX_STRING_LEN],
		*parameter,
		*value;

	zbx_uint64_t	var;

	int	result = SUCCEED;

	assert(cfg);

	if (++level > ZBX_MAX_INCLUDE_LEVEL)
	{
		zbx_error("Recursion detected! Skipped processing of '%s'", cfg_file);
		return FAIL;
	}

	if (cfg_file)
	{
		if (NULL == (file = fopen(cfg_file,"r")))
		{
			goto lbl_cannot_open;
		}
		else
		{
			for(lineno = 1; fgets(line,MAX_STRING_LEN,file) != NULL; lineno++)
			{
				zbx_ltrim(line, ZBX_CFG_LTRIM_CHARS);

				if(line[0]=='#')	continue;
				if(strlen(line) < 3)	continue;

				parameter	= line;

				value		= strstr(line,"=");
				if(NULL == value)
				{
					zbx_error("Error in line [%d] \"%s\"", lineno, line);
					result = FAIL;
					break;
				}

				*value = '\0';
				value++;

				zbx_rtrim(parameter, ZBX_CFG_RTRIM_CHARS);

				zbx_ltrim(value, ZBX_CFG_LTRIM_CHARS);
				zbx_rtrim(value, ZBX_CFG_RTRIM_CHARS);

				zabbix_log(LOG_LEVEL_DEBUG, "cfg: para: [%s] val [%s]", parameter, value);

				if(strcmp(parameter, "Include") == 0)
				{
					if (FAIL == (result = parse_cfg_object(value, cfg, level)))
						break;
				}

				for(i = 0; value[i] != '\0'; i++)
				{
					if(value[i] == '\n')
					{
						value[i] = '\0';
						break;
					}
				}


				for(i = 0; cfg[i].parameter != 0; i++)
				{
					if(strcmp(cfg[i].parameter, parameter))
						continue;

					zabbix_log(LOG_LEVEL_DEBUG, "Accepted configuration parameter: '%s' = '%s'",parameter, value);

					if(cfg[i].function != 0)
					{
						if(cfg[i].function(value) != SUCCEED)
							goto lbl_incorrect_config;
					}
					else if(TYPE_INT == cfg[i].type)
					{
						if (FAIL == str2uint64(value, &var))
							goto lbl_incorrect_config;

						if ( (cfg[i].min && var < cfg[i].min) ||
							(cfg[i].max && var > cfg[i].max) )
								goto lbl_incorrect_config;

						*((int*)cfg[i].variable) = var;
					}
					else
					{
						*((char **)cfg[i].variable) = strdup(value);
					}
				}
			}
			fclose(file);
		}
	}

	if (1 != level)	/* skip mandatory parameters check  for included files */
		return result;

	/* Check for mandatory parameters */
	for(i = 0; cfg[i].parameter != 0; i++)
	{
		if(PARM_MAND != cfg[i].mandatory)
			continue;

		if(TYPE_INT == cfg[i].type)
		{
			if(*((int*)cfg[i].variable) == 0)
				goto lbl_missing_mandatory;
		}
		else if(TYPE_STRING == cfg[i].type)
		{
			if((*(char **)cfg[i].variable) == NULL)
				goto lbl_missing_mandatory;
		}
	}

	return result;

lbl_cannot_open:
	if (optional)
		return result;
	zbx_error("Cannot open config file [%s] [%s].",cfg_file,strerror(errno));
	exit(1);

lbl_missing_mandatory:
	zbx_error("Missing mandatory parameter [%s].", cfg[i].parameter);
	exit(1);

lbl_incorrect_config:
	zbx_error("Wrong value for [%s] in line %d.", cfg[i].parameter, lineno);
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
