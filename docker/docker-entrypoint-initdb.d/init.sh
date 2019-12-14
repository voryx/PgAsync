#!/bin/bash
set -x

echo "Running as $USER in $PWD"

createuser -U postgres --createdb pgasync
createuser -U postgres --createdb pgasyncpw
psql -U postgres -c "ALTER ROLE pgasyncpw PASSWORD 'example_password'"

cd /app
cp pg_hba_new.conf database/pg_hba.conf

createdb -U pgasync pgasync_test

psql -U pgasync -f test_db.sql pgasync_test


