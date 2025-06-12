#!/bin/bash
echo "🔁 Starte Docker-Container neu ..."

docker restart myloginsrv-nginx
docker restart myloginsrv-php

echo "🧹 Entferne alte PHP-Session-Dateien ..."
docker exec myloginsrv-php bash -c "rm -f /tmp/sess_* || true"

echo "✅ Neustart und Bereinigung abgeschlossen."
