/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package main

import (
	"errors"
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"time"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/eventlog"
	"golang.org/x/sys/windows/svc/mgr"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/keyaccess"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/log"
)

var (
	serviceName = "Zabbix Agent 2"

	svcInstallFlag       bool
	svcUninstallFlag     bool
	svcStartFlag         bool
	svcStopFlag          bool
	svcMultipleAgentFlag bool

	winServiceRun bool

	eLog *eventlog.Log

	winServiceWg sync.WaitGroup
	fatalStopWg  sync.WaitGroup

	fatalStopChan chan bool
	startChan     chan bool
)

func loadOSDependentFlags() {
	const (
		svcInstallDefault     = false
		svcInstallDescription = "Install Zabbix agent 2 as service"
	)
	flag.BoolVar(&svcInstallFlag, "install", svcInstallDefault, svcInstallDescription)
	flag.BoolVar(&svcInstallFlag, "i", svcInstallDefault, svcInstallDescription+" (shorthand)")

	const (
		svcUninstallDefault     = false
		svcUninstallDescription = "Uninstall Zabbix agent 2 from service"
	)
	flag.BoolVar(&svcUninstallFlag, "uninstall", svcUninstallDefault, svcUninstallDescription)
	flag.BoolVar(&svcUninstallFlag, "d", svcUninstallDefault, svcUninstallDescription+" (shorthand)")

	const (
		svcStartDefault     = false
		svcStartDescription = "Start Zabbix agent 2 service"
	)
	flag.BoolVar(&svcStartFlag, "start", svcStartDefault, svcStartDescription)
	flag.BoolVar(&svcStartFlag, "s", svcStartDefault, svcStartDescription+" (shorthand)")

	const (
		svcStopDefault     = false
		svcStopDescription = "Stop Zabbix agent 2 service"
	)
	flag.BoolVar(&svcStopFlag, "stop", svcStopDefault, svcStopDescription)
	flag.BoolVar(&svcStopFlag, "x", svcStopDefault, svcStopDescription+" (shorthand)")

	const (
		svcMultipleDefault     = false
		svcMultipleDescription = "For -i -d -s -x functions service name will\ninclude Hostname parameter specified in\nconfiguration file"
	)
	flag.BoolVar(&svcMultipleAgentFlag, "multiple-agents", svcMultipleDefault, svcMultipleDescription)
	flag.BoolVar(&svcMultipleAgentFlag, "m", svcMultipleDefault, svcMultipleDescription+" (shorthand)")
}

func isWinLauncher() bool {
	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag || svcMultipleAgentFlag {
		return true
	}
	return false
}

func setServiceRun(foreground bool) {
	winServiceRun = !foreground
}

func fatalCloseOSItems() {
	if winServiceRun {
		sendFatalStopSig()
	}
	closeEventLog()
}

func openEventLog() (err error) {
	if isWinLauncher() {
		eLog, err = eventlog.Open(serviceName)
	}
	return
}

func sendFatalStopSig() {
	fatalStopWg.Add(1)
	select {
	case fatalStopChan <- true:
		fatalStopWg.Wait()
	default:
		fatalStopWg.Done()
	}
}

func closeEventLog() {
	if eLog != nil {
		eLog.Close()
	}

	return
}

func eventLogInfo(msg string) (err error) {
	if isWinLauncher() {
		return eLog.Info(1, msg)
	}
	return nil
}

func eventLogErr(err error) error {
	if isWinLauncher() {
		return eLog.Error(3, err.Error())
	}
	return nil
}

func validateExclusiveFlags() error {
	defaultFlagSet := argTest || argPrint || argVerbose
	serviceFlagsSet := []bool{svcInstallFlag, svcUninstallFlag, svcStartFlag, svcStopFlag}
	var count int
	for _, serserviceFlagSet := range serviceFlagsSet {
		if serserviceFlagSet {
			count++
		}
		if count >= 2 || (serserviceFlagSet && defaultFlagSet) {
			return errors.New("mutually exclusive options used, use help '-help'('-h'), for additional information")
		}
	}

	if svcMultipleAgentFlag && count == 0 && !winServiceRun {
		return errors.New("multiple agents '-multiple-agents'('-m'), flag has to be used with another windows service flag, use help '-help'('-h'), for additional information")
	}

	return nil
}

func isInteractiveSession() (bool, error) {
	return svc.IsAnInteractiveSession()
}

func setHostname() error {
	if err := log.Open(log.Console, log.None, "", 0); err != nil {
		return fmt.Errorf("cannot initialize logger: %s", err)
	}

	if err := keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
		return fmt.Errorf("failed to load key access rules: %s", err)
	}

	var m *scheduler.Manager
	var err error
	if m, err = scheduler.NewManager(&agent.Options); err != nil {
		return fmt.Errorf("cannot create scheduling manager: %s", err)
	}
	m.Start()
	if err = configUpdateItemParameters(m, &agent.Options); err != nil {
		return fmt.Errorf("cannot process configuration: %s", err)
	}
	m.Stop()
	monitor.Wait(monitor.Scheduler)
	return nil
}

func handleWindowsService(confPath string) error {
	if svcMultipleAgentFlag {
		if len(agent.Options.Hostname) == 0 {
			if err := setHostname(); err != nil {
				return err
			}
		}
		hostnames, err := agent.ValidateHostnames(agent.Options.Hostname)
		if err != nil {
			return fmt.Errorf("cannot parse the \"Hostname\" parameter: %s", err)
		}
		agent.FirstHostname = hostnames[0]
		serviceName = fmt.Sprintf("%s [%s]", serviceName, agent.FirstHostname)
	}

	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag {
		absPath, err := filepath.Abs(confPath)
		if err != nil {
			return err
		}
		if err := resolveWindowsService(absPath); err != nil {
			return err
		}
		closeEventLog()
		os.Exit(0)
	}

	if winServiceRun {
		fatalStopChan = make(chan bool, 1)
		startChan = make(chan bool)
		go runService()
	}

	return nil
}

func resolveWindowsService(confPath string) error {
	var msg string
	switch true {
	case svcInstallFlag:
		if err := svcInstall(confPath); err != nil {
			return fmt.Errorf("failed to install %s as service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' installed successfully", serviceName)
	case svcUninstallFlag:
		if err := svcUninstall(); err != nil {
			return fmt.Errorf("failed to uninstall %s as service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' uninstalled successfully", serviceName)
	case svcStartFlag:
		if err := svcStart(confPath); err != nil {
			return fmt.Errorf("failed to start %s service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' started successfully", serviceName)
	case svcStopFlag:
		if err := svcStop(); err != nil {
			return fmt.Errorf("failed to stop %s service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' stopped successfully", serviceName)
	}

	msg = fmt.Sprintf("zabbix_agent2 [%d]: %s\n", os.Getpid(), msg)
	fmt.Fprintf(os.Stdout, msg)
	if err := eventLogInfo(msg); err != nil {
		return fmt.Errorf("failed to log to event log: %s", err)
	}
	return nil
}

func getAgentPath() (p string, err error) {
	if p, err = filepath.Abs(os.Args[0]); err != nil {
		return
	}

	var i os.FileInfo
	if i, err = os.Stat(p); err != nil {
		if filepath.Ext(p) == "" {
			p += ".exe"
			i, err = os.Stat(p)
			if err != nil {
				return
			}
		}
	}

	if i.Mode().IsDir() {
		return p, fmt.Errorf("incorrect path to executable '%s'", p)

	}

	return
}

func svcInstall(conf string) error {
	exepath, err := getAgentPath()
	if err != nil {
		return fmt.Errorf("failed to get Zabbix Agent 2 exeutable path: %s", err.Error())
	}

	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err == nil {
		s.Close()
		return errors.New("service already exists")
	}

	s, err = m.CreateService(serviceName, exepath, mgr.Config{StartType: mgr.StartAutomatic, DisplayName: serviceName,
		Description: "Provides system monitoring", BinaryPathName: fmt.Sprintf("%s -c %s -f=false", exepath, conf)}, "-c", conf, "-f=false")
	if err != nil {
		return fmt.Errorf("failed to create service: %s", err.Error())
	}
	defer s.Close()

	if err = eventlog.InstallAsEventCreate(serviceName, eventlog.Error|eventlog.Warning|eventlog.Info); err != nil {
		err = fmt.Errorf("failed to report service into the event log: %s", err.Error())
		derr := s.Delete()
		if derr != nil {
			return fmt.Errorf("%s and %s", err.Error(), derr.Error())
		}

		return err
	}
	return nil
}

func svcUninstall() error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()

	if err = s.Delete(); err != nil {
		return fmt.Errorf("failed to delete service: %s", err.Error())
	}

	if err = eventlog.Remove(serviceName); err != nil {
		return fmt.Errorf("failed to remove service from the event log: %s", err.Error())
	}

	return nil
}

func svcStart(conf string) error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()

	if err = s.Start("-c", conf, "-f=false"); err != nil {
		return fmt.Errorf("failed to start service: %s", err.Error())
	}

	return nil
}

func svcStop() error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()

	status, err := s.Control(svc.Stop)
	if err != nil {
		return fmt.Errorf("failed to send stop request to service: %s", err.Error())
	}

	timeout := time.Now().Add(10 * time.Second)
	for status.State != svc.Stopped {
		if timeout.Before(time.Now()) {
			return fmt.Errorf("failed to stop '%s' service", serviceName)
		}
		time.Sleep(300 * time.Millisecond)
		if status, err = s.Query(); err != nil {
			return fmt.Errorf("failed to get service status: %s", err.Error())
		}
	}

	return nil
}

func confirmService() {
	if winServiceRun {
		startChan <- true
	}
}

func waitServiceClose() {
	if winServiceRun {
		winServiceWg.Done()
		<-closeChan
	}
}

func sendServiceStop() {
	if winServiceRun {
		winServiceWg.Add(1)
		stopChan <- true
	}
}

func runService() {
	if err := svc.Run(serviceName, &winService{}); err != nil {
		fatalExit("use foreground option to run Zabbix agent as console application", err)
		return
	}
}

type winService struct{}

func (ws *winService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	changes <- svc.Status{State: svc.StartPending}
	select {
	case <-startChan:
		changes <- svc.Status{State: svc.Running, Accepts: svc.AcceptStop | svc.AcceptShutdown}
	case <-fatalStopChan:
		changes <- svc.Status{State: svc.Stopped}
		// This is needed to make sure that windows will receive the status stopped before zabbix agent 2 process ends
		<-time.After(time.Millisecond * 500)
		fatalStopWg.Done()
		return
	}

loop:
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Stop, svc.Shutdown:
				changes <- svc.Status{State: svc.StopPending}
				winServiceWg.Add(1)
				closeChan <- true
				winServiceWg.Wait()
				changes <- svc.Status{State: svc.Stopped}
				// This is needed to make sure that windows will receive the status stopped before zabbix agent 2 process ends
				<-time.After(time.Millisecond * 500)
				closeChan <- true
				break loop
			default:
				log.Debugf("unsupported windows service command '%s' received", getCmdName(c.Cmd))
			}
		case <-stopChan:
			changes <- svc.Status{State: svc.StopPending}
			winServiceWg.Wait()
			changes <- svc.Status{State: svc.Stopped}
			// This is needed to make sure that windows will receive the status stopped before zabbix agent 2 process ends
			<-time.After(time.Millisecond * 500)
			closeChan <- true
			break loop
		}
	}

	return
}

func getCmdName(cmd svc.Cmd) string {
	switch cmd {
	case svc.Stop:
		return "Stop"
	case svc.Pause:
		return "Pause"
	case svc.Continue:
		return "Continue"
	case svc.Interrogate:
		return "Interrogate"
	case svc.Shutdown:
		return "Shutdown"
	case svc.ParamChange:
		return "ParamChange"
	case svc.NetBindAdd:
		return "NetBindAdd"
	case svc.NetBindRemove:
		return "NetBindRemove"
	case svc.NetBindEnable:
		return "NetBindEnable"
	case svc.NetBindDisable:
		return "NetBindDisable"
	case svc.DeviceEvent:
		return "DeviceEvent"
	case svc.HardwareProfileChange:
		return "HardwareProfileChange"
	case svc.PowerEvent:
		return "PowerEvent"
	case svc.SessionChange:
		return "SessionChange"
	default:
		return "unknown"
	}
}
