<?php
if ( isset($_GET['refresh']) && !empty($_GET['refresh']))
    $refresh = $_GET['refresh'];
else $refresh = false;

if ( isset($_GET['notable']) && !empty($_GET['notable']))
    $notable = $_GET['notable'];
else $notable = false;

if ( isset($_GET['db']) && !empty($_GET['db']))
    $db = $_GET['db'];
else $db = 'una';

if ($db === 'sentidos') $file = file_get_contents("eventos_sentidos.json");
else $file = file_get_contents("eventos.json");


if ( isset($_GET['estaciones']) && !empty($_GET['estaciones'])) {
    $estaciones = true;
    $file = file_get_contents('stations.json');
    $notable = true;
} else {
    $estaciones = false;
}

$file = mb_convert_encoding($file, "UTF-8");
$json = json_decode($file,true);

function print_new_event($i,$registro) {

    $defaultsize = 10; # size default
    $now = time();
    $time= $now - $registro['time'];
    $latitud= $registro['lat'];
    $longitud= $registro['lon'];
    $fecha= $registro['diaLocal'];
    //$hora= $registro['horaLocal']." ".$registro['timeZone'];
    $hora= $registro['horaLocal'];
    $magnitudraw= intval($registro['magnitude']);
    $magnitud= $registro['magnitude']." ".$registro['magtype'];
    $evid= $registro['evid'];
    //$calcs= "nass:".$registro['nass']." ndef:".$registro['ndef']." sdobs:".$registro['sdobs'];
    $calcs= "nass:".$registro['nass']." ndef:".$registro['ndef'];
    $origen= "Evid:".$registro['evid']." Orid:".$registro['orid'];
    $revisado= $registro['review'];
    $autor= $registro['auth'];
    $profundidad = $registro['depth'];
    $localizacion = lugar($registro['distancia'], $registro['acimut'], $registro['pueblo'], $registro['distrito'], $registro['canton'], $registro['provincia']);
        
    //$t  ="<table><tr><td width=120><h2><img src=logo.jpg></h2></td></tr>"; 
    $t  ="<div class=noscrollbar><table>"; 
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Fecha:</td> <td>$fecha</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Hora:</td> <td>$hora</td></tr>";
    $t .= "<tr><td width=90>Profundidad:</td> <td>$profundidad</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Magnitud:</td> <td>$magnitud</td></tr>";
    $t .= "<tr><td width=90>Localizacion:</td> <td>$localizacion</td></tr>";					
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Origen:</td> <td>$origen</td></tr>";
    $t .= "<tr><td width=90>Coordenadas:</td> <td>($latitud,$longitud)</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Autor:</td> <td>$autor</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Revisado:</td> <td>$revisado</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Info:</td> <td>$calcs</td></tr>";
    $t .= "</table></div>";

    if ($time <= 86400){ # menos de 1 dia
        $icono = ($i == 0)? 'sismoblanco.gif' : 'sismorojo.png';
        $index = 3;
    } else if($time <= 604800){ # menos de 1 semana
        $icono= "sismoverde.png";
        $index = 2;
    } else{
        $index = 1;
        $icono= "sismoazul.png";
    }
    if ($magnitudraw > 1 ) {
        $size = intval($magnitudraw * $defaultsize);
    } else {
        $size = $defaultsize;
    }

    echo "\n\t\tdatos[$i] = {
            fecha : '$fecha',
            hora : '$hora',
            profundidad : '$profundidad',
            magnitud : '$magnitud',
            localizacion : '$localizacion',
            latitud : '$latitud',
            longitud : '$longitud',
            icono : '$icono',
            size : '$size',
            revisado : '$revisado',
            evid : '$evid',
            index : '$index',
            tabla : '$t'
        };\n";
}
function print_new_sta($i,$registro) {
    $sta= $registro['sta'];
    $snet= $registro['snet'];
    $latitud= $registro['lat'];
    $longitud= $registro['lon'];
    $elev= $registro['elev'];
    $ondate= $registro['ondate'];
    $staname= $registro['staname'];
        
    $t  ="<div class=noscrollbar><table>"; 
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Estacion:</td> <td>${snet}_${sta}</td></tr>";
    $t .= "<tr><td width=90>Coordenadas:</td> <td>($latitud,$longitud) $elev km</td></tr>";
    $t .= "<tr bgcolor=#DEDEEF><td width=90>Inicio:</td> <td>$ondate</td></tr>";
    $t .= "<tr><td width=90>Lugar:</td> <td>$staname</td></tr>";
    $t .= "</table></div>";

    if ($snet == 'OV'){
        $icono= 'OV.png';
    } else if($snet == 'II'){
        $icono= "II.png";
    } else if($snet == 'CU'){
        $icono= "CU.png";
    } else if($snet == 'NU'){
        $icono= "NU.png";
    } else if($snet == 'PR'){
        $icono= "PR.png";
    } else{
        $icono= "XX.png";
    }

    $size = 15;

    echo "\n\t\tdatos[$i] = {
            sta : '$sta',
            snet : '$snet',
            latitud : '$latitud',
            longitud : '$longitud',
            icono : '$icono',
            elev : '$elev',
            index : '3',
            size : '$size',
            magnitud : '1',
            evid : '$i',
            ondate : '$ondate',
            name : '$staname',
            tabla : '$t'
        };\n";
}
function orientacion($baz) {

    if ( $baz > 350 or $baz <= 10) return  'Norte';
    elseif ( $baz > 10 and $baz <= 80) return  'Noreste';
    elseif ( $baz > 80 and $baz <= 100) return  'Este';
    elseif ( $baz > 100 and $baz <= 170) return  'Sureste';
    elseif ( $baz > 170 and $baz <= 190) return  'Sur';
    elseif ( $baz > 190 and $baz <= 260) return  'Suroeste';
    elseif ( $baz > 260 and $baz <= 280) return  'Oeste';

    return  'Noroeste';

}
function lugar($dist, $az, $pueblo, $distrito, $canton, $provincia){

    return "$dist km al ".orientacion($az)." de $pueblo, $distrito, $canton, $provincia ";
} 


?>	
<html> 
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <?php echo ($refresh ? "<meta http-equiv=\"refresh\" content=\"$refresh\">" : '') ?>

    <title>Mapa Sismicidad Reciente</title> 
    <style type="text/css">
        .small_icon{
            width: 15px;
            height: 15px;
        }
        .boxed{
            background: none repeat scroll 0 0 #bdbdbd;
            margin: 3px;
            padding: 3px;
            border: 1px solid black;
        }
        .boxed_logo{
            margin: 3px;
            padding: 5px;
            background: none repeat scroll 0 0 #FFFFFF;
        }
        #map{
            width: 100%;
            height: <?php echo ($notable ? '100%' : '600px') ?>;
        }
        .noscrollbar {
            line-height:1.35;
            overflow:hidden;
            white-space:nowrap;
            min-height: 270px;
            width: auto;
        }
        .fuente{
            background:#F0F0F0; 
            border-right-color:#666; 
            border:#CCC 1px; font-family:"Courier New", Courier, monospace; 
        }
        #tabla{
            border: 1px solid black;
            border-collapse: collapse;
            margin: 3px;
            width: 100%;
            
        }
        #tabla td{
            border: 1px solid black;
            padding: 3px;
        }
        #tabla th{
            background:#333;
            color:#FFF;
            padding: 13px;
        }
        .maplink {
        }
        .maplink:hover {
            cursor: pointer;
        }
    </style>

	<script type='text/javascript' src='http://maps.google.com/maps/api/js?sensor=false&language=es'></script>
	<script type='text/javascript'>	
        var notable = <?php echo ($notable ? 'true' : 'false') ?>;
        var markers = new Array();
        var map = new Object();
        var mapDiv = 'map';
        var mapDivObject = new Object();
        var openmarker = new Object();
        var datos = new Array();
        var n=1;
        var options = {
            zoom: 7,
            scrollwheel: false,
            center: new google.maps.LatLng(9, -84),
            streetViewControl: false,
            zoomControlOptions: { style:google.maps.ZoomControlStyle.SMALL }, 
            mapTypeId: google.maps.MapTypeId.TERRAIN
        };
        
        <?php		
            $i = -1;
            if ( $estaciones ) {
                foreach($json as $registro) {
                    $i += 1;
                    print_new_sta($i,$registro);
                }
            }else {
                foreach($json as $registro) {
                    $i += 1;
                    print_new_event($i,$registro);
                }
            }
        ?>

        function show(i) {
            google.maps.event.trigger(markers[i], 'click');
        }

		window.onload = function(){

            map = new google.maps.Map(document.getElementById(mapDiv), options);
        
            mapDivObject =  document.getElementById(mapDiv);
            
            var leyenda = document.getElementById('leyenda');
            map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(leyenda);

			for(var x=0; x<datos.length; x++){

                var size = parseInt(datos[x].size,10);
                var color = datos[x].icono;
                var image = {
                    url: datos[x].icono,
                    scaledSize: new google.maps.Size(size, size),
                };

                if ( notable ) {
                } else {

                    // Add row to table first
                    var table = document.getElementById("tabla");
                    var rowCount = table.rows.length;
                    var row = table.insertRow(rowCount); 
                    row.id = datos[x].evid + '_';
                    //row.onclick = abreVentana(datos[x].evid);
                    row.insertCell(0).innerHTML= datos[x].fecha;
                    row.insertCell(1).innerHTML= datos[x].hora;
                    row.insertCell(2).innerHTML= datos[x].magnitud;
                    row.insertCell(3).innerHTML= datos[x].profundidad;
                    row.insertCell(4).innerHTML= datos[x].localizacion;
                    //row.insertCell(5).innerHTML= datos[x].origen;
                    row.insertCell(5).innerHTML= datos[x].revisado;
                    row.insertCell(6).innerHTML= datos[x].latitud;
                    row.insertCell(7).innerHTML= datos[x].longitud;

                    row.addEventListener("click", function( event ) {
                        var evento = this.id.split('_')[0];
                        show(evento);
                    });

                }

				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(datos[x].latitud, datos[x].longitud),
					map: map,
                    draggable: false,
                    raiseOnDrag: false,
                    icon: image,
                    info: new google.maps.InfoWindow({ content: datos[x].tabla }),
                    clickable: true,
					zIndex: parseInt(datos[x].index,10)
				});

                //marker.id = datos[x].evid;
                markers[datos[x].evid] = marker;

                google.maps.event.addListener(marker, 'click', function() {
                    if(typeof openmarker.info !== 'undefined') openmarker.info.close();
                    this.info.open(this.getMap(),this)
                    openmarker = this;
                    mapDivObject.scrollIntoView(true);
                });

			}
		}; //window.onload()
				
	</script>
		
</head>

<body>
	
	<div id="map"></div>
	<div id="leyenda">
		<table>
<?php 
    if ($estaciones) { ?>
			<tr>
                <td class='boxed'><img class='small_icon' src="OV.png" />red OV</td>
                <td class='boxed'><img class='small_icon' src="II.png" />red II</td>
                <td class='boxed'><img class='small_icon' src="CU.png" />red CU</td>
                <td class='boxed'><img class='small_icon' src="NU.png" />red NU</td>
                <td class='boxed'><img class='small_icon' src="PR.png" />red PR</td>
                <td class='boxed'><img class='small_icon' src="XX.png" />otras</td>
				<td class='boxed_logo' ><img class='small_icon' src="logo.jpg" /><strong>OVSICORI-UNA</strong></td>
			</tr>
<?php } else {  ?>
			<tr>
                <td class='boxed'><img class='small_icon' src="sismoblanco.gif" />  mas reciente</td>
                <td class='boxed'><img class='small_icon' src="sismorojo.png" />  < 24 horas</td>
                <td class='boxed'><img class='small_icon' src="sismoverde.png" />  1-7 dias</td>
                <td class='boxed'><img class='small_icon' src="sismoazul.png" /> > 7  dias</td>
				<td class='boxed_logo' ><img class='small_icon' src="logo.jpg" /><strong>OVSICORI-UNA</strong></td>
			</tr>
<?php } ?>

		</table>
	</div>

<?php 

if ( $notable ) {
    echo "</body></html>";
    return;
} else {
?>
    <div>
        <table id=tabla>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Mag.</th>
                    <th>Prof.</th>
                    <th width=120 >Localizacion</th>
                    <!--
                    <th width=120 >Origen</th>
                    -->
                    <th>Rev.</th>
                    <th>Lat</th>
                    <th>Lon</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
} 
?>
