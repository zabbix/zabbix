#~/bin/sh

OPT_REVERT=0
OPT_ARGS=
OPT_TEST_PATH=
OPT_REPORT_PATH="."
OPT_MODE="basic"
OPT_CONFIGURE="yes"

TEST_COMMENT="embedded unit tests - remove before commit"

revert_changes()
{
	echo "Reverting changes..."

	# remove inserted includes from source files
	for file in $CHANGED_FILES; do
		echo -e "\t$file"
		sed -i "/$TEST_COMMENT/d" $file
	done
	
	# remove copied unit test runner sources
	[ -f "src/zabbix_server/zabbix_server_cu.c" ] && rm "src/zabbix_server/zabbix_server_cu.c"
	[ -f "src/zabbix_proxy/zabbix_proxy_cu.c" ] && rm "src/zabbix_proxy/zabbix_proxy_cu.c" 
	[ -f "src/zabbix_agent/zabbix_agent_cu.c" ] && rm "src/zabbix_agent/zabbix_agent_cu.c"
}

exit_on_error()
{
	echo "ERROR: $1"
	revert_changes
	
	exit 1
}

prepare_sources()
{
	target=$1
	main=$2
	
	echo "Preparing $target unit tests..."
	# get the test list
	files=$(find "$OPT_TEST_PATH/$target" -type f -name "*.c")
	
	echo -e "\tgenerate test runner ${target}_cu.c"
	# copy the new startup function sources
	if ! cp "$OPT_TEST_PATH/${target}_cu.c" "src/$target/"; then
		exit_on_error "failed to copy file: $OPT_TEST_PATH/${target}_cu.c -> src/$target/"
	fi
	
	# override the server startup function
	if ! sed -ri "s/(.*)main\(([^\)]+)\)/#include \"${target}_cu.c\" \/\/$TEST_COMMENT\n\1main\(\2\)/" "src/$target/$main"; then
		exit_on_error "failed to insert test code into file: src/$target/$main"
	fi

	# generate test suite initialization function
	echo "int	initialize_cu_tests()" >> "src/$target/${target}_cu.c"
	echo "{" >> "src/$target/${target}_cu.c"

	# append tests to the source files and generate initialize_tests() function contents
	for file in $files; do
		module=$(grep 'ZBX_CU_MODULE\(.*\)' $file | sed -r 's/.*ZBX_CU_MODULE\((.*)\)/\1/')
		if [ -n "$module" ]; then
			echo "	if (CUE_SUCCESS != zbx_cu_init_$module()) return CU_get_error();" >> "src/$target/${target}_cu.c"
			
			file=${file#$OPT_TEST_PATH/$target/}
			echo -e "\tadd test suit to $file"
			echo "#include \"$OPT_TEST_PATH/$target/$file\" //$TEST_COMMENT" >> "src/${file}"
		fi
	done

	echo "}" >> "src/$target/${target}_cu.c"

	# generate test runner
	echo "void	run_cu_tests()" >> "src/$target/${target}_cu.c"
	echo "{" >> "src/$target/${target}_cu.c"

	case $OPT_MODE in
		basic)
			echo "	CU_basic_set_mode(CU_BRM_VERBOSE);" >> "src/$target/${target}_cu.c"
			echo "	CU_basic_run_tests();" >> "src/$target/${target}_cu.c"
		;;
		automated)
			echo "	CU_set_output_filename(\"$OPT_REPORT_PATH/$target\");" >> "src/$target/${target}_cu.c"
			echo "	CU_automated_run_tests();" >> "src/$target/${target}_cu.c"
			echo "	CU_list_tests_to_file();" >> "src/$target/${target}_cu.c"
		;;
	esac

	echo "}" >> "src/$target/${target}_cu.c"

}

filter_arguments()
{
	while [ $# -gt 0 ]; do
		case $1 in
			--revert)
				OPT_REVERT=1
			;;
			--testsrc=*)
				OPT_TEST_PATH="${1#*=}"
			;;
			--report=*)
				OPT_REPORT_PATH="${1#*=}"
			;;
			--mode=*)
				OPT_MODE="${1#*=}"
			;;
			--skip-configure)
				OPT_CONFIGURE=""
			;;
			*)
				OPT_ARGS="$OPT_ARGS \"$1\""
			;;
		esac
		shift
	done
}

filter_arguments "$@"

# Note - could be slow to do it for every configure. Might be better to store in some
# file like it was done with diffs
CHANGED_FILES=$(grep "$TEST_COMMENT" src/* -r 2>/dev/null  | cut -f1 -d: | uniq)

if [ "$OPT_REVERT" -eq 1 ]; then
	if [ -z "$CHANGED_FILES" ]; then
		echo "No patched source files found."
		exit 1
	fi
	
	revert_changes
	exit 0
fi

if [ -n "$CHANGED_FILES" ]; then
	echo "Source files are already patched. Skipping changes."
else	
	if [ -z "$OPT_TEST_PATH" ]; then
		echo "ERROR: no test source path defined"
		exit 1
	fi
	
	prepare_sources "zabbix_server" "server.c"
	prepare_sources "zabbix_proxy" "proxy.c"
	prepare_sources "zabbix_agent" "zabbix_agentd.c"
fi

if [ -n "$OPT_CONFIGURE" ]; then
	export LIBS=-lcunit
	export CFLAGS="-I$OPT_TEST_PATH"

	eval ./configure "$OPT_ARGS"
fi
