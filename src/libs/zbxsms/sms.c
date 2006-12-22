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

	zabbix_log(LOG_LEVEL_WARNING, "Write [%s]\n", str);

	if (write(fd, str, len) < len)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error writing to GSM modem [%s]", strerror(errno));
		zabbix_syslog("Error writing to GSM modem [%s]", strerror(errno));
		snprintf(error,max_error_len-1, "Error writing to GSM modem [%s]", strerror(errno));
		return FAIL;
	}

	return ret;
}

static int read_gsm(int fd, const char *expect, char *error, int max_error_len)
{
	static char	buffer[0xFF];
	static char	*ebuf = buffer;
	static char	*sbuf = buffer;

	int	i, nbytes;
	int	ret = SUCCEED;

	/* read characters into our string buffer until we get a CR or NL */
	while ((nbytes = read(fd, ebuf, buffer + sizeof(buffer) - 1 - ebuf)) > 0)
	{
		ebuf += nbytes;
		if (ebuf[-1] == '\n' || ebuf[-1] == '\r')
			break;
	}
	/* nul terminate the string and see if we got an OK response */
	*ebuf = '\0';

	for( ; sbuf < ebuf && (*sbuf == '\n' || *sbuf == '\r'); sbuf++); /* left trim of '\r' & '\n' */
	for(i = 0 ; i < (ebuf - sbuf) && (sbuf[i] != '\n' && sbuf[i] != '\r'); i++); /* find first '\r' & '\n' */

	if(i < ebuf - sbuf)	sbuf[i++] = '\0';

	/* start WORNING info */
	zabbix_log(LOG_LEVEL_DEBUG, "Read buffer [%s]", sbuf);
	/* for(i=0;i<strlen(buffer);i++)
		zabbix_log(LOG_LEVEL_DEBUG, "[%x]", buffer[i]); */
	/* end WORNING info */

	if (strstr(sbuf, expect) == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Read something unexpected from GSM modem. Expected [%s]", expect);
		zabbix_syslog("Read something unexpected from GSM modem");
		snprintf(error,max_error_len-1, "Read something unexpected from GSM modem");
		ret = FAIL;
	}
	
	sbuf += i;

	if(sbuf != buffer)
	{
		memmove(buffer, sbuf, ebuf - sbuf + 1); /* +1 for '\0' */
		sbuf = buffer;
	}

	return ret;
}

typedef struct {
	char 		*message;
	const char 	*result;
} zbx_sms_scenario;

int	send_sms(char *device,char *number,char *message, char *error, int max_error_len)
{
	zbx_sms_scenario scenario[] = {
/*  0  */	{"ATE0\r"		, "OK"		},	/* Turn off echo */
/*  1  */	{"AT\r"			, "OK"		},	/* Init modem */
/*  2  */	{"AT+CMGF=1\r"		, "OK"		},	/* Switch to text mode */
/*  3  */	{"AT+CMGS=\""		, NULL		},	/* Set phone number */
/*  4  */	{number			, NULL		},	/* Write phone number */
/*  5  */	{"\"\r"			, ">"		},	/* Set phone number */
/*  6  */	{message		, NULL		},	/* Write message */
/*  7  */	{"\x01a"		, "+CMGS: "	},	/* Send message */
/*  8  */	{NULL			, "OK"		},
/* EOS */	{NULL			, NULL		}
		};

	zbx_sms_scenario *step = NULL;

	struct termios
		options,
		old_options;

	int	i,
		f,
		ret = SUCCEED;


	f=open(device,O_RDWR | O_NOCTTY | O_NDELAY);
	if(f == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error open(%s) [%s]", device, strerror(errno));
		zabbix_syslog("Error open(%s) [%s]", device, strerror(errno));
		snprintf(error,max_error_len-1, "Error open(%s) [%s]", device, strerror(errno));
		return FAIL;
	}
	fcntl(f, F_SETFL,0);
	tcgetattr(f, &old_options);

	memset(&options, 0, sizeof(struct termios));

	options.c_iflag     = IGNCR | INLCR | ICRNL;

#ifdef ONOCR
	options.c_oflag     = ONOCR;
#endif /* ONOCR */
	
	options.c_cflag     = B38400 | CRTSCTS | CS8 | CLOCAL | CREAD;
	options.c_lflag     &= ~(ICANON | ECHO | ECHOE | ISIG);
	options.c_cc[VMIN]  = 0;
	options.c_cc[VTIME] = 100;

	tcsetattr(f, TCSANOW, &options);

	for(step = scenario; step->message || step->result; step++)
	{
		if(step->message)
		{
			if(FAIL == write_gsm(f, step->message, error, max_error_len)) break;
		}
		if(step->result)
		{
			if(FAIL == read_gsm(f, step->result, error, max_error_len)) break;
		}
	}

	tcsetattr(f, TCSANOW, &old_options);
	close(f);

	return ret;
}
