/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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


#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

/* #define TEST_PARAMETERS */
 
void	process(char *command, char *value);

void    add_user_parameter(char *key,char *command);
void	test_parameters(void);
double	getPROC(char *file,int lineno,int fieldno);

double	BUFFERSMEM(void);
double	CACHEDMEM(void);
double	CKSUM(const char * filename);
double	FILESIZE(const char * filename);
double	DISKFREE(const char * mountPoint);
double	DISKTOTAL(const char * mountPoint);
double	DISK_IO(void);
double	DISK_RIO(void);
double	DISK_WIO(void);
double	DISK_RBLK(void);
double	DISK_WBLK(void);
double	FREEMEM(void);
double	INODE(const char * mountPoint);
double	INODETOTAL(const char * mountPoint);
double	KERNEL_MAXPROC(void);
double	KERNEL_MAXFILES(void);
double	NETLOADIN1(char *interface);
double	NETLOADIN5(char *interface);
double	NETLOADIN15(char *interface);
double	NETLOADOUT1(char *interface);
double	NETLOADOUT5(char *interface);
double	NETLOADOUT15(char *interface);
double	PING(void);
double	SHAREDMEM(void);
double	TOTALMEM(void);
double	PROCCNT(const char *procname);
double	PROCCOUNT(void);
double	PROCLOAD(void);
double	PROCLOAD5(void);
double	PROCLOAD15(void);
double	SWAPFREE(void);
double	SWAPTOTAL(void);
double	TCP_LISTEN(const char *porthex);
double	UPTIME(void);

double	EXECUTE(char *command);
char	*EXECUTE_STR(char *command);
char	*VERSION(void);

double	CHECK_SERVICE(char *service);

/*double	CHECK_SERVICE_SSH(void);
double	CHECK_SERVICE_SMTP(void);
double	CHECK_SERVICE_FTP(void);
double	CHECK_SERVICE_HTTP(void);
double	CHECK_SERVICE_POP(void);
double	CHECK_SERVICE_NNTP(void);
double	CHECK_SERVICE_IMAP(void);*/

double	CHECK_PORT(char *ip_and_port);

#define COMMAND struct command_type
COMMAND
{
	char	*key;
	double   (*function)();
        char    *(*function_str)();
	char	*parameter;
};


#endif
