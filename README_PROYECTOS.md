# Aplicacion_Pruebas

Esta carpeta contiene dos productos similares para administrar, crear y rendir evaluaciones educativas. Comparten gran parte de la lógica funcional, pero apuntan a escenarios distintos:

- `Pruebas_Simce`: plataforma para un solo colegio.
- `evaluaciones_colegios`: plataforma multicolegio, con separación de datos por establecimiento.

## 1. Pruebas_Simce

`Pruebas_Simce` es la versión simple del sistema. Está pensada para funcionar con un solo colegio, sin selector de establecimiento ni `colegio_id`.

### Objetivo

Permitir que un colegio administre estudiantes, cursos, pruebas, asignaciones, configuración de rendición, resultados y personalización básica de la plataforma.

### Perfiles principales

- `superadmin`: administra la plataforma completa del colegio.
- `administrador`: administra usuarios docentes, cursos, pruebas, estudiantes y configuración.
- `docente`: puede trabajar con cursos, pruebas, PIN, resultados y configuración según las reglas definidas en la aplicación.
- `estudiante`: ingresa al portal, selecciona o escribe sus datos según el modo configurado, y rinde pruebas disponibles para su curso.

### Funciones principales

- Inicio de sesión administrativo.
- Portal de estudiantes.
- Importación de alumnos desde planilla.
- Creación y edición de pruebas.
- Administración de cursos.
- Asignación de pruebas por curso.
- PIN común por curso.
- Configuración por curso y prueba:
  - retroalimentación visible u oculta;
  - resultado visible u oculto;
  - intentos máximos;
  - volver al listado al finalizar o permanecer en la prueba;
  - modo `Solo pruebas asignadas`;
  - modo `Todas las pruebas de su nivel`.
- Resultados y dashboard.
- Personalización de marca.

### Base de datos

Si no se configuran variables MySQL, usa SQLite local en la carpeta `data`. También puede trabajar con MySQL/MariaDB mediante variables de entorno:

```text
SIMCE_DB_DSN
SIMCE_DB_USER
SIMCE_DB_PASSWORD
SIMCE_DATABASE_PATH
```

### Archivos relevantes

- `database.php`: conexión, esquema y migraciones livianas.
- `seguridad.php`: sesiones, roles, CSRF y seguridad.
- `gestion.php` y `gestion_api.php`: administración de usuarios, cursos, PIN y configuración.
- `cursos.js`: interfaz de cursos, asignaciones y configuración por curso.
- `portal_estudiante.php` y `portal_estudiante_api.php`: acceso del alumno y listado de pruebas.
- `rendir_prueba.php`: carga la prueba y aplica la política de rendición.
- `resultados_api.php`: guarda resultados, controla intentos y evita duplicados.
- `politica_pruebas.php`: calcula configuración efectiva de cada prueba para un curso.
- `politica-runtime.js`: aplica la política dentro del navegador del estudiante.
- `guardar_prueba.php`: guarda pruebas creadas o importadas.
- `README_INSTALACION.md`: instrucciones propias de instalación.
- `CONFIGURACION_CURSOS.md`: documentación específica de cursos y configuración.

## 2. evaluaciones_colegios

`evaluaciones_colegios` es la versión multicolegio. Mantiene una plataforma central desde la que se crean colegios y administradores, y cada colegio trabaja con sus propios datos.

### Objetivo

Permitir que una misma instalación atienda a múltiples colegios, separando alumnos, cursos, pruebas, resultados, usuarios docentes y configuración mediante `colegio_id`.

### Diferencia clave con Pruebas_Simce

La diferencia central es la separación multicolegio:

- cada colegio tiene su propio código de acceso;
- cada administrador pertenece a un colegio;
- cada alumno, curso, prueba, asignación y resultado pertenece a un `colegio_id`;
- el `plataforma_superadmin` no pertenece a un colegio y administra la creación/habilitación de establecimientos.

### Perfiles principales

- `plataforma_superadmin`: administra colegios desde el panel global. No pertenece a un colegio.
- `colegio_admin`: administra su colegio, docentes, estudiantes, cursos, pruebas, PIN y configuración.
- `docente`: trabaja dentro de su colegio y puede administrar cursos, pruebas, PIN y configuración según las pantallas habilitadas.
- `estudiante`: ingresa con código de colegio, curso, estudiante y PIN si corresponde.

### Funciones principales

- Panel de plataforma para crear y administrar colegios.
- Login con o sin código de colegio:
  - sin código: entra el superadministrador global;
  - con código: entra el administrador/docente del colegio.
- Importación de estudiantes separada por colegio.
- Creación y edición de pruebas por colegio.
- Administración de cursos por colegio.
- Listado de cursos creados para administrador y docente.
- Edición, habilitación, deshabilitación y eliminación controlada de cursos.
- PIN común por curso.
- Configuración por curso y prueba:
  - retroalimentación visible u oculta;
  - resultado visible u oculto;
  - intentos máximos;
  - volver al listado al finalizar o permanecer en la prueba;
  - modo `Solo pruebas asignadas`;
  - modo `Todas las pruebas de su nivel`.
- Portal de estudiantes con listado de pruebas asignadas.
- Bloqueo visual y funcional de pruebas con intentos agotados.
- Resultados por colegio.
- Personalización de marca por colegio.

### Base de datos

El instalador oficial para servidor usa MySQL/MariaDB. Para pruebas locales en Windows también puede funcionar con SQLite si no se definen variables MySQL.

Variables soportadas:

```text
SIMCE_DB_DSN
SIMCE_DB_USER
SIMCE_DB_PASSWORD
SIMCE_DATABASE_PATH
```

Base MySQL predeterminada en el instalador Linux:

```text
evaluaciones_colegios_v21
```

### Archivos relevantes

- `database.php`: conexión, esquema multicolegio y migraciones livianas.
- `seguridad.php`: sesiones, roles, `colegio_id`, CSRF y seguridad.
- `plataforma.php` y `plataforma_api.php`: panel del superadministrador global.
- `gestion_v21.php` y `gestion_v21_api.php`: administración del colegio, cursos, docentes, PIN y configuración.
- `cursos.js`: interfaz de cursos, asignaciones y configuración por curso.
- `portal_estudiante.php` y `portal_estudiante_api.php`: acceso del alumno usando código de colegio.
- `rendir_prueba.php`: carga la prueba y aplica la política de rendición del curso/colegio.
- `resultados_api.php`: guarda resultados, controla intentos y evita duplicados.
- `politica_pruebas.php`: calcula configuración efectiva por colegio, curso y prueba.
- `politica-runtime.js`: aplica la política dentro del navegador del estudiante.
- `guardar_prueba.php`: guarda pruebas y auto-asigna solo a cursos configurados como `Todas las pruebas de su nivel`.
- `README_INSTALACION.md`: instalación oficial en Debian/Ubuntu.
- `Gestion_Evaluaciones_Colegios_v3.0.sh`: gestor de instalación Linux con Apache, PHP, MariaDB y mantenimiento.

## Instalación rápida en Windows para pruebas

Desde la raíz `Aplicacion_Pruebas` se puede levantar cualquiera de los dos productos con el servidor integrado de PHP.

Para `Pruebas_Simce`:

```powershell
cd D:\Proyectos_ChatGPT\Github\GitHub-BastControl\Aplicacion_Pruebas
php -S 127.0.0.1:8000 -t Pruebas_Simce
```

Abrir:

```text
http://127.0.0.1:8000/
```

Para `evaluaciones_colegios`:

```powershell
cd D:\Proyectos_ChatGPT\Github\GitHub-BastControl\Aplicacion_Pruebas
php -S 127.0.0.1:8001 -t evaluaciones_colegios
```

Abrir:

```text
http://127.0.0.1:8001/
```

Si no existe una clave inicial, se puede crear:

```powershell
php -r "echo bin2hex(random_bytes(32));" > evaluaciones_colegios\data\setup.key
```

Luego abrir:

```text
http://127.0.0.1:8001/instalar.php
```

## Criterio para mantener ambos productos alineados

Cuando se agregue una función a `Pruebas_Simce`, evaluar si corresponde llevarla también a `evaluaciones_colegios`. Si se adapta a la versión multicolegio, todas las consultas y escrituras deben respetar `colegio_id`.

Reglas recomendadas:

- No mezclar datos entre colegios.
- Mantener intacto el rol `plataforma_superadmin` salvo que se planifique explícitamente un cambio global.
- Mantener pantallas similares entre ambos productos cuando el flujo sea equivalente.
- Probar siempre como administrador/docente y como estudiante.
- Validar sintaxis con:

```powershell
php -l ruta\archivo.php
node --check ruta\archivo.js
```
