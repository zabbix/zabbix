/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package main

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"time"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/eventlog"
	"golang.org/x/sys/windows/svc/mgr"
	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/keyaccess"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxflag"
)

const usageMessageExampleConfPath = `C:\zabbix\zabbix_agent2.conf`

const (
	startTypeAutomatic = "automatic"
	startTypeDelayed   = "delayed"
	startTypeManual    = "manual"
	startTypeDisabled  = "disabled"
)

var (
	serviceName = "Zabbix Agent 2"

	svcInstallFlag       bool
	svcUninstallFlag     bool
	svcStartFlag         bool
	svcStopFlag          bool
	svcMultipleAgentFlag bool
	svcStartType         string

	winServiceRun bool

	eLog *eventlog.Log

	winServiceWg sync.WaitGroup
	fatalStopWg  sync.WaitGroup

	fatalStopChan chan bool
	startChan     chan bool
	stopChan      = make(chan bool)
)

func osDependentFlags() zbxflag.Flags {
	return zbxflag.Flags{
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:      "multiple-agents",
				Shorthand: "m",
				Description: "For -i -d -s -x functions service name will " +
					"include Hostname parameter specified in configuration file",
			},
			Default: false,
			Dest:    &svcMultipleAgentFlag,
		},

		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "install",
				Shorthand:   "i",
				Description: "Install Zabbix agent 2 as service",
			},
			Default: false,
			Dest:    &svcInstallFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "uninstall",
				Shorthand:   "d",
				Description: "Uninstall Zabbix agent 2 from service",
			},
			Default: false,
			Dest:    &svcUninstallFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "start",
				Shorthand:   "s",
				Description: "Start Zabbix agent 2 service",
			},
			Default: false,
			Dest:    &svcStartFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "stop",
				Shorthand:   "x",
				Description: "Stop Zabbix agent 2 service",
			},
			Default: false,
			Dest:    &svcStopFlag,
		},
		&zbxflag.StringFlag{
			Flag: zbxflag.Flag{
				Name:      "startup-type",
				Shorthand: "S",
				Description: fmt.Sprintf(
					"Set startup type of the Zabbix Windows agent service to be installed."+
						" Allowed values: %s (default), %s, %s, %s",
					startTypeAutomatic,
					startTypeDelayed,
					startTypeManual,
					startTypeDisabled,
				),
			},
			Default: "",
			Dest:    &svcStartType,
		},
	}
}

func isWinLauncher() bool {
	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag || svcMultipleAgentFlag ||
		svcStartType != "" {
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

// eventLogErr reports err to Windows event log if agent is launched on
// windows.
// On success returns parameter err.
// On failure returns error that occurred during reporting to event log.
func eventLogErr(err error) error {
	if isWinLauncher() {
		elErr := eLog.Error(3, err.Error())
		if elErr != nil {
			return errs.Wrapf(
				elErr, "failed to report error (%s) to event log", err.Error(),
			)
		}
	}

	return err
}

func validateMultipleAgentFlag() bool {
	if svcMultipleAgentFlag &&
		!(svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag || svcStartType != "") &&
		!winServiceRun {
		return false
	}

	return true
}

func validateExclusiveFlags(args *Arguments) error {
	var (
		exclusiveFlagsSet = []bool{
			svcInstallFlag,
			svcUninstallFlag,
			svcStartFlag,
			svcStopFlag,
			args.print,
			args.test != "",
			args.runtimeCommand != "",
			args.testConfig,
		}
		count int
	)

	if args.verbose && !(args.test != "" || args.print) {
		return errors.New("option -v, --verbose can only be specified with -t or -p")
	}

	for _, exclusiveFlagSet := range exclusiveFlagsSet {
		if exclusiveFlagSet {
			count++
		}
		if count >= 2 { //nolint:gomnd
			return errors.New("mutually exclusive options used, see -h, --help for more information")
		}
	}

	if !validateMultipleAgentFlag() {
		return errors.New(
			"option -m, --multiple-agents can only be used with one of the service options, see -h, --help for more information",
		)
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
			return fmt.Errorf(
				"cannot parse the \"Hostname\" parameter: %s",
				err,
			)
		}
		agent.FirstHostname = hostnames[0]
		serviceName = fmt.Sprintf("%s [%s]", serviceName, agent.FirstHostname)
	}

	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag || svcStartType != "" {
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
			return fmt.Errorf(
				"failed to install %s as service: %s",
				serviceName,
				err,
			)
		}
		msg = fmt.Sprintf("'%s' installed successfully", serviceName)
	case svcUninstallFlag:
		if err := svcUninstall(); err != nil {
			return fmt.Errorf(
				"failed to uninstall %s as service: %s",
				serviceName,
				err,
			)
		}
		msg = fmt.Sprintf("'%s' uninstalled successfully", serviceName)
	case svcStartFlag:
		if err := svcStart(confPath); err != nil {
			return fmt.Errorf(
				"failed to start %s service: %s",
				serviceName,
				err,
			)
		}
		msg = fmt.Sprintf("'%s' started successfully", serviceName)
	case svcStopFlag:
		if err := svcStop(); err != nil {
			return fmt.Errorf("failed to stop %s service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' stopped successfully", serviceName)
	case svcStartType != "":
		if err := svcStartupTypeSet(); err != nil {
			return errs.Wrapf(err, "failed to set service '%s' startup type", serviceName)
		}
		msg = fmt.Sprintf("service '%s' startup type configured successfully", serviceName)
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

func svcStartTypeFlagParse() (uint32, bool, error) {
	var startType uint32
	var delayedAutoStart bool
	var err error

	switch svcStartType {
	case "":
		startType = mgr.StartAutomatic
	case startTypeAutomatic:
		startType = mgr.StartAutomatic
	case startTypeDelayed:
		delayedAutoStart = true
		startType = mgr.StartAutomatic
	case startTypeManual:
		startType = mgr.StartManual
	case startTypeDisabled:
		startType = mgr.StartDisabled
	default:
		err = fmt.Errorf("unknown service start type: '%s'", svcStartType)
	}

	return startType, delayedAutoStart, err
}

func svcInstall(conf string) error {
	exepath, err := getAgentPath()
	if err != nil {
		return fmt.Errorf("failed to get Zabbix Agent 2 executable path: %s", err.Error())
	}

	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf(
			"failed to connect to service manager: %s",
			err.Error(),
		)
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err == nil {
		s.Close()
		return errors.New("service already exists")
	}

	startType, delayedAutoStart, err := svcStartTypeFlagParse()
	if err != nil {
		return errs.Wrap(err, "failed to get new startup type")
	}

	s, err = m.CreateService(
		serviceName,
		exepath,
		mgr.Config{
			StartType:        startType,
			DisplayName:      serviceName,
			Description:      "Provides system monitoring",
			BinaryPathName:   fmt.Sprintf("%s -c %s -f=false", exepath, conf),
			DelayedAutoStart: delayedAutoStart,
		},
		"-c", conf,
		"-f=false",
	)
	if err != nil {
		return fmt.Errorf("failed to create service: %s", err.Error())
	}
	defer s.Close()

	if err = eventlog.InstallAsEventCreate(serviceName, eventlog.Error|eventlog.Warning|eventlog.Info); err != nil {
		err = fmt.Errorf(
			"failed to report service into the event log: %s",
			err.Error(),
		)
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
		return fmt.Errorf(
			"failed to connect to service manager: %s",
			err.Error(),
		)
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
		return fmt.Errorf(
			"failed to remove service from the event log: %s",
			err.Error(),
		)
	}

	return nil
}

func svcStart(conf string) error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf(
			"failed to connect to service manager: %s",
			err.Error(),
		)
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
		return fmt.Errorf(
			"failed to connect to service manager: %s",
			err.Error(),
		)
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()

	status, err := s.Control(svc.Stop)
	if err != nil {
		return fmt.Errorf(
			"failed to send stop request to service: %s",
			err.Error(),
		)
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

func svcStartupTypeSet() error {
	m, err := mgr.Connect()
	if err != nil {
		return errs.Wrap(err, "failed to connect to service manager")
	}

	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return errs.Wrap(err, "failed to open service")
	}

	defer s.Close()

	c, err := s.Config()
	if err != nil {
		return errs.Wrap(err, "failed to retrieve service config")
	}

	c.StartType, c.DelayedAutoStart, err = svcStartTypeFlagParse()
	if err != nil {
		return errs.Wrap(err, "failed to get new startup type")
	}

	err = s.UpdateConfig(c)
	if err != nil {
		return errs.Wrap(err, "failed to update service config")
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
		panic(errs.Wrap(err, "use foreground option to run Zabbix agent as console application"))
	}
}

type winService struct{}

func (ws *winService) Execute(
	args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status,
) (ssec bool, errno uint32) {
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
