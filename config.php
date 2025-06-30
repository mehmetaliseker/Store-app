<?php
$host = "localhost";
$kullanici_adi = "root"; 
$sifre = "";            
$veritabani = "magaza";

$baglanti = mysqli_connect($host, $kullanici_adi, $sifre, $veritabani);

if(!$baglanti) {
    die("Bağlantı hatası: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($baglanti, "utf8");
?> 