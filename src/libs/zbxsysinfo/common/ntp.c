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
#include "sysinfo.h"
#include "zbxsock.h"
#include "log.h"
#include "cfg.h"

#define NTP_SCALE  4294967296.0        /* 2^32, of course! */

#define NTP_PACKET_MIN       48        /* Without authentication */
#define NTP_PACKET_MAX       68        /* With authentication (ignored) */
#define NTP_DISP_FIELD        8        /* Offset of dispersion field */
#define NTP_REFERENCE        16        /* Offset of reference timestamp */
#define NTP_ORIGINATE        24        /* Offset of originate timestamp */
#define NTP_RECEIVE          32        /* Offset of receive timestamp */
#define NTP_TRANSMIT         40        /* Offset of transmit timestamp */

#define NTP_LI_FUDGE          0        /* The current 'status' */
#define NTP_VERSION           3        /* The current version */
#define NTP_VERSION_MAX       4        /* The maximum valid version */
#define NTP_STRATUM          15        /* The current stratum as a server */
#define NTP_STRATUM_MAX      15        /* The maximum valid stratum */
#define NTP_POLLING           8        /* The current 'polling interval' */
#define NTP_PRECISION         0        /* The current 'precision' - 1 sec. */

#define NTP_ACTIVE            1        /* NTP symmetric active request */
#define NTP_PASSIVE           2        /* NTP symmetric passive response */
#define NTP_CLIENT            3        /* NTP client request */
#define NTP_SERVER            4        /* NTP server response */
#define NTP_BROADCAST         5        /* NTP server broadcast */

#define NTP_INSANITY     3600.0        /* Errors beyond this are hopeless */
#define RESET_MIN            15        /* Minimum period between resets */
#define ABSCISSA            3.0        /* Scale factor for standard errors */

typedef struct ntp_data_s {

    unsigned char 
	    status, 
	    version, 
	    mode, 
	    stratum, 
	    polling, 
	    precision;
    double 
	    dispersion, 
	    reference, 
	    originate, 
	    receive, 
	    transmit, 
	    current;

} ntp_data;

static void make_packet (ntp_data *data)
{
	data->status	= NTP_LI_FUDGE<<6;
	data->stratum	= NTP_STRATUM;
	data->reference = data->dispersion = 0.0;
    
	data->version	= NTP_VERSION;
	data->mode	= 1;
	data->polling	= NTP_POLLING;
	data->precision	= NTP_PRECISION;
	data->receive	= data->originate = 0.0;
	data->current	= data->transmit = zbx_current_time();
}

static void pack_ntp (unsigned char *packet, int length, ntp_data *data)
{

/* Pack the essential data into an NTP packet, bypassing struct layout and
endian problems.  Note that it ignores fields irrelevant to SNTP. */

    int i, k;
    double d;

    assert(length >= (NTP_TRANSMIT + 8));

    memset(packet,0,(size_t)length);

    packet[0] = (data->status << 6) | (data->version << 3) | data->mode;
    packet[1] = data->stratum;
    packet[2] = data->polling;
    packet[3] = data->precision;

    d = data->originate / NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_ORIGINATE + i] = k;
        d -= k;
    }

    d = data->receive / NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_RECEIVE + i] = k;
        d -= k;
    }

    d = data->transmit / NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_TRANSMIT + i] = k;
        d -= k;
    }
}

static void unpack_ntp (ntp_data *data, unsigned char *packet, int length) {

/* Unpack the essential data from an NTP packet, bypassing struct layout and
endian problems.  Note that it ignores fields irrelevant to SNTP. */

    int i;
    double d;

    memset(data, 0, sizeof(ntp_data));

    if(length == 0)
	    return;

    assert(length >= (NTP_TRANSMIT + 8));

    data->current	= zbx_current_time();    /* Best to come first */
    data->status	= (packet[0] >> 6);
    data->version	= (packet[0] >> 3) & 0x07;
    data->mode		= packet[0] & 0x07;
    data->stratum	= packet[1];
    data->polling	= packet[2];
    data->precision	= packet[3];

    d = 0.0;
    for (i = 0; i < 4; ++i) d = 256.0 * d + packet[NTP_DISP_FIELD + i];
    data->dispersion = d / 65536.0;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0 * d + packet[NTP_REFERENCE + i];
    data->reference = d / NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0 * d + packet[NTP_ORIGINATE + i];
    data->originate = d / NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0 * d + packet[NTP_RECEIVE + i];
    data->receive = d / NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0 * d + packet[NTP_TRANSMIT + i];
    data->transmit = d / NTP_SCALE;
}

/*
static void display_data (ntp_data *data) {

    printf("sta = %d ver = %d mod = %d str = %d pol = %d dis = %.6f ref = %.6f\n",
        data->status,data->version,data->mode,data->stratum,data->polling,
        data->dispersion,data->reference);
    printf("ori = %.6f rec = %.6f\n",data->originate, data->receive);
    printf("tra = %.6f cur = %.6f\n",data->transmit, data->current);
}
*/

#if OFF

static time_t convert_time (double value, int *millisecs) {

/* Convert the time to the ANSI C form. */

    time_t result = (time_t)value;

    if ((*millisecs = (int)(1000.0*(value-result))) >= 1000) {
        *millisecs = 0;
        ++result;
    }
    return result;
}

/* !!! damaged function !!! for using correct tham !!! */
static int format_time (
	char *text, 
	int length, 
	double offset, 
	double error,	/* not USED */
	double drift,	/* not USED */
	double drifterr /* not USED */
	) {

/* Format the current time into a string, with the extra information as
requested.  Note that the rest of the program uses the correction needed, which
is what is printed for diagnostics, but this formats the error in the local
system for display to users.  So the results from this are the negation of
those printed by the verbose options. */

    int 
	    milli,
	    len;
    time_t
	    now;
    struct tm	
	    *gmt;
    static const char 
	    *months[] = {
        "Jan", "Feb", "Mar", "Apr", "May", "Jun",
        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    };


/* Work out and format the current local time.  Note that some semi-ANSI
systems do not set the return value from (s)printf. */

    now = convert_time(zbx_time() + offset,&milli);
    errno = 0;

    if ((gmt = localtime(&now)) == NULL)
    {
        zbx_error("unable to work out local time");
	return -1;
    }
    len = 24;
    if (length <= len)
    {
	    zbx_error("internal error calling format_time");
	    return -1;
    }

    errno = 0;
    printf("%.4d %s %.2d %.2d:%.2d:%.2d.%.3d\n",
            gmt->tm_year+1900,months[gmt->tm_mon],gmt->tm_mday,
            gmt->tm_hour,gmt->tm_min,gmt->tm_sec,milli);

    return now;
}

#endif /* OFF */

int	check_ntp(char *host, unsigned short port, int *value_int)
{

	ZBX_SOCKET	s;
	ZBX_SOCKADDR	servaddr_in;

	int		len;
	unsigned char	buf[MAX_STRING_LEN];

	struct hostent *hp;

	ntp_data	data;
	unsigned char	packet[NTP_PACKET_MIN];

    	*value_int = 0;

	if(NULL == (hp = gethostbyname(host)) )
	{
#ifdef	HAVE_HSTRERROR		
		zabbix_log( LOG_LEVEL_DEBUG, "gethostbyname() failed for NTP server [%d]", (char*)hstrerror((int)h_errno));
#else
		zabbix_log( LOG_LEVEL_DEBUG, "gethostbyname() failed for NTP server [%s]", strerror_from_system(h_errno));
#endif
		return	SYSINFO_RET_OK;
	}

	servaddr_in.sin_family		= AF_INET;
	servaddr_in.sin_addr.s_addr	= ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port		= htons(port);

	if( SOCKET_ERROR == (s = (ZBX_SOCKET)socket(AF_INET,SOCK_DGRAM,0)) )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot create socket for NTP server. [%s]", strerror_from_system(errno));
		return	SYSINFO_RET_OK;
	}
 
	if(SOCKET_ERROR == connect(s, (struct sockaddr *)&servaddr_in, sizeof(ZBX_SOCKADDR)) )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log(LOG_LEVEL_DEBUG, "Timeout while connecting to NTP server.");
				break;
			case EHOSTUNREACH:
				zabbix_log(LOG_LEVEL_DEBUG, "No route to NTP server.");
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "Cannot connect to NTP server. [%s]", strerror(errno));
				break;
		} 
		goto lbl_error;
	}

	make_packet(&data);

/*	display_data(&data); */

	pack_ntp(packet, sizeof(packet), &data);

	if(SOCKET_ERROR == zbx_sock_write(s, packet, sizeof(packet)))
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log(LOG_LEVEL_DEBUG, "Timeout while sending data to NTP server.");
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "Error while sending data to NTP server. [%s]", strerror(errno));
				break;
		} 
		goto lbl_error;
	} 

	memset(buf, 0, sizeof(buf));

	if( SOCKET_ERROR == (len = zbx_sock_read(s, buf, sizeof(buf), CONFIG_TIMEOUT)))
	{
		switch (errno)
		{
			case 	EINTR:
				zabbix_log( LOG_LEVEL_DEBUG,"Timeout while receiving data from NTP server");
				break;
			case	ECONNRESET:
				zabbix_log( LOG_LEVEL_DEBUG,"Connection to NTP server reseted by peer.");
				break;
			default:
				zabbix_log( LOG_LEVEL_DEBUG,"Error while receiving data from NTP server [%s]", strerror(errno));
				break;
		} 
		goto lbl_error;
	}

	unpack_ntp(&data, buf, len);

	zbx_sock_close(s);

/*	display_data(&data); */

/*        format_time(text,sizeof(text),offset,error,0.0,-1.0);*/

/*    if (dispersion < data->dispersion) dispersion = data->dispersion;
        x = data->receive-data->originate;
        y = (data->transmit == 0.0 ? 0.0 : data->transmit-data->current);
        *off = 0.5*(x+y);
        *err = x-y;
        x = data->current-data->originate;
        if (0.5*x > *err) *err = 0.5*x; */

/*        *value_int = format_time(text,sizeof(text),0,0,0.0,-1.0); */

#if OFF
	*value_int = time(NULL);						/* local time */
#else
	*value_int = (data.receive > 0) ? (int)(data.receive - ZBX_JAN_1970_IN_SEC) : 0;	/* server time */
#endif

	return SYSINFO_RET_OK;

lbl_error:
	zbx_sock_close(s);

	return	SYSINFO_RET_OK;
}

