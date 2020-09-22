package ceph

import (
	"io/ioutil"
	"log"
	"os"
	"strings"
	"testing"
)

var fixtures map[command][]byte

const cmdBroken command = "broken"

func TestMain(m *testing.M) {
	var err error

	fixtures = make(map[command][]byte)

	for _, cmd := range []command{cmdDf, cmdPgDump, cmdOSDCrushRuleDump, cmdOSDCrushTree, cmdOSDDump, cmdHealth, cmdStatus} {
		fixtures[cmd], err = ioutil.ReadFile("testdata/" + strings.ReplaceAll(string(cmd), " ", "_") + ".json")
		if err != nil {
			log.Fatal(err)
		}
	}

	fixtures[cmdBroken] = []byte{1, 2, 3, 4, 5}

	os.Exit(m.Run())
}
