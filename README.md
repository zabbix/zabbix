## Zabbix+LLNW customizations

This repo is a sync fork from Zabbix SVN (see `trunk` info); LLNW custom API improvements and additions to the existing product are tracked in branches and shall integrate with upstream.


#### Zabbix feature branches and contributions

Branch strategy

*  `trunk`: svn.zabbix.com latest (updated by [Zabbix SVN scripts](https://github.llnw.net/Zabbix/misc_tools/tree/master/Zabbix-SVN))
*  LLNW branches
   *  `LLNW-UIAPI` Aggregate branch representing current dev
   *  `F-*` are feature and/or bug fix branches we maintain on top of trunk
      *  `F-LLNW-{LDAP,API}` Internal Zabbix specific improvements or additions.
      *  `F-{MON-*,SYSDEV-*}` Internal Jira tracked branches (*Note* These branches should be avoided and favor Zabbix issue queue tracked branches)
      *  `F-{ZBX-*,ZBXNEXT-*,LMN-*}` [Zabbix support](https://support.zabbix.com) related improvements or additions.
*  Tags
   *  refs from SVN in git repo ([compare/2.0.8...2.0.9](https://github.llnw.net/Zabbix/svn.zabbix.com/compare/2.0.8...2.0.9))
   *  LLNW specific tags are `<release>+llnw.<increment from 1>` (e.g. [`2.0.8+llnw.6`](https://github.llnw.net/Zabbix/svn.zabbix.com/tree/2.0.8+llnw.6))

(ref: http://semver.org)


##### Contributing

Note the `F-*` branch you should be building changes off of (for example: LLNW custom API changes would be `F-LLNW-API`) and make updates/additions based off the branch. If it is a new feature: first makes sure you've consulted [support.zabbix.com](https://support.zabbix.com) and the [forums](https://www.zabbix.com/forum) to ensure upstream alignment. New features are based off the Zabbix release tag where the feature starts and later all feature branches are merged together.


*protip*

`git fetch upstream refs/tags/*:refs/tags/*` to get all tag info from upstream to your local repo.

*Upstream major version cherry-pick onto new branch*

`git rebase --onto F-LLNW-API-2.2 a495dde~1 upstream/F-LLNW-API`

a495dde is base of comparison, in this case: 2.0.3rc1.

(Note: helpful comparison: https://github.llnw.net/Zabbix/svn.zabbix.com/compare/2.0.8...F-LLNW-API)
