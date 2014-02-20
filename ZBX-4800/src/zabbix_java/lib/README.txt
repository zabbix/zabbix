In order to upgrade the Android JSON library, follow the procedure below:

### get the repository

$ git clone https://android.googlesource.com/platform/libcore android-libcore
$ cd android-libcore

### choose the tag you wish to upgrade to (android-4.3_r3.1 in the example below)

$ git tag | less
$ git checkout android-4.3_r3.1

### compile and package the library

$ cd json/src/main/java
$ javac -source 1.5 org/json/*.java
$ jar cf android-json-4.3_r3.1.jar org/json/*.class
$ mv android-json-4.3_r3.1.jar ${zabbix}/src/zabbix_java/lib

### clean up

$ rm org/json/*.class
$ cd ../../../..
$ git checkout master
