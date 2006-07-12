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

#define JAN_1970   2208988800.0        /* 1970 - 1900 in seconds */
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

typedef struct NTP_DATA {
    unsigned char status, version, mode, stratum, polling, precision;
    double dispersion, reference, originate, receive, transmit, current;
} ntp_data;

double current_time (double offset)
{

#if !defined(WIN32) || (defined(WIN32) && defined(TODO))

/* Get the current UTC time in seconds since the Epoch plus an offset (usually
the time from the beginning of the century to the Epoch!) */

    struct timeval current;

    errno = 0;
#ifdef WIN32
#	error "Replace <gettimeofday> function"
#endif /* WIN32 */
    
    if (gettimeofday(&current,NULL))
    {
	    /* No processing of error condition here */
    }
    return offset+current.tv_sec+1.0e-6*current.tv_usec;\

#else

	    return 0.;

#endif /* TODO */
}

void make_packet (ntp_data *data)
{
	data->status = NTP_LI_FUDGE<<6;
	data->stratum = NTP_STRATUM;
	data->reference = data->dispersion = 0.0;
    
	data->version = NTP_VERSION;
	data->mode = 1;
	data->polling = NTP_POLLING;
	data->precision = NTP_PRECISION;
	data->receive = data->originate = 0.0;
	data->current = data->transmit = current_time(JAN_1970);
}

void pack_ntp (unsigned char *packet, int length, ntp_data *data) {

/* Pack the essential data into an NTP packet, bypassing struct layout and
endian problems.  Note that it ignores fields irrelevant to SNTP. */

    int i, k;
    double d;

    memset(packet,0,(size_t)length);
    packet[0] = (data->status<<6)|(data->version<<3)|data->mode;
    packet[1] = data->stratum;
    packet[2] = data->polling;
    packet[3] = data->precision;
    d = data->originate/NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_ORIGINATE+i] = k;
        d -= k;
    }
    d = data->receive/NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_RECEIVE+i] = k;
        d -= k;
    }
    d = data->transmit/NTP_SCALE;
    for (i = 0; i < 8; ++i) {
        if ((k = (int)(d *= 256.0)) >= 256) k = 255;
        packet[NTP_TRANSMIT+i] = k;
        d -= k;
    }
}

void unpack_ntp (ntp_data *data, unsigned char *packet, int length) {

/* Unpack the essential data from an NTP packet, bypassing struct layout and
endian problems.  Note that it ignores fields irrelevant to SNTP. */

    int i;
    double d;

    data->current = current_time(JAN_1970);    /* Best to come first */
    data->status = (packet[0] >> 6);
    data->version = (packet[0] >> 3)&0x07;
    data->mode = packet[0]&0x07;
    data->stratum = packet[1];
    data->polling = packet[2];
    data->precision = packet[3];
    d = 0.0;
    for (i = 0; i < 4; ++i) d = 256.0*d+packet[NTP_DISP_FIELD+i];
    data->dispersion = d/65536.0;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0*d+packet[NTP_REFERENCE+i];
    data->reference = d/NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0*d+packet[NTP_ORIGINATE+i];
    data->originate = d/NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0*d+packet[NTP_RECEIVE+i];
    data->receive = d/NTP_SCALE;
    d = 0.0;
    for (i = 0; i < 8; ++i) d = 256.0*d+packet[NTP_TRANSMIT+i];
    data->transmit = d/NTP_SCALE;
}

/*
void display_data (ntp_data *data) {

    printf("sta=%d ver=%d mod=%d str=%d pol=%d dis=%.6f ref=%.6f\n",
        data->status,data->version,data->mode,data->stratum,data->polling,
        data->dispersion,data->reference);
    printf("ori=%.6f rec=%.6f\n",data->originate,data->receive);
    printf("tra=%.6f cur=%.6f\n",data->transmit,data->current);
}
*/


time_t convert_time (double value, int *millisecs) {

/* Convert the time to the ANSI C form. */

    time_t result = (time_t)value;

    if ((*millisecs = (int)(1000.0*(value-result))) >= 1000) {
        *millisecs = 0;
        ++result;
    }
    return result;
}

int format_time (char *text, int length, double offset, double error,
    double drift, double drifterr) {

/* Format the current time into a string, with the extra information as
requested.  Note that the rest of the program uses the correction needed, which
is what is printed for diagnostics, but this formats the error in the local
system for display to users.  So the results from this are the negation of
those printed by the verbose options. */

    int milli, len;
    time_t now;
    struct tm *gmt;
    static const char *months[] = {
        "Jan", "Feb", "Mar", "Apr", "May", "Jun",
        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    };


/* Work out and format the current local time.  Note that some semi-ANSI
systems do not set the return value from (s)printf. */

    now = convert_time(current_time(offset),&milli);
    errno = 0;
    if ((gmt = localtime(&now)) == NULL)
    {
        printf("unable to work out local time");
	return -1;
    }
    len = 24;
    if (length <= len)
    {
	    printf("internal error calling format_time");
	    return -1;
    }

    errno = 0;
    printf("%.4d %s %.2d %.2d:%.2d:%.2d.%.3d\n",
            gmt->tm_year+1900,months[gmt->tm_mon],gmt->tm_mday,
            gmt->tm_hour,gmt->tm_min,gmt->tm_sec,milli);

    return now;
}

int	check_ntp(char *host, int port, int *value_int)
{

#if !defined(WIN32) || (defined(WIN32) && defined(TODO))

	ZBX_SOCKET	s;
	ZBX_SOCKADDR	servaddr_in;

	int	len;
	unsigned char	c[MAX_STRING_LEN];

	struct hostent *hp;

	char	text[50];

	ntp_data data;
	unsigned char	packet[NTP_PACKET_MIN];

    	*value_int = 0;

	make_packet(&data);

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(host);

	if(hp==NULL)
	{
/*		zbx_error("gethostbyname(%s) failed [%s]", host, hstrerror(h_errno));*/
		return	SYSINFO_RET_OK;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_DGRAM,0);

	if(s == -1)
	{
/*		zbx_error("Cannot create socket [%s]", strerror(errno));*/
		return	SYSINFO_RET_OK;
	}
 
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		/* useless code
		switch (errno)
		{
			case EINTR:
				break;
			case EHOSTUNREACH:
				break;
			default:
				break;
		}
		*/
/*		zbx_error("Cannot connect [%s]", strerror(errno));*/
		goto lbl_error;
	}

	pack_ntp(packet,NTP_PACKET_MIN,&data);

#ifdef WIN32
#	error "TIDO replace <write> function"
#endif /* WIN32 */
	if( write(s,&packet,NTP_PACKET_MIN) == -1 )
	{
		/* useless code
		switch (errno)
		{
			case EINTR:
				break;
			default:
				break;
		} 
		*/
/*		zbx_error("Cannot write [%s]", strerror(errno));*/
		goto lbl_error;
	} 

	memset(c,0,MAX_STRING_LEN);

#ifdef WIN32
#	error "TIDO replace <read> function"
#endif /* WIN32 */
	
	len = read(s,c,MAX_STRING_LEN);

	if(len == -1)
	{
		/* useless code
		switch (errno)
		{
			case 	EINTR:
					break;
			case	ECONNRESET:
					break;
			default:
					break;
		} 
		*/
/*		zbx_error("Cannot read0 [%d]", errno);*/
		goto lbl_error;
	}
	zbx_sock_close(s);

	unpack_ntp(&data,c,len);

/*	display_data(&data); */

        zbx_snprintf(text, sizeof(text), "%d",0);

/*        format_time(text,75,offset,error,0.0,-1.0);*/

/*    if (dispersion < data->dispersion) dispersion = data->dispersion;
        x = data->receive-data->originate;
        y = (data->transmit == 0.0 ? 0.0 : data->transmit-data->current);
        *off = 0.5*(x+y);
        *err = x-y;
        x = data->current-data->originate;
        if (0.5*x > *err) *err = 0.5*x; */

        *value_int = format_time(text,75,0,0,0.0,-1.0);

	return SYSINFO_RET_OK;

lbl_error:
	zbx_sock_close(s);

#endif /* TODO */
	return	SYSINFO_RET_OK;
}

