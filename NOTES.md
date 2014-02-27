### How this mirror was built

Currently a 20m crontab runs the following process:

```sh
# Note original mirror of SVN takes a very long time
# git svn clone --stdlayout svn://svn.zabbix.com # original mirror
git svn fetch
git checkout trunk
git reset remotes/trunk --hard
git branch -D master # hack; git svn re-creates master branch
# git push --mirror git@github.com:zabbix/svn.zabbix.com.git # destructive to other branches
git push --all git@github.com:zabbix/zabbix.git
# explicitly push SVN related refs (i.e. for svn->git tags)
git push git@github.com:zabbix/zabbix.git refs/remotes/*
git push git@github.com:zabbix/zabbix.git refs/remotes/tags/*:refs/tags/*
```
