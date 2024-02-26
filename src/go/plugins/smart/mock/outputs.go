package mock

import (
	_ "embed"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
)

var Outputs = EnvironmentCollection{}

type EnvironmentCollection map[string]Environment

type Environment struct {
	Version         json.RawMessage `json:"version"`
	AllDevicesScan  json.RawMessage `json:"all_devices_scan"`
	RaidDevicesScan json.RawMessage `json:"raid_devices_scan"`
}

func init() {
	_, file, _, ok := runtime.Caller(0)
	if !ok {
		panic("No caller information")
	}

	b, err := os.ReadFile(filepath.Join(filepath.Dir(file), "outputs.json"))
	if err != nil {
		panic(err)
	}

	err = json.Unmarshal(b, &Outputs)
	if err != nil {
		panic(err)
	}
}

func (ec EnvironmentCollection) Get(name string) Environment {
	e, ok := ec[name]
	if !ok {
		panic(fmt.Sprintf("Environment %s not found", name))
	}

	return e
}

var (
	//go:embed outputs/version_valid.json
	OutputVersionValid []byte

	//go:embed outputs/version_invalid.json
	OutputVersionInvalid []byte

	//go:embed outputs/scan.json
	OutputScan []byte

	//go:embed outputs/env_mac_scan_basic.json
	OutputEnvMacScanBasic []byte

	//go:embed outputs/env_mac_scan_raid.json
	OutputEnvMacScanRaid []byte

	//go:embed outputs/scan_type_sat.json
	OutputScanTypeSAT []byte

	//go:embed outputs/scan_basic.json
	OutputScanBasic []byte

	//go:embed outputs/all_disc_info_csmi.json
	OutputAllDiscInfoCSMI []byte

	//go:embed outputs/all_disc_info_sda.json
	OutputAllDiscInfoSDA []byte

	//go:embed outputs/all_disc_info_mac.json
	OutputAllDiscInfoMac []byte

	//go:embed outputs/env_1_scan_basic.json
	OutputEnv1ScanBasic []byte

	//go:embed outputs/env_1_scan_raid.json
	OutputEnv1ScanRaid []byte

	//go:embed outputs/env_1_get_raid_sda_3ware_0.json
	OutputEnv1GetRaidSda3Ware0 []byte

	//go:embed outputs/env_1_get_raid_sda_sat.json
	OutputEnv1GetRaidSdaSat []byte

	//go:embed outputs/env_1_get_raid_sda_areca_1.json
	OutputEnv1GetRaidSdaAreca1 []byte

	//go:embed outputs/env_1_get_raid_sda_cciss_0.json
	OutputEnv1GetRaidSdaCciss0 []byte
)
