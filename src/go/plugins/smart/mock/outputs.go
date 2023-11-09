package mock

import _ "embed"

var (
	//go:embed outputs/version_valid.json
	OutputVersionValid []byte

	//go:embed outputs/version_invalid.json
	OutputVersionInvalid []byte

	//go:embed outputs/scan.json
	OutputScan []byte

	//go:embed outputs/scan_type_sat.json
	OutputScanTypeSAT []byte
)
