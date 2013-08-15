<?php

function print_table($offices) {
    echo '<table border="1">';
        echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>Город</th>';
            echo '<th>Область</th>';
            echo '<th>Ссылка</th>';
            echo '<th>Адрес</th>';
            echo '<th>Телефон</th>';
        echo '</tr>';
        foreach($offices as $office) {
            echo '<tr>';
                echo '<td>'.$office['id'].'</td>';
                echo '<td>'.$office['city'].'</td>';
                echo '<td>'.$office['region'].'</td>';
                echo '<td><a href="'.$office['link'].'">'.$office['name'].'</a></td>';
                echo '<td>'.$office['address'].'</td>';
                echo '<td>'.$office['phone'].'</td>';
            echo '</tr>';
        }
    echo '</table>';
}

function print_status($status) {
    if (is_array($status)) {
        echo '<table border="1">';
            foreach($status as $row) {
                echo '<tr>';
                    echo '<th>'.$row['caption'].'</th>';
                    echo '<td>'.$row['message'];
                    
                    if (isset($row['ref_office'])) {
                        echo ' <a href="'.$row['ref_office']['link'].'">Карта</a>';
                    }
                    echo '</td>';
                echo '</tr>';
            }
        echo '</table>';
    } else {
        echo $status;
    }
}

require 'Novaposhta.php';

$NP = new Novaposhta();

//Все офисы в Киеве
$kievOffices = $NP->getOfficesBy('city', "Киев");
echo '<h2>Все офисы в Киеве</h2>';
print_table($kievOffices);

//Все офисы в киевской области
$kievRegionOffices = $NP->getOfficesBy('region', "Киевск", true);
echo '<h2>Все офисы в киевской области</h2>';
print_table($kievRegionOffices);

//Ближайшие офисы
$address = 'Киев, Хрещатик';
$closestOffices = $NP->getClosestOffices($address);
echo '<h2>Ближайшие офисы к "'.$address.'"</h2>';
print_table($closestOffices);

//Статус доставки
$ttn = '12345678901234';
$deliveryStatus = $NP->getDeliveryStatus($ttn);
echo '<h2>Статус доставки с номером накладной №'.$ttn.'</h2>';
print_status($deliveryStatus);