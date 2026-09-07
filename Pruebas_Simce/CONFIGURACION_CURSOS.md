# Cursos y opciones de evaluación

En el panel docente, abre **Cursos, pruebas y usuarios** (Administración).

1. En **Crear curso**, elige nivel y sección. El PIN es opcional. No necesitas importar una nómina si usas acceso libre.
2. En **Pruebas y configuración por curso**, selecciona el curso.
3. Elige **Solo pruebas asignadas** y marca las pruebas por asignatura, o **Todas las pruebas de su nivel** para incluir también las nuevas automáticamente.
4. Para cada prueba, conserva **Usar opciones del creador** o selecciona **Personalizar para este curso**.
5. Guarda la configuración.

Las opciones permiten mostrar/ocultar la retroalimentación y el resultado, fijar intentos máximos (0: ilimitados; 1: sin repetición) y permanecer en la evaluación o volver al listado al revisar. Ocultar el resultado o volver al listado también oculta la retroalimentación. Los informes detallados no se ofrecen si se oculta la retroalimentación.

Los valores del creador manual se incluyen en las pruebas nuevas; la configuración de la asignación tiene prioridad. En pruebas anteriores se respeta inicialmente la opción existente de mostrar respuestas. Los cursos existentes conservan la visibilidad automática; los creados desde el nuevo formulario empiezan con asignación manual.

El servidor guarda antes de mostrar resultados o regresar al portal. Si falla la conexión, se mantiene la evaluación abierta con una opción para reintentar el guardado; el mismo envío no duplica el resultado. El límite se verifica también al abrir y guardar.

Con listas, los intentos se cuentan por estudiante registrado y prueba. En acceso libre se cuentan por nombre (sin distinguir mayúsculas) y curso: nombres distintos se consideran personas distintas y los homónimos del mismo curso comparten el conteo. La identificación mediante nómina es preferible cuando se requiere controlar intentos por persona.

## Actualización

Publica los archivos PHP, JavaScript, HTML y `.htaccess` modificados junto con los nuevos `cursos.js`, `politica-runtime.js`, `politica_pruebas.php` y `archivo_prueba.php`. No reemplaces `data/simce.sqlite` ni la carpeta de pruebas del servidor con datos de validación. Las columnas nuevas se incorporan automáticamente al conectar con SQLite o MySQL/MariaDB.

Apache debe tener `mod_rewrite` y permitir las reglas de `.htaccess` (el instalador usa Apache). La regla nueva dirige los enlaces HTML de estudiantes al portal protegido; el personal autenticado mantiene la vista previa. En otro servidor web debe configurarse el equivalente para `pruebas/*.html` hacia `archivo_prueba.php?ruta=...` antes de ofrecer restricciones de acceso.

Las copias HTML descargadas son autónomas: aplican las opciones visuales, pero el guardado central y el límite entre sesiones requieren rendir desde el portal. Las claves de corrección permanecen en el HTML, como en el diseño original; ocultar retroalimentación controla la interfaz, no constituye un sistema de examen con corrección secreta en el servidor.

## Validación

Se comprobaron con PHP 8.4 y SQLite: creación sin nómina, curso duplicado, selección manual y automática, autorización de prueba, guardado e idempotencia, intentos entre sesiones y compatibilidad de las 24 pruebas existentes. Se revisó la sintaxis PHP/JavaScript y el recorrido de administración y evaluación en navegador. MySQL/MariaDB y las reglas Apache requieren comprobación en el servidor de destino.
