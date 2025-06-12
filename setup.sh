#!/bin/bash
# Shell-Skript zur Einrichtung der myloginsrv-Umgebung

# ------------------------ Flags initialisieren ------------------------
FORCE_INSTALL=false
DEBUG_MODE=false
DEBUG_FILE="./debug.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Übergabe-Parameter auswerten
for arg in "$@"; do
  case $arg in
    --debug) DEBUG_MODE=true ;;
    --force) FORCE_INSTALL=true ;;
  esac
done

# ------------------------ Log-Funktionen definieren ------------------------
log_info()    { echo -e "\033[1;34m[INFO]\033[0m $1"; $DEBUG_MODE && echo "$TIMESTAMP [INFO] $1" >> "$DEBUG_FILE"; }
log_warn()    { echo -e "\033[1;33m[WARN]\033[0m $1"; $DEBUG_MODE && echo "$TIMESTAMP [WARN] $1" >> "$DEBUG_FILE"; }
log_success() { echo -e "\033[1;32m[ OK ]\033[0m $1"; $DEBUG_MODE && echo "$TIMESTAMP [ OK ] $1" >> "$DEBUG_FILE"; }
log_error()   { echo -e "\033[1;31m[ERR!]\033[0m $1"; $DEBUG_MODE && echo "$TIMESTAMP [ERR!] $1" >> "$DEBUG_FILE"; }
divider()     { echo -e "\033[1;30m--------------------------------------------------------\033[0m"; }

# ------------------------ Vorbereitung --------------------------
cd "/root/myloginsrv" 2>/dev/null || cd "myloginsrv" || { log_error "Arbeitsverzeichnis 'myloginsrv' nicht gefunden."; exit 1; }
$DEBUG_MODE && echo "$TIMESTAMP DEBUG MODE ENABLED" >> "$DEBUG_FILE"

divider
log_info "Starte Docker-Container (Docker Compose up)..."
docker-compose up -d
sleep 3

# PHP-Containernamen ermitteln
PHP_CONTAINER=$(docker ps --format '{{.Names}}' | grep -E 'myloginsrv-php|php')
if [ -z "$PHP_CONTAINER" ]; then
  log_error "PHP-Container wurde nicht gefunden. Prüfung der Container-Namen!"
  exit 1
fi

# Optional: Docker-Exec Shortcut definieren mit Arbeitsverzeichnis
DOCKER_EXEC="docker exec -w /var/www/html $PHP_CONTAINER"

divider
echo "Fixing ownership of /var/www/html and /var/www/html/uploads inside Docker container..."

# HTML-Verzeichnis rekursiv auf www-data umstellen
docker exec -u root myloginsrv-php chown -R www-data:www-data /var/www/html

# Sicherheitscheck: uploads/ gezielt noch einmal setzen
docker exec -u root myloginsrv-php chown -R www-data:www-data /var/www/html/uploads

# Optional: Zeige aktuelle Rechte
docker exec myloginsrv-php ls -ld /var/www/html /var/www/html/uploads



divider
# ------------------------ Umgebung prüfen (.env, .envad) ------------------------
log_info "Prüfe .env und .envad Konfigurationsdateien..."
for FILE in ".env" ".envad"; do
  TEMPLATE="${FILE}.example"
  if $DOCKER_EXEC bash -c "[ ! -f '$FILE' ]" || $FORCE_INSTALL; then
    log_warn "$FILE fehlt oder Force-Modus aktiv - erstelle neu..."
    if $DOCKER_EXEC bash -c "[ -f '$TEMPLATE' ]"; then
      # Template vorhanden -> kopieren
      $DOCKER_EXEC cp "$TEMPLATE" "$FILE" 2>/dev/null
      if [ $? -eq 0 ]; then
        $DOCKER_EXEC chmod 664 "$FILE" && $DOCKER_EXEC chown www-data:www-data "$FILE"
        log_success "$FILE wurde aus $TEMPLATE erstellt."
      else
        log_error "$FILE konnte nicht aus Template erstellt werden. Bitte $TEMPLATE prüfen."
        exit 1
      fi
    else
      # Kein Template -> Datei mit Standardwerten anlegen
      log_warn "Kein $TEMPLATE gefunden. Erstelle $FILE mit Platzhaltern..."
      $DOCKER_EXEC bash -c "echo 'MAIL_HOST=smtp.example.com' > '$FILE'"
      $DOCKER_EXEC bash -c "echo 'MAIL_USER=user@example.com' >> '$FILE'"
      $DOCKER_EXEC bash -c "echo 'MAIL_PASS=secret' >> '$FILE'" 
      # (Für .envad analog LDAP Defaults einfügen, falls FILE=.envad)
      if [ "$FILE" = ".envad" ]; then
        $DOCKER_EXEC bash -c "echo 'LDAP_SERVER=ldap://localhost' >> '$FILE'"
        $DOCKER_EXEC bash -c "echo 'LDAP_USER=cn=admin,dc=example,dc=com' >> '$FILE'"
        $DOCKER_EXEC bash -c "echo 'LDAP_PASS=secret' >> '$FILE'"
      fi
      $DOCKER_EXEC chmod 664 "$FILE" && $DOCKER_EXEC chown www-data:www-data "$FILE"
      log_success "$FILE wurde mit Default-Werten neu erstellt."
    fi
  else
    log_info "$FILE ist bereits vorhanden."
  fi
done

divider
# ------------------------ Datenbankdateien vorbereiten ------------------------
log_info "Prüfe Datenbankdateien (users.db, info.db)..."
$DOCKER_EXEC bash -c 'for db in users.db info.db; do
    if [ ! -f "$db" ]; then
      touch "$db" && echo "[ OK ] $db created." || echo "[ERR!] Failed to create $db."
    else
      echo "[INFO] $db already exists."
    fi
    chown www-data:www-data "$db"
    chmod 664 "$db"
done'
log_success "Datenbank-Dateien geprüft und vorbereitet."

# ------------------------ Datenbank-Skripte ausführen ------------------------
divider
log_info "PHP-Syntaxcheck für init-db.php..."
$DOCKER_EXEC php -l /var/www/html/init-db.php || { log_error "Syntaxfehler in init-db.php! Abbruch."; exit 1; }

log_info "Führe init-db.php aus (Initialisiere users.db)..."
$DOCKER_EXEC php /var/www/html/init-db.php
if [ $? -eq 0 ]; then
  log_success "User-Datenbank (users.db) initialisiert."
else
  log_error "Initialisierung der User-Datenbank fehlgeschlagen."
  exit 1
fi

divider
log_info "PHP-Syntaxcheck für init_cms_db.php..."
$DOCKER_EXEC php -l /var/www/html/init_cms_db.php || { log_error "Syntaxfehler in init_cms_db.php! Abbruch."; exit 1; }

log_info "Führe init_cms_db.php aus (Initialisiere CMS-DB)..."
if $DOCKER_EXEC php /var/www/html/init_cms_db.php; then
  log_success "CMS-Datenbank (info.db) initialisiert."
else
  log_error "Initialisierung der CMS-Datenbank fehlgeschlagen."
  exit 1
fi

divider
docker exec -w /var/www/html myloginsrv-php rm -f users.db-journal users.db-shm users.db-wal


# ------------------------ PHPMailer Installation prüfen ------------------------
divider
log_info "Prüfe PHPMailer Installation..."
if $DOCKER_EXEC bash -c "[ ! -d 'vendor/phpmailer/phpmailer' ]" || $FORCE_INSTALL; then
  log_info "PHPMailer nicht gefunden - Installation wird durchgeführt..."
  # Sicherheitsnetz: erforderliche PHP-Extensions für Composer sicherstellen
  $DOCKER_EXEC apt-get update -qq && $DOCKER_EXEC apt-get install -y -qq php-curl php-zip unzip curl
  # Composer nutzen, um PHPMailer zu installieren
  $DOCKER_EXEC bash -c "cd /var/www/html && ( [ -f composer.phar ] || curl -sS https://getcomposer.org/installer | php ) && php composer.phar require phpmailer/phpmailer"
  if [ $? -eq 0 ]; then
    log_success "PHPMailer erfolgreich installiert/aktualisiert."
  else
    log_error "PHPMailer Installation fehlgeschlagen."
    exit 1
  fi
else
  log_info "PHPMailer bereits installiert (Vendor-Paket vorhanden)."
fi

# ------------------------ LDAP-PHP Modul prüfen ------------------------
divider
log_info "Prüfe PHP-LDAP Modul..."
$DOCKER_EXEC php -m | grep -qi ldap
if [ $? -ne 0 ] || $FORCE_INSTALL; then
  log_info "LDAP-Modul nicht aktiv - Installation wird durchgeführt..."
  $DOCKER_EXEC apt-get update -qq && $DOCKER_EXEC apt-get install -y -qq libldap2-dev
  $DOCKER_EXEC docker-php-ext-configure ldap && $DOCKER_EXEC docker-php-ext-install ldap
  if [ $? -eq 0 ]; then
    log_success "PHP-LDAP Modul wurde installiert."
  else
    log_error "Installation des PHP-LDAP Moduls fehlgeschlagen."
    exit 1
  fi
else
  log_info "PHP-LDAP Modul bereits verfügbar."
fi

# ------------------------ Upload-Verzeichnis prüfen ------------------------
divider
log_info "Richte Upload-Verzeichnis ein..."
UPLOAD_DIR="/var/www/html/uploads"
TEST_FILE="test_$(date +%s).tmp"
$DOCKER_EXEC mkdir -p "$UPLOAD_DIR" && $DOCKER_EXEC chown www-data:www-data "$UPLOAD_DIR" && $DOCKER_EXEC chmod 755 "$UPLOAD_DIR"
log_success "Upload-Verzeichnis vorhanden und Berechtigungen gesetzt."

# Schreibtest im Upload-Verzeichnis

if $DOCKER_EXEC sh -c "echo 'test' > '$UPLOAD_DIR/$TEST_FILE'"; then
  $DOCKER_EXEC chown www-data:www-data "$UPLOAD_DIR/$TEST_FILE"
  log_success "Schreibtest erfolgreich (Datei $TEST_FILE erstellt)."
else
  log_error "Schreibtest im Upload-Verzeichnis fehlgeschlagen!"
  exit 1
fi


# Test-Datei entfernen
$DOCKER_EXEC rm -f "$UPLOAD_DIR/$TEST_FILE"

# ------------------------ Abschlussmeldung ------------------------
divider
log_info "Setup abgeschlossen."
echo -e "🌐 Zugang lokal: \033[1;36mhttp://localhost:8080\033[0m"
INTERNAL_IP=$(hostname -I | awk '{print $1}')
echo -e "🌐 Zugang im LAN: \033[1;36mhttp://$INTERNAL_IP:8080\033[0m"
divider

$DEBUG_MODE && log_success "Detailiertes Debug-Log wurde in $DEBUG_FILE geschrieben."
