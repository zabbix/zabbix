package systemd

import "fmt"

type get struct {
	InvocationID []uint8 `json:"InvocationID,omitempty"`

	SuccessActionExitStatus int32 `json:"SuccessActionExitStatus,omitempty"`
	FailureActionExitStatus int32 `json:"FailureActionExitStatus,omitempty"`
	KillSignal              int32 `json:"KillSignal,omitempty"`
	WatchdogSignal          int32 `json:"WatchdogSignal,omitempty"`
	FinalKillSignal         int32 `json:"FinalKillSignal,omitempty"`
	OOMScoreAdjust          int32 `json:"OOMScoreAdjust,omitempty"`

	StartLimitBurst        uint32 `json:"StartLimitBurst,omitempty,omitempty"`
	Job                    uint32 `json:"Job,omitempty"`
	FileDescriptorStoreMax uint32 `json:"FileDescriptorStoreMax,omitempty"`

	ActiveExitTimestampMonotonic uint64 `json:"ActiveExitTimestampMonotonic,omitempty"`
	JobRunningTimeoutUSec        uint64 `json:"JobRunningTimeoutUSec,omitempty"`
	ActiveExitTimestamp          uint64 `json:"ActiveExitTimestamp,omitempty"`
	AmbientCapabilities          uint64 `json:"AmbientCapabilities,omitempty"`
	CapabilityBoundingSet        uint64 `json:"CapabilityBoundingSet,omitempty"`

	StopWhenUnneeded       bool `json:"StopWhenUnneeded,omitempty"`
	CanReload              bool `json:"CanReload,omitempty"`
	DefaultDependencies    bool `json:"DefaultDependencies,omitempty"`
	RefuseManualStop       bool `json:"RefuseManualStop,omitempty"`
	Perpetual              bool `json:"Perpetual,omitempty"`
	IgnoreOnIsolate        bool `json:"IgnoreOnIsolate,omitempty"`
	RefuseManualStart      bool `json:"RefuseManualStart,omitempty"`
	AllowIsolate           bool `json:"AllowIsolate,omitempty"`
	DynamicUser            bool `json:"DynamicUser,omitempty"`
	RootDirectoryStartOnly bool `json:"RootDirectoryStartOnly,omitempty"`
	GuessMainPID           bool `json:"GuessMainPID,omitempty"`
	PrivateTmp             bool `json:"PrivateTmp,omitempty"`
	Delegate               bool `json:"Delegate,omitempty"`
	RemainAfterExit        bool `json:"RemainAfterExit,omitempty"`
	PrivateDevices         bool `json:"PrivateDevices,omitempty"`
	NonBlocking            bool `json:"NonBlocking,omitempty"`

	Description              string `json:"Description,omitempty"`
	PIDFile                  string `json:"PIDFile,omitempty"`
	USBFunctionDescriptors   string `json:"USBFunctionDescriptors,omitempty"`
	SubState                 string `json:"SubState,omitempty"`
	JobTimeoutRebootArgument string `json:"JobTimeoutRebootArgument,omitempty"`
	SourcePath               string `json:"SourcePath,omitempty"`
	FragmentPath             string `json:"FragmentPath,omitempty"`
	JobTimeoutAction         string `json:"JobTimeoutAction,omitempty"`
	ID                       string `json:"ID,omitempty"`
	RebootArgument           string `json:"RebootArgument,omitempty"`
	Following                string `json:"Following,omitempty"`
	KillMode                 string `json:"KillMode,omitempty"`
	RootDirectory            string `json:"RootDirectory,omitempty"`
	Slice                    string `json:"Slice,omitempty"`
	Group                    string `json:"Group,omitempty"`
	USBFunctionStrings       string `json:"USBFunctionStrings,omitempty"`
	User                     string `json:"User,omitempty"`
	BusName                  string `json:"BusName,omitempty"`

	JoinsNamespaceOf     []string `json:"JoinsNamespaceOf,omitempty"`
	RequiresMountsFor    []string `json:"RequiresMountsFor,omitempty"`
	RequisiteOf          []string `json:"RequisiteOf,omitempty"`
	Requires             []string `json:"Requires,omitempty"`
	Wants                []string `json:"Wants,omitempty"`
	ConflictedBy         []string `json:"ConflictedBy,omitempty,omitempty"`
	ConsistsOf           []string `json:"ConsistsOf,omitempty"`
	After                []string `json:"After,omitempty,omitempty"`
	Documentation        []string `json:"Documentation,omitempty,omitempty"`
	BindsTo              []string `json:"BindsTo,omitempty,omitempty"`
	WantedBy             []string `json:"WantedBy,omitempty,omitempty"`
	Triggers             []string `json:"Triggers,omitempty,omitempty"`
	Requisite            []string `json:"Requisite,omitempty,omitempty"`
	Before               []string `json:"Before,omitempty"`
	Names                []string `json:"Names,omitempty"`
	RequiredBy           []string `json:"RequiredBy,omitempty"`
	PropagatesReloadTo   []string `json:"PropagatesReloadTo,omitempty"`
	BoundBy              []string `json:"BoundBy,omitempty"`
	PartOf               []string `json:"PartOf,omitempty"`
	TriggeredBy          []string `json:"TriggeredBy,omitempty"`
	OnFailure            []string `json:"OnFailure,omitempty"`
	Conflicts            []string `json:"Conflicts,omitempty"`
	ReloadPropagatedFrom []string `json:"ReloadPropagatedFrom,omitempty"`
	Environment          []string `json:"Environment,omitempty"`
	SupplementaryGroups  []string `json:"SupplementaryGroups,omitempty"`

	LoadState        *state `json:"LoadState,omitempty"`
	ActiveState      *state `json:"ActiveState,omitempty"`
	UnitFileState    *state `json:"UnitFileState,omitempty"`
	SuccessAction    *state `json:"SuccessAction,omitempty"`
	FailureAction    *state `json:"FailureAction,omitempty,omitempty"`
	StartLimitAction *state `json:"StartLimitAction,omitempty,omitempty"`
	CollectMode      *state `json:"CollectMode,omitempty"`
	OnFailureJobMode *state `json:"OnFailureJobMode,omitempty"`
	Restart          *state `json:"Restart,omitempty"`
	NotifyAccess     *state `json:"NotifyAccess,omitempty"`
	Type             *state `json:"Type,omitempty"`

	RestartPreventExitStatus []interface{} `json:"RestartPreventExitStatus,omitempty"`
	SystemCallFilter         []interface{} `json:"SystemCallFilter,omitempty"`
	RestartForceExitStatus   []interface{} `json:"RestartForceExitStatus,omitempty"`
	SuccessExitStatus        []interface{} `json:"SuccessExitStatus,omitempty"`

	Asserts       [][]interface{} `json:"Asserts,omitempty"`
	Conditions    [][]interface{} `json:"Conditions,omitempty"`
	ExecStart     [][]interface{} `json:"ExecStart,omitempty"`
	ExecReload    [][]interface{} `json:"ExecReload,omitempty"`
	ExecStopPost  [][]interface{} `json:"ExecStopPost,omitempty"`
	ExecStop      [][]interface{} `json:"ExecStop,omitempty"`
	ExecStartPost [][]interface{} `json:"ExecStartPost,omitempty"`
	ExecStartPre  [][]interface{} `json:"ExecStartPre,omitempty"`
}

func getUnit(v map[string]interface{}) (*get, error) {
	out := &get{}

	iIDs, ok := v["InvocationID"].([]uint8)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "InvocationID")
	}
	out.InvocationID = iIDs

	description, ok := v["Description"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Description")
	}

	out.Description = description
	subState, ok := v["SubState"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SubState")
	}

	out.SubState = subState
	jobSlice, ok := v["Job"].([]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Job")
	}

	if len(jobSlice) < 1 {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Job")
	}

	job, ok := jobSlice[0].(uint32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Job")
	}

	out.Job = job
	loadState, ok := v["LoadState"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "LoadState")
	}
	out.LoadState = createState([]string{"loaded", "error", "masked"}, loadState)

	activeState, ok := v["ActiveState"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ActiveState")
	}
	out.ActiveState = createState([]string{"active", "reloading", "inactive", "failed", "activating", "deactivating"}, activeState)

	unitFileState, ok := v["UnitFileState"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "UnitFileState")
	}
	out.UnitFileState = createState([]string{"enabled", "enabled-runtime", "linked", "linked-runtime", "masked", "masked-runtime", "static", "disabled", "invalid"},
		unitFileState)

	joinsNamespaceOf, ok := v["JoinsNamespaceOf"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "JoinsNamespaceOf")
	}
	out.JoinsNamespaceOf = joinsNamespaceOf

	actExTMon, ok := v["ActiveExitTimestampMonotonic"].(uint64)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ActiveExitTimestampMonotonic")
	}
	out.ActiveExitTimestampMonotonic = actExTMon

	onFailureJobMode, ok := v["OnFailureJobMode"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "OnFailureJobMode")
	}
	out.OnFailureJobMode = createState([]string{"fail", "replace", "replace-irreversibly", "isolate", "flush", "ignore-dependencies", "ignore-requirements"},
		onFailureJobMode)

	reqMonFor, ok := v["RequiresMountsFor"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RequiresMountsFor")
	}
	out.RequiresMountsFor = reqMonFor

	requisiteOf, ok := v["RequisiteOf"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RequisiteOf")
	}
	out.RequisiteOf = requisiteOf

	jobTimeoutRebootArgument, ok := v["JobTimeoutRebootArgument"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "JobTimeoutRebootArgument")
	}
	out.JobTimeoutRebootArgument = jobTimeoutRebootArgument

	sourcePath, ok := v["SourcePath"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SourcePath")
	}
	out.SourcePath = sourcePath

	jobRunningTimeoutUSec, ok := v["JobRunningTimeoutUSec"].(uint64)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "JobRunningTimeoutUSec")
	}
	out.JobRunningTimeoutUSec = jobRunningTimeoutUSec

	requires, ok := v["Requires"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Requires")
	}
	out.Requires = requires

	stop, ok := v["StopWhenUnneeded"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "StopWhenUnneeded")
	}
	out.StopWhenUnneeded = stop

	fragmentPath, ok := v["FragmentPath"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "FragmentPath")
	}
	out.FragmentPath = fragmentPath

	canReload, ok := v["CanReload"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "CanReload")
	}
	out.CanReload = canReload

	wants, ok := v["Wants"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Wants")
	}
	out.Wants = wants

	defaultDependencies, ok := v["DefaultDependencies"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "DefaultDependencies")
	}
	out.DefaultDependencies = defaultDependencies

	activeExitTimestamp, ok := v["ActiveExitTimestamp"].(uint64)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ActiveExitTimestamp")
	}
	out.ActiveExitTimestamp = activeExitTimestamp

	jobTimeoutAction, ok := v["JobTimeoutAction"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ActiveExitTimestamp")
	}
	out.JobTimeoutAction = jobTimeoutAction

	collectMode, ok := v["CollectMode"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "CollectMode")
	}
	out.CollectMode = createState([]string{"inactive, inactive-or-failed"}, collectMode)

	asserts, ok := v["Asserts"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Asserts")
	}
	out.Asserts = asserts

	after, ok := v["After"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "After")
	}
	out.After = after

	startLimitAction, ok := v["StartLimitAction"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "StartLimitAction")
	}
	out.StartLimitAction = createState([]string{"none", "reboot", "reboot-force", "reboot-immediate", "poweroff", "poweroff-force", "poweroff-immediate", "exit", "exit-force"}, startLimitAction)

	doc, ok := v["Documentation"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Documentation")
	}
	out.Documentation = doc

	bind, ok := v["BindsTo"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "BindsTo")
	}
	out.BindsTo = bind

	wantedBy, ok := v["WantedBy"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "WantedBy")
	}
	out.WantedBy = wantedBy

	triggers, ok := v["Triggers"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Triggers")
	}
	out.Triggers = triggers

	failureAction, ok := v["FailureAction"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "FailureAction")
	}
	out.FailureAction = createState([]string{"none", "reboot", "reboot-force", "reboot-immediate", "poweroff", "poweroff-force", "poweroff-immediate", "exit", "exit-force"}, failureAction)

	requisite, ok := v["Requisite"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Requisite")
	}
	out.Requisite = requisite

	startLimitBurst, ok := v["StartLimitBurst"].(uint32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "StartLimitBurst")
	}
	out.StartLimitBurst = startLimitBurst

	conflictedBy, ok := v["ConflictedBy"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ConflictedBy")
	}
	out.ConflictedBy = conflictedBy

	consistsOf, ok := v["ConsistsOf"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ConsistsOf")
	}
	out.ConsistsOf = consistsOf

	successActionExitStatus, ok := v["SuccessActionExitStatus"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SuccessActionExitStatus")
	}
	out.SuccessActionExitStatus = successActionExitStatus

	before, ok := v["Before"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Before")
	}
	out.Before = before

	id, ok := v["Id"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ID")
	}
	out.ID = id

	successAction, ok := v["SuccessAction"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SuccessAction")
	}
	out.SuccessAction = createState([]string{"none", "reboot", "reboot-force", "reboot-immediate", "poweroff", "poweroff-force", "poweroff-immediate", "exit", "exit-force"}, successAction)

	names, ok := v["Names"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Names")
	}
	out.Names = names

	failureActionExitStatus, ok := v["FailureActionExitStatus"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "FailureActionExitStatus")
	}
	out.FailureActionExitStatus = failureActionExitStatus

	rebootArgument, ok := v["RebootArgument"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RebootArgument")
	}
	out.RebootArgument = rebootArgument

	requiredBy, ok := v["RequiredBy"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RequiredBy")
	}
	out.RequiredBy = requiredBy

	refuseManualStop, ok := v["RefuseManualStop"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RefuseManualStop")
	}
	out.RefuseManualStop = refuseManualStop

	following, ok := v["Following"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Following")
	}
	out.Following = following

	conditions, ok := v["Conditions"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Conditions")
	}
	out.Conditions = conditions

	propagatesReloadTo, ok := v["PropagatesReloadTo"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "PropagatesReloadTo")
	}
	out.PropagatesReloadTo = propagatesReloadTo

	perpetual, ok := v["Perpetual"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Perpetual")
	}
	out.Perpetual = perpetual

	boundBy, ok := v["BoundBy"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "BoundBy")
	}
	out.BoundBy = boundBy

	partOf, ok := v["PartOf"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "PartOf")
	}
	out.PartOf = partOf

	ignoreOnIsolate, ok := v["IgnoreOnIsolate"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "IgnoreOnIsolate")
	}
	out.IgnoreOnIsolate = ignoreOnIsolate

	onFailure, ok := v["OnFailure"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "OnFailure")
	}
	out.OnFailure = onFailure

	triggeredBy, ok := v["TriggeredBy"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "TriggeredBy")
	}
	out.TriggeredBy = triggeredBy

	refuseManualStart, ok := v["RefuseManualStart"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RefuseManualStart")
	}
	out.RefuseManualStart = refuseManualStart

	conflicts, ok := v["Conflicts"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "conflicts")
	}
	out.Conflicts = conflicts

	allowIsolate, ok := v["AllowIsolate"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "allowIsolate")
	}
	out.AllowIsolate = allowIsolate

	reloadPropagatedFrom, ok := v["ReloadPropagatedFrom"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ReloadPropagatedFrom")
	}
	out.ReloadPropagatedFrom = reloadPropagatedFrom

	return out, nil
}

func getService(v map[string]interface{}) (*get, error) {
	out := &get{}

	dynamicUser, ok := v["DynamicUser"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "DynamicUser")
	}
	out.DynamicUser = dynamicUser

	killSignal, ok := v["KillSignal"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "KillSignal")
	}
	out.KillSignal = killSignal

	ambientCapabilities, ok := v["AmbientCapabilities"].(uint64)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "AmbientCapabilities")
	}
	out.AmbientCapabilities = ambientCapabilities

	watchdogSignal, ok := v["WatchdogSignal"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "WatchdogSignal")
	}
	out.WatchdogSignal = watchdogSignal

	USBFunctionDescriptors, ok := v["USBFunctionDescriptors"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "USBFunctionDescriptors")
	}
	out.USBFunctionDescriptors = USBFunctionDescriptors

	restartPreventExitStatus, ok := v["RestartPreventExitStatus"].([]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RestartPreventExitStatus")
	}
	out.RestartPreventExitStatus = restartPreventExitStatus

	rootDirectoryStartOnly, ok := v["RootDirectoryStartOnly"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RootDirectoryStartOnly")
	}
	out.RootDirectoryStartOnly = rootDirectoryStartOnly

	fileDescriptorStoreMax, ok := v["FileDescriptorStoreMax"].(uint32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "FileDescriptorStoreMax")
	}
	out.FileDescriptorStoreMax = fileDescriptorStoreMax

	PIDFile, ok := v["PIDFile"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "PIDFile")
	}
	out.PIDFile = PIDFile

	OOMScoreAdjust, ok := v["OOMScoreAdjust"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "OOMScoreAdjust")
	}
	out.OOMScoreAdjust = OOMScoreAdjust

	killMode, ok := v["KillMode"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "KillMode")
	}
	out.KillMode = killMode

	execStart, ok := v["ExecStart"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecStart")
	}
	out.ExecStart = execStart

	notifyAccess, ok := v["NotifyAccess"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "NotifyAccess")
	}
	out.NotifyAccess = createState([]string{"none", "main", "exec", "all"}, notifyAccess)

	systemCallFilter, ok := v["SystemCallFilter"].([]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SystemCallFilter")
	}
	out.SystemCallFilter = systemCallFilter

	execReload, ok := v["ExecReload"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecReload")
	}
	out.ExecReload = execReload

	guessMainPID, ok := v["GuessMainPID"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "GuessMainPID")
	}
	out.GuessMainPID = guessMainPID

	supplementaryGroups, ok := v["SupplementaryGroups"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SupplementaryGroups")
	}
	out.SupplementaryGroups = supplementaryGroups

	execStopPost, ok := v["ExecStopPost"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecStopPost")
	}
	out.ExecStopPost = execStopPost

	rootDirectory, ok := v["RootDirectory"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RootDirectory")
	}
	out.RootDirectory = rootDirectory

	privateTmp, ok := v["PrivateTmp"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "PrivateTmp")
	}
	out.PrivateTmp = privateTmp

	restartForceExitStatus, ok := v["RestartForceExitStatus"].([]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RestartForceExitStatus")
	}
	out.RestartForceExitStatus = restartForceExitStatus

	delegate, ok := v["Delegate"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Delegate")
	}
	out.Delegate = delegate

	slice, ok := v["Slice"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Slice")
	}
	out.Slice = slice

	capabilityBoundingSet, ok := v["CapabilityBoundingSet"].(uint64)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "CapabilityBoundingSet")
	}
	out.CapabilityBoundingSet = capabilityBoundingSet

	execStop, ok := v["ExecStop"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecStop")
	}
	out.ExecStop = execStop

	group, ok := v["Group"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Group")
	}
	out.Group = group

	environment, ok := v["Environment"].([]string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Environment")
	}
	out.Environment = environment

	privateDevices, ok := v["PrivateDevices"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "PrivateDevices")
	}
	out.PrivateDevices = privateDevices

	restart, ok := v["Restart"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Restart")
	}
	out.Restart = createState([]string{"no", "on-success", "on-failure", "on-abnormal", "on-watchdog", "on-abort", "always"}, restart)

	USBFunctionStrings, ok := v["USBFunctionStrings"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "USBFunctionStrings")
	}
	out.USBFunctionStrings = USBFunctionStrings

	t, ok := v["Type"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "Type")
	}
	out.Type = createState([]string{"simple", "exec", "forking", "oneshot", "dbus", "notify", "idle"}, t)

	execStartPost, ok := v["ExecStartPost"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecStartPost")
	}
	out.ExecStartPost = execStartPost

	successExitStatus, ok := v["SuccessExitStatus"].([]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "SuccessExitStatus")
	}
	out.SuccessExitStatus = successExitStatus

	remainAfterExit, ok := v["RemainAfterExit"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "RemainAfterExit")
	}
	out.RemainAfterExit = remainAfterExit

	nonBlocking, ok := v["NonBlocking"].(bool)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "NonBlocking")
	}
	out.NonBlocking = nonBlocking

	user, ok := v["User"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "User")
	}
	out.User = user

	execStartPre, ok := v["ExecStartPre"].([][]interface{})
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "ExecStartPre")
	}
	out.ExecStartPre = execStartPre

	finalKillSignal, ok := v["FinalKillSignal"].(int32)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "FinalKillSignal")
	}
	out.FinalKillSignal = finalKillSignal

	busName, ok := v["BusName"].(string)
	if !ok {
		return nil, fmt.Errorf("Cannot format '%s' unit property for a response.", "BusName")
	}
	out.BusName = busName

	return out, nil
}

func createState(options []string, value string) *state {
	for i, option := range options {
		if value == option {
			return &state{i + 1, value}
		}
	}

	return &state{0, value}
}
