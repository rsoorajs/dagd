This is what a development sandbox's
`/etc/apache2/sites-available/dagd.conf` can look like on Debian 13.

```
<VirtualHost *:80>
  # This file is loosely based on the recommended Phabricator apache config.
  ServerName dagd.local

  DocumentRoot /home/ricky/devel/da.gd/src/webroot/

  ErrorLog logs/da.gd-error_log
  CustomLog logs/da.gd-access_log combined

  AllowEncodedSlashes On

  RewriteEngine on
  RewriteRule ^(.*)$          /index.php [L,QSA]

  # This file remains tracked in dagd's master branch. If this scares you
  # (if you are not a da.gd developer, it should), change this to
  # config.prod.php, and copy src/config.dev.php appropriately, then edit.
  SetEnv DaGdConfigFile config.dev.php
</VirtualHost>
```
