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

#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <kvm.h>
#include <limits.h>
#include <nlist.h>
#include <string.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include <sys/socket.h>
#include <sys/timeout.h>
#include <netinet/in.h>
#include <net/if.h> 
#include <netinet/ip_var.h>
#include <netinet/tcp.h>
#include <netinet/tcp_fsm.h>
#include <netinet/tcp_timer.h>
#include <netinet/tcp_var.h>
#include <netinet/in_systm.h>
#include <net/route.h>
#include <netinet/ip.h>
#include <netinet/in_pcb.h>
#include <arpa/inet.h>
#include <sys/disk.h>
#include <sys/dkstat.h>
#include <sys/mount.h>
#include <sys/param.h>
#include <sys/proc.h>
#include <sys/select.h>
#include <sys/stat.h>
#include <sys/sysctl.h>
#include <sys/swap.h>
#include <sys/types.h>
#include <sys/vmmeter.h>
#include <unistd.h>
#include <uvm/uvm_extern.h>

#ifdef HAVE_LDAP
        #include <ldap.h>
#endif

#include "common.h"
#include "log.h"
#include "sysinfo.h"

typedef struct busy_data
{
  struct timeval clock;
  struct timeval rtime;
} BUSY_DATA;

typedef struct read_ios_data
{
  struct timeval clock;
  u_int64_t reads;
} READ_IOS_DATA;

typedef struct write_ios_data
{
  struct timeval clock;
  u_int64_t writes;
} WRITE_IOS_DATA;

typedef struct rblocks_data
{
  struct timeval clock;
  u_int64_t nread;
} RBLOCKS_DATA;

typedef struct wblocks_data
{
  struct timeval clock;
  u_int64_t nwritten;
} WBLOCKS_DATA;

typedef struct disk_data
{
  struct disk_data *next;
  char *name;
  BUSY_DATA busy;
  READ_IOS_DATA reads;
  WRITE_IOS_DATA writes;
  RBLOCKS_DATA rblocks;
  WBLOCKS_DATA wblocks;
 } DISK_DATA;

typedef struct rbytes_data
{
  struct timeval clock;
  u_long rbytes;
} RBYTES_DATA;

typedef struct obytes_data
{
  struct timeval clock;
  u_long obytes;
} OBYTES_DATA;

typedef struct rpackets_data
{
  struct timeval clock;
  u_long rpackets;
} RPACKETS_DATA;

typedef struct opackets_data
{
  struct timeval clock;
  u_long opackets;
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
static DISK_DATA *disks;

/*
 * ADDED to net.c
 *
static struct nlist kernel_symbols[] = 
   {
       {"_ifnet", N_UNDF, 0, 0, 0},
       {"_tcbtable", N_UNDF, 0, 0, 0},
       {NULL, 0, 0, 0, 0}
   };
*/

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

static int PROCCNT(const char *cmd, const char *procname,double  *value, const char *msg, int mlen_max)
{
        int result = SYSINFO_RET_FAIL;
	kvm_t 	*kp;

        kp = kvm_open(NULL,NULL,NULL,O_RDONLY,NULL);

        if (kp)
           {
              int count;
              struct kinfo_proc2 *proc; 

              proc = kvm_getproc2(kp, 
                                  KERN_PROC_ALL, 
                                  0, 
                                  sizeof(struct kinfo_proc2),
                                  &count);

              if (proc)
                 {
                    int i;
                    int proccount=0;

                    for (i = 0; i < count; i++)
                       if (strstr(proc[i].p_comm,procname))
                         {
                            proccount++;
                         }

                    *value=(double)proccount;
                    result = SYSINFO_RET_OK;
                 }

              kvm_close(kp);
           }

   	return result;
}


/*
 * ADDED to devio.c
 *
static int get_disk_stats(const char *device, struct diskstats *returned_stats)
{
   int result = SYSINFO_RET_FAIL;
   int mib[2];
   int drive_count;
   size_t l; 

   mib[0] = CTL_HW;
   mib[1] = HW_DISKCOUNT;

   l = sizeof(drive_count);

   if (sysctl(mib, 2, &drive_count, &l, NULL, 0) == 0 ) 
      {
         struct diskstats *stats;

         stats = calloc(drive_count, sizeof (struct diskstats));

         if (stats)
            {
               mib[0] = CTL_HW;
               mib[1] = HW_DISKSTATS;
             
               l = (drive_count * sizeof (struct diskstats));
 
               if (sysctl(mib, 2, stats, &l, NULL, 0) == 0)
                  {
                     int i;

                     for (i = 0; i < drive_count; i++)
                        if (strcmp(device, stats[i].ds_name) == 0)
                           {
                              *returned_stats = stats[i];
                              result = SYSINFO_RET_OK;
                              break;
                           }
                  }

               free(stats);
            }
      }

   return result;
}

*/
/*
 * ADDED to devio.c
 *
static int DISKREADOPS(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   DISK_DATA *p;

   p = get_disk_data_record(device);

   if (p)
      {
         struct diskstats ds;

         result = get_disk_stats(device, &ds);

         if (result == SYSINFO_RET_OK) 
            {
               struct timeval interval;
               struct timeval snaptime;

               gettimeofday(&snaptime, NULL);

               timersub(&snaptime, &p->reads.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ds.ds_rxfer - p->reads.reads) / interval.tv_sec;
      	       else 
                  *value = 0.0;

               p->reads.clock = snaptime;

               p->reads.reads = ds.ds_rxfer;
            }
      }

  return result;
}

*/
/*
 * ADDED to devio.c
 *
static int DISKREADBLOCKS(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   DISK_DATA *p;

   p = get_disk_data_record(device);

   if (p)
      {
         struct diskstats ds;

         result = get_disk_stats(device, &ds);

         if (result == SYSINFO_RET_OK) 
            {
               struct timeval interval;
               struct timeval snaptime;

               gettimeofday(&snaptime, NULL);

               timersub(&snaptime, &p->rblocks.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = ((ds.ds_rbytes/1024) - p->rblocks.nread) / interval.tv_sec;
      	       else 
                  *value = 0.0;

               p->rblocks.clock = snaptime;

               p->rblocks.nread = ds.ds_rbytes/1024;
            }
      }

  return result;
}

*/
/*
 * ADDED to devio.c
 *
static int DISKWRITEOPS(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   DISK_DATA *p;

   p = get_disk_data_record(device);

   if (p)
      {
         struct diskstats ds;

         result = get_disk_stats(device, &ds);

         if (result == SYSINFO_RET_OK) 
            {
               struct timeval interval;
               struct timeval snaptime;

               gettimeofday(&snaptime, NULL);

               timersub(&snaptime, &p->writes.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ds.ds_wxfer - p->writes.writes) / interval.tv_sec;
      	       else 
                  *value = 0.0;

               p->writes.clock = snaptime;

               p->writes.writes = ds.ds_wxfer;
            }
      }

  return result;
}
*/
/*
 * ADDED to devio.c
 *
static int DISKWRITEBLOCKS(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   DISK_DATA *p;

   p = get_disk_data_record(device);

   if (p)
      {
         struct diskstats ds;

         result = get_disk_stats(device, &ds);

         if (result == SYSINFO_RET_OK) 
            {
               struct timeval interval;
               struct timeval snaptime;

               gettimeofday(&snaptime, NULL);

               timersub(&snaptime, &p->wblocks.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = ((ds.ds_wbytes/1024) - p->wblocks.nwritten) / interval.tv_sec;
      	       else 
                  *value = 0.0;

               p->wblocks.clock = snaptime;

               p->wblocks.nwritten = ds.ds_wbytes/1024;
            }
      }

  return result;
}
*/
static int DISKBUSY(const char *cmd, const char *device, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   DISK_DATA *p;

   p = get_disk_data_record(device);

   if (p)
      {
         struct diskstats ds;

         result = get_disk_stats(device, &ds);

         if (result == SYSINFO_RET_OK) 
            {
               struct timeval interval;
               struct timeval snaptime;

               gettimeofday(&snaptime, NULL);

               if (snaptime.tv_sec > p->busy.clock.tv_sec)
                  {
                     struct timeval time_busy;

                     timersub(&snaptime, &p->busy.clock, &interval);

                     timersub(&ds.ds_time, &p->busy.rtime, &time_busy);

                     *value = (time_busy.tv_sec * 100.0) / interval.tv_sec;

                     /* The ds_time field is not always monotonically increasing
                        for some reason.... */

                     if (*value < 0.0)
                        *value = 0.0;

                   }

      	       else 
                  *value = 0.0;

               p->busy.clock = snaptime;

               p->busy.rtime = ds.ds_time;
            }
      }

  return result;
}

/*
 * ADDED to cpu.c
 *
static int CPUIDLE(const char *cmd, const char *param, double  *value, const char *msg, int mlen_max)
{
   static u_int64_t last[cpustates];
   u_int64_t current[cpustates];
   int result = sysinfo_ret_fail;
   int mib[2];
   size_t l; 

   mib[0] = ctl_kern;
   mib[1] = kern_cptime;

   l = sizeof(current);

   if (sysctl(mib, 2, current, &l, null, 0) == 0 ) 
      {
         u_int64_t interval_size;

         interval_size =    (current[cp_idle] - last[cp_idle])
                         +  (current[cp_user] - last[cp_user])
                         +  (current[cp_nice] - last[cp_nice])
                         +  (current[cp_sys] - last[cp_sys])
                         +  (current[cp_intr] - last[cp_intr]);
 
         if (interval_size > 0)
            {
	       *value = ((current[cp_idle] - last[cp_idle]) * 100.0)/interval_size;

               memcpy(&last, &current, sizeof(current)); 

               result = sysinfo_ret_ok;
            }
      }

   return result;
}
*/
/*
 * ADDED to cpu.c
 *
static int CPUUSER(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
   static u_int64_t last[CPUSTATES];
   u_int64_t current[CPUSTATES];
   int result = SYSINFO_RET_FAIL;
   int mib[2];
   size_t l; 

   mib[0] = CTL_KERN;
   mib[1] = KERN_CPTIME;

   l = sizeof(current);

   if (sysctl(mib, 2, current, &l, NULL, 0) == 0 ) 
      {
         u_int64_t interval_size;

         interval_size =    (current[CP_IDLE] - last[CP_IDLE])
                         +  (current[CP_USER] - last[CP_USER])
                         +  (current[CP_NICE] - last[CP_NICE])
                         +  (current[CP_SYS] - last[CP_SYS])
                         +  (current[CP_INTR] - last[CP_INTR]);
 
         if (interval_size > 0)
            {
	       *value = ((current[CP_USER] - last[CP_USER]) * 100.0)/interval_size;

               memcpy(&last, &current, sizeof(current)); 

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}
*/
/*
 * ADDED to cpu.c
 *
static int CPUSYSTEM(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
   static u_int64_t last[CPUSTATES];
   u_int64_t current[CPUSTATES];
   int result = SYSINFO_RET_FAIL;
   int mib[2];
   size_t l; 

   mib[0] = CTL_KERN;
   mib[1] = KERN_CPTIME;

   l = sizeof(current);

   if (sysctl(mib, 2, current, &l, NULL, 0) == 0 ) 
      {
         u_int64_t interval_size;

         interval_size =    (current[CP_IDLE] - last[CP_IDLE])
                         +  (current[CP_USER] - last[CP_USER])
                         +  (current[CP_NICE] - last[CP_NICE])
                         +  (current[CP_SYS] - last[CP_SYS])
                         +  (current[CP_INTR] - last[CP_INTR]);
 
         if (interval_size > 0)
            {
	       *value = ((current[CP_SYS] - last[CP_SYS]) * 100.0)/interval_size;

               memcpy(&last, &current, sizeof(current)); 

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}
*/
/*
 * ADDED to cpu.c
 *
static int CPUNICE(const char *cmd, const char *param, double *value, const char *msg, int mlen_max)
{
   static u_int64_t last[CPUSTATES];
   u_int64_t current[CPUSTATES];
   int result = SYSINFO_RET_FAIL;
   int mib[2];
   size_t l; 

   mib[0] = CTL_KERN;
   mib[1] = KERN_CPTIME;

   l = sizeof(current);

   if (sysctl(mib, 2, current, &l, NULL, 0) == 0 ) 
      {
         u_int64_t interval_size;

         interval_size =    (current[CP_IDLE] - last[CP_IDLE])
                         +  (current[CP_USER] - last[CP_USER])
                         +  (current[CP_NICE] - last[CP_NICE])
                         +  (current[CP_SYS] - last[CP_SYS])
                         +  (current[CP_INTR] - last[CP_INTR]);
 
         if (interval_size > 0)
            {
	       *value = ((current[CP_NICE] - last[CP_NICE]) * 100.0)/interval_size;

               memcpy(&last, &current, sizeof(current)); 

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}
*/
/*
 * ADDED to cpu.c
 *
static int CPUINTERRUPT(const char *cmd, const char *param, double *value, const char *msg, int mlen_max)
{
   static u_int64_t last[CPUSTATES];
   u_int64_t current[CPUSTATES];
   int result = SYSINFO_RET_FAIL;
   int mib[2];
   size_t l; 

   mib[0] = CTL_KERN;
   mib[1] = KERN_CPTIME;

   l = sizeof(current);

   if (sysctl(mib, 2, current, &l, NULL, 0) == 0 ) 
      {
         u_int64_t interval_size;

         interval_size =    (current[CP_IDLE] - last[CP_IDLE])
                         +  (current[CP_USER] - last[CP_USER])
                         +  (current[CP_NICE] - last[CP_NICE])
                         +  (current[CP_SYS] - last[CP_SYS])
                         +  (current[CP_INTR] - last[CP_INTR]);
 
         if (interval_size > 0)
            {
	       *value = ((current[CP_INTR] - last[CP_INTR]) * 100.0)/interval_size;

               memcpy(&last, &current, sizeof(current)); 

               result = SYSINFO_RET_OK;
            }
      }

   return result;
}
*/

/*
 * ADDED to net.c
 *
static int get_ifdata(const char *device, struct if_data *returned_data)
{
   int result = SYSINFO_RET_FAIL;
   kvm_t *kp;

   kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL);

   if (kp)
      {
         if (kernel_symbols[0].n_type == N_UNDF)
            {
               if (kvm_nlist(kp, &kernel_symbols[0]) != 0)
                  {
                     kvm_close(kp);

                     return result;
                  }
            }

         if (kernel_symbols[0].n_type != N_UNDF)
            {
               struct ifnet_head head;

               if (kvm_read(kp, kernel_symbols[0].n_value, &head, sizeof head) >= sizeof head)
                  {
                     struct ifnet ifn;
                     struct ifnet *ifp = head.tqh_first;

                     while (     (ifp)
                              && (kvm_read(kp, (u_long) ifp, &ifn, sizeof ifn) >= sizeof ifn)
                           )
                       {
                          char ifname[IFNAMSIZ+1];

                          memcpy(ifname, ifn.if_xname, sizeof ifname - 1);
                          ifname[IFNAMSIZ] = '\0';

                          if (strcmp(device, ifname) == 0)
                             {
                                *returned_data = ifn.if_data;

                                result = SYSINFO_RET_OK;
                               
                                break;
                             }

                          ifp = ifn.if_list.tqe_next;
                       }
                  }
            }

         kvm_close(kp);
      }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETLOADIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         NETWORK_DATA *p;

         p = get_net_data_record(parameter);

         if (p)
            {
               struct timeval snaptime;
               struct timeval interval;

               gettimeofday(&snaptime, NULL);
	  
               timersub(&snaptime, &p->rb.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ifd.ifi_ibytes - p->rb.rbytes) /  interval.tv_sec;
               else
                  *value = 0.0;

               p->rb.rbytes = ifd.ifi_ibytes;

               p->rb.clock = snaptime;
            }

         else
            result = SYSINFO_RET_FAIL;  
     }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETPACKETSIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         NETWORK_DATA *p;

         p = get_net_data_record(parameter);

         if (p)
            {
               struct timeval snaptime;
               struct timeval interval;

               gettimeofday(&snaptime, NULL);
	  
               timersub(&snaptime, &p->rp.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ifd.ifi_ipackets - p->rp.rpackets) /  interval.tv_sec;
               else
                  *value = 0.0;

               p->rp.rpackets = ifd.ifi_ipackets;

               p->rp.clock = snaptime;
            }

         else
            result = SYSINFO_RET_FAIL;  
     }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETERRSIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         *value = (double) ifd.ifi_ierrors;
     }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETLOADOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         NETWORK_DATA *p;

         p = get_net_data_record(parameter);

         if (p)
            {
               struct timeval snaptime;
               struct timeval interval;

               gettimeofday(&snaptime, NULL);
	  
               timersub(&snaptime, &p->ob.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ifd.ifi_obytes - p->ob.obytes) /  interval.tv_sec;
               else
                  *value = 0.0;

               p->ob.obytes = ifd.ifi_obytes;

               p->ob.clock = snaptime;
            }

         else
            result = SYSINFO_RET_FAIL;  
     }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETPACKETSOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         NETWORK_DATA *p;

         p = get_net_data_record(parameter);

         if (p)
            {
               struct timeval snaptime;
               struct timeval interval;

               gettimeofday(&snaptime, NULL);
	  
               timersub(&snaptime, &p->op.clock, &interval);

               if (interval.tv_sec > 0)
                  *value = (ifd.ifi_opackets - p->op.opackets) /  interval.tv_sec;
               else
                  *value = 0.0;

               p->op.opackets = ifd.ifi_opackets;

               p->op.clock = snaptime;
            }

         else
            result = SYSINFO_RET_FAIL;  
     }

   return result;
}
*/

/*
 * ADDED to net.c
 *
static int NETERRSOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         *value = (double) ifd.ifi_oerrors;
     }

   return result;
}
*/
/*
 * ADDED to net.c
 *
static int NETCOLLOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int result;
   struct if_data ifd;

   result = get_ifdata(parameter, &ifd);

   if (result == SYSINFO_RET_OK)
      {
         *value = (double) ifd.ifi_collisions;
     }

   return result;
}
*/
/*
 * IGNORED already added
 *
int     INODEFREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
        struct statfs   s;

        if ( statfs( (char *)mountPoint, &s) != 0 )
        {
                return  SYSINFO_RET_FAIL;
        }

        *value=s.f_ffree;
        return SYSINFO_RET_OK;
}
*/
/*
 * IGNORED already added
 *
int     INODETOTAL(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
        struct statfs   s;

        if ( statfs( (char *)mountPoint, &s) != 0 )
        {
                return  SYSINFO_RET_FAIL;
        }

        *value=s.f_files;
        return SYSINFO_RET_OK;
}
*/
/*
 * IGNORED already added
 *
int     DISKFREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
        struct statfs   s;

        if ( statfs( (char *)mountPoint, &s) != 0 )
        {
                return  SYSINFO_RET_FAIL;
        }

        *value=s.f_bavail*s.f_bsize;
        return SYSINFO_RET_OK;
}
*/
/*
 * IGNORED already added
 *
int     DISKUSED(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
        struct statfs   s;

        if ( statfs( (char *)mountPoint, &s) != 0 )
        {
                return  SYSINFO_RET_FAIL;
        }

        *value=(s.f_blocks-s.f_bavail)*s.f_bsize;
        return SYSINFO_RET_OK;
}
*/
/*
 * IGNORED already added
 *
int     DISKTOTAL(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
        struct statfs   s;

        if ( statfs( (char *)mountPoint, &s) != 0 )
        {
                return  SYSINFO_RET_FAIL;
        }

        *value= s.f_blocks*s.f_bsize;
        return SYSINFO_RET_OK;
}
*/

static int TCP_LISTEN(const char *cmd, const char *porthex, double  *value, const char *msg, int mlen_max)
{
   int result = SYSINFO_RET_FAIL;
   kvm_t *kp;

   *value = 0.0;

   kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL);

   if (kp)
      {
         if (kernel_symbols[1].n_type == N_UNDF)
            {
               if (kvm_nlist(kp, &kernel_symbols[0]) != 0)
                  {
                     kvm_close(kp);

                     return result;
                  }
            }

         if (kernel_symbols[1].n_type != N_UNDF)
            {
               struct inpcbtable pcbq_head; 

               if (kvm_read(kp, kernel_symbols[1].n_value, &pcbq_head, sizeof pcbq_head) >= sizeof pcbq_head)
                  {
                     struct inpcb pcb;
                     struct inpcb *next = pcbq_head.inpt_queue.cqh_first;
                     struct inpcb *headp = (struct inpcb *) &((struct inpcbtable *) kernel_symbols[1].n_value)->inpt_queue.cqh_first;
                     struct inpcb *prev;

                     prev = headp;

                     while (    (next != headp)
                             && (kvm_read(kp, (u_long) next, &pcb, sizeof pcb) >= sizeof pcb)
                             && (pcb.inp_queue.cqe_prev == prev)
                           )
                       {
                          prev = next;
                          next = pcb.inp_queue.cqe_next;

                          if (    (! (pcb.inp_flags & INP_IPV6))
                               && (inet_lnaof(pcb.inp_laddr) == INADDR_ANY)
                               && (ntohs(pcb.inp_lport) == strtoul(porthex, NULL, 16))
                             )
                             {
                                *value = 1.0;

                                result = SYSINFO_RET_OK;

                                break;
                             }
                       }
                  }
            }

         kvm_close(kp);
      }

   return result;
}

static int count_active_sockets()
{
   kvm_t *kp;
   int socket_count = 0;

   kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL);

   if (kp)
      {
         if (kernel_symbols[1].n_type == N_UNDF)
            {
               if (kvm_nlist(kp, &kernel_symbols[0]) != 0)
                  {
                     kvm_close(kp);

                     return 0;
                  }
            }

         if (kernel_symbols[1].n_type != N_UNDF)
            {
               struct inpcbtable pcbq_head; 

               if (kvm_read(kp, kernel_symbols[1].n_value, &pcbq_head, sizeof pcbq_head) >= sizeof pcbq_head)
                  {
                     struct inpcb pcb;
                     struct inpcb *next = pcbq_head.inpt_queue.cqh_first;
                     struct inpcb *headp = (struct inpcb *) &((struct inpcbtable *) kernel_symbols[1].n_value)->inpt_queue.cqh_first;
                     struct inpcb *prev;

                     prev = headp;

                     while (    (next != headp)
                             && (kvm_read(kp, (u_long) next, &pcb, sizeof pcb) >= sizeof pcb)
                             && (pcb.inp_queue.cqe_prev == prev)
                           )
                       {
                          prev = next;
                          next = pcb.inp_queue.cqe_next;

                          if (    (! (pcb.inp_flags & INP_IPV6))
                               && (inet_lnaof(pcb.inp_laddr) != INADDR_ANY)
                             )
                             {
                                struct tcpcb tcpcb;

                                if (     (kvm_read(kp, (u_long)pcb.inp_ppcb, &tcpcb, sizeof tcpcb) >= sizeof tcpcb)
                                      && (tcpcb.t_state >= TCPS_ESTABLISHED)
                                      && (tcpcb.t_state < TCPS_TIME_WAIT)
                                   ) 
                                   socket_count++;
                             }
                       }
                  }
            }

         kvm_close(kp);
      }

   return socket_count;
}

static void count_active_sockets_on_port(int port, int *sockets_in, int *sockets_out)
{
   kvm_t *kp;

   *sockets_in = *sockets_out = 0;

   kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL);

   if (kp)
      {
         if (kernel_symbols[1].n_type == N_UNDF)
            {
               if (kvm_nlist(kp, &kernel_symbols[0]) != 0)
                  {
                     kvm_close(kp);
                  }
            }

         if (kernel_symbols[1].n_type != N_UNDF)
            {
               struct inpcbtable pcbq_head; 

               if (kvm_read(kp, kernel_symbols[1].n_value, &pcbq_head, sizeof pcbq_head) >= sizeof pcbq_head)
                  {
                     struct inpcb pcb;
                     struct inpcb *next = pcbq_head.inpt_queue.cqh_first;
                     struct inpcb *headp = (struct inpcb *) &((struct inpcbtable *) kernel_symbols[1].n_value)->inpt_queue.cqh_first;
                     struct inpcb *prev;

                     prev = headp;

                     while (    (next != headp)
                             && (kvm_read(kp, (u_long) next, &pcb, sizeof pcb) >= sizeof pcb)
                             && (pcb.inp_queue.cqe_prev == prev)
                           )
                       {
                          prev = next;
                          next = pcb.inp_queue.cqe_next;

                          if (    (! (pcb.inp_flags & INP_IPV6))
                               && (inet_lnaof(pcb.inp_laddr) != INADDR_ANY)
                               && (     (ntohs(pcb.inp_lport) ==  port)
                                     || (ntohs(pcb.inp_fport) == port)
                                  )
                             )
                             {
                                struct tcpcb tcpcb;

                                if (     (kvm_read(kp, (u_long)pcb.inp_ppcb, &tcpcb, sizeof tcpcb) >= sizeof tcpcb)
                                      && (tcpcb.t_state >= TCPS_ESTABLISHED)
                                      && (tcpcb.t_state < TCPS_TIME_WAIT)
                                   ) 
                                   {
                                      if (ntohs(pcb.inp_lport) ==  port)
                                         *sockets_in++;
                                      else
                                         *sockets_out++;
                                   }
                             }
                       }
                  }
            }

         kvm_close(kp);
      }
}

static int TCP_SOCKETS(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   *value = (double) count_active_sockets();

   return SYSINFO_RET_OK;
}

static int TCP_SOCKETS_IN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int sockets_in, sockets_out;

   count_active_sockets_on_port(atoi(parameter), &sockets_in, &sockets_out);

   *value = (double) sockets_in;

   return SYSINFO_RET_OK;
}

static int TCP_SOCKETS_OUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int sockets_in, sockets_out;

   count_active_sockets_on_port(atoi(parameter), &sockets_in, &sockets_out);

   *value = (double) sockets_out;

   return SYSINFO_RET_OK;
}

/*
 * ADDED to memory.c
 *
static int TOTALMEM(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int mib[2];
        size_t len;
	struct vmtotal v;

	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	*value=(double)((v.t_rm+v.t_free) * sysconf(_SC_PAGESIZE));
	return SYSINFO_RET_OK;

}
*/
/*
 * ADDED to memory.c
 *
static int FREEMEM(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int mib[2];
        size_t len;
	struct vmtotal v;

	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	*value=(double)(v.t_free * sysconf(_SC_PAGESIZE));
	return SYSINFO_RET_OK;

}
*/
/*
 * SKIPED - already added
 *
static int KERNEL_MAXFILES(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
   int	mib[2];
   size_t len;
   int	maxfiles;

   mib[0]=CTL_KERN;
   mib[1]=KERN_MAXFILES;

   len=sizeof(maxfiles);

   if (sysctl(mib,2,&maxfiles,(size_t *)&len,NULL,0) != 0)
      {
         return	SYSINFO_RET_FAIL;
      }

   *value=(double)(maxfiles);
   return SYSINFO_RET_OK;
}
*/
/*
 * SKIPED - already added
 *
static int     KERNEL_MAXPROC(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
	int	maxproc;

	mib[0]=CTL_KERN;
	mib[1]=KERN_MAXPROC;

	len=sizeof(maxproc);

	if(sysctl(mib,2,&maxproc,(size_t *)&len,NULL,0) != 0)
	{
		return	SYSINFO_RET_FAIL;
	}

	*value=(double)(maxproc);
	return SYSINFO_RET_OK;
}
*/
/*
 * ADDED to uptime.c
 *
static int     UPTIME(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
	struct timeval	uptime;
	int	now;

	mib[0]=CTL_KERN;
	mib[1]=KERN_BOOTTIME;

	len=sizeof(uptime);

	if(sysctl(mib,2,&uptime,(size_t *)&len,NULL,0) != 0)
	{
		return	SYSINFO_RET_FAIL;
	}

	now=time(NULL);

	*value=(double)(now-uptime.tv_sec);
	return SYSINFO_RET_OK;
}
*/
/*
 * ADDED to cpu.c
 *
static int     PROCLOAD(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
        double  load[3];

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
 * ADDED to cpu.c
 *
static int     PROCLOAD5(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
        double  load[3];

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
 * ADDED to cpu.c
 *
static int     PROCLOAD15(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
        double  load[3];

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
 * ADDED TO cwap.c
 * 
static int SWAPFREE(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = ((long) (vm.swpages - vm.swpginuse)) * vm.pagesize;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}
*/
/*
 * ADDED TO cwap.c
 * 
static int     SWAPTOTAL(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t  len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = ((long) vm.swpages) * vm.pagesize;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}
*/
/*
 * ADDED TO cwap.c
 * 
static int SWAPIN(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = vm.swapins;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}
*/
/*
 * ADDED to swap.c
static int SWAPOUT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = vm.swapouts;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}
*/
static int     PROCCOUNT(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
        int result = SYSINFO_RET_FAIL;
	kvm_t 	*kp;

        kp = kvm_open(NULL,NULL,NULL,O_RDONLY,NULL);

        if (kp)
           {
              int count;
              struct kinfo_proc2 *proc; 

              proc = kvm_getproc2(kp, 
                                  KERN_PROC_ALL, 
                                  0, 
                                  sizeof(struct kinfo_proc2),
                                  &count);

              if (proc)
                 {
                    *value=(double) count;
                    result = SYSINFO_RET_OK;
                 }

              kvm_close(kp);
           }

   	return result;
}

static int PROCRUNNING(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
        int result = SYSINFO_RET_FAIL;
	kvm_t 	*kp;

        kp = kvm_open(NULL,NULL,NULL,O_RDONLY,NULL);

        if (kp)
           {
              int count;
              struct kinfo_proc2 *proc; 

              proc = kvm_getproc2(kp, 
                                  KERN_PROC_ALL, 
                                  0, 
                                  sizeof(struct kinfo_proc2),
                                  &count);

              if (proc)
                 {
                    int i;
                    int proccount=0;

                    for (i = 0; i < count; i++)
                       if (proc[i].p_stat == SONPROC)
                         {
                            proccount++;
                         }

                    *value=(double)proccount;
                    result = SYSINFO_RET_OK;
                 }

              kvm_close(kp);
           }

   	return result;
}

static int CSWITCHES(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = vm.swtch;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}

static int INTERRUPTS(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) != -1)
	{
            *value = vm.intrs;

	    return SYSINFO_RET_OK;
        }

        else
	   return SYSINFO_RET_FAIL;
}

/*
 * IGNORED
 *
#ifdef HAVE_LDAP
static int    check_ldap(char *hostname, short port,int *value, const char *msg, int mlen_max)
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
                return  SYSINFO_RET_OK;
        }

        rc = ldap_search_s(ldap, base, scope, filter, attrs, attrsonly, &res);
        if( rc != 0 )
        {
                *value=0;
                return  SYSINFO_RET_OK;
        }

        msg = ldap_first_entry(ldap, res);
        if( !msg )
        {
                *value=0;
                return  SYSINFO_RET_OK;
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
        return  SYSINFO_RET_OK;
}
#endif
*/

ZBX_METRIC agent_commands[]=
/*      KEY             FUNCTION (if double) FUNCTION (if string) PARAM*/
        {
        {"kern[maxfiles]"       ,KERNEL_MAXFILES,       0, 0},
        {"kern[maxproc]"        ,KERNEL_MAXPROC,        0, 0},

        {"proc_cnt[*]"          ,PROCCNT,               0, "inetd"},

        {"memory[total]"        ,TOTALMEM,              0, 0},
        {"memory[free]"         ,FREEMEM,               0, 0},

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
        {"cpu[nice]"            ,CPUNICE,               0, 0},
        {"cpu[user]"            ,CPUUSER,               0, 0},
        {"cpu[system]"          ,CPUSYSTEM,             0, 0},
        {"cpu[interrupt]"       ,CPUINTERRUPT,          0, 0},

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
        {"tcp_count"            ,TCP_SOCKETS,   0, 0},
        {"sockets_in[*]"        ,TCP_SOCKETS_IN,   0, "25"},
        {"sockets_out[*]"       ,TCP_SOCKETS_OUT,   0, "25"},

        {"net_listen[*]"        ,TCP_LISTEN,    0, "0050"},

        {"check_port[*]"        ,CHECK_PORT,    0, "80"},

        {"check_service[*]"     ,CHECK_SERVICE,         0, "ssh,127.0.0.1,22"},
        {"check_service_perf[*]",CHECK_SERVICE_PERF,    0, "ssh,127.0.0.1,22"},

        {0}
        };

