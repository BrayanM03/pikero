<?php
require_once __DIR__ . '/../../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $pass = $_POST['pass'] ?? '';

    $auth = new AuthController();
    $response = $auth->login($username, $pass);

    header('Content-Type: application/json');
    echo json_encode($response);
}
