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


int	CONFIG_ZABBIX_FORKS		= ZABBIX_FORKS;
char	*CONFIG_FILE			= NULL;


char	*CONFIG_LOG_FILE		= NULL;
char	CONFIG_ALLOW_ROOT_PERMISSION	= 0;
int	CONFIG_TIMEOUT			= AGENT_TIMEOUT;

/******************************************************************************
 *                                                                            *
 * Function: parse_cfg_file                                                   *
 *                                                                            *
 * Purpose: parse configuration file                                          *
 *                                                                            *
 * Parameters: cfg_file - full name of config filesocker descriptor           *
 *             cfg - pointer to configuration parameter structure             *
 *                                                                            *
 * Return value: SUCCEED - parsed succesfully                                 *
 *               FAIL - error processing config file                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *         Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	parse_cfg_file(char *cfg_file,struct cfg_line *cfg)
{
	FILE	*file;
	int	lineno;

	char	line[MAX_STRING_LEN];
	char	*parameter;
	char	*value;

	int	i,var;

	if(NULL == (file = fopen(cfg_file,"r")))
	{
		zbx_error("Cannot open config file [%s] [%s].",cfg_file,strerror(errno));
		return	FAIL;
	}

	
	for(lineno = 1; fgets(line,MAX_STRING_LEN,file) != NULL; lineno++)
	{
		if(line[0]=='#')	continue;
		if(strlen(line) < 3)	continue;

		parameter	= line;

		value		= strstr(line,"=");
		if(NULL == value)
		{
			zbx_error("Error in line [%d] \"%s\"", lineno, line);
			return	FAIL;
		}

		*value = '\0';
		value++;

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

			zbx_error("Accepted configuration parameter: '%s' = '%s'",parameter, value);

			if(cfg[i].function != 0)
			{
				if(cfg[i].function(value) != SUCCEED)
					goto lbl_incorrect_config;
			}
			else if(TYPE_INT == cfg[i].type)
			{
				var = atoi(value);

				if(cfg[i].min) 
					if(var < cfg[i].min)
						goto lbl_incorrect_config;

				if(cfg[i].max) 
					if(var > cfg[i].max)
						goto lbl_incorrect_config;

				*((int*)cfg[i].variable) = var;
			}
			else
			{
				*((char **)cfg[i].variable) = strdup(value);
			}
		}
	}

	/* Check for mandatory parameters */
	for(i = 0; cfg[i].parameter != 0; i++)
	{
		if(!cfg[i].mandatory)
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

	return	SUCCEED;

lbl_missing_mandatory:
	zbx_error("Missing mandatory parameter [%s].", cfg[i].parameter);
	return	FAIL;

lbl_incorrect_config:
	zbx_error("Wrong value of [%s] in line %d.", cfg[i].parameter, lineno);
	return	FAIL;
}
