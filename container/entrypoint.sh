#!/usr/bin/env bash
set -x

function is_db_live {
  php <<'EOD'
<?php
$errno = null;
$errstr = null;
$res=@fsockopen("db", 3306, $errno, $errstr, 0.5);
if ($res) {
  fclose($res);
  exit(0);
} else {
  exit(1);
}
EOD
}

i=0

is_db_live
r=$?

while [[ $r -ne 0 ]]; do
  i=$((i+1))
  sleep 1
  is_db_live
  r=$?
  if [[ $i -ge 30 ]]; then
     echo "Could not connect to mysql within 30 seconds, failing."
     exit 1
  fi
done

set -e

if [[ "$1" == "worker" ]]; then
  # Wait for migrations to create the task table...
  sleep 2
  ./scripts/dagd-worker -w
else
  echo 0 > sql/current_schema
  ./scripts/sql -a .

  cp -v container/dagd-httpd.conf /etc/apache2/sites-enabled/000-default.conf

  # Add public fallbacks for DNS_ALL queries while retaining Docker's resolver
  # for service discovery.
  #   dns_get_record('google.com', DNS_ALL);
  echo "nameserver 8.8.8.8" >> /etc/resolv.conf
  echo "nameserver 8.8.4.4" >> /etc/resolv.conf

  # Immediately before we start, touch a file to tell CI that we are
  # ready to start working.
  touch .ready-for-ci

  apache2ctl -D FOREGROUND
fi
