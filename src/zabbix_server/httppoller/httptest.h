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

#ifndef ZABBIX_HTTPTEST_H
#define ZABBIX_HTTPTEST_H

#define S_ZBX_HTTPPAGE	struct s_zbx_httppage_t
S_ZBX_HTTPPAGE
{
	char		*data;
	int		allocated;
	int		offset;
};

#define S_ZBX_HTTPSTAT	struct s_zbx_httpstat_t
S_ZBX_HTTPSTAT
{
	long    	rspcode;
	double  	total_time;
	double  	speed_download;
	double		test_total_time;
	int		test_last_step;
};

#ifdef	HAVE_LIBCURL
	void process_httptests(int now);
#else
#	define process_httptests(now)
#endif /* HAVE_LIBCURL */

extern	int	httppoller_num;

extern  int     CONFIG_HTTPPOLLER_FORKS;

#endif
