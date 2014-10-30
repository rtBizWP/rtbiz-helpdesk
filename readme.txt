=== rtLib [![Build Status](https://travis-ci.org/rtCamp/rt-lib.svg?branch=master)](https://travis-ci.org/rtCamp/rt-lib) ===
Contributors:      rtcamp, rahul286, faishal, desaiuditd
Tags:              library, autoloader, database model, database updater, attributes, user groups
Requires at least: 3.6
Tested up to:      3.9.1
Stable tag:        master
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html



== Description ==

rtLib is library of class that are required in development of any WordPress plugins.

Following are some classes:

 * RT_DB_Model
 * RT_DB_Update
 * RT_Plugin_Info
 * RT_Plugin_Update_Checker
 * RT_WP_Autoloader
 * RT_Theme_Update_Checker
 * RT_Email_Template
 * RT_Attributes

**NOTE**: Development in progress

Inspired from https://github.com/zendframework/zf2/tree/master/library/Zend/

To add it in your plugin/theme
```
git subtree add --prefix app/lib https://github.com/rtCamp/rt-lib.git master  --squash
```

To update the library
```
git subtree pull --prefix app/lib https://github.com/rtCamp/rt-lib.git master  --squash
```

Add following line in plugin loader file

```
include_once 'app/lib/rt-lib.php';
```

Alternatively you can add as a plugin also

** License **

Same [GPL] (http://www.gnu.org/licenses/gpl-2.0.txt) that WordPress uses!

**Coming soon:**

 * Private Attributes Support

**See room for improvement?**

Great! There are several ways you can get involved to help make Stream better:

1. **Report Bugs:** If you find a bug, error or other problem, please report it! You can do this by [creating a new topic](https://github.com/rtCamp/rt-lib/issues) in the issue tracker.
2. **Suggest New Features:** Have an awesome idea? Please share it! Simply [create a new topic](https://github.com/rtCamp/rt-lib/issues) in the issure tracker to express your thoughts on why the feature should be included and get a discussion going around your idea.

== Changelog ==

= 0.7 =
A Few bug fixes for RT_LIB_FILE constant
DB Update Key changed for User Groups

= 0.6 =
Test Cases updated & Code Sniffer Config updated & pre-commit hook updated

= 0.5 =
Initial Basic Libraries.