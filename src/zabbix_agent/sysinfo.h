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
float	getPROC(char *file,int lineno,int fieldno);

float	BUFFERSMEM(void);
float	CACHEDMEM(void);
float	CKSUM(const char * filename);
float	FILESIZE(const char * filename);
float	DISKFREE(const char * mountPoint);
float	DISKTOTAL(const char * mountPoint);
float	DISK_IO(void);
float	DISK_RIO(void);
float	DISK_WIO(void);
float	DISK_RBLK(void);
float	DISK_WBLK(void);
float	FREEMEM(void);
float	INODE(const char * mountPoint);
float	INODETOTAL(const char * mountPoint);
float	KERNEL_MAXPROC(void);
float	KERNEL_MAXFILES(void);
float	NETLOADIN1(char *interface);
float	NETLOADIN5(char *interface);
float	NETLOADIN15(char *interface);
float	NETLOADOUT1(char *interface);
float	NETLOADOUT5(char *interface);
float	NETLOADOUT15(char *interface);
float	PING(void);
float	SHAREDMEM(void);
float	TOTALMEM(void);
float	PROCCNT(const char *procname);
float	PROCCOUNT(void);
float	PROCLOAD(void);
float	PROCLOAD5(void);
float	PROCLOAD15(void);
float	SWAPFREE(void);
float	SWAPTOTAL(void);
float	TCP_LISTEN(const char *porthex);
float	UPTIME(void);

float	EXECUTE(char *command);
char	*EXECUTE_STR(char *command);
char	*VERSION(void);

float	CHECK_SERVICE(char *service);

/*float	CHECK_SERVICE_SSH(void);
float	CHECK_SERVICE_SMTP(void);
float	CHECK_SERVICE_FTP(void);
float	CHECK_SERVICE_HTTP(void);
float	CHECK_SERVICE_POP(void);
float	CHECK_SERVICE_NNTP(void);
float	CHECK_SERVICE_IMAP(void);*/

float	CHECK_PORT(char *ip_and_port);

#define COMMAND struct command_type
COMMAND
{
	char	*key;
        float   (*function)();
        char    *(*function_str)();
	char	*parameter;
};


#endif
