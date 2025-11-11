<?php
session_start();
require_once 'conexion.php';
require_once 'verificar_admin.php';


verificarAdmin();

echo "<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background-color: #f5f5f5;
}

.container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    overflow: hidden;
}

.table-container h3 {
    margin: 0 0 15px 0;
    color: #333;
    border-bottom: 3px solid #643AB6;
    padding-bottom: 10px;
    font-size: 1.2rem;
}

.table-info {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-weight: bold;
    color: #495057;
}

table { 
    border-collapse: collapse; 
    width: 100%; 
    font-size: 0.85rem;
    background: white;
}

th, td { 
    border: 1px solid #dee2e6; 
    padding: 8px 10px; 
    text-align: left; 
    word-wrap: break-word;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
}

th { 
    background-color: #643AB6; 
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
}

tr:nth-child(even) {
    background-color: #f8f9fa;
}

tr:hover {
    background-color: #e9ecef;
}

h1 { 
    color: #333; 
    text-align: center;
    margin-bottom: 10px;
}

.connection-status {
    text-align: center;
    font-weight: bold;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.empty-table {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 20px;
}

@media (max-width: 600px) {
    .container {
        grid-template-columns: 1fr;
    }
    
    .table-container {
        padding: 15px;
    }
    
    th, td {
        padding: 6px 8px;
        font-size: 0.8rem;
        max-width: 100px;
    }
}
</style>";

echo "<h1>Visor de Base de Datos</h1>";

// Verificar conexión
if (!$conexion) {
    echo "<div class='connection-status error'>Error: No se pudo conectar a la base de datos</div>";
    exit;
}

echo "<div class='connection-status success'>Conexión exitosa a la base de datos</div>";

// Listar todas las tablas
$query_tables = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name";
$result_tables = pg_query($conexion, $query_tables);

echo "<div class='container'>";

if ($result_tables) {
    while ($table = pg_fetch_assoc($result_tables)) {
        $table_name = $table['table_name'];
        
        echo "<div class='table-container'>";
        echo "<h3>$table_name</h3>";
        
        // Contar registros
        $count_query = "SELECT COUNT(*) as total FROM \"$table_name\"";
        $count_result = pg_query($conexion, $count_query);
        $count = pg_fetch_assoc($count_result)['total'];
        
        echo "<div class='table-info'>Total de registros: $count</div>";
        
        if ($count > 0) {
            // Mostrar datos
            $data_query = "SELECT * FROM \"$table_name\" LIMIT 15";
            $data_result = pg_query($conexion, $data_query);
            
            if ($data_result) {
                echo "<div style='overflow-x: auto;'>";
                echo "<table>";
                
                // Encabezados
                $fields = pg_num_fields($data_result);
                echo "<tr>";
                for ($i = 0; $i < $fields; $i++) {
                    echo "<th>" . htmlspecialchars(pg_field_name($data_result, $i)) . "</th>";
                }
                echo "</tr>";
                
                // Datos
                while ($row = pg_fetch_assoc($data_result)) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        $display_value = $value ?? 'NULL';
                        if (strlen($display_value) > 30) {
                            $display_value = substr($display_value, 0, 30) . '...';
                        }
                        echo "<td title='" . htmlspecialchars($value ?? 'NULL') . "'>" . htmlspecialchars($display_value) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
                
                if ($count > 15) {
                    echo "<div style='text-align: center; margin-top: 10px; color: #6c757d; font-size: 0.9rem;'>";
                    echo "Mostrando 15 de $count registros";
                    echo "</div>";
                }
            }
        } else {
            echo "<div class='empty-table'>No hay datos en esta tabla</div>";
        }
        
        echo "</div>";
    }
} else {
    echo "<div class='table-container'>";
    echo "<div class='connection-status error'>Error al obtener las tablas</div>";
    echo "</div>";
}

echo "</div>";
?>