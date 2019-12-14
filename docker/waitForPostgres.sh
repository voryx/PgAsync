#!/bin/bash

echo "Waiting for database..."

ROW_COUNT=0

TRY_COUNT=0

while [ "$ROW_COUNT" -ne 1 ]; do
  if [ "$TRY_COUNT" -ge 60 ]; then
    echo "Timeout waiting for database..."
    exit 1;
  fi
  sleep 5
  TRY_COUNT=$(($TRY_COUNT+1))
  echo "Attempt $TRY_COUNT..."
  if ! ROW_COUNT=$(docker exec docker_pgasync-postgres_1 psql -U postgres pgasync_test -c "select count(*) from test_bool_param" -A -t); then
    ROW_COUNT=0
  fi
done

echo "Database is up..."