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

#include <stdio.h>
#include <stdlib.h>

#include <string.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"

int	autoregister(char *server)
{
	char	*p,*line,*host;
	char	*server,*key,*value_string;
	char	copy[MAX_STRING_LEN];
	char	result[MAX_STRING_LEN];
	char	host_b64[MAX_STRING_LEN],key_b64[MAX_STRING_LEN],value_b64[MAX_STRING_LEN];
	char	host_dec[MAX_STRING_LEN],key_dec[MAX_STRING_LEN],value_dec[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	char	timestamp[MAX_STRING_LEN];
	char	source[MAX_STRING_LEN];
	char	severity[MAX_STRING_LEN];

	int	ret=SUCCEED;
	int 	i;

	return ret;
}
