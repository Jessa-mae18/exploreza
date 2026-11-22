<?php
// Database connection (PDO).
$pdo = new PDO('mysql:host=localhost;dbname=tourbook;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
