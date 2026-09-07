#!/usr/bin/env bash
# Starts PHP's built-in dev server with local-php.ini applied, so upload
# limits match production instead of PHP's 8MB default.
#
# Always kills anything already listening on port 8000 first, so you can
# just run this script whenever, without hunting for stray php processes.

cd "$(dirname "$0")"

PORT=8000

# Find and kill anything already listening on $PORT (macOS/Linux).
EXISTING_PID="$(lsof -ti tcp:$PORT)"
if [ -n "$EXISTING_PID" ]; then
  echo "Port $PORT is in use (PID $EXISTING_PID) -- stopping it first..."
  kill -9 $EXISTING_PID
  sleep 0.5
fi

if [ ! -f local-php.ini ]; then
  echo "ERROR: local-php.ini not found in $(pwd)."
  echo "Make sure this script and local-php.ini are in the same folder."
  exit 1
fi

echo "Starting server with local-php.ini (post_max_size=45M, upload_max_filesize=40M)..."
php -c local-php.ini -S localhost:$PORT