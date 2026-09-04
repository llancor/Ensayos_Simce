# Evaluaciones Colegios v2.1.0

Plataforma multi-colegio para crear, asignar y rendir evaluaciones. Usa MySQL/MariaDB y separa los datos de cada establecimiento mediante `colegio_id`.

## Perfiles

- **Superadministrador de plataforma:** crea y habilita colegios. No pertenece a un colegio.
- **Administrador de colegio:** administra únicamente su colegio, crea docentes, importa alumnos, configura PIN por curso y personaliza nombre, textos, logotipo e icono del navegador.
- **Docente:** crea evaluaciones y consulta resultados de su colegio.
- **Estudiante:** entra con código de colegio, curso, nombre y el PIN común del curso cuando esté configurado; sólo puede rendir pruebas asignadas a su curso.

## Instalación en Debian o Ubuntu

Copie la carpeta completa al servidor. Debe incluir los archivos PHP, HTML, JavaScript, CSS, `.htaccess`, `formato_importar.xlsx` y el instalador:

```bash
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh
```

En el menú:

- Opción 1: instala Apache, PHP, `PDO_MySQL` y MariaDB.
- Opción 2: instala o reinstala desde GitHub.
- Opción 3: instala o reinstala desde la carpeta local indicada.
- Opción 4: muestra estado, versiones y URLs.
- Opciones 5 y 6: actualizan desde GitHub o carpeta local.
- Opción 8: crea un respaldo manual de archivos y un volcado MySQL.
- Opción 9: ejecuta el diagnóstico.
- Opción 10: configura el VirtualHost para un proxy inverso HTTPS.
- Opción 13: gestiona cuentas administrativas desde la consola.
- Opción 15: muestra o genera la clave de instalación sólo si todavía no existe ningún administrador.

También puede ejecutar comandos directos:

```bash
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh install-local
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh install-github
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh update-local
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh update-github
sudo bash Gestion_Evaluaciones_Colegios_v3.0.sh diagnose
```

La carpeta local predeterminada es la que contiene el script. Para GitHub, el valor predeterminado de la subcarpeta es `evaluaciones_colegios`; puede cambiarse con `SIMCE_GITHUB_SUBDIR`.

## Primera puesta en marcha

1. Ejecute la instalación.
2. Abra `https://SU-DOMINIO/RUTA/instalar.php`.
3. Lea la clave mediante la opción 15 del script.
4. Cree el superadministrador de plataforma.
5. Inicie sesión dejando vacío el código de colegio.
6. En el panel de plataforma cree cada colegio y su administrador inicial.
7. El administrador del colegio ingresa con el código del establecimiento, importa sus alumnos y crea docentes.

La base predeterminada es `evaluaciones_colegios_v21`. Esta versión no importa ni necesita SQLite. Una instalación o actualización normal conserva la base MySQL; el respaldo sólo se crea al escoger expresamente la opción 8.

## Importación de estudiantes

El administrador del colegio descarga `formato_importar.xlsx` desde la página Estudiantes. La importación acepta enseñanza básica y media, y asocia cada alumno exclusivamente al colegio de la sesión.

| Columna | Contenido |
|---|---|
| `Nombre` | Nombre completo |
| `Rut` | RUT o identificador único dentro del colegio |
| `Curso` | Por ejemplo `4° básico A` o `2° medio B` |
| `idgrado` | 1 a 8 para básica y 9 a 12 para 1° a 4° medio |

## Acceso desde Internet

La URL interna que muestra el script sólo comprueba el servidor de aplicación. Si existe un proxy inverso, la dirección real es la URL pública HTTPS registrada mediante la opción 10.

No exponga directamente MariaDB ni el puerto HTTP interno. Publique únicamente el VirtualHost mediante el proxy HTTPS. El instalador bloquea el acceso web directo a `pruebas_colegios`, archivos de configuración, claves y bases locales.

## Archivos para GitHub

Suba la carpeta completa de la aplicación, incluido el instalador y `formato_importar.xlsx`. No suba credenciales, copias de seguridad, archivos generados en `pruebas_colegios`, imágenes cargadas en `uploads` ni archivos de datos locales.
