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

package services

import (
	"encoding/json"
	"errors"
	"strings"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/mgr"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	startupTypeAuto = iota
	startupTypeAutoDelayed
	startupTypeManual
	startupTypeDisabled
	startupTypeUnknown
	startupTypeTrigger
)

const (
	ZBX_NON_EXISTING_SRV = 255
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

type serviceDiscovery struct {
	Name           string `json:"{#SERVICE.NAME}"`
	DisplayName    string `json:"{#SERVICE.DISPLAYNAME}"`
	Description    string `json:"{#SERVICE.DESCRIPTION}"`
	State          int    `json:"{#SERVICE.STATE}"`
	StateName      string `json:"{#SERVICE.STATENAME}"`
	Path           string `json:"{#SERVICE.PATH}"`
	User           string `json:"{#SERVICE.USER}"`
	StartupTrigger int    `json:"{#SERVICE.STARTUPTRIGGER}"`
	Startup        int    `json:"{#SERVICE.STARTUP}"`
	StartupName    string `json:"{#SERVICE.STARTUPNAME}"`
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "WindowsServices",
		"service.discovery", "List of Windows services for low-level discovery.",
		"service.info", "Information about a service.",
		"services", "Filtered list of Windows sercices.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func startupName(startup int) string {
	switch startup {
	case startupTypeAuto:
		return "automatic"
	case startupTypeAutoDelayed:
		return "automatic delayed"
	case startupTypeManual:
		return "manual"
	case startupTypeDisabled:
		return "disabled"
	default:
		return "unknown"
	}
}

func startupType(service *mgr.Service, config *mgr.Config) (stype, strigger int) {
	n := uint32(1024)
	for {
		b := make([]byte, n)
		err := windows.QueryServiceConfig2(service.Handle, windows.SERVICE_CONFIG_TRIGGER_INFO, &b[0], n, &n)
		if err == nil {
			if *(*uint32)(unsafe.Pointer(&b[0])) > 0 {
				strigger = 1
			}
			break
		}
		if err.(syscall.Errno) != syscall.ERROR_INSUFFICIENT_BUFFER {
			break
		}
		if n <= uint32(len(b)) {
			break
		}
	}
	switch config.StartType {
	case mgr.StartAutomatic:
		if config.DelayedAutoStart {
			stype = startupTypeAutoDelayed
		} else {
			stype = startupTypeAuto
		}
	case mgr.StartManual:
		stype = startupTypeManual
	default:
		stype = startupTypeUnknown
	}
	return
}

func serviceState(state svc.State) (code int, name string) {
	switch state {
	case svc.Running:
		return 0, "running"
	case svc.Paused:
		return 1, "paused"
	case svc.StartPending:
		return 2, "start pending"
	case svc.PausePending:
		return 3, "pause pending"
	case svc.ContinuePending:
		return 4, "continue pending"
	case svc.StopPending:
		return 5, "stop pending"
	case svc.Stopped:
		return 6, "stopped"
	default:
		return 8, "unknown"
	}
}

// openScManager function is replacement for mgr.Connect() to open service manager with lower access rights.
func openScManager() (m *mgr.Mgr, err error) {
	h, err := windows.OpenSCManager(nil, nil, windows.GENERIC_READ)
	if err != nil {
		return nil, err
	}
	return &mgr.Mgr{Handle: h}, nil
}

// openService is replacement for Mgr.OpenService() to open service with lower access rights.
func openService(m *mgr.Mgr, name string) (s *mgr.Service, err error) {
	wname, err := syscall.UTF16PtrFromString(name)
	if err != nil {
		return
	}
	h, err := windows.OpenService(m.Handle, wname, uint32(windows.SERVICE_QUERY_CONFIG|windows.SERVICE_QUERY_STATUS))
	if err != nil {
		return
	}
	return &mgr.Service{Name: name, Handle: h}, nil
}

// openServiceEx opens service by its name or display name
func openServiceEx(m *mgr.Mgr, name string) (s *mgr.Service, err error) {
	s, err = openService(m, name)
	if err == nil {
		return
	}

	wname, err := win32.GetServiceKeyName(syscall.Handle(m.Handle), name)
	if err != nil {
		return
	}
	return openService(m, wname)
}

func (p *Plugin) exportServiceDiscovery(params []string) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters.")
	}
	m, err := openScManager()
	if err != nil {
		return nil, err
	}
	defer m.Disconnect()

	names, err := m.ListServices()
	if err != nil {
		return
	}

	services := make([]serviceDiscovery, 0)
	for _, name := range names {
		service, serr := openService(m, name)
		if serr != nil {
			p.Debugf(`cannot open service "%s": %s`, name, serr)
			continue
		}
		defer service.Close()

		cfg, err := service.Config()
		if err != nil {
			p.Debugf(`cannot obtain service "%s" configuration: %s`, name, err)
			continue
		}
		status, err := service.Query()
		if err != nil {
			p.Debugf(`cannot obtain service "%s" status: %s`, name, err)
			continue
		}

		state, stateName := serviceState(status.State)

		sd := serviceDiscovery{
			Name:        name,
			DisplayName: cfg.DisplayName,
			Description: cfg.Description,
			State:       state,
			StateName:   stateName,
			Path:        cfg.BinaryPathName,
			User:        cfg.ServiceStartName,
		}

		if cfg.StartType == mgr.StartDisabled {
			sd.StartupTrigger = 0
			sd.Startup = startupTypeDisabled
		} else {
			sd.Startup, sd.StartupTrigger = startupType(service, &cfg)
		}
		sd.StartupName = startupName(sd.Startup)

		services = append(services, sd)
	}

	b, err := json.Marshal(&services)
	if err != nil {
		return
	}
	return string(b), nil
}

const (
	infoParamState = iota
	infoParamDisplayName
	infoParamPath
	infoParamUser
	infoParamStartup
	infoParamDescription
)

func (p *Plugin) exportServiceInfo(params []string) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	param := infoParamState
	if len(params) == 2 {
		switch params[1] {
		case "state", "":
			param = infoParamState
		case "displayname":
			param = infoParamDisplayName
		case "path":
			param = infoParamPath
		case "user":
			param = infoParamUser
		case "startup":
			param = infoParamStartup
		case "description":
			param = infoParamDescription
		default:
			return nil, errors.New("Invalid second parameter.")
		}
	}

	m, err := openScManager()
	if err != nil {
		return
	}
	defer m.Disconnect()

	service, err := openServiceEx(m, params[0])
	if err != nil {
		if err.(syscall.Errno) == windows.ERROR_SERVICE_DOES_NOT_EXIST {
			return ZBX_NON_EXISTING_SRV, nil
		}
		return
	}
	defer service.Close()

	if param == infoParamState {
		status, err := service.Query()
		if err != nil {
			return nil, err
		}
		state, _ := serviceState(status.State)
		return state, nil
	} else {
		cfg, err := service.Config()
		if err != nil {
			return nil, err
		}
		switch param {
		case infoParamDisplayName:
			return cfg.DisplayName, nil
		case infoParamPath:
			return cfg.BinaryPathName, nil
		case infoParamUser:
			return cfg.ServiceStartName, nil
		case infoParamStartup:
			if cfg.StartType == mgr.StartDisabled {
				return startupTypeDisabled, nil
			} else {
				startup, trigger := startupType(service, &cfg)
				if trigger != 0 {
					startup += startupTypeTrigger
				}
				return startup, nil
			}
		case infoParamDescription:
			return cfg.Description, nil
		}
	}
	return
}

const (
	stateFlagStopped = 1 << iota
	stateFlagStartPending
	stateFlagStopPending
	stateFlagRunning
	stateFlagContinuePending
	stateFlagPausePending
	stateFlagPaused
	stateFlagStarted = stateFlagStartPending | stateFlagStopPending | stateFlagRunning | stateFlagContinuePending |
		stateFlagPausePending | stateFlagPaused
	stateFlagAll = stateFlagStarted | stateFlagStopped
)

func (p *Plugin) appendServiceName(
	m *mgr.Mgr,
	services *[]string,
	name string,
	excludeFilter map[string]bool,
	stateFilter int,
	typeFilter *uint32,
) {
	if len(excludeFilter) != 0 {
		if _, ok := excludeFilter[name]; ok {
			return
		}
	}

	if typeFilter != nil || stateFilter != stateFlagAll {
		service, err := openService(m, name)
		if err != nil {
			p.Debugf(`cannot open service "%s": %s`, name, err)
			return
		}
		defer service.Close()

		if typeFilter != nil {
			cfg, err := service.Config()
			if err != nil {
				p.Debugf(`cannot obtain service "%s" configuration: %s`, name, err)
				return
			}
			if cfg.StartType != *typeFilter {
				return
			}
		}
		if stateFilter != stateFlagAll {
			status, err := service.Query()
			if err != nil {
				p.Debugf(`cannot obtain service "%s" status: %s`, name, err)
				return
			}
			switch status.State {
			case svc.Running:
				if stateFilter&stateFlagRunning == 0 {
					return
				}
			case svc.Paused:
				if stateFilter&stateFlagPaused == 0 {
					return
				}
			case svc.StartPending:
				if stateFilter&stateFlagStartPending == 0 {
					return
				}
			case svc.PausePending:
				if stateFilter&stateFlagPausePending == 0 {
					return
				}
			case svc.ContinuePending:
				if stateFilter&stateFlagContinuePending == 0 {
					return
				}
			case svc.StopPending:
				if stateFilter&stateFlagStopPending == 0 {
					return
				}
			case svc.Stopped:
				if stateFilter&stateFlagStopped == 0 {
					return
				}
			}
		}
	}

	*services = append(*services, name)
}

func (p *Plugin) exportServices(params []string) (result interface{}, err error) {
	if len(params) > 3 {
		return nil, errors.New("Too many parameters.")
	}
	var typeFilter *uint32
	if len(params) > 0 && params[0] != "all" && params[0] != "" {
		var tmp uint32
		switch params[0] {
		case "automatic":
			tmp = mgr.StartAutomatic
		case "manual":
			tmp = mgr.StartManual
		case "disabled":
			tmp = mgr.StartDisabled
		default:
			return nil, errors.New("Invalid first parameter.")
		}
		typeFilter = &tmp
	}

	stateFilter := stateFlagAll
	if len(params) > 1 && params[1] != "all" && params[1] != "" {
		switch params[1] {
		case "stopped":
			stateFilter = stateFlagStopped
		case "started":
			stateFilter = stateFlagStarted
		case "start_pending":
			stateFilter = stateFlagStartPending
		case "stop_pending":
			stateFilter = stateFlagStopPending
		case "running":
			stateFilter = stateFlagRunning
		case "continue_pending":
			stateFilter = stateFlagContinuePending
		case "pause_pending":
			stateFilter = stateFlagPausePending
		case "paused":
			stateFilter = stateFlagPaused
		default:
			return nil, errors.New("Invalid second parameter.")
		}
	}

	excludeFilter := make(map[string]bool)
	if len(params) > 2 {
		for _, name := range strings.Split(params[2], ",") {
			excludeFilter[strings.Trim(name, " ")] = true
		}
	}

	m, err := openScManager()
	if err != nil {
		return nil, err
	}
	defer m.Disconnect()

	names, err := m.ListServices()
	if err != nil {
		return
	}

	services := make([]string, 0)
	for _, name := range names {
		p.appendServiceName(m, &services, name, excludeFilter, stateFilter, typeFilter)
	}

	if len(services) == 0 {
		return "0", nil
	}
	return strings.Join(services, "\n") + "\n", nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "service.discovery":
		return p.exportServiceDiscovery(params)
	case "service.info":
		return p.exportServiceInfo(params)
	case "services":
		return p.exportServices(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
