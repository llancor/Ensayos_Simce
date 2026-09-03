#!/usr/bin/env bash
set -uo pipefail

# ============================================================
# Gestión Ensayo SIMCE v2.7
# Instalador con autenticación, perfiles y MySQL/MariaDB.
# Compatible: Debian / Ubuntu con Apache 2.4 y PHP 7.4+
# ============================================================

umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd -P)"
LOCAL_SOURCE="${SIMCE_SOURCE_DIR:-$SCRIPT_DIR}"
DEFAULT_REPO_URL="https://github.com/llancor/Ensayos_Simce.git"
REPO_URL="${SIMCE_REPO_URL:-$DEFAULT_REPO_URL}"
GITHUB_SUBDIR="${SIMCE_GITHUB_SUBDIR:-Pruebas_Simce}"
APP_DIR="${SIMCE_APP_DIR:-/var/www/html/pruebas_educativas}"
PRIVATE_DIR="${SIMCE_PRIVATE_DIR:-/var/lib/ensayo-simce}"
DB_PATH="${SIMCE_DATABASE_PATH:-$PRIVATE_DIR/simce.sqlite}"
MYSQL_DATABASE="${SIMCE_MYSQL_DATABASE:-ensayo_simce}"
MYSQL_USER="${SIMCE_MYSQL_USER:-simce_app}"
MYSQL_CREDENTIALS="$PRIVATE_DIR/mysql.conf"
URL_PATH="${SIMCE_URL_PATH:-/pruebas_educativas}"
WEB_USER="${SIMCE_WEB_USER:-www-data}"
WEB_GROUP="${SIMCE_WEB_GROUP:-www-data}"
BACKUP_DIR="${SIMCE_BACKUP_DIR:-/var/backups/ensayo-simce}"
LOG_FILE="${SIMCE_LOG_FILE:-/var/log/ensayo-simce-manager.log}"
APACHE_SECURITY_CONF="/etc/apache2/conf-available/ensayo-simce-security.conf"
APACHE_VHOST_CONF="/etc/apache2/sites-available/ensayo-simce-vhost.conf"
PROXY_CONF="$PRIVATE_DIR/proxy.conf"
PUBLIC_URL="${SIMCE_PUBLIC_URL:-}"
MANAGER_VERSION="2.7.0"

if [[ -t 1 ]]; then
  RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
  CYAN='\033[0;36m'; BLUE='\033[0;34m'; NC='\033[0m'
else
  RED=''; GREEN=''; YELLOW=''; CYAN=''; BLUE=''; NC=''
fi

TEMP_DIRS=()
DEPLOY_SOURCE=""
LAST_BACKUP=""

cleanup(){
  local directory
  for directory in "${TEMP_DIRS[@]}"; do
    if [[ -n "$directory" && "$directory" == /tmp/ensayo-simce.* ]]; then
      if [[ -d "$directory" ]]; then
        rm -rf -- "$directory"
      elif [[ -f "$directory" ]]; then
        rm -f -- "$directory"
      fi
    fi
  done
}
trap cleanup EXIT

log(){
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" 2>/dev/null >> "$LOG_FILE" || true
}
info(){ printf '%b[INFO]%b %s\n' "$CYAN" "$NC" "$*"; log "INFO: $*"; }
ok(){ printf '%b[OK]%b %s\n' "$GREEN" "$NC" "$*"; log "OK: $*"; }
warn(){ printf '%b[AVISO]%b %s\n' "$YELLOW" "$NC" "$*"; log "AVISO: $*"; }
err(){ printf '%b[ERROR]%b %s\n' "$RED" "$NC" "$*" >&2; log "ERROR: $*"; }
die(){ err "$*"; exit 1; }

register_temp_dir(){ TEMP_DIRS+=("$1"); }

require_root(){
  if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    die "Ejecute este instalador como root: sudo bash $0"
  fi
}

validate_safe_path(){
  local label="$1" path="$2"
  if [[ ! "$path" =~ ^/[A-Za-z0-9._@/-]+$ ]]; then
    die "$label debe ser una ruta absoluta sin espacios ni caracteres especiales: $path"
  fi
  case "$path" in
    /|/var|/var/www|/var/www/html|/var/lib|/etc|/tmp)
      die "$label es demasiado amplia y no es segura: $path"
      ;;
  esac
}

validate_configuration(){
  validate_safe_path "SIMCE_APP_DIR" "$APP_DIR"
  validate_safe_path "SIMCE_PRIVATE_DIR" "$PRIVATE_DIR"
  validate_safe_path "SIMCE_DATABASE_PATH" "$DB_PATH"
  validate_safe_path "SIMCE_BACKUP_DIR" "$BACKUP_DIR"
  [[ "$DB_PATH" == "$PRIVATE_DIR/"* ]] || die "SIMCE_DATABASE_PATH debe estar dentro de SIMCE_PRIVATE_DIR."
  if [[ "$APP_DIR" == "$PRIVATE_DIR" || "$APP_DIR" == "$PRIVATE_DIR/"* || "$PRIVATE_DIR" == "$APP_DIR/"* ]]; then
    die "La aplicación pública y la carpeta privada deben estar separadas."
  fi
  [[ "$URL_PATH" =~ ^/[A-Za-z0-9._/-]+$ ]] || die "SIMCE_URL_PATH debe ser una ruta web válida que comience con /"
  [[ ! -L "$APP_DIR" && ! -L "$PRIVATE_DIR" ]] || die "APP_DIR y PRIVATE_DIR no pueden ser enlaces simbólicos."
}

get_ip(){ hostname -I 2>/dev/null | awk '{print $1}'; }
service_state(){ systemctl is-active "$1" 2>/dev/null || true; }
enabled_state(){ systemctl is-enabled "$1" 2>/dev/null || true; }
load_proxy_config(){
  if [[ -s "$PROXY_CONF" ]]; then
    # Archivo generado por este gestor con valores previamente validados.
    # shellcheck disable=SC1090
    source "$PROXY_CONF"
  fi
}

source_is_valid(){
  local source_dir="$1" required
  for required in index.html database.php seguridad.php estudiantes_api.php instalar.php guardar_prueba.php portal_estudiante.php portal_estudiante_api.php resultados_api.php gestion.php gestion_api.php personalizacion.php personalizacion_api.php marca.js migrar_sqlite_mysql.php api.php; do
    [[ -f "$source_dir/$required" ]] || return 1
  done
  return 0
}

source_is_separate_from_target(){
  local source_real app_real
  source_real="$(realpath -m -- "$1")"
  app_real="$(realpath -m -- "$APP_DIR")"
  [[ "$source_real" != "$app_real" && "$source_real" != "$app_real/"* && "$app_real" != "$source_real/"* ]]
}

resolve_deploy_source(){
  local mode="${1:-local}" clone_root
  DEPLOY_SOURCE=""
  case "$mode" in
    local)
      if ! source_is_valid "$LOCAL_SOURCE"; then
        err "La carpeta no contiene una distribución completa de esta versión: $LOCAL_SOURCE"
        err "Debe incluir index.html, database.php, seguridad.php, estudiantes_api.php y los demás archivos del proyecto."
        return 1
      fi
      if ! source_is_separate_from_target "$LOCAL_SOURCE"; then
        err "La carpeta de origen debe ser distinta de la carpeta instalada: $APP_DIR"
        return 1
      fi
      DEPLOY_SOURCE="$(realpath -m -- "$LOCAL_SOURCE")"
      info "Se instalará desde la carpeta local: $DEPLOY_SOURCE"
      ;;
    github)
      if [[ ! "$REPO_URL" =~ ^https://github\.com/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+(\.git)?$ ]]; then
        err "Use una URL HTTPS válida de GitHub, sin credenciales incrustadas."
        return 1
      fi
      clone_root="$(mktemp -d /tmp/ensayo-simce.XXXXXX)" || return 1
      register_temp_dir "$clone_root"
      info "Descargando repositorio desde GitHub: $REPO_URL"
      git clone --depth 1 --branch main -- "$REPO_URL" "$clone_root/repository" || {
        err "No fue posible descargar el repositorio de GitHub."
        return 1
      }
      DEPLOY_SOURCE="$clone_root/repository/$GITHUB_SUBDIR"
      if [[ ! -d "$DEPLOY_SOURCE" ]]; then
        err "No existe la subcarpeta '$GITHUB_SUBDIR' dentro del repositorio."
        return 1
      fi
      if ! source_is_valid "$DEPLOY_SOURCE"; then
        err "La subcarpeta '$GITHUB_SUBDIR' no contiene una distribución completa de Ensayo SIMCE."
        return 1
      fi
      ok "Proyecto GitHub encontrado en: $GITHUB_SUBDIR"
      ;;
    *)
      err "Origen de instalación no válido: $mode"
      return 1
      ;;
  esac
}

prompt_local_source(){
  local selected
  read -r -e -p "Carpeta que contiene esta versión [$SCRIPT_DIR]: " selected
  LOCAL_SOURCE="${selected:-$SCRIPT_DIR}"
}

prompt_github_source(){
  local selected
  read -r -p "Repositorio GitHub [$REPO_URL]: " selected
  REPO_URL="${selected:-$REPO_URL}"
}

php_has_pdo_sqlite(){
  php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' >/dev/null 2>&1
}

php_has_pdo_mysql(){
  php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' >/dev/null 2>&1
}

ensure_php_sqlite_module(){
  local php_version sqlite_package
  if php_has_pdo_sqlite; then
    ok "PDO_SQLite está habilitado para PHP $(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')."
    return 0
  fi

  php_version="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null)"
  [[ "$php_version" =~ ^[0-9]+\.[0-9]+$ ]] || die "No se pudo determinar la versión activa de PHP."
  sqlite_package="php${php_version}-sqlite3"

  warn "PDO_SQLite no está activo para PHP $php_version; se intentará reparar automáticamente."
  if ! apt-cache show "$sqlite_package" >/dev/null 2>&1; then
    info "Actualizando el índice de paquetes para buscar $sqlite_package..."
    DEBIAN_FRONTEND=noninteractive apt-get update || die "Falló apt-get update."
  fi

  if apt-cache show "$sqlite_package" >/dev/null 2>&1; then
    info "Instalando el módulo específico: $sqlite_package"
    DEBIAN_FRONTEND=noninteractive apt-get install -y "$sqlite_package" || die "No se pudo instalar $sqlite_package."
  else
    info "No existe $sqlite_package en los repositorios configurados; reinstalando php-sqlite3."
    DEBIAN_FRONTEND=noninteractive apt-get install -y --reinstall php-sqlite3 || die "No se pudo reinstalar php-sqlite3."
  fi

  if command -v phpenmod >/dev/null 2>&1; then
    phpenmod -v "$php_version" sqlite3 pdo_sqlite >/dev/null 2>&1 || warn "phpenmod no pudo crear todos los enlaces; se verificará el módulo directamente."
  fi
  systemctl restart apache2 >/dev/null 2>&1 || true

  if ! php_has_pdo_sqlite; then
    err "PDO_SQLite continúa deshabilitado para PHP $php_version."
    err "Archivo php.ini de la consola: $(php --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2}')"
    err "Compruebe que el comando php y Apache utilicen la misma versión."
    return 1
  fi
  ok "PDO_SQLite quedó habilitado para PHP $php_version."
}

ensure_php_mysql_module(){
  local php_version mysql_package
  if php_has_pdo_mysql; then
    ok "PDO_MySQL está habilitado para PHP $(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')."
    return 0
  fi

  php_version="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null)"
  [[ "$php_version" =~ ^[0-9]+\.[0-9]+$ ]] || die "No se pudo determinar la versión activa de PHP."
  mysql_package="php${php_version}-mysql"

  warn "PDO_MySQL no está activo para PHP $php_version; se intentará reparar automáticamente."
  if ! apt-cache show "$mysql_package" >/dev/null 2>&1; then
    info "Actualizando el índice de paquetes para buscar $mysql_package..."
    DEBIAN_FRONTEND=noninteractive apt-get update || die "Falló apt-get update."
  fi

  if apt-cache show "$mysql_package" >/dev/null 2>&1; then
    info "Instalando/reinstalando el módulo específico: $mysql_package"
    DEBIAN_FRONTEND=noninteractive apt-get install -y --reinstall "$mysql_package" || die "No se pudo instalar $mysql_package."
  else
    info "No existe $mysql_package en los repositorios configurados; reinstalando php-mysql."
    DEBIAN_FRONTEND=noninteractive apt-get install -y --reinstall php-mysql || die "No se pudo reinstalar php-mysql."
  fi

  if command -v phpenmod >/dev/null 2>&1; then
    phpenmod -v "$php_version" mysqli pdo_mysql >/dev/null 2>&1 || warn "phpenmod no pudo habilitar todos los módulos MySQL; se verificará directamente."
  fi
  systemctl restart apache2 >/dev/null 2>&1 || true

  if ! php_has_pdo_mysql; then
    err "PDO_MySQL continúa deshabilitado para PHP $php_version."
    err "Archivo php.ini de la consola: $(php --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2}')"
    err "Módulos MySQL detectados: $(php -m 2>/dev/null | grep -Ei 'mysql|pdo' | tr '\n' ' ' || true)"
    err "Compruebe que el comando php y Apache utilicen la misma versión."
    return 1
  fi
  ok "PDO_MySQL quedó habilitado para PHP $php_version."
}

install_dependencies(){
  require_root
  validate_configuration
  local packages missing package
  packages=(apache2 php libapache2-mod-php php-mysql php-sqlite3 sqlite3 mariadb-server mariadb-client git rsync curl ca-certificates unzip openssl)
  missing=()
  command -v apt-get >/dev/null 2>&1 || die "Este instalador requiere Debian o Ubuntu con apt-get."
  for package in "${packages[@]}"; do
    if ! dpkg-query -W -f='${Status}' "$package" 2>/dev/null | grep -q 'install ok installed'; then
      missing+=("$package")
    fi
  done
  if (( ${#missing[@]} > 0 )); then
    info "Instalando dependencias: ${missing[*]}"
    DEBIAN_FRONTEND=noninteractive apt-get update || die "Falló apt-get update."
    DEBIAN_FRONTEND=noninteractive apt-get install -y "${missing[@]}" || die "No se pudieron instalar las dependencias."
  else
    ok "Las dependencias ya están instaladas."
  fi
  a2enmod rewrite headers env >/dev/null || die "No se pudieron habilitar los módulos de Apache."
  php -r 'exit(version_compare(PHP_VERSION, "7.4.0", "<") ? 1 : 0);' || die "Se requiere PHP 7.4 o superior."
  ensure_php_sqlite_module || die "PHP no tiene habilitada la extensión PDO_SQLite."
  ensure_php_mysql_module || die "PHP no tiene habilitada la extensión PDO_MySQL."
  systemctl enable --now mariadb >/dev/null 2>&1 || die "No se pudo iniciar MariaDB."
  systemctl enable apache2 >/dev/null 2>&1 || true
  ok "Apache, PHP, PDO_MySQL y MariaDB están disponibles."
}

configure_mysql(){
  local mysql_password
  mkdir -p -- "$PRIVATE_DIR" || die "No se pudo crear la carpeta privada."
  if [[ -s "$MYSQL_CREDENTIALS" ]]; then
    # shellcheck disable=SC1090
    source "$MYSQL_CREDENTIALS"
    mysql_password="${SIMCE_DB_PASSWORD:-}"
  else
    mysql_password="$(openssl rand -hex 24)" || die "No se pudo generar la contraseña MySQL."
    printf 'SIMCE_DB_PASSWORD=%q\n' "$mysql_password" > "$MYSQL_CREDENTIALS"
    chmod 600 "$MYSQL_CREDENTIALS"
  fi
  [[ -n "$mysql_password" ]] || die "La contraseña MySQL guardada está vacía."
  mariadb -uroot <<SQL || die "No se pudo preparar la base MySQL."
CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$MYSQL_USER'@'localhost' IDENTIFIED BY '$mysql_password';
ALTER USER '$MYSQL_USER'@'localhost' IDENTIFIED BY '$mysql_password';
GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
  export SIMCE_DB_DSN="mysql:host=localhost;dbname=$MYSQL_DATABASE;charset=utf8mb4" SIMCE_DB_USER="$MYSQL_USER" SIMCE_DB_PASSWORD="$mysql_password"
}

database_integrity_ok(){
  local database="$1" result
  [[ -f "$database" ]] || return 1
  result="$(sqlite3 "$database" 'PRAGMA integrity_check;' 2>/dev/null | head -n 1)"
  [[ "$result" == "ok" ]]
}

backup_app(){
  require_root
  validate_configuration
  configure_mysql
  local stamp stage archive database_source
  stamp="$(date '+%Y%m%d_%H%M%S')"
  stage="$(mktemp -d /tmp/ensayo-simce.XXXXXX)" || die "No se pudo crear el directorio temporal."
  register_temp_dir "$stage"
  archive="$BACKUP_DIR/ensayo-simce_${stamp}.tar.gz"
  mkdir -p -- "$BACKUP_DIR" "$stage/aplicacion" "$stage/datos" "$stage/apache" || die "No se pudo preparar el respaldo."
  chmod 700 "$BACKUP_DIR" || die "No se pudo proteger la carpeta de respaldos."
  if [[ -d "$APP_DIR" ]]; then
    rsync -a --exclude='data/simce.sqlite*' "$APP_DIR/" "$stage/aplicacion/" || die "Falló la copia de la aplicación."
  fi
  database_source=""
  if [[ -f "$DB_PATH" ]]; then
    database_source="$DB_PATH"
  elif [[ -f "$APP_DIR/data/simce.sqlite" ]]; then
    database_source="$APP_DIR/data/simce.sqlite"
  fi
  if [[ -n "$database_source" ]]; then
    if command -v sqlite3 >/dev/null 2>&1 && database_integrity_ok "$database_source"; then
      sqlite3 "$database_source" ".backup '$stage/datos/simce.sqlite'" || die "No se pudo respaldar la base SQLite."
    else
      cp -a -- "$database_source" "$stage/datos/simce.sqlite" || die "No se pudo copiar la base de datos."
      warn "La base se respaldó sin verificar su integridad."
    fi
  fi
  mariadb-dump --single-transaction -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" > "$stage/datos/ensayo_simce.sql" \
    || die "No se pudo respaldar MySQL."
  chmod 600 "$stage/datos/ensayo_simce.sql"
  [[ -f "$APACHE_SECURITY_CONF" ]] && cp -a -- "$APACHE_SECURITY_CONF" "$stage/apache/"
  [[ -f "$APACHE_VHOST_CONF" ]] && cp -a -- "$APACHE_VHOST_CONF" "$stage/apache/"
  if [[ ! -d "$APP_DIR" && -z "$database_source" ]]; then
    warn "No hay una instalación ni una base de datos para respaldar."
    return 0
  fi
  tar -C "$stage" -czf "$archive" . || die "No se pudo crear el archivo de respaldo."
  chmod 600 "$archive"
  LAST_BACKUP="$archive"
  ok "Respaldo creado: $archive"
  warn "Contiene datos personales; manténgalo cifrado y con acceso restringido."
}

preflight_databases(){
  local candidate=""
  if [[ -f "$DB_PATH" ]]; then
    candidate="$DB_PATH"
  elif [[ -f "$APP_DIR/data/simce.sqlite" ]]; then
    candidate="$APP_DIR/data/simce.sqlite"
  elif [[ -f "$DEPLOY_SOURCE/data/simce.sqlite" ]]; then
    candidate="$DEPLOY_SOURCE/data/simce.sqlite"
  fi
  if [[ -n "$candidate" ]]; then
    database_integrity_ok "$candidate" || die "La base SQLite no supera la verificación de integridad: $candidate"
  fi
}

deploy_files(){
  [[ -n "$DEPLOY_SOURCE" ]] || die "No hay una fuente preparada para instalar."
  mkdir -p -- "$APP_DIR" "$APP_DIR/data" "$APP_DIR/pruebas" "$APP_DIR/uploads/marca" || die "No se pudo crear la carpeta de instalación."
  info "Copiando la aplicación sin sobrescribir la base ni las pruebas existentes..."
  rsync -a --delete \
    --exclude='.git/' \
    --exclude='.agents/' \
    --exclude='.codex/' \
    --exclude='node_modules/' \
    --exclude='pruebas/' \
    --exclude='data/setup.key' \
    --exclude='data/*.sqlite' \
    --exclude='data/*.sqlite-*' \
    --exclude='data/*.db' \
    "$DEPLOY_SOURCE/" "$APP_DIR/" || die "Falló la copia de archivos."
  if [[ -d "$DEPLOY_SOURCE/pruebas" ]]; then
    # Actualiza pruebas incluidas que fueron corregidas, pero no borra pruebas adicionales del servidor.
    rsync -a "$DEPLOY_SOURCE/pruebas/" "$APP_DIR/pruebas/" || die "Falló la copia de las pruebas incluidas."
  fi
}

initialize_database(){
  local candidate=""
  mkdir -p -- "$PRIVATE_DIR" || die "No se pudo crear la carpeta privada de datos."
  configure_mysql
  [[ -f "$DB_PATH" ]] && candidate="$DB_PATH"
  [[ -z "$candidate" && -f "$APP_DIR/data/simce.sqlite" ]] && candidate="$APP_DIR/data/simce.sqlite"
  [[ -z "$candidate" && -n "$DEPLOY_SOURCE" && -f "$DEPLOY_SOURCE/data/simce.sqlite" ]] && candidate="$DEPLOY_SOURCE/data/simce.sqlite"
  php -r 'require $argv[1]; simceDatabase();' "$APP_DIR/database.php" || die "PHP no pudo inicializar el esquema MySQL."
  if [[ -n "$candidate" ]]; then
    database_integrity_ok "$candidate" || die "La base SQLite anterior no supera la verificación de integridad."
    info "Migrando cuentas y estudiantes desde SQLite a MySQL..."
    php "$APP_DIR/migrar_sqlite_mysql.php" "$candidate" || die "Falló la migración de SQLite a MySQL."
  fi
  rm -f -- \
    "$APP_DIR/data/simce.sqlite" \
    "$APP_DIR/data/simce.sqlite-wal" \
    "$APP_DIR/data/simce.sqlite-shm" \
    "$APP_DIR/data/simce.sqlite-journal"
  ok "Base MySQL/MariaDB activa: $MYSQL_DATABASE (credenciales en $MYSQL_CREDENTIALS)"
}

ensure_setup_key(){
  local admin_count setup_key
  setup_key="$APP_DIR/data/setup.key"
  configure_mysql
  admin_count="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM administradores;' 2>/dev/null || printf '0')"
  if [[ "$admin_count" =~ ^[0-9]+$ ]] && (( admin_count > 0 )); then
    rm -f -- "$setup_key"
    ok "Ya existe un administrador; el instalador web permanece cerrado."
    return 0
  fi
  if [[ ! -s "$setup_key" ]]; then
    openssl rand -hex 32 > "$setup_key" || die "No se pudo generar la clave inicial."
    ok "Se generó una clave inicial única para crear el administrador."
  else
    warn "Se conservó la clave inicial pendiente que ya existía en el servidor."
  fi
  warn "Lea la clave sólo en el servidor: sudo cat $setup_key"
}

apply_permissions(){
  require_root
  validate_configuration
  [[ -d "$APP_DIR" ]] || die "La aplicación no está instalada en $APP_DIR"
  id "$WEB_USER" >/dev/null 2>&1 || die "No existe el usuario web $WEB_USER"
  getent group "$WEB_GROUP" >/dev/null 2>&1 || die "No existe el grupo web $WEB_GROUP"
  info "Aplicando permisos de código, pruebas y datos privados..."
  chown -R -h root:"$WEB_GROUP" "$APP_DIR" || die "No se pudo asignar el propietario del código."
  find "$APP_DIR" -xdev -type d -exec chmod 750 {} + || die "No se pudieron proteger las carpetas del código."
  find "$APP_DIR" -xdev -type f -exec chmod 640 {} + || die "No se pudieron proteger los archivos del código."
  mkdir -p -- "$APP_DIR/pruebas" "$APP_DIR/uploads/marca" "$APP_DIR/data" "$PRIVATE_DIR" || die "No se pudieron preparar las carpetas escribibles."
  chown -R -h "$WEB_USER":"$WEB_GROUP" "$APP_DIR/pruebas" || die "No se pudo asignar la carpeta de pruebas."
  find "$APP_DIR/pruebas" -xdev -type d -exec chmod 770 {} + || die "No se pudieron ajustar las carpetas de pruebas."
  find "$APP_DIR/pruebas" -xdev -type f -exec chmod 660 {} + || die "No se pudieron ajustar los archivos de pruebas."
  chown -R -h "$WEB_USER":"$WEB_GROUP" "$APP_DIR/uploads" || die "No se pudo asignar la carpeta de imágenes."
  find "$APP_DIR/uploads" -xdev -type d -exec chmod 770 {} + || die "No se pudieron ajustar las carpetas de imágenes."
  find "$APP_DIR/uploads" -xdev -type f -exec chmod 660 {} + || die "No se pudieron ajustar las imágenes."
  chown -R -h root:"$WEB_GROUP" "$APP_DIR/data" || die "No se pudo asignar la carpeta de configuración."
  find "$APP_DIR/data" -xdev -type d -exec chmod 770 {} + || die "No se pudo proteger la carpeta de configuración."
  find "$APP_DIR/data" -xdev -type f -exec chmod 640 {} + || die "No se pudieron proteger los archivos de configuración."
  chown -R -h "$WEB_USER":"$WEB_GROUP" "$PRIVATE_DIR" || die "No se pudo asignar la base privada."
  find "$PRIVATE_DIR" -xdev -type d -exec chmod 770 {} + || die "No se pudieron ajustar las carpetas privadas."
  find "$PRIVATE_DIR" -xdev -type f -exec chmod 660 {} + || die "No se pudieron ajustar los archivos privados."
  [[ -f "$MYSQL_CREDENTIALS" ]] && chown root:root "$MYSQL_CREDENTIALS" && chmod 600 "$MYSQL_CREDENTIALS"
  ok "Permisos aplicados. Apache puede escribir pruebas; las credenciales MySQL quedan protegidas."
}

configure_apache_security(){
  require_root
  validate_configuration
  local config_temp
  config_temp="$(mktemp /tmp/ensayo-simce.XXXXXX)" || die "No se pudo crear la configuración temporal."
  register_temp_dir "$config_temp"
  info "Configurando Apache para proteger la base y los archivos internos..."
  cat > "$config_temp" <<EOF
# Gestionado por Gestion_Ensayo_SIMCE_v2.0.sh
<Directory "$APP_DIR">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    SetEnv SIMCE_DB_DSN "mysql:host=localhost;dbname=$MYSQL_DATABASE;charset=utf8mb4"
    SetEnv SIMCE_DB_USER "$MYSQL_USER"
    SetEnv SIMCE_DB_PASSWORD "$SIMCE_DB_PASSWORD"
    <FilesMatch "^(database|seguridad)\\.php$|(^\\.|\\.(?:sqlite(?:-(?:wal|shm|journal))?|db|key|log|ini|env))$">
        Require all denied
    </FilesMatch>
</Directory>
<Directory "$APP_DIR/data">
    Require all denied
</Directory>
<Directory "$PRIVATE_DIR">
    Require all denied
</Directory>
EOF
  [[ -s "$config_temp" ]] || die "No se pudo escribir la configuración de seguridad de Apache."
  install -m 640 -o root -g root "$config_temp" "$APACHE_SECURITY_CONF" || die "No se pudo instalar la configuración de Apache."
  a2enmod rewrite headers env >/dev/null || die "No se pudieron habilitar los módulos necesarios."
  a2enconf ensayo-simce-security >/dev/null || die "No se pudo habilitar la configuración de seguridad."
  apache2ctl configtest || die "Apache rechazó la configuración; no se reinició el servicio."
  systemctl restart apache2 || die "No se pudo reiniciar Apache."
  ok "Protección de Apache habilitada."
}

install_or_update(){
  local action="$1" source_mode="$2" ip admin_count student_count
  require_root
  validate_configuration
  install_dependencies
  configure_mysql
  load_proxy_config
  resolve_deploy_source "$source_mode" || return 1
  preflight_databases
  if [[ -d "$APP_DIR" || -f "$DB_PATH" ]]; then
    info "Creando un respaldo antes de $action..."
    backup_app
  fi
  deploy_files
  initialize_database
  ensure_setup_key
  apply_permissions
  configure_apache_security
  ip="$(get_ip)"
  admin_count="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM administradores;' 2>/dev/null || printf '?')"
  student_count="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM alumnos;' 2>/dev/null || printf '?')"
  ok "La versión con base de datos quedó instalada correctamente."
  printf '\n  Alumnos en la base: %s\n' "$student_count"
  printf '  Administradores: %s\n' "$admin_count"
  printf '  Acceso interno: http://%s%s/\n' "${ip:-IP_DEL_SERVIDOR}" "$URL_PATH"
  if [[ -n "$PUBLIC_URL" ]]; then
    printf '  Acceso público:  %s\n' "$PUBLIC_URL"
  else
    printf '  Acceso público:  no registrado; use la opción 10 para configurar/verificar el proxy\n'
  fi
  if [[ "$admin_count" == "0" ]]; then
    printf '  Crear administrador: http://%s%s/instalar.php\n' "${ip:-IP_DEL_SERVIDOR}" "$URL_PATH"
  fi
  printf '\n'
  warn "Para Internet publique únicamente una URL HTTPS mediante firewall o proxy inverso."
  warn "No exponga directamente el puerto HTTP del servidor de aplicación."
}

install_local(){ install_or_update "instalar desde carpeta local" "local"; }
install_github(){ install_or_update "instalar desde GitHub" "github"; }
update_local(){ install_or_update "actualizar desde carpeta local" "local"; }
update_github(){ install_or_update "actualizar desde GitHub" "github"; }

install_local_interactive(){ prompt_local_source; install_local; }
install_github_interactive(){ prompt_github_source; install_github; }
update_local_interactive(){ prompt_local_source; update_local; }
update_github_interactive(){ prompt_github_source; update_github; }

# Alias conservados para instalaciones automatizadas anteriores.
install_app(){ install_local; }
update_app(){ update_local; }

status(){
  require_root
  validate_configuration
  local apache_active apache_enabled mariadb_active php_version pdo_state db_state admins students setup_state ip app_state vhost_state
  configure_mysql
  load_proxy_config
  apache_active="$(service_state apache2)"
  apache_enabled="$(enabled_state apache2)"
  mariadb_active="$(service_state mariadb)"
  php_version="$(php -r 'echo PHP_VERSION;' 2>/dev/null || printf 'no instalado')"
  pdo_state="no disponible"
  php_has_pdo_mysql && pdo_state="disponible"
  db_state="ERROR"; admins="?"; students="?"; setup_state="no corresponde"; ip="$(get_ip)"
  [[ -f "$APP_DIR/index.html" ]] && app_state="instalada" || app_state="no instalada"
  if mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT 1;' >/dev/null 2>&1; then
    db_state="conectada"
    admins="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM administradores;' 2>/dev/null || printf '?')"
    students="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM alumnos;' 2>/dev/null || printf '?')"
  fi
  [[ -s "$APACHE_VHOST_CONF" ]] && vhost_state="configurado" || vhost_state="no configurado por este gestor"
  if [[ "$admins" == "0" ]]; then
    if [[ -s "$APP_DIR/data/setup.key" ]]; then setup_state="pendiente"; else setup_state="falta clave"; fi
  elif [[ "$admins" =~ ^[1-9][0-9]*$ ]]; then
    setup_state="cerrado"
  fi
  printf '%bEstado de Ensayo SIMCE%b\n\n' "$BLUE" "$NC"
  printf '  Gestor:             %s\n' "$MANAGER_VERSION"
  printf '  Aplicación:         %s\n' "$app_state"
  printf '  Apache activo:      %s\n' "$apache_active"
  printf '  Apache al inicio:   %s\n' "$apache_enabled"
  printf '  MariaDB activo:     %s\n' "$mariadb_active"
  printf '  PHP:                %s\n' "$php_version"
  printf '  PDO_MySQL:          %s\n' "$pdo_state"
  printf '  Base MySQL:         %s\n' "$db_state"
  printf '  Estudiantes:        %s\n' "$students"
  printf '  Administradores:    %s\n' "$admins"
  printf '  Configuración:      %s\n' "$setup_state"
  printf '  VirtualHost proxy:  %s\n' "$vhost_state"
  printf '  URL interna:        http://%s%s/\n' "${ip:-IP_DEL_SERVIDOR}" "$URL_PATH"
  printf '  URL pública:        %s\n' "${PUBLIC_URL:-no registrada; ejecute la opción 10}"
}

check_http_code(){
  local label="$1" expected="$2" url="$3" code
  if ! code="$(curl -sS --max-time 10 -o /dev/null -w '%{http_code}' "$url" 2>/dev/null)"; then
    code="${code:-000}"
  fi
  if [[ ",$expected," == *",$code,"* ]]; then
    ok "$label: HTTP $code"
    return 0
  fi
  err "$label: HTTP $code; se esperaba $expected"
  return 1
}

diagnose(){
  require_root
  validate_configuration
  local failures=0 public_code
  configure_mysql
  load_proxy_config
  info "Ejecutando diagnóstico local..."
  apache2ctl configtest || failures=$((failures + 1))
  if php_has_pdo_mysql; then ok "PDO_MySQL está habilitado."; else err "PDO_MySQL no está habilitado."; failures=$((failures + 1)); fi
  if mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT 1;' >/dev/null 2>&1; then ok "Conexión MySQL correcta."; else err "No se pudo conectar a MySQL."; failures=$((failures + 1)); fi
  if runuser -u "$WEB_USER" -- test -w "$APP_DIR/pruebas" && runuser -u "$WEB_USER" -- test -w "$APP_DIR/uploads"; then
    ok "Apache puede escribir pruebas y archivos personalizados."
  else
    err "El usuario $WEB_USER no tiene todos los permisos requeridos."
    failures=$((failures + 1))
  fi
  check_http_code "API pública" "200" "http://127.0.0.1${URL_PATH}/api.php" || failures=$((failures + 1))
  check_http_code "Panel docente sin sesión" "302" "http://127.0.0.1${URL_PATH}/pruebas_educativas.php" || failures=$((failures + 1))
  check_http_code "API de estudiantes sin sesión" "401" "http://127.0.0.1${URL_PATH}/estudiantes_api.php" || failures=$((failures + 1))
  check_http_code "Bloqueo de la base pública" "403,404" "http://127.0.0.1${URL_PATH}/data/simce.sqlite" || failures=$((failures + 1))
  check_http_code "Bloqueo del código interno" "403,404" "http://127.0.0.1${URL_PATH}/database.php" || failures=$((failures + 1))
  if [[ -n "$PUBLIC_URL" ]]; then
    public_code="$(curl -sS -L --max-time 15 -o /dev/null -w '%{http_code}' "$PUBLIC_URL" 2>/dev/null || printf '000')"
    if [[ "$public_code" =~ ^(200|301|302)$ ]]; then ok "Proxy público responde: $PUBLIC_URL (HTTP $public_code)"; else warn "La URL pública no respondió desde el servidor: $PUBLIC_URL (HTTP $public_code). Verifíquela desde otra red."; fi
  else
    warn "No hay URL pública registrada. Ejecute la opción 10 para registrarla."
  fi
  if (( failures == 0 )); then
    ok "Diagnóstico completado sin fallas locales."
    warn "DNS, certificado HTTPS, NAT y firewall deben comprobarse desde otra red."
  else
    err "Diagnóstico terminado con $failures falla(s). Revise los mensajes y logs."
    return 1
  fi
}

show_logs(){
  require_root
  printf '%bÚltimas líneas del log de Apache%b\n' "$BLUE" "$NC"
  journalctl -u apache2 -n 80 --no-pager 2>/dev/null || true
  [[ -f /var/log/apache2/error.log ]] && tail -n 80 /var/log/apache2/error.log
}

restart_apache(){
  require_root
  apache2ctl configtest || die "Apache tiene errores de configuración."
  systemctl restart apache2 || die "No se pudo reiniciar Apache."
  ok "Apache reiniciado."
}

ensure_user_management_ready(){
  require_root
  validate_configuration
  [[ -f "$APP_DIR/database.php" ]] || die "No se encontró database.php. Instale primero esta versión."
  command -v php >/dev/null 2>&1 || die "PHP no está instalado."
  id "$WEB_USER" >/dev/null 2>&1 || die "No existe el usuario web $WEB_USER."
  php_has_pdo_mysql || die "PHP no tiene habilitado PDO_MySQL."
  configure_mysql
  runuser -u "$WEB_USER" -- env SIMCE_DB_DSN="$SIMCE_DB_DSN" SIMCE_DB_USER="$SIMCE_DB_USER" SIMCE_DB_PASSWORD="$SIMCE_DB_PASSWORD" php -r 'require $argv[1]; simceDatabase();' "$APP_DIR/database.php" \
    || die "No se pudo comprobar el esquema de administradores."
}

list_administrators(){
  ensure_user_management_ready
  local admin_count
  admin_count="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e 'SELECT COUNT(*) FROM administradores;' 2>/dev/null || printf '0')"
  if [[ "$admin_count" == "0" ]]; then
    warn "No existen administradores. Use instalar.php para crear el administrador inicial."
    return 0
  fi

  printf '\n%bAdministradores registrados%b\n\n' "$BLUE" "$NC"
  mariadb --table -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e "SELECT id AS ID,usuario AS Usuario,rol AS Perfil,IF(activo=1,'Activo','Inactivo') AS Estado,created_at AS Creado,COALESCE(last_login_at,'Nunca') AS Ultimo_ingreso FROM administradores ORDER BY id;" || die "No se pudo listar los administradores."
  printf '\n'
  info "Perfiles disponibles: docente, administrador y superadmin."
}

reset_administrator_password(){
  ensure_user_management_ready
  local admin_id exists username state_label password confirmation php_result

  list_administrators
  read -r -p "ID del administrador cuya contraseña desea restablecer: " admin_id
  if [[ ! "$admin_id" =~ ^[1-9][0-9]*$ ]]; then
    err "El ID debe ser un número entero positivo."
    return 1
  fi

  exists="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM administradores WHERE id=$admin_id;" 2>/dev/null || printf '0')"
  if [[ "$exists" != "1" ]]; then
    err "No existe un administrador con ID $admin_id."
    return 1
  fi
  username="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e "SELECT usuario FROM administradores WHERE id=$admin_id;" 2>/dev/null)"
  state_label="$(mariadb --batch --skip-column-names -u"$MYSQL_USER" -p"$SIMCE_DB_PASSWORD" "$MYSQL_DATABASE" -e "SELECT IF(activo=1,'activo','inactivo') FROM administradores WHERE id=$admin_id;" 2>/dev/null)"

  printf 'Restableciendo contraseña de: %s (ID %s, %s)\n' "$username" "$admin_id" "$state_label"
  printf 'Debe contener al menos 12 caracteres, mayúscula, minúscula y número.\n'
  read -r -s -p "Nueva contraseña: " password || return 1
  printf '\n'
  read -r -s -p "Repita la contraseña: " confirmation || { unset password; return 1; }
  printf '\n'

  if (( ${#password} < 12 )) || [[ ! "$password" =~ [A-Z] ]] || [[ ! "$password" =~ [a-z] ]] || [[ ! "$password" =~ [0-9] ]]; then
    unset password confirmation
    err "La contraseña no cumple los requisitos de seguridad."
    return 1
  fi
  if [[ "$password" != "$confirmation" ]]; then
    unset password confirmation
    err "Las contraseñas no coinciden."
    return 1
  fi

  php_result="$(printf '%s' "$password" | runuser -u "$WEB_USER" -- env SIMCE_DB_DSN="$SIMCE_DB_DSN" SIMCE_DB_USER="$SIMCE_DB_USER" SIMCE_DB_PASSWORD="$SIMCE_DB_PASSWORD" php -r '
    require $argv[1];
    $adminId = filter_var($argv[2], FILTER_VALIDATE_INT);
    $password = stream_get_contents(STDIN);
    if ($adminId === false || strlen($password) < 12 || strlen($password) > 4096) {
        exit(2);
    }
    $database = simceDatabase();
    $database->beginTransaction();
    try {
        $statement = $database->prepare("UPDATE administradores SET password_hash = :password_hash WHERE id = :id");
        $statement->execute(array(
            ":password_hash" => password_hash($password, PASSWORD_DEFAULT),
            ":id" => $adminId,
        ));
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException("Administrador no encontrado.");
        }
        $database->exec("DELETE FROM intentos_login");
        $audit = $database->prepare("INSERT INTO auditoria_administracion (accion, detalle, administrador_id) VALUES (:accion, :detalle, NULL)");
        $audit->execute(array(
            ":accion" => "restablecer_password_cli",
            ":detalle" => "Contraseña restablecida para administrador ID " . $adminId,
        ));
        $database->commit();
        echo "ok";
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        exit(3);
    }
  ' "$APP_DIR/database.php" "$admin_id" 2>/dev/null)"
  unset password confirmation

  if [[ "$php_result" != "ok" ]]; then
    err "No se pudo restablecer la contraseña. La base no fue modificada."
    return 1
  fi

  ok "Contraseña restablecida para $username. También se limpiaron los bloqueos de acceso."
  if [[ "$state_label" == "inactivo" ]]; then
    warn "El administrador continúa inactivo y no podrá ingresar hasta ser activado directamente en la base."
  fi
  ok "Las sesiones abiertas con la contraseña anterior quedarán invalidadas automáticamente."
  log "Contraseña restablecida para administrador ID $admin_id mediante el gestor local."
}

manage_administrators(){
  require_root
  validate_configuration
  local user_option
  while true; do
    clear 2>/dev/null || true
    printf '%b============================================%b\n' "$CYAN" "$NC"
    printf '%b   GESTIÓN DE USUARIOS ADMINISTRADORES%b\n' "$CYAN" "$NC"
    printf '%b============================================%b\n\n' "$CYAN" "$NC"
    printf '%b1)%b Listar administradores\n' "$YELLOW" "$NC"
    printf '%b2)%b Restablecer contraseña\n' "$YELLOW" "$NC"
    printf '%b0)%b Volver al menú principal\n\n' "$YELLOW" "$NC"
    read -r -p "Seleccione una opción: " user_option
    case "$user_option" in
      1) list_administrators ;;
      2) reset_administrator_password ;;
      0) return 0 ;;
      *) warn "Opción no válida." ;;
    esac
    printf '\n'
    read -r -p "Presione ENTER para continuar..." _
  done
}

create_vhost(){
  require_root
  validate_configuration
  configure_mysql
  [[ -d "$APP_DIR" ]] || die "Instale primero la aplicación."
  local server_name port trusted_proxy public_url remote_ip_lines config_temp public_code
  read -r -p "Dominio interno del VirtualHost [simce.local]: " server_name
  server_name="${server_name:-simce.local}"
  [[ "$server_name" =~ ^[A-Za-z0-9.-]+$ ]] || die "El nombre de servidor no es válido."
  read -r -p "Puerto interno para el proxy inverso [8080]: " port
  port="${port:-8080}"
  if [[ ! "$port" =~ ^[0-9]+$ ]] || (( port < 1024 || port > 65535 || port == 80 || port == 443 )); then
    die "Use un puerto entre 1024 y 65535, distinto de 80 y 443."
  fi
  read -r -p "IP/CIDR del proxy de confianza [127.0.0.1]: " trusted_proxy
  trusted_proxy="${trusted_proxy:-127.0.0.1}"
  [[ "$trusted_proxy" =~ ^[0-9A-Fa-f:./]+$ ]] || die "La IP o red del proxy no es válida."
  load_proxy_config
  read -r -p "URL pública HTTPS [${PUBLIC_URL:-https://simce.ejemplo.cl/}]: " public_url
  public_url="${public_url:-$PUBLIC_URL}"
  [[ "$public_url" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?(/[A-Za-z0-9._~%/-]*)?$ ]] || die "La URL pública debe ser HTTPS y no puede contener espacios ni parámetros."
  [[ "$public_url" == */ ]] || public_url="$public_url/"
  if ! grep -Eq "^[[:space:]]*Listen[[:space:]]+$port([[:space:]]|$)" /etc/apache2/ports.conf; then
    printf '\nListen %s\n' "$port" >> /etc/apache2/ports.conf
  fi
  a2enmod remoteip >/dev/null || die "No se pudo habilitar mod_remoteip."
  remote_ip_lines="    RemoteIPHeader X-Forwarded-For
    RemoteIPTrustedProxy $trusted_proxy"
  config_temp="$(mktemp /tmp/ensayo-simce.XXXXXX)" || die "No se pudo crear el VirtualHost temporal."
  register_temp_dir "$config_temp"
  cat > "$config_temp" <<EOF
# Gestionado por Gestion_Ensayo_SIMCE_v2.0.sh
<VirtualHost *:$port>
    ServerName $server_name
    DocumentRoot "$APP_DIR"
$remote_ip_lines
    <Directory "$APP_DIR">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        SetEnv SIMCE_DB_DSN "mysql:host=localhost;dbname=$MYSQL_DATABASE;charset=utf8mb4"
        SetEnv SIMCE_DB_USER "$MYSQL_USER"
        SetEnv SIMCE_DB_PASSWORD "$SIMCE_DB_PASSWORD"
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/ensayo-simce-error.log
    CustomLog \${APACHE_LOG_DIR}/ensayo-simce-access.log combined
</VirtualHost>
EOF
  [[ -s "$config_temp" ]] || die "No se pudo escribir el VirtualHost."
  install -m 640 -o root -g root "$config_temp" "$APACHE_VHOST_CONF" || die "No se pudo instalar el VirtualHost."
  a2ensite ensayo-simce-vhost >/dev/null || die "No se pudo habilitar el VirtualHost."
  apache2ctl configtest || die "Apache rechazó el VirtualHost; no se reinició."
  systemctl restart apache2 || die "No se pudo reiniciar Apache."
  PUBLIC_URL="$public_url"
  printf 'PUBLIC_URL=%q\nPROXY_INTERNAL_NAME=%q\nPROXY_INTERNAL_PORT=%q\nPROXY_TRUSTED_IP=%q\n' "$PUBLIC_URL" "$server_name" "$port" "$trusted_proxy" > "$PROXY_CONF" \
    || die "No se pudo guardar la configuración del proxy."
  chown root:root "$PROXY_CONF" && chmod 600 "$PROXY_CONF"
  ok "VirtualHost interno disponible en http://$(get_ip):$port/"
  check_http_code "VirtualHost interno con Host correcto" "200" "http://127.0.0.1:$port/" || warn "Revise el VirtualHost interno antes de configurar el proxy."
  public_code="$(curl -sS -L --max-time 15 -o /dev/null -w '%{http_code}' "$PUBLIC_URL" 2>/dev/null || printf '000')"
  if [[ "$public_code" =~ ^(200|301|302)$ ]]; then ok "URL pública responde: $PUBLIC_URL (HTTP $public_code)"; else warn "La URL pública no pudo verificarse desde este servidor: $PUBLIC_URL (HTTP $public_code). Pruébela también desde una red externa."; fi
  warn "Permita ese puerto sólo desde el proxy $trusted_proxy."
  warn "El proxy público debe usar HTTPS y enviar Host, X-Forwarded-For y X-Forwarded-Proto=https."
}

uninstall_app(){
  require_root
  validate_configuration
  printf '%bEsta acción quitará el código y la configuración Apache.%b\n' "$YELLOW" "$NC"
  printf 'La base privada se conservará salvo que se confirme por separado.\n'
  read -r -p "Escriba DESINSTALAR para continuar: " confirmation
  [[ "$confirmation" == "DESINSTALAR" ]] || { warn "Operación cancelada."; return 0; }
  backup_app
  rm -rf -- "$APP_DIR"
  a2dissite ensayo-simce-vhost >/dev/null 2>&1 || true
  a2disconf ensayo-simce-security >/dev/null 2>&1 || true
  rm -f -- "$APACHE_VHOST_CONF" "$APACHE_SECURITY_CONF"
  read -r -p "Para borrar también alumnos y administradores escriba ELIMINAR_DATOS: " data_confirmation
  if [[ "$data_confirmation" == "ELIMINAR_DATOS" ]]; then
    rm -rf -- "$PRIVATE_DIR"
    warn "La base privada fue eliminada. Puede recuperarse desde: ${LAST_BACKUP:-el respaldo más reciente}"
  else
    ok "La base privada se conservó en $PRIVATE_DIR"
  fi
  apache2ctl configtest && systemctl reload apache2 || true
  ok "Aplicación desinstalada. Respaldo: ${LAST_BACKUP:-no creado}"
}

print_help(){
  cat <<EOF
Uso: sudo bash $0 [comando]

Comandos:
  install-dependencies  Instala Apache, PHP, MariaDB y PDO_MySQL
  install-local         Instala desde SIMCE_SOURCE_DIR o la carpeta del script
  install-github        Instala desde SIMCE_REPO_URL o el repositorio predeterminado
  update-local          Actualiza desde la carpeta local
  update-github         Actualiza desde GitHub
  install               Alias compatible de install-local
  update                Alias compatible de update-local
  status                Muestra el estado de la instalación
  permissions           Repara permisos
  backup                Crea un respaldo privado
  diagnose              Ejecuta comprobaciones locales
  vhost                 Configura un puerto interno para proxy inverso
  logs                  Muestra logs de Apache
  restart               Valida y reinicia Apache
  users                 Lista administradores o restablece contraseñas
  uninstall             Desinstala con confirmaciones y respaldo

Variables opcionales:
  SIMCE_SOURCE_DIR, SIMCE_REPO_URL, SIMCE_APP_DIR, SIMCE_PRIVATE_DIR,
  SIMCE_MYSQL_DATABASE, SIMCE_MYSQL_USER, SIMCE_URL_PATH, SIMCE_BACKUP_DIR y SIMCE_GITHUB_SUBDIR.
EOF
}
clave_install(){
  require_root

  local KEY_FILE="/var/www/html/pruebas_educativas/data/setup.key"

  [[ -f "$KEY_FILE" ]] || die "No existe el archivo setup.key."

  ok "Setup key:"
  cat "$KEY_FILE"
}
menu(){
  local option
  require_root
  validate_configuration
  while true; do
    clear 2>/dev/null || true
    printf '%b============================================%b\n' "$CYAN" "$NC"
    printf '%b   GESTIÓN ENSAYO SIMCE — MySQL/Internet%b\n' "$CYAN" "$NC"
    printf '%b============================================%b\n\n' "$CYAN" "$NC"
    printf '%b1)%b  %bInstalar%b dependencias / PHP - Apache2\n' "$YELLOW" "$NC" "$YELLOW" "$NC"
    printf '%b2)%b  %bInstalar%b / reinstalar desde GitHub\n' "$YELLOW" "$NC" "$CYAN" "$NC"
    printf '%b3)%b  %bInstalar%b / reinstalar desde una carpeta local\n' "$YELLOW" "$NC" "$CYAN" "$NC"
    printf '%b4)%b  Ver estado\n' "$YELLOW" "$NC"
    printf '%b5)%b  %bActualizar%b desde GitHub conservando datos\n' "$YELLOW" "$NC" "$GREEN" "$NC"
    printf '%b6)%b  %bActualizar%b desde una carpeta local\n' "$YELLOW" "$NC" "$GREEN" "$NC"
    printf '%b7)%b  Reparar permisos\n' "$YELLOW" "$NC"
    printf '%b8)%b  Crear respaldo\n' "$YELLOW" "$NC"
    printf '%b9)%b  Diagnóstico completo\n' "$YELLOW" "$NC"
    printf '%b10)%b Configurar VirtualHost para proxy HTTPS\n' "$YELLOW" "$NC"
    printf '%b11)%b Ver logs de Apache\n' "$YELLOW" "$NC"
    printf '%b12)%b Reiniciar Apache\n' "$YELLOW" "$NC"
    printf '%b13)%b Gestión de usuarios administradores\n' "$YELLOW" "$NC"
    printf '%b14)%b Desinstalar Ensayo SIMCE\n\n' "$YELLOW" "$NC"
	printf '%b15)%b Clave Instalacion Ensayo SIMCE\n\n' "$YELLOW" "$NC"
	printf '%b0)%b  Salir\n\n' "$YELLOW" "$NC"
    read -r -p "Seleccione una opción: " option
    case "$option" in
      1) install_dependencies ;;
      2) install_github_interactive ;;
      3) install_local_interactive ;;
      4) status ;;
      5) update_github_interactive ;;
      6) update_local_interactive ;;
      7) apply_permissions ;;
      8) backup_app ;;
      9) diagnose ;;
      10) create_vhost ;;
      11) show_logs ;;
      12) restart_apache ;;
      13) manage_administrators ;;
      14) uninstall_app ;;
	  15) clave_install ;;
      0) ok "Saliendo..."; exit 0 ;;
      *) warn "Opción no válida." ;;
    esac
    printf '\n'
    read -r -p "Presione ENTER para continuar..." _
  done
}

case "${1:-menu}" in
  install-dependencies) install_dependencies ;;
  install-local) install_local ;;
  install-github) install_github ;;
  update-local) update_local ;;
  update-github) update_github ;;
  install) install_app ;;
  update) update_app ;;
  status) status ;;
  permissions) apply_permissions ;;
  backup) backup_app ;;
  diagnose) diagnose ;;
  vhost) create_vhost ;;
  logs) show_logs ;;
  restart) restart_apache ;;
  users) manage_administrators ;;
  uninstall) uninstall_app ;;
  menu) menu ;;
  -h|--help|help) print_help ;;
  *) err "Comando no reconocido: $1"; print_help; exit 2 ;;
esac
