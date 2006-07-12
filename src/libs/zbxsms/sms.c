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

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include <string.h>

#include <errno.h>

#include <termios.h>

#include "common.h"
#include "log.h"
#include "zlog.h"

#include "sms.h"

static int write_gsm(int fd, char *str, char *error, int max_error_len)
{
	int	len;
	int	ret = SUCCEED;

	len = strlen(str);

	zabbix_log(LOG_LEVEL_WARNING, "Write [%s]", str);

	if (write(fd, str, len) < len)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error writing to GSM modem [%s]", strerror(errno));
		zabbix_syslog("Error writing to GSM modem [%s]", strerror(errno));
		zbx_snprintf(error,max_error_len, "Error writing to GSM modem [%s]", strerror(errno));
		return FAIL;
	}

	return ret;
}

static int read_gsm(int fd, char *expect, char *error, int max_error_len)
{
	char	buffer[255];
	char	*bufptr;
	int	i,nbytes;
	int	ret = SUCCEED;

	/* read characters into our string buffer until we get a CR or NL */
	bufptr = buffer;
	while ((nbytes = read(fd, bufptr, buffer + sizeof(buffer) - bufptr - 1)) > 0)
	{
		bufptr += nbytes;
		if (bufptr[-1] == '\n' || bufptr[-1] == '\r')
			break;
	}
	/* nul terminate the string and see if we got an OK response */
	*bufptr = '\0';
/*	printf("Read buffer [%s]\n", buffer);
	for(i=0;i<strlen(buffer);i++)
		printf("[%x]\n",buffer[i]);*/
	zabbix_log(LOG_LEVEL_WARNING, "Read buffer [%s]", buffer);
	for(i=0;i<strlen(buffer);i++)
		zabbix_log(LOG_LEVEL_WARNING, "[%x]", buffer[i]);
	if (strstr(buffer, expect) == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Read something unexpected from GSM modem");
		zabbix_syslog("Read something unexpected from GSM modem");
		zbx_snprintf(error,max_error_len, "Read something unexpected from GSM modem");
		ret = FAIL;
	}
	return ret;
}

int	send_sms(char *device,char *number,char *message, char *error, int max_error_len)
{
	int	f;
	char	str[MAX_STRING_LEN];

	struct termios options, old_options;

	int	ret = SUCCEED;

	f=open(device,O_RDWR | O_NOCTTY | O_NDELAY);
	if(f == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error open(%s) [%s]", device, strerror(errno));
		zabbix_syslog("Error open(%s) [%s]", device, strerror(errno));
		zbx_snprintf(error,max_error_len, "Error open(%s) [%s]", device, strerror(errno));
		return FAIL;
	}
	fcntl(f, F_SETFL,0);
	tcgetattr(f, &old_options);

	memset(&options, 0, sizeof(struct termios));

	options.c_iflag     = IGNCR | INLCR | ICRNL;
	options.c_oflag     = ONOCR;
	options.c_cflag     = B38400 | CRTSCTS | CS8 | CLOCAL | CREAD;
	options.c_lflag     &= ~(ICANON | ECHO | ECHOE | ISIG);
	options.c_cc[VMIN]  = 0;
	options.c_cc[VTIME] = 100;

	tcsetattr(f, TCSANOW, &options);
	
	/* Turn off echo */
	if(ret == SUCCEED)
		ret = write_gsm(f,"ATE0\r", error, max_error_len);
	if(ret == SUCCEED)
		ret = read_gsm(f,"\rOK\r", error, max_error_len);

	/* Init modem */
	if(ret == SUCCEED)
		ret = write_gsm(f,"AT\r", error, max_error_len);
	if(ret == SUCCEED)
		ret = read_gsm(f,"\rOK\r", error, max_error_len);

	/* Switch to text mode */
	if(ret == SUCCEED)
		ret = write_gsm(f,"AT+CMGF=1\r", error, max_error_len);
	if(ret == SUCCEED)
		ret = read_gsm(f,"\rOK\r", error, max_error_len);

	/* Send phone number */
	if(ret == SUCCEED)
	{
		zbx_snprintf(str, sizeof(str),"AT+CMGS=\"%s\"\r", number);
		ret = write_gsm(f,str, error, max_error_len);
	}
	if(ret == SUCCEED)
		ret = read_gsm(f,"\r> ", error, max_error_len);

	/* Send message */
	if(ret == SUCCEED)
	{
		zbx_snprintf(str, sizeof(str),"%s\x01a", message);
		ret = write_gsm(f, str, error, max_error_len);
	}
	if(ret == SUCCEED)
		ret = read_gsm(f,"\r+CMGS: ", error, max_error_len);
	if(ret == SUCCEED)
		ret = read_gsm(f,"OK\r", error, max_error_len);

	tcsetattr(f, TCSANOW, &old_options);
	close(f);

	return ret;
}
