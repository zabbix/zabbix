package mock

import _ "embed"

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
)
