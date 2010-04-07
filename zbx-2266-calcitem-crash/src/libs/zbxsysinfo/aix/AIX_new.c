#include <stdio.h>
#include <stdlib.h>
#include "prfstatwrp.h"
#include <procinfo.h>
#include <sys/proc.h>
#include <nlist.h>
#include <sys/types.h>
#include <sys/times.h>

#define KMEM "/dev/kmem"
int kmem;                 /* file descriptor */

/* Indices in the nlist array */
#define X_AVENRUN       0
#define X_SYSINFO       1
#define X_VMKER         2
#define X_V             3


static struct nlist nlst[] = {
  { "avenrun", 0, 0, 0, 0, 0 },
  { "sysinfo", 0, 0, 0, 0, 0 },
  { "vmker",   0, 0, 0, 0, 0 },
  { "v",    0, 0, 0, 0, 0 },
  {  NULL, 0, 0, 0, 0, 0 }
};


/* offsets in kernel */
static unsigned long avenrun_offset;
static unsigned long sysinfo_offset;
static unsigned long vmker_offset;
static unsigned long proc_offset;
static unsigned long v_offset;

struct proc *p_proc;            /* a copy of the process table */
struct procentry64 *p_info;

struct var v_info;              /* to determine nprocs */
int nprocs;                     /* maximum nr of procs in proctab */
int ncpus;                      /* nr of cpus installed */
int ptsize;                     /* size of process table in bytes */


void init() {

  if ((kmem = open(KMEM, O_RDONLY)) == -1) {
    perror(KMEM);
    return ;
  }


  if (knlist(nlst, 1, sizeof(struct nlist)) == -1) {

    perror("knlist, proc entry not found");
    return;
  }


  avenrun_offset = nlst[X_AVENRUN].n_value;
  sysinfo_offset = nlst[X_SYSINFO].n_value;
  vmker_offset   = nlst[X_VMKER].n_value;
  v_offset       = nlst[X_V].n_value;

  getkval(v_offset, (caddr_t)&v_info, sizeof v_info, "v");

  ncpus = v_info.v_ncpus;

  nprocs = 20480;

  ptsize = nprocs * sizeof (struct proc);
  p_info = (struct procentry64 *)malloc(nprocs * sizeof (struct procentry64));


  if (!p_info) {
    zbx_error("not enough memory.");
    return;
  }

}


long get_num_procs()
{
  struct procsinfo ps[8192];
  pid_t index = 0;
  int nprocs;
  int i;
  char state;

  if ((nprocs = getprocs(&ps, sizeof(struct procsinfo), NULL, 0, &index, 8192)) > 0) {
    return nprocs;
  } else {
    return -1;
  }

}


long get_running_procs()
{

  struct procentry64 *pp;
  int running = 0, i, nproc;
  pid_t procsindex = 0;
  int ptsize_util;
  struct proc *p;

  init();

  if ((nproc = getprocs(p_info, sizeof (struct procsinfo), NULL, 0,
                     &procsindex, nprocs)) > 0) {

    for (pp=p_info, i=0; i < nproc;pp++, i++) {

      if (pp->pi_state == SACTIVE && pp->pi_cpu != 0)
	running++;

    }

    return running;

  } else {
    return -1;
  }

}

double get_loadavg(int data_type) {

  perfstat_cpu_total_t ub;

  if (perfstat_cpu_total ((perfstat_id_t*)NULL, &ub, sizeof(perfstat_cpu_total_t),1) >= 0) {

    switch(data_type) {

    case CPU_LOADAVG:
      return (double) ub.loadavg[0] / 65535;
	break;
    case CPU_LOADAVG5:
      return (double) ub.loadavg[1] / 65535;
	break;
    case CPU_LOADAVG15:
      return (double) ub.loadavg[2] / 65535;
      break;
    }

  } else {
    return -1;
  }

}

u_longlong_t get_disk_io(int data_type) {

  perfstat_disk_total_t ub;

  if (perfstat_disk_total ((perfstat_id_t*)NULL, &ub, sizeof(perfstat_disk_total_t),1) >= 0) {

    switch(data_type) {

    case DISK_IO_RBLKS:
      return ub.rblks;
      break;
    case DISK_IO_WBLKS:
      return ub.wblks;
      break;
    case DISK_IO_TOTAL:
      return ub.rblks + ub.wblks;
      break;
    }

  } else {
    return -1;
  }

}



u_longlong_t get_disk_stat(char diskname[32], int data_type)
{

  perfstat_id_t name;

  perfstat_disk_t *ub;

  int ndisk,i;

  ub = malloc(sizeof(perfstat_disk_t)*1);

  strcpy(name.name,diskname);

  if (perfstat_disk (&name,ub,sizeof(perfstat_disk_t),1) >= 0) {

    switch(data_type) {

    case DISK_IO_RBLKS:
      return ub[0].rblks;
      break;
    case DISK_IO_WBLKS:
      return ub[0].wblks;
      break;
    }

  } else {
    return -1;
  }

}


int getkval(unsigned long offset, caddr_t ptr, int size, char *refstr)
{
  int upper_2gb = 0;


  if (offset > 1<<31) {
    upper_2gb = 1;
    offset &= 0x7fffffff;
  }

  if (lseek(kmem, offset, SEEK_SET) != offset) {
    return -1;
  }

  if (readx(kmem, ptr, size, upper_2gb) != size) {
    if (*refstr == '!')
      return 0;
    else {
      return -1;
    }
  }

  return 1 ;
}


unsigned int get_uptime() {

  struct tms tbuf;
  time_t uptime;
  time_t timeofday;

  uptime = (times(&tbuf) / HZ);

  return (unsigned int) uptime;
}
