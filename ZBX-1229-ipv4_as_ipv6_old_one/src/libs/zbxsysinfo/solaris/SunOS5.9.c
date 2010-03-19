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
#include "log.h"

#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <kstat.h>
#include <memory.h>
#include <procfs.h>
#include <string.h>
#include <strings.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/loadavg.h>
#include <sys/proc.h>
#include <sys/stat.h>
#include <sys/statvfs.h>
#include <sys/sysinfo.h>
#include <sys/time.h>
#include <sys/types.h>
#include <sys/var.h>

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

/*
 * ADDED to /solaric/diskio.c
 *
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
*/

/*
 * ADDED to /solaric/net.c
 *
typedef union value_overlay
{
  union
  {
    zbx_uint64_t ui64;
    uint32_t ui32;
  } u;
} VALUE_OVERLAY;

typedef struct rbytes_data
{
  hrtime_t clock;
  VALUE_OVERLAY rbytes;
} RBYTES_DATA;

typedef struct obytes_data
{
  hrtime_t clock;
  VALUE_OVERLAY obytes;
} OBYTES_DATA;

typedef struct rpackets_data
{
  hrtime_t clock;
  VALUE_OVERLAY rpackets;
} RPACKETS_DATA;

typedef struct opackets_data
{
  hrtime_t clock;
  VALUE_OVERLAY opackets;
} OPACKETS_DATA;

typedef struct network_data
{
  struct network_data *next;
  char *name;
  RBYTES_DATA rb;
  OBYTES_DATA ob;
  RPACKETS_DATA rp;
  OPACKETS_DATA op;
} NETWORK_DATA;

static NETWORK_DATA *interfaces;
*/

/*
 * ADDED to /solaric/diskio.c
 *
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
*/

/*
 * ADDED to /solaric/net.c
 *
static NETWORK_DATA *get_net_data_record(const char *device)
{
  NETWORK_DATA *p;

  p = interfaces;

  while ((p) && (strcmp(p->name, device) != 0))
    p = p->next;

  if (p == (NETWORK_DATA *) NULL)
    {
      p = (NETWORK_DATA *) calloc(1, sizeof(NETWORK_DATA));

      if (p)
	{
	  p->name = strdup(device);

          if (p->name)
            {
	      p->next = interfaces;

              interfaces = p;
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
*/

/*
 * SKIPPED
 *
static int  PROCCNT(const char *cmd, const char *procname,double  *value, const char *msg, int mlen_max)
{
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[MAX_STRING_LEN];

	int	fd;
// In the correct procfs.h, the structure name is psinfo_t
	psinfo_t psinfo;

	int	proccount=0;

	dir=opendir("/proc");
	if(NULL == dir)
	{
		return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");
		strncat(filename,entries->d_name,MAX_STRING_LEN);
		strncat(filename,"/psinfo",MAX_STRING_LEN);

		if(stat(filename,&buf)==0)
		{
			fd = open (filename, O_RDONLY);
			if (fd != -1)
			{
				if (read (fd, &psinfo, sizeof(psinfo)) == -1)
				{
					closedir(dir);
					return SYSINFO_RET_FAIL;
				}
				else
				{
					if(strcmp(procname,psinfo.pr_fname)==0)
					{
						proccount++;
					}
				}
				close (fd);
			}
			else
			{
				continue;
			}
		}
	}
	closedir(dir);
	*value=(double)proccount;
	return	SYSINFO_RET_OK;
}
*/

/*
 * ADDED to /solaric/diskio.c
 *
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

static int DISKREADOPS(const char *cmd, const char *device,double  *value, const char *msg, int mlen_max)
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

static int DISKREADBLOCKS(const char *cmd, const char *device,double  *value, const char *msg, int mlen_max)
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

static int DISKWRITEOPS(const char *cmd, const char *device,double  *value, const char *msg, int mlen_max)
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

static int DISKWRITEBLOCKS(const char *cmd, const char *device,double  *value, const char *msg, int mlen_max)
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

static int DISKBUSY(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
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

static int DISKSVC(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
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
*/

/*
 * ADDED to /solaric/cpu.c
 *
static int get_cpu_data(zbx_uint64_t *idle,
                        zbx_uint64_t *system,
                        zbx_uint64_t *user,
                        zbx_uint64_t *iowait)
{
  kstat_ctl_t *kc;
  int cpu_count = 0;

  *idle =
     *system =
     *user =
     *iowait = 0LL;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *k = kc->kc_chain;

      while (k)
	{
	  if (     (strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	     )
	    {
   	       cpu_stat_t *cpu = (cpu_stat_t *) k->ks_data;

               *idle +=  cpu->cpu_sysinfo.cpu[CPU_IDLE];
               *system +=  cpu->cpu_sysinfo.cpu[CPU_KERNEL];
               *iowait +=  cpu->cpu_sysinfo.cpu[CPU_WAIT];
               *user +=  cpu->cpu_sysinfo.cpu[CPU_USER];

               cpu_count += 1;
  	    }

	  k = k->ks_next;
        }

      kstat_close(kc);
    }

  return cpu_count;
}

static int CPUIDLE(const char *cmd, const char *param, double  *value, const char *msg, int mlen_max)
{
   static zbx_uint64_t idle[2];
   static zbx_uint64_t system[2];
   static zbx_uint64_t user[2];
   static zbx_uint64_t iowait[2];

   int result = SYSINFO_RET_FAIL;

   if (get_cpu_data(&idle[1], &system[1], &user[1], &iowait[1]))
      {
         zbx_uint64_t interval_size;

         interval_size =    (idle[1] - idle[0])
	                  + (system[1] - system[0])
	                  + (user[1] - user[0])
	                  + (iowait[1] - iowait[0]);

         if (interval_size > 0)
            {
	       *value = ((idle[1] - idle[0]) * 100.0)/interval_size;

               idle[0] = idle[1];
               system[0] = system[1];
               user[0] = user[1];
               iowait[0] = iowait[1];

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}

static int CPUUSER(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
   static unsigned long long idle[2];
   static unsigned long long system[2];
   static unsigned long long user[2];
   static unsigned long long iowait[2];

   int result = SYSINFO_RET_FAIL;

   if (get_cpu_data(&idle[1], &system[1], &user[1], &iowait[1]))
      {
         unsigned interval_size;

         interval_size =    (idle[1] - idle[0])
                          + (system[1] - system[0])
	                  + (user[1] - user[0])
	                  + (iowait[1] - iowait[0]);

         if (interval_size > 0)
            {
	       *value = ((user[1] - user[0]) * 100.0)/interval_size;

               idle[0] = idle[1];
               system[0] = system[1];
               user[0] = user[1];
               iowait[0] = iowait[1];

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}

static int CPUSYSTEM(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
   static unsigned long long idle[2];
   static unsigned long long system[2];
   static unsigned long long user[2];
   static unsigned long long iowait[2];

   int result = SYSINFO_RET_FAIL;

   if (get_cpu_data(&idle[1], &system[1], &user[1], &iowait[1]))
      {
         unsigned interval_size;

         interval_size =    (idle[1] - idle[0])
                          + (system[1] - system[0])
	                  + (user[1] - user[0])
	                  + (iowait[1] - iowait[0]);

         if (interval_size > 0)
            {
	       *value = ((system[1] - system[0]) * 100.0)/interval_size;

               idle[0] = idle[1];
               system[0] = system[1];
               user[0] = user[1];
               iowait[0] = iowait[1];

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}

static int CPUIOWAIT(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
   static unsigned long long idle[2];
   static unsigned long long system[2];
   static unsigned long long user[2];
   static unsigned long long iowait[2];

   int result = SYSINFO_RET_FAIL;

   if (get_cpu_data(&idle[1], &system[1], &user[1], &iowait[1]))
      {
         unsigned interval_size;

         interval_size =    (idle[1] - idle[0])
                          + (system[1] - system[0])
	                  + (user[1] - user[0])
	                  + (iowait[1] - iowait[0]);

         if (interval_size > 0)
            {
	       *value = ((iowait[1] - iowait[0]) * 100.0)/interval_size;

               idle[0] = idle[1];
               system[0] = system[1];
               user[0] = user[1];
               iowait[0] = iowait[1];

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}
*/

/*
 * ADDED to /solaric/net.c
 *
static int get_named_field(const char *name,
                           const char *field,
                           kstat_named_t *returned_data,
                           hrtime_t *snaptime)
   {
      int result = SYSINFO_RET_FAIL;
      kstat_ctl_t   *kc;

      kc = kstat_open();

        if (kc)
	  {
	     kstat_t       *kp;

	     kp = kstat_lookup(kc, NULL, -1, (char *) name);

             if ((kp) && (kstat_read(kc, kp, 0) != -1))
	       {
	          kstat_named_t *kn;

	          kn = (kstat_named_t*) kstat_data_lookup(kp, (char *) field);

                  *snaptime = kp->ks_snaptime;

                  *returned_data = *kn;

                  result = SYSINFO_RET_OK;
               }

	      kstat_close(kc);
          }

	return result;
   }


static int NETLOADIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result = SYSINFO_RET_FAIL;
  NETWORK_DATA *p;

  p = get_net_data_record(parameter);

  if (p)
    {
       kstat_named_t kn;
       hrtime_t snaptime;

       result = get_named_field(parameter, "rbytes64", &kn, &snaptime);

       if (result == SYSINFO_RET_OK)
	 {
	   int interval_seconds;

           interval_seconds = (snaptime - p->rb.clock) / 1000000000;

	   *value = (double) (kn.value.ui64 - p->rb.rbytes.u.ui64) / interval_seconds;

           p->rb.rbytes.u.ui64 = kn.value.ui64;

           p->rb.clock = snaptime;
         }

       else
         {
             result = get_named_field(parameter, "rbytes", &kn, &snaptime);

             if (result == SYSINFO_RET_OK)
	        {
	           int interval_seconds;

                   interval_seconds = (snaptime - p->rb.clock) / 1000000000;

	           *value = (double) (kn.value.ui32 - p->rb.rbytes.u.ui32) / interval_seconds;

                   p->rb.rbytes.u.ui32 = kn.value.ui32;

                   p->rb.clock = snaptime;
                }
         }
    }

  return result;
}

static int NETPACKETSIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result = SYSINFO_RET_FAIL;
  NETWORK_DATA *p;

  p = get_net_data_record(parameter);

  if (p)
    {
       kstat_named_t kn;
       hrtime_t snaptime;

       result = get_named_field(parameter, "ipackets64", &kn, &snaptime);

       if (result == SYSINFO_RET_OK)
	 {
	   int interval_seconds;

           interval_seconds = (snaptime - p->rp.clock) / 1000000000;

	   *value = (double) (kn.value.ui64 - p->rp.rpackets.u.ui64) / interval_seconds;

           p->rp.rpackets.u.ui64 = kn.value.ui64;

           p->rp.clock = snaptime;
         }

       else
         {
             result = get_named_field(parameter, "ipacket", &kn, &snaptime);

             if (result == SYSINFO_RET_OK)
	        {
	           int interval_seconds;

                   interval_seconds = (snaptime - p->rp.clock) / 1000000000;

	           *value = (double) (kn.value.ui32 - p->rp.rpackets.u.ui32) / interval_seconds;

                   p->rp.rpackets.u.ui32 = kn.value.ui32;

                   p->rp.clock = snaptime;
                }
         }
    }

  return result;
}

static int NETERRSIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result;
  kstat_named_t kn;
  hrtime_t snaptime;

  result = get_named_field(parameter, "ierrors", &kn, &snaptime);

  if (result == SYSINFO_RET_OK)
    *value = (double) kn.value.ui32;

  return result;
}

static int NETLOADOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result = SYSINFO_RET_FAIL;
  NETWORK_DATA *p;

  p = get_net_data_record(parameter);

  if (p)
    {
       kstat_named_t kn;
       hrtime_t snaptime;

       result = get_named_field(parameter, "obytes64", &kn, &snaptime);

       if (result == SYSINFO_RET_OK)
	 {
	   int interval_seconds;

           interval_seconds = (snaptime - p->ob.clock) / 1000000000;

	   *value = (double) (kn.value.ui64 - p->ob.obytes.u.ui64) / interval_seconds;

           p->ob.obytes.u.ui64 = kn.value.ui64;

           p->ob.clock = snaptime;
         }

       else
         {
             result = get_named_field(parameter, "obytes", &kn, &snaptime);

             if (result == SYSINFO_RET_OK)
	        {
	           int interval_seconds;

                   interval_seconds = (snaptime - p->ob.clock) / 1000000000;

	           *value = (double) (kn.value.ui32 - p->ob.obytes.u.ui32) / interval_seconds;

                   p->ob.obytes.u.ui32 = kn.value.ui32;

                   p->ob.clock = snaptime;
                }
         }
    }

  return result;
}

static int NETPACKETSOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result = SYSINFO_RET_FAIL;
  NETWORK_DATA *p;

  p = get_net_data_record(parameter);

  if (p)
    {
       kstat_named_t kn;
       hrtime_t snaptime;

       result = get_named_field(parameter, "opackets64", &kn, &snaptime);

       if (result == SYSINFO_RET_OK)
	 {
	   int interval_seconds;

           interval_seconds = (snaptime - p->op.clock) / 1000000000;

	   *value = (double) (kn.value.ui64 - p->op.opackets.u.ui64) / interval_seconds;

           p->op.opackets.u.ui64 = kn.value.ui64;

           p->op.clock = snaptime;
         }

       else
         {
             result = get_named_field(parameter, "opacket", &kn, &snaptime);

             if (result == SYSINFO_RET_OK)
	        {
	           int interval_seconds;

                   interval_seconds = (snaptime - p->op.clock) / 1000000000;

	           *value = (double) (kn.value.ui32 - p->op.opackets.u.ui32) / interval_seconds;

                   p->op.opackets.u.ui32 = kn.value.ui32;

                   p->op.clock = snaptime;
                }
         }
    }

  return result;
}

static int NETERRSOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result;
  kstat_named_t kn;
  hrtime_t snaptime;

  result = get_named_field(parameter, "oerrors", &kn, &snaptime);

  if (result == SYSINFO_RET_OK)
    *value = (double) kn.value.ui32;

  return result;
}

static int NETCOLLOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result;
  kstat_named_t kn;
  hrtime_t snaptime;

  result = get_named_field(parameter, "collisions", &kn, &snaptime);

  if (result == SYSINFO_RET_OK)
    *value = (double) kn.value.ui32;

  return result;
}
*/

/*
 * ADDED
 *
int	INODEFREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	*value=s.f_favail;
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
int	INODETOTAL(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	*value=s.f_files;
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
int	DISKFREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

//	return  s.f_bavail * (s.f_bsize / 1024.0);
	*value=s.f_bavail * (s.f_frsize / 1024.0);
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
int	DISKUSED(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

//	return  (s.f_blocks-s.f_bavail) * (s.f_bsize / 1024.0);
	*value=(s.f_blocks-s.f_bavail) * (s.f_frsize / 1024.0);
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
int	DISKTOTAL(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

//	return  s.f_blocks * (s.f_bsize / 1024.0);
	*value= s.f_blocks * (s.f_frsize / 1024.0);
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
static int	TOTALMEM(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	*value=(double)sysconf(_SC_PHYS_PAGES)*sysconf(_SC_PAGESIZE);
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
static int	FREEMEM(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	*value=(double)sysconf(_SC_AVPHYS_PAGES)*sysconf(_SC_PAGESIZE);
	return SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
static int KERNEL_MAXPROC(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result = SYSINFO_RET_FAIL;
  kstat_ctl_t *kc;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *kt;

      kt = kstat_lookup(kc, "unix", 0, "var");

      if (kt)
	{
	  if (    (kt->ks_type == KSTAT_TYPE_RAW)
               && (kstat_read(kc, kt, NULL) != -1)
	     )
            {
	       struct var *v = (struct var *) kt->ks_data;

               *value = v->v_proc;
	       result = SYSINFO_RET_OK;
            }
        }

      kstat_close(kc);
    }

  return result;
}
*/

/*
 * ADDED
 *
static int UPTIME(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
   {
      int result = SYSINFO_RET_FAIL;
      kstat_ctl_t   *kc;

      kc = kstat_open();

        if (kc)
	  {
	     kstat_t       *kp;

	     kp = kstat_lookup(kc, "unix", 0, "system_misc");

             if ((kp) && (kstat_read(kc, kp, 0) != -1))
	       {
                  time_t now;
	          kstat_named_t *kn;

	          kn = (kstat_named_t*) kstat_data_lookup(kp, "boot_time");

                  time(&now);

	          *value=difftime(now, (time_t) kn->value.ul);

                  result = SYSINFO_RET_OK;
               }

	      kstat_close(kc);
          }

	return result;
   }
*/

/*
 * ADDED
 *
static int	PROCLOAD(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[0];
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}
*/

/*
 * ADDED
 *
static int	PROCLOAD5(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[1];
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}
*/

/*
 * ADDED
 *
static int	PROCLOAD15(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[2];
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}
*/

/*
 * ADDED
 *
static int get_swap_data(zbx_uint64_t *resv,
                         zbx_uint64_t *avail,
                         zbx_uint64_t *free)
{
  int result = SYSINFO_RET_FAIL;
  kstat_ctl_t *kc;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *ksp;

      ksp = kstat_lookup(kc, "unix", 0, "vminfo");

      if ((ksp) && (kstat_read(kc, ksp, NULL) != -1))
	{
           vminfo_t *vm;
           int i;
           zbx_uint64_t oresv, ofree, oavail;

           vm = (vminfo_t *) ksp->ks_data;

           oresv = vm->swap_resv;
           ofree = vm->swap_free;
           oavail = vm->swap_avail;

           for (i = 0; i < 12; i++)
              {
                 usleep(100000);

                 if (kstat_read(kc, ksp, NULL) != -1)
		   {
                      vm = (vminfo_t *) ksp->ks_data;

                      if ((oresv != vm->swap_resv) || (ofree != vm->swap_free) || (oavail != vm->swap_avail))
	                 {
			    *resv = vm->swap_resv - oresv;
                            *free = vm->swap_free - ofree;
                            *avail = vm->swap_avail - oavail;

                             result = SYSINFO_RET_OK;

			     break;
                         }
		   }
              }
        }

      kstat_close(kc);
    }

  return result;
}

static int SWAPFREE(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result;
  zbx_uint64_t resv, avail, free;

  result = get_swap_data(&resv, &avail, &free);

  if (result == SYSINFO_RET_OK)
    {
      *value = free * sysconf(_SC_PAGESIZE);
    }

   return result;
}

int SWAPTOTAL(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  int result;
  zbx_uint64_t resv, avail, free;

  result = get_swap_data(&resv, &avail, &free);

  if (result == SYSINFO_RET_OK)
    {
      zbx_uint64_t swap_total_bytes;

      swap_total_bytes = (resv + avail) * sysconf(_SC_PAGESIZE);
      *value = (double) swap_total_bytes;
    }

   return result;
}
*/

/*
 * ADDED
 *
static int SWAPIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  kstat_ctl_t *kc;
  int cpu_count = 0;

  *value = 0.0;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *k = kc->kc_chain;

      while (k)
	{
	  if (     (strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	     )
	    {
   	       cpu_stat_t *cpu = (cpu_stat_t *) k->ks_data;

               *value +=  cpu->cpu_vminfo.swapin;

               cpu_count += 1;
  	    }

	  k = k->ks_next;
        }

      kstat_close(kc);
    }

  return ((cpu_count > 0) ? SYSINFO_RET_OK : SYSINFO_RET_FAIL);
}
*/

/*
 * ADDED
 *
static int SWAPOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  kstat_ctl_t *kc;
  int cpu_count = 0;

  *value = 0.0;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *k = kc->kc_chain;

      while (k)
	{
	  if (     (strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	     )
	    {
   	       cpu_stat_t *cpu = (cpu_stat_t *) k->ks_data;

               *value +=  cpu->cpu_vminfo.swapout;

               cpu_count += 1;
  	    }

	  k = k->ks_next;
        }

      kstat_close(kc);
    }

  return ((cpu_count > 0) ? SYSINFO_RET_OK : SYSINFO_RET_FAIL);
}
*/

/*
 * SKIPPED
 *
static int PROCCOUNT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
   {
      int result = SYSINFO_RET_FAIL;
      kstat_ctl_t *kc;

      kc = kstat_open();

        if (kc)
	  {
	     kstat_t   *kp;

	     kp = kstat_lookup(kc, "unix", 0, "system_misc");

             if ((kp) && (kstat_read(kc, kp, 0) != -1))
	       {
	          kstat_named_t *kn;

	          kn = (kstat_named_t*) kstat_data_lookup(kp, "nproc");

	          *value = (double) kn->value.ul;

                  result = SYSINFO_RET_OK;
               }

	      kstat_close(kc);
          }

	return result;
   }
*/

/*
 * ADDED
 *
static int PROCRUNNING(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[MAX_STRING_LEN];

	int	fd;
// In the correct procfs.h, the structure name is psinfo_t

	psinfo_t psinfo;

	int	proccount=0;

	dir=opendir("/proc");
	if(NULL == dir)
	{
		return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");
		strncat(filename,entries->d_name,MAX_STRING_LEN);
		strncat(filename,"/psinfo",MAX_STRING_LEN);

		if(stat(filename,&buf)==0)
		{
			fd = open (filename, O_RDONLY);
			if(fd != -1)
			{
				if(read (fd, &psinfo, sizeof(psinfo)) == -1)
				{
					closedir(dir);
					return SYSINFO_RET_FAIL;
				}
				else
				{
					if(psinfo.pr_lwp.pr_state == SRUN)
					{
						proccount++;
					}
				}
				close (fd);
			}
			else
			{
				continue;
			}
		}
	}
	closedir(dir);
	*value=(double)proccount;
	return	SYSINFO_RET_OK;
}
*/

/*
 * ADDED
 *
static int CSWITCHES(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  kstat_ctl_t *kc;
  int cpu_count = 0;

  *value = 0.0;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *k = kc->kc_chain;

      while (k)
	{
	  if (     (strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	     )
	    {
   	       cpu_stat_t *cpu = (cpu_stat_t *) k->ks_data;

               *value +=  cpu->cpu_sysinfo.pswitch;

               cpu_count += 1;
  	    }

	  k = k->ks_next;
        }

      kstat_close(kc);
    }

  return ((cpu_count > 0) ? SYSINFO_RET_OK : SYSINFO_RET_FAIL);
}
*/

/*
 * ADDED to /solaris/net.c
 *
static int TCP_LISTEN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  char command[MAX_STRING_LEN];

  memset(command, '\0', sizeof command);

  zbx_snprintf(command, sizeof(command), "netstat -an | grep '*.%s' | wc -l", parameter);

  return EXECUTE_INT(NULL, command, value, msg, mlen_max);
}
*/

/*
 * ADDED
 *
static int INTERRUPTS(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
  kstat_ctl_t *kc;
  int cpu_count = 0;

  *value = 0.0;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *k = kc->kc_chain;

      while (k)
	{
	  if (     (strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	     )
	    {
   	       cpu_stat_t *cpu = (cpu_stat_t *) k->ks_data;

               *value +=  cpu->cpu_sysinfo.intr;

               cpu_count += 1;
  	    }

	  k = k->ks_next;
        }

      kstat_close(kc);
    }

  return ((cpu_count > 0) ? SYSINFO_RET_OK : SYSINFO_RET_FAIL);
}
*/

/*
 * ALREADY ADDED
 *
#ifdef HAVE_LDAP
static int check_ldap(char *hostname, short port,int *value, const char *msg, int mlen_max)
{
	int rc;
	LDAP *ldap;
	LDAPMessage *res;
	LDAPMessage *msg;

	char *base = "";
	int scope = LDAP_SCOPE_BASE;
	char *filter="(objectClass=*)";
	int attrsonly=0;
	char *attrs[2];

	attrs[0] = "namingContexts";
	attrs[1] = NULL;

	BerElement *ber;
	char *attr=NULL;
	char **valRes=NULL;

	ldap = ldap_init(hostname, port);
	if ( !ldap )
	{
		*value=0;
		return	SYSINFO_RET_OK;
	}

	rc = ldap_search_s(ldap, base, scope, filter, attrs, attrsonly, &res);
	if( rc != 0 )
	{
		*value=0;
		return	SYSINFO_RET_OK;
	}

	msg = ldap_first_entry(ldap, res);
	if( !msg )
	{
		*value=0;
		return	SYSINFO_RET_OK;
	}

	attr = ldap_first_attribute (ldap, msg, &ber);
	valRes = ldap_get_values( ldap, msg, attr );

	ldap_value_free(valRes);
	ldap_memfree(attr);
	if (ber != NULL) {
		ber_free(ber, 0);
	}
	ldap_msgfree(res);
	ldap_unbind(ldap);

	*value=1;
	return	SYSINFO_RET_OK;
}
#endif
*/

ZBX_METRIC agent_commands[]=
/*      KEY             FUNCTION (if double) FUNCTION (if string) PARAM*/
        {
	  /*
          {"kern[maxfiles]"       ,KERNEL_MAXFILES,       0, 0},
          */

        {"kern[maxproc]"        ,KERNEL_MAXPROC,        0, 0},

        {"proc_cnt[*]"          ,PROCCNT,               0, "inetd"},

        {"memory[total]"        ,TOTALMEM,              0, 0},
        {"memory[free]"         ,FREEMEM,               0, 0},

        /*
        {"memory[shared]"       ,SHAREDMEM,             0, 0},
        {"memory[buffers]"      ,BUFFERSMEM,            0, 0},
        {"memory[cached]"       ,CACHEDMEM,             0, 0},
        {"memory[free]"         ,FREEMEM,               0, 0},
	*/

        {"version[zabbix_agent]",0,                     VERSION, 0},

        {"diskfree[*]"          ,DISKFREE,              0, "/"},
        {"disktotal[*]"         ,DISKTOTAL,             0, "/"},
        {"diskused[*]"          ,DISKUSED,              0, "/"},

        {"diskfree_perc[*]"     ,DISKFREE_PERC,         0, "/"},
        {"diskused_perc[*]"     ,DISKUSED_PERC,         0, "/"},

        {"inodefree[*]"         ,INODEFREE,             0, "/"},
        {"inodefree_perc[*]"    ,INODEFREE_PERC,        0, "/"},
        {"inodetotal[*]"        ,INODETOTAL,            0, "/"},

        {"cksum[*]"             ,CKSUM,                 0, "/etc/services"},

        {"md5sum[*]"            ,0,                     MD5SUM, "/etc/services"},

        {"filesize[*]"          ,FILESIZE,              0, "/etc/passwd"},
        {"file[*]"              ,ISFILE,                0, "/etc/passwd"},

        {"cpu[idle]"            ,CPUIDLE,               0, 0},
        {"cpu[user]"            ,CPUUSER,               0, 0},
        {"cpu[system]"          ,CPUSYSTEM,             0, 0},
        {"cpu[iowait]"          ,CPUIOWAIT,             0, 0},

	/*
        {"cpu[nice]"            ,CPUNICE,               0, 0},
        {"cpu[interrupt]"       ,CPUINTERRUPT,          0, 0},
        */

        {"netloadin[*]"         ,NETLOADIN,             0, "lo"},
        {"netloadout[*]"        ,NETLOADOUT,            0, "lo"},
        {"netpacketsin[*]"      ,NETPACKETSIN,          0, "lo"},
        {"netpacketsout[*]"     ,NETPACKETSOUT,         0, "lo"},
        {"neterrsin[*]"         ,NETERRSIN,             0, "lo"},
        {"neterrsout[*]"        ,NETERRSOUT,            0, "lo"},
        {"netcollout[*]"        ,NETCOLLOUT,            0, "lo"},


        {"disk_read_ops[*]"     ,DISKREADOPS,           0, "hda"},
        {"disk_read_kbs[*]"     ,DISKREADBLOCKS,        0, "hda"},
        {"disk_write_ops[*]"    ,DISKWRITEOPS,          0, "hda"},
        {"disk_write_kbs[*]"    ,DISKWRITEBLOCKS,       0, "hda"},
        {"disk_busy[*]"         ,DISKBUSY,              0, "hda"},
        {"disk_svc[*]"          ,DISKSVC,               0, "hda"},

	/*
        {"sensor[temp1]"        ,SENSOR,                0, "temp1"},
        {"sensor[temp2]"        ,SENSOR,                0, "temp2"},
        {"sensor[temp3]"        ,SENSOR,                0, "temp3"},
	*/

        {"swap[free]"           ,SWAPFREE,              0, 0},
        {"swap[total]"          ,SWAPTOTAL,             0, 0},
        {"swap[in]"             ,SWAPIN,                0, 0},
        {"swap[out]"            ,SWAPOUT,               0, 0},

        {"system[interrupts]"   ,INTERRUPTS,            0, 0},
        {"system[switches]"     ,CSWITCHES,             0, 0},
        {"system[procload]"     ,PROCLOAD,              0, 0},
        {"system[procload5]"    ,PROCLOAD5,             0, 0},
        {"system[procload15]"   ,PROCLOAD15,            0, 0},
        {"system[proccount]"    ,PROCCOUNT,             0, 0},
        {"system[procrunning]"  ,PROCRUNNING,           0, 0},

        {"system[hostname]"     ,0,             EXECUTE_STR, "hostname"},
        {"system[uname]"        ,0,             EXECUTE_STR, "uname -a"},
        {"system[uptime]"       ,UPTIME,        0, 0},
        {"system[users]"        ,EXECUTE_INT,       0,"who|wc -l"},

        {"ping"                 ,PING,          0, 0},
        {"tcp_count"            ,EXECUTE_INT,       0, "netstat -s -P tcp | grep tcpCurrEstab | cut -f2 | tr -s ' ' | cut -d' ' -f3"},

        {"tcp_listen[*]"        ,TCP_LISTEN,    0, "22"},

        {"check_port[*]"        ,CHECK_PORT,    0, "80"},

        {"check_service[*]"     ,CHECK_SERVICE,         0, "ssh,127.0.0.1,22"},
        {"check_service_perf[*]",CHECK_SERVICE_PERF,    0, "ssh,127.0.0.1,22"},

        {0}
        };
