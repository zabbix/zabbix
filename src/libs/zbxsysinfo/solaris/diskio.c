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
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>

/* Definitions of uint32_t under OS/X */
#ifdef HAVE_STDINT_H
	#include <stdint.h>
#endif
#ifdef HAVE_STRINGS_H
	#include <strings.h>
#endif
#ifdef HAVE_FCNTL_H
	#include <fcntl.h>
#endif
#ifdef HAVE_DIRENT_H
	#include <dirent.h>
#endif
/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
#ifdef HAVE_SYS_SYSINFO_H
	#include <sys/sysinfo.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_STATVFS_H
	#include <sys/statvfs.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_PROCFS_H
/* This is needed to access the correct procfs.h definitions */
	#define _STRUCTURED_PROC 1
	#include <sys/procfs.h>
#endif
#ifdef HAVE_SYS_LOADAVG_H
	#include <sys/loadavg.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif
#ifdef HAVE_NETINET_IN_H
	#include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_INET_H
	#include <arpa/inet.h>
#endif
/* OpenBSD/Solaris */
#ifdef HAVE_SYS_PARAM_H
	#include <sys/param.h>
#endif

#ifdef HAVE_SYS_MOUNT_H
	#include <sys/mount.h>
#endif

/* HP-UX */
#ifdef HAVE_SYS_PSTAT_H
	#include <sys/pstat.h>
#endif

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SWAP_H
	#include <sys/swap.h>
#endif

/* FreeBSD */
#ifdef HAVE_SYS_SYSCTL_H
	#include <sys/sysctl.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SYSCALL_H
	#include <sys/syscall.h>
#endif

/* FreeBSD */
#ifdef HAVE_VM_VM_PARAM_H
	#include <vm/vm_param.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_VMMETER_H
	#include <sys/vmmeter.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_TIME_H
	#include <sys/time.h>
#endif

#ifdef HAVE_MACH_HOST_INFO_H
	#include <mach/host_info.h>
#endif
#ifdef HAVE_MACH_MACH_HOST_H
	#include <mach/mach_host.h>
#endif


#ifdef HAVE_KSTAT_H
	#include <kstat.h>
#endif

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

#if 0

/* !!!    don't work on solaris     !!! 
   !!! missing 'hda' field in kstat !!! */

typedef struct busy_data
{
  hrtime_t clock;
  hrtime_t rtime;
} BUSY_DATA;

typedef struct svc_time_data
{
  hrtime_t rtime;
  uint_t reads;
  uint_t writes;
} SVC_TIME_DATA;

typedef struct read_ios_data
{
  hrtime_t clock;
  uint_t reads;
} READ_IOS_DATA;

typedef struct write_ios_data
{
  hrtime_t clock;
  uint_t writes;
} WRITE_IOS_DATA;

typedef struct rblocks_data
{
  hrtime_t clock;
  u_longlong_t nread;
} RBLOCKS_DATA;

typedef struct wblocks_data
{
  hrtime_t clock;
  u_longlong_t nwritten;
} WBLOCKS_DATA;

typedef struct disk_data
{
  struct disk_data *next;
  char *name;
  BUSY_DATA busy;
  SVC_TIME_DATA svc;
  READ_IOS_DATA reads;
  WRITE_IOS_DATA writes;
  RBLOCKS_DATA rblocks;
  WBLOCKS_DATA wblocks;
} DISK_DATA;

static DISK_DATA *disks;

static DISK_DATA *get_disk_data_record(const char *device)
{
  DISK_DATA *p;

  p = disks;

  while ((p) && (strcmp(p->name, device) != 0))
    p = p->next;  

  if (p == (DISK_DATA *) NULL)
    {
      p = (DISK_DATA *) calloc(1, sizeof(DISK_DATA));

      if (p)
	{
	  p->name = strdup(device);

          if (p->name)
            {
	      p->next = disks;
              
              disks = p;
            }

          else
	    {
	      free(p);

              p = NULL;
            }
        }
    }

  return p;
}

static int get_disk_kstat_record(const char *name,
                                 hrtime_t *crtime,
                                 hrtime_t *snaptime, 
                                 kstat_io_t *returned_data)
{
  int result = SYSINFO_RET_FAIL;
  kstat_ctl_t *kc;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *kt;

      kt = kstat_lookup(kc, NULL, -1, (char *) name);

      if (kt)
	{
	  if (    (kt->ks_type == KSTAT_TYPE_IO)
               && (kstat_read(kc, kt, returned_data) != -1)
	     )
            {
               *crtime = kt->ks_crtime;
	       *snaptime = kt->ks_snaptime;
	       result = SYSINFO_RET_OK;
            }
        }

      kstat_close(kc);
    }

  return result;
}

int	DISKREADOPS(const char *cmd, const char *device,double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
             int interval_seconds;

             interval_seconds = (snaptime - p->reads.clock) / 1000000000;

             if (interval_seconds > 0)
               *value = (kio.reads - p->reads.reads) / interval_seconds;

   	     else 
                *value = 0.0;

            p->reads.clock = snaptime;

            p->reads.reads = kio.reads;
          }
    }

  return result;
}

static int DISKREADBLOCKS(const char *cmd, const char *device,double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
	     int interval_seconds;

             interval_seconds = (snaptime - p->rblocks.clock) / 1000000000;

             if (interval_seconds > 0)
                *value = ((kio.nread - p->rblocks.nread) / 1024.0) / interval_seconds;

   	     else 
                *value = 0.0; 

            p->rblocks.clock = snaptime;

            p->rblocks.nread = kio.nread;
          }
    }

  return result;
}

static int DISKWRITEOPS(const char *cmd, const char *device,double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
	     int interval_seconds;

             interval_seconds = (snaptime - p->writes.clock) / 1000000000;

	     if (interval_seconds > 0)
                *value = (kio.writes - p->writes.writes) / interval_seconds;

  	     else 
                *value = 0.0; 

            p->writes.clock = snaptime;

            p->writes.writes = kio.writes;
          }
    }

  return result;
}

static int DISKWRITEBLOCKS(const char *cmd, const char *device,double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
	     int interval_seconds;

             interval_seconds = (snaptime - p->wblocks.clock) / 1000000000;

             if (interval_seconds > 0)
                *value = ((kio.nwritten - p->wblocks.nwritten) / 1024.0) / interval_seconds;

	     else 
                *value = 0.0; 

            p->wblocks.clock = snaptime;

            p->wblocks.nwritten = kio.nwritten;
          }
    }

  return result;
}

static int DISKBUSY(const char *cmd, const char *device, double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
             if (snaptime > p->busy.clock)
                *value = ((kio.rtime - p->busy.rtime) * 100.0) / (snaptime - p->busy.clock);

   	     else 
               *value = 0.0;

             p->busy.clock = snaptime;

             p->busy.rtime = kio.rtime;
          }
    }

   return result;
}

static int DISKSVC(const char *cmd, const char *device, double  *value)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
             unsigned long ios;

             ios = (kio.reads - p->svc.reads) + (kio.writes - p->svc.writes);

             if (ios > 0)
	        *value = ((kio.rtime - p->svc.rtime)/ios)/1000000.0;

             else
   		*value = 0.0;

             p->svc.writes = kio.writes;
             p->svc.reads = kio.reads;
             p->svc.rtime = kio.rtime;
          }
    }

   return result;
}

#endif

int	DISKREADOPS1(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_ops1[%s]",device);

	return	get_stat(key,value);
}

int	DISKREADOPS5(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_ops5[%s]",device);

	return	get_stat(key,value);
}

int	DISKREADOPS15(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_ops15[%s]",device);

	return	get_stat(key,value);
}

int	DISKREADBLKS1(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_blks1[%s]",device);

	return	get_stat(key,value);
}

int	DISKREADBLKS5(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_blks5[%s]",device);

	return	get_stat(key,value);
}

int	DISKREADBLKS15(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_read_blks15[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEOPS1(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_ops1[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEOPS5(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_ops5[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEOPS15(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_ops15[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEBLKS1(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_blks1[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEBLKS5(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_blks5[%s]",device);

	return	get_stat(key,value);
}

int	DISKWRITEBLKS15(const char *cmd, const char *device,double  *value)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"disk_write_blks15[%s]",device);

	return	get_stat(key,value);
}

int	DISK_IO(const char *cmd, const char *parameter,double  *value)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",2,2,value);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	DISK_RIO(const char *cmd, const char *parameter,double  *value)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",3,2,value);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	DISK_WIO(const char *cmd, const char *parameter,double  *value)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",4,2,value);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	DISK_RBLK(const char *cmd, const char *parameter,double  *value)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",5,2,value);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	DISK_WBLK(const char *cmd, const char *parameter,double  *value)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",6,2,value);
#else
	return	SYSINFO_RET_FAIL;
#endif
}
