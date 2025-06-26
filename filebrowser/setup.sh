#!/bin/bash

# === Konfiguration ===
APP_DIR="$(pwd)"
DB_DIR="$APP_DIR/database"
CONFIG_DIR="$APP_DIR/config"
COMPOSE_FILE="$APP_DIR/docker-compose.yml"
ADMIN_USER="admin"
ADMIN_PASS="adminpass"
PORT="8083"
BROWSE_DIR="/root/Pictures"  # ← Hier dein Anzeigeverzeichnis setzen
CONTAINER_NAME="filebrowser"

# === IP-Adresse des Hosts ermitteln ===
HOST_IP=$(hostname -I | awk '{print $1}')

# === Reset-Modus ===
if [ "$1" == "--reset" ]; then
  echo "🔄 Reset: Vorherige Instanz wird entfernt..."
  docker compose -f "$COMPOSE_FILE" down
  rm -rf "$DB_DIR" "$CONFIG_DIR"
  echo "🧹 Ordner 'database/' & 'config/' entfernt."
fi

# === Verzeichnis prüfen ===
if [ ! -d "$BROWSE_DIR" ]; then
  echo "❌ Fehler: '$BROWSE_DIR' existiert nicht."
  exit 1
fi

if ! sudo -u nobody test -r "$BROWSE_DIR"; then
  echo "⚠️ Warnung: '$BROWSE_DIR' scheint nicht lesbar für den Container."
fi

# === Ordner vorbereiten ===
mkdir -p "$DB_DIR" "$CONFIG_DIR"
chown 1000:1000 "$DB_DIR" "$CONFIG_DIR"
chmod 770 "$DB_DIR" "$CONFIG_DIR"

# === Datenbank anlegen, wenn nicht vorhanden ===
if [ ! -f "$DB_DIR/filebrowser.db" ]; then
  echo "🔧 Erzeuge Datenbank..."
  docker run --rm \
    -v "$DB_DIR":/database \
    filebrowser/filebrowser \
    --database /database/filebrowser.db config init || {
      echo "❌ Fehler beim Initialisieren der Datenbank."
      exit 1
    }
else
  echo "📁 Existierende Datenbank wird verwendet."
fi

# === Admin-Benutzer anlegen (wenn nicht vorhanden) ===
echo "👤 Prüfe Benutzer '$ADMIN_USER'..."
docker run --rm \
  -v "$DB_DIR":/database \
  filebrowser/filebrowser \
  --database /database/filebrowser.db users list | grep -qw "$ADMIN_USER"

if [ $? -eq 0 ]; then
  echo "✅ Benutzer '$ADMIN_USER' existiert bereits."
else
  echo "➕ Erstelle Benutzer '$ADMIN_USER'..."
  docker run --rm \
    -v "$DB_DIR":/database \
    filebrowser/filebrowser \
    --database /database/filebrowser.db users add "$ADMIN_USER" "$ADMIN_PASS" --perm.admin || {
      echo "❌ Fehler beim Anlegen des Benutzers."
      exit 1
    }
  echo "✅ Benutzer erfolgreich erstellt."
fi

# === docker-compose.yml schreiben ===
cat > "$COMPOSE_FILE" <<EOF
services:
  filebrowser:
    image: filebrowser/filebrowser
    container_name: $CONTAINER_NAME
    user: "0:0"
    ports:
      - "${PORT}:80"
    volumes:
      - "${BROWSE_DIR}:/srv"
      - "./database:/database"
      - "./config:/config"
    command: ["--database", "/database/filebrowser.db"]
    restart: unless-stopped
EOF

echo "📄 docker-compose.yml geschrieben."

# === Container starten ===
echo "🚀 Starte File Browser..."
docker compose -f "$COMPOSE_FILE" up -d

# === Abschlussinfo ===
echo
echo "🌐 Zugriff auf File Browser:"
echo "   → http://$HOST_IP:$PORT"
echo "📂 Anzeige-Verzeichnis: $BROWSE_DIR"
echo "🔐 Login:"
echo "   Benutzer: $ADMIN_USER"
echo "   Passwort: $ADMIN_PASS"
echo
echo "✅ Setup abgeschlossen – fertig zum Durchstarten!"