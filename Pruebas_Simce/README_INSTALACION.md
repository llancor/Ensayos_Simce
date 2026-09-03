# Pruebas SIMCE — instalación para acceso por Internet

La aplicación quedó preparada para ejecutarse desde una URL pública sin depender de la IP privada `192.168.11.253`. Las rutas internas son relativas, por lo que funciona tanto en la raíz de un dominio como dentro de una carpeta.

## Instalación automatizada en Debian o Ubuntu

Copie la carpeta completa de esta versión al servidor y ejecute desde ella:

```bash
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh install
```

El instalador usa los archivos locales que están junto al script, instala Apache, PHP y `PDO_SQLite`, conserva las pruebas existentes y migra la base a `/var/lib/ensayo-simce/simce.sqlite`, fuera del directorio público. También genera una clave inicial nueva si todavía no existe un administrador; la clave incluida en el paquete nunca se copia al servidor.

Para aplicar posteriormente otra copia de esta misma versión sin borrar alumnos, administradores ni pruebas adicionales:

```bash
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh update
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh diagnose
```

El menú interactivo continúa disponible ejecutando el script sin argumentos. Use la opción de VirtualHost cuando Apache quede detrás de un proxy inverso HTTPS.

En el menú, la opción **2** instala desde GitHub y solicita la URL del repositorio; la opción **3** instala desde una carpeta local. El repositorio de GitHub debe contener esta versión completa, incluidos `database.php`, `seguridad.php`, `estudiantes_api.php` e `instalar.php`; el gestor rechazará automáticamente una versión antigua o incompleta.

Los mismos orígenes están disponibles sin menú:

```bash
# Carpeta local (por defecto, la carpeta que contiene el script)
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh install-local

# GitHub (puede definir otra URL mediante SIMCE_REPO_URL)
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh install-github
```

La opción **13 — Gestión de usuarios administradores** permite listar las cuentas y restablecer la contraseña de cualquiera de ellas, incluido el primer administrador, que se identifica como **Principal**. Todas las cuentas poseen actualmente los mismos permisos administrativos. La contraseña se solicita de forma oculta, nunca se muestra ni se guarda en el log, y las sesiones abiertas con la contraseña anterior quedan invalidadas. También puede abrirse el submenú directamente con:

```bash
sudo bash Gestion_Ensayo_SIMCE_v2.0.sh users
```

## Requisitos

- Apache 2.4 con PHP 7.4 o superior.
- Extensión PHP `PDO_SQLite` habilitada.
- Módulos de Apache `mod_rewrite` y `mod_headers` habilitados.
- `AllowOverride All` para que Apache aplique los archivos `.htaccess`.
- Certificado HTTPS válido. No publique el puerto HTTP sin TLS.
- Permiso de escritura para el usuario de Apache en `data` y `pruebas`.

Si aparece `PHP no tiene habilitada la extensión PDO_SQLite`, vuelva a ejecutar la opción **1 — Instalar dependencias**. El gestor detecta la versión activa de PHP, instala su paquete `phpX.Y-sqlite3`, habilita `sqlite3` y `pdo_sqlite`, reinicia Apache y comprueba nuevamente la extensión.

Si se usa Nginx o IIS, los archivos `.htaccess` no tienen efecto. En ese caso hay que reproducir en la configuración del servidor estas reglas: bloquear completamente `data`, archivos SQLite y claves; impedir el acceso estático directo a `pruebas_educativas.html` y `estudiantes.html`; y dirigir esas dos rutas a sus envoltorios `.php`.

## Puesta en marcha

1. Copie toda esta carpeta al servidor web, incluida `.htaccess`.
2. Confirme que `data/simce.sqlite` y la carpeta `pruebas` son escribibles por PHP, pero no descargables desde el navegador.
3. Abra `https://SU-DOMINIO/RUTA/instalar.php` desde una red de confianza.
4. Lea la clave inicial directamente en el servidor desde `data/setup.key`; no la envíe por correo ni mensajería.
5. Cree el usuario administrador con una contraseña única de al menos 12 caracteres. Al terminar, `setup.key` se elimina automáticamente.
6. Cierre sesión y compruebe que `pruebas_educativas.php` y `estudiantes.php` exigen autenticación.
7. Compruebe desde una conexión externa que la dirección pública usa HTTPS y que `data/simce.sqlite`, `data/setup.key`, `database.php` y `seguridad.php` responden con acceso denegado.

Para mayor aislamiento, la base puede guardarse fuera de la carpeta pública definiendo la variable de entorno `SIMCE_DATABASE_PATH` con una ruta absoluta, por ejemplo una carpeta privada sólo accesible por PHP. La base incluida en `data/simce.sqlite` sirve como configuración inicial protegida por Apache.

## Base de estudiantes

La base inicial contiene los 243 registros del archivo proporcionado. La interfaz está en **Panel docente → Estudiantes**. Los identificadores aparecen enmascarados en pantalla; la base conserva el valor completo para detectar actualizaciones y duplicados.

La importación acepta la primera hoja de archivos `.xlsx` o `.xls`, con hasta 5.000 filas y estas columnas:

| Columna | Contenido |
|---|---|
| `Nombre` | Nombre del estudiante |
| `Rut` | RUT o identificador único |
| `Curso` | Ejemplo: `4° básico A` o `1° medio A` |
| `idgrado` | Código numérico del nivel |

Correspondencia de `idgrado`:

| Nivel | idgrado |
|---|---:|
| 1° a 8° básico | 1 a 8 |
| 1° medio | 9 |
| 2° medio | 10 |
| 3° medio | 11 |
| 4° medio | 12 |

Una nueva importación actualiza a un estudiante existente cuando coincide su RUT normalizado; no crea un duplicado.

Para reemplazar una nómina completa, use **Vaciar nómina** en el módulo de estudiantes, escriba `VACIAR` en la confirmación y luego importe el Excel actualizado. El vaciado sólo puede ejecutarlo un administrador autenticado, requiere el token de seguridad de la sesión y queda registrado en la auditoría de la base de datos.

## Seguridad y operación

- Mantenga copias de respaldo de la carpeta privada de datos. Si copia una base activa, incluya también sus archivos `-wal` y `-shm`, o realice el respaldo durante una pausa de escritura.
- No comparta `simce.sqlite`: contiene datos personales de estudiantes.
- No importe pruebas HTML obtenidas de fuentes no confiables. Las pruebas guardadas son páginas ejecutables y se publican para los alumnos.
- La sesión docente usa cookies `HttpOnly`, `SameSite=Strict`, expiración por inactividad de dos horas, protección CSRF y bloqueo temporal tras intentos fallidos.
- Los envíos de resultados a servicios externos quedaron bloqueados. Los resultados se descargan localmente.
- Las pruebas HTML contienen sus respuestas correctas para poder corregir en el navegador. Un alumno con conocimientos técnicos puede inspeccionar el código fuente; para evaluaciones de alto impacto se requiere corrección en el servidor y no sólo una prueba HTML estática.
- Actualice PHP, Apache y las bibliotecas del navegador periódicamente. El acceso público debe pasar por un firewall o proxy inverso con HTTPS y registros de acceso.

## Comprobación rápida

- Alumno: `https://SU-DOMINIO/RUTA/menu.html`
- Profesor: `https://SU-DOMINIO/RUTA/index.html` → **Docentes**
- Estudiantes: `https://SU-DOMINIO/RUTA/estudiantes.php` (requiere sesión)
- Instalador: queda cerrado después de crear el primer administrador
