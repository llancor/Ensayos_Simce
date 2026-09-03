<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Carpeta base donde se guardarán las pruebas
$baseDir = __DIR__ . '/pruebas';
$allowedCourses = array(
    '1basico', '2basico', '3basico', '4basico',
    '5basico', '6basico', '7basico', '8basico',
    '1medio', '2medio', '3medio', '4medio',
);
$allowedSubjects = array(
    'lenguaje', 'matematica', 'ciencia', 'historia', 'ingles',
    'tecnologia', 'artes', 'musica', 'educacion_fisica', 'otra',
);

$results = array();

if (is_dir($baseDir)) {
    // 1. Escanear carpetas de cursos (ej: 1basico, 2medio)
    $courses = array_intersect(array_diff(scandir($baseDir), array('..', '.')), $allowedCourses);
    
    foreach ($courses as $course) {
        if (is_dir($baseDir . '/' . $course)) {
            $results[$course] = array();
            
            // 2. Escanear carpetas de asignaturas (ej: lenguaje, matematica)
            $subjects = array_intersect(array_diff(scandir($baseDir . '/' . $course), array('..', '.')), $allowedSubjects);
            
            foreach ($subjects as $subject) {
                if (is_dir($baseDir . '/' . $course . '/' . $subject)) {
                    $results[$course][$subject] = array();
                    
                    // 3. Escanear archivos HTML de pruebas
                    $files = array_diff(scandir($baseDir . '/' . $course . '/' . $subject), array('..', '.'));
                    
                    // Ordenar archivos alfabéticamente para mantener orden en el menú
                    sort($files);
                    
                    foreach ($files as $file) {
                        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'html' && preg_match('/^[A-Za-z0-9_.-]+\.html$/', $file)) {
                            
                            // Formatear el nombre del archivo para que sea un título bonito
                            // Ej: "Prueba_de_Lectura_1.html" -> "Prueba de Lectura 1"
                            $title = str_replace(array('_', '.html', '.HTML'), array(' ', '', ''), $file);
                            
                            $results[$course][$subject][] = array(
                                'titulo' => $title,
                                'desc' => 'Prueba generada de ' . ucfirst($subject),
                                'url' => 'pruebas/' . $course . '/' . $subject . '/' . rawurlencode($file)
                            );
                        }
                    }
                }
            }
        }
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
