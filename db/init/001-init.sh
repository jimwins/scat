#!/usr/bin/env bash
set -e
mysqladmin -uroot -p${MYSQL_ROOT_PASSWORD} create scat
mysql -uroot -p${MYSQL_ROOT_PASSWORD} \
      -e "GRANT ALL PRIVILEGES ON scat.* TO '${MYSQL_USER}'@'%';"
