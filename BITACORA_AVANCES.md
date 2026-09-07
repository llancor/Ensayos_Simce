# Bitacora de avances

Este archivo registra los cambios importantes realizados dentro de `Aplicacion_Pruebas`, especialmente cuando una mejora se aplica primero en `Pruebas_Simce` y luego se adapta a `evaluaciones_colegios`.

## 2026-09-05

### Contexto general

Se identificaron dos productos relacionados:

- `Pruebas_Simce`: version simple para un solo colegio.
- `evaluaciones_colegios`: version multicolegio, con separacion por `colegio_id` y rol `plataforma_superadmin`.

La decision de trabajo fue mantener ambas plataformas visual y funcionalmente alineadas cuando corresponda, respetando que `evaluaciones_colegios` debe conservar su estructura multicolegio.

### Avances en Pruebas_Simce

Se mejoro la administracion de cursos y configuracion por curso:

- Se agrego creacion de cursos desde gestion.
- Se agrego listado de cursos creados debajo del formulario.
- Se agregaron acciones para editar curso.
- Se agrego habilitar/deshabilitar curso.
- Se agrego eliminacion controlada de cursos solo cuando no tienen alumnos ni resultados.
- Se agrego PIN opcional por curso.
- Se agrego modo de visibilidad:
  - `Solo pruebas asignadas`.
  - `Todas las pruebas de su nivel`.
- Se agrego configuracion por curso y prueba:
  - retroalimentacion;
  - resultado;
  - intentos maximos;
  - comportamiento al finalizar.
- Se agrego resumen compacto de configuracion:
  - `Activo` o `Deshabilitado`;
  - `Solo asignadas` o `Todas las del nivel`;
  - `PIN` o `Sin PIN`;
  - retroalimentacion;
  - resultado;
  - intentos.
- Se corrigio el problema donde el resumen podia mostrar `Deshabilitado` aunque el curso estuviera activo, porque la API no estaba devolviendo correctamente el campo `activo`.
- Se adapto el portal del estudiante para respetar intentos y configuracion.
- Se adapto el guardado de resultados para evitar duplicados y controlar intentos.

Archivos relevantes modificados o agregados en `Pruebas_Simce`:

- `.htaccess`
- `archivo_prueba.php`
- `CONFIGURACION_CURSOS.md`
- `cursos.js`
- `database.php`
- `gestion_api.php`
- `gestion.php`
- `logica.js`
- `politica_pruebas.php`
- `politica-runtime.js`
- `portal_estudiante_api.php`
- `portal_estudiante.php`
- `pruebas_educativas.html`
- `rendir_prueba.php`
- `resultados_api.php`

### Avances en evaluaciones_colegios

Se adapto la mejora de cursos y configuracion desde `Pruebas_Simce` hacia la version multicolegio.

Principios aplicados:

- Mantener `plataforma_superadmin` sin cambios funcionales en esta etapa.
- Aplicar la mejora al flujo del `colegio_admin` y `docente`.
- Respetar `colegio_id` en consultas, inserciones, actualizaciones y eliminaciones.
- Mantener la separacion de datos entre colegios.
- Conservar el panel global de colegios como responsabilidad del superadministrador.

Cambios realizados:

- `gestion_v21.php` ahora muestra:
  - formulario para crear cursos;
  - listado `Cursos creados`;
  - acciones de editar, habilitar/deshabilitar y eliminar;
  - configuracion de pruebas por curso;
  - resumen compacto de configuracion;
  - PIN comun por curso;
  - administracion de docentes solo para `colegio_admin`.
- `gestion_v21_api.php` ahora entrega:
  - cursos activos;
  - todos los cursos, incluidos deshabilitados;
  - pruebas activas del colegio;
  - asignaciones del colegio;
  - configuracion por asignacion;
  - docentes del colegio cuando el perfil es `colegio_admin`.
- Se agregaron acciones API:
  - `create_course`;
  - `update_course`;
  - `toggle_course`;
  - `delete_course`;
  - `save_course_tests`;
  - `course_pin`;
  - `clear_course_pin`.
- `database.php` agrega migraciones livianas:
  - `cursos.modo_pruebas`;
  - `asignaciones.configuracion`;
  - `resultados.submission_token`.
- `simceSyncStoredTests` auto-asigna pruebas solo a cursos activos con `modo_pruebas='todas'`.
- `guardar_prueba.php` auto-asigna nuevas pruebas solo a cursos con `Todas las pruebas de su nivel`.
- Se agrego `politica_pruebas.php` adaptado a multicolegio.
- Se agrego `politica-runtime.js` para aplicar la configuracion en el navegador del estudiante.
- `portal_estudiante_api.php` ahora informa intentos usados, intentos maximos y si una prueba esta agotada.
- `portal_estudiante.php` muestra pruebas agotadas sin enlace para rendir.
- `rendir_prueba.php` valida disponibilidad, colegio, curso, prueba e intentos antes de abrir la prueba.
- `resultados_api.php` valida token de rendicion, evita duplicados y vuelve a controlar intentos antes de guardar.

Archivos modificados o agregados en `evaluaciones_colegios`:

- `database.php`
- `gestion_v21.php`
- `gestion_v21_api.php`
- `cursos.js`
- `politica_pruebas.php`
- `politica-runtime.js`
- `portal_estudiante_api.php`
- `portal_estudiante.php`
- `rendir_prueba.php`
- `resultados_api.php`
- `guardar_prueba.php`

### Validaciones realizadas

Se instalo PHP en Windows mediante `winget`:

```powershell
winget install --id PHP.PHP.8.4 --source winget --accept-package-agreements --accept-source-agreements
```

Version instalada:

```text
PHP 8.4.24
```

Se agrego la carpeta real de PHP al `PATH` de usuario para poder ejecutar:

```powershell
php -l archivo.php
```

Validaciones realizadas:

```powershell
php -l evaluaciones_colegios\database.php
php -l evaluaciones_colegios\gestion_v21_api.php
php -l evaluaciones_colegios\gestion_v21.php
php -l evaluaciones_colegios\portal_estudiante_api.php
php -l evaluaciones_colegios\rendir_prueba.php
php -l evaluaciones_colegios\resultados_api.php
php -l evaluaciones_colegios\politica_pruebas.php
php -l evaluaciones_colegios\guardar_prueba.php
node --check evaluaciones_colegios\cursos.js
node --check evaluaciones_colegios\politica-runtime.js
```

Resultado:

```text
Sin errores de sintaxis detectados en los archivos PHP validados.
JavaScript validado correctamente con node --check.
```

### Pendientes sugeridos

- Probar en navegador el flujo completo de `evaluaciones_colegios`:
  - ingreso como `plataforma_superadmin`;
  - creacion de colegio;
  - ingreso como `colegio_admin`;
  - creacion de curso;
  - configuracion de pruebas;
  - asignacion de PIN;
  - ingreso de estudiante;
  - rendicion;
  - bloqueo por intentos maximos.
- Revisar si algunas funciones del `colegio_admin` deben exponerse tambien al `plataforma_superadmin`.
- Evaluar si conviene dejar un instalador Windows formal o documentar solo el uso con `php -S`.
- Mantener sincronizadas las mejoras futuras entre `Pruebas_Simce` y `evaluaciones_colegios` cuando tengan sentido funcional.

### Mejora de portada principal

Se redisenaron las paginas principales de ambos productos para reducir errores de ingreso de estudiantes:

- El acceso de estudiantes queda como accion central y principal.
- El acceso de profesores, docentes y administradores queda separado en la esquina superior derecha como boton `Acceso`.
- Se mantiene el modal de autenticacion administrativa.
- Se muestra el logo institucional cargado desde la personalizacion cuando existe.
- En `evaluaciones_colegios`, el logo del colegio se muestra cuando la URL incluye el codigo del colegio, por ejemplo:

```text
index.html?colegio=CODIGO_DEL_COLEGIO
```

Archivos modificados:

- `Pruebas_Simce/index.html`
- `evaluaciones_colegios/index.html`

Validaciones realizadas:

```powershell
node --check` sobre los scripts inline extraidos de ambas portadas.
php -l Pruebas_Simce\personalizacion_api.php
php -l evaluaciones_colegios\personalizacion_v21_api.php
php -l evaluaciones_colegios\autenticacion.php
php -l Pruebas_Simce\autenticacion.php
```

### Biblioteca de pruebas: importar, exportar y borrar

Se completo la opcion de biblioteca para que docentes y administradores puedan gestionar pruebas HTML desde el panel:

- Importar pruebas HTML con curso, asignatura y titulo visible.
- Exportar pruebas existentes como archivo HTML.
- Borrar pruebas desde la biblioteca mediante desactivacion logica, conservando resultados historicos.
- Restringir estas acciones a perfiles administrativos/docentes.
- En `evaluaciones_colegios`, listar pruebas desde la base de datos filtrada por `colegio_id`, para que cada colegio vea solo su propia biblioteca.

Archivos modificados:

- `Pruebas_Simce/menu.html`
- `Pruebas_Simce/api.php`
- `Pruebas_Simce/biblioteca_api.php`
- `evaluaciones_colegios/menu.html`
- `evaluaciones_colegios/api.php`
- `evaluaciones_colegios/biblioteca_api.php`

Validaciones realizadas:

```powershell
php -l Pruebas_Simce\api.php
php -l Pruebas_Simce\biblioteca_api.php
php -l evaluaciones_colegios\api.php
php -l evaluaciones_colegios\biblioteca_api.php
node --check sobre los scripts inline extraidos de Pruebas_Simce\menu.html
node --check sobre los scripts inline extraidos de evaluaciones_colegios\menu.html
```

## 2026-09-06 — Acceso libre por colegio y biblioteca masiva

- `evaluaciones_colegios`: selector administrativo por colegio para alternar entre lista de estudiantes y acceso libre por nombre. Portal, sesiones, intentos y resultados respetan el modo.
- Bibliotecas de ambos proyectos: seleccion multiple, importacion de varios HTML, exportacion de seleccionadas y borrado masivo con desactivacion logica. El borrado masivo requiere permisos administrativos.
- Validacion: `php -l` en los endpoints PHP modificados y `node --check` en los scripts inline de gestion y portal.
- Exportacion filtrada: la biblioteca ahora permite elegir curso y asignatura para exportar todas las pruebas coincidentes en ambos proyectos.
