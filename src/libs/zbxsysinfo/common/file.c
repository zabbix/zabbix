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

#include "config.h"

#include <errno.h>
#include <string.h>

/* stat() */
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>

/* FILE */
#include <stdio.h>

#include "common.h"
#include "sysinfo.h"

#define MAX_FILE_LEN 1024*1024

int	VFS_FILE_SIZE(const char *cmd, const char *filename,double  *value)
{
	struct stat	buf;

	if(stat(filename,&buf) == 0)
	{
		*value=(double)buf.st_size;
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_ATIME(const char *cmd, const char *filename,double  *value)
{
	struct stat	buf;

	if(stat(filename,&buf) == 0)
	{
		*value=(double)buf.st_atime;
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_CTIME(const char *cmd, const char *filename,double  *value)
{
	struct stat	buf;

	if(stat(filename,&buf) == 0)
	{
		*value=(double)buf.st_ctime;
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_MTIME(const char *cmd, const char *filename,double  *value)
{
	struct stat	buf;

	if(stat(filename,&buf) == 0)
	{
		*value=(double)buf.st_mtime;
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_EXISTS(const char *cmd, const char *filename,double  *value)
{
	struct stat	buf;

	*value=(double)0;

	/* File exists */
	if(stat(filename,&buf) == 0)
	{
		/* Regular file */
		if(S_ISREG(buf.st_mode))
		{
			*value=(double)1;
		}
	}
	/* File does not exist or any other error */
	return SYSINFO_RET_OK;
}

int	VFS_FILE_REGEXP(const char *cmd, const char *param, char **value)
{
	char	filename[MAX_STRING_LEN];
	char	regexp[MAX_STRING_LEN];
	FILE	*f = NULL;
	char	*buf = NULL;
	int	len;
	char	tmp[MAX_STRING_LEN];
	char	*c;

	int	ret = SYSINFO_RET_OK;

	memset(tmp,0,MAX_STRING_LEN);

	if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
	{
		ret = SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, regexp, MAX_STRING_LEN) != 0)
	{
		ret = SYSINFO_RET_FAIL;
	}


	if(ret == SYSINFO_RET_OK)
	{
		f=fopen(filename,"r");
		if(f==NULL)
		{
			ret = SYSINFO_RET_FAIL;
		}
	}

	if(ret == SYSINFO_RET_OK)
	{
		buf=(char *)malloc((size_t)MAX_FILE_LEN);
		if(buf == NULL)
		{
			ret = SYSINFO_RET_FAIL;
		}
		else
		{
			memset(buf,0,MAX_FILE_LEN);
		}
	}

	if(ret == SYSINFO_RET_OK)
	{
		if(0 == fread(buf, 1, MAX_FILE_LEN-1, f))
		{
			ret = SYSINFO_RET_FAIL;
		}
	}


	if(f != NULL)
	{
		fclose(f);
	}

	if(ret == SYSINFO_RET_OK)
	{
		c=zbx_regexp_match(buf, regexp, &len);

		if(c == NULL)
		{
			tmp[0]=0;
		}
		else
		{
			strncpy(tmp,c,len);
		}

		*value = strdup(tmp);
	}

	if(buf != NULL)
	{
		free(buf);
	}

	return	ret;
}

int	VFS_FILE_REGMATCH(const char *cmd, const char *param,double  *value)
{
	char	filename[MAX_STRING_LEN];
	char	regexp[MAX_STRING_LEN];

	int	ret = SYSINFO_RET_OK;

	if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
	{
		ret = SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, regexp, MAX_STRING_LEN) != 0)
	{
		ret = SYSINFO_RET_FAIL;
	}

	ret = SYSINFO_RET_FAIL;

	return ret;
}
