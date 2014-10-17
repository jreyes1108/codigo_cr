<?php

if ( isset($_GET['db']) && !empty($_GET['db']))
    $db = $_GET['db'];
else $db = 'una';

if ($db === 'sentidos') $file = file_get_contents("eventos_sentidos.json");
else $file = file_get_contents("eventos.json");

$file = mb_convert_encoding($file, "UTF-8");
$json = json_decode($file,true);


?>
<HEAD>
    <STYLE type="text/css">
        table, th, td { border: 1px solid black; border-collapse: collapse;  }
        thead { background-color: gray; }
    </STYLE>
</HEAD>


<h2>Listado de eventos <?php echo $db; ?></h2>
<table>
    <thead>
        <tr>
        <?php foreach($json as $obj) { foreach ($obj as $k=>$v) echo "<td>$k</td>"; break; } ?>
        </tr>
    </thead>
    <tbody>
        <?php 
            foreach($json as $obj) { 
                echo "<tr>";
                foreach ($obj as $k=>$v) {
                    echo "<td>$v</td>"; 
                }
                echo "</tr>";
            }
        ?>
    </tbody>
</table>
