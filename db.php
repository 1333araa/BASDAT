<?php 
$host = 'localhost'; 
$port = '5432';
$dbname = 'sistemmanajemenanggaransma'; 
$user = 'postgres'; 
$password = '1sampai8'; 

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password"); 
if (!$conn) { 
    die("Koneksi gagal: " . pg_last_error()); 
} 

// HAPUS search_path
?>
