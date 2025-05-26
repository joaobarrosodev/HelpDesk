<?php
session_start();
header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'erro' => 'Nenhum arquivo foi enviado']);
    exit;
}

$file = $_FILES['file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'erro' => 'Erro no upload do arquivo']);
    exit;
}

// Check if it's an image
$imageInfo = getimagesize($file['tmp_name']);
if (!$imageInfo) {
    echo json_encode(['success' => false, 'erro' => 'O arquivo deve ser uma imagem válida']);
    exit;
}

// Check file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'erro' => 'O arquivo é muito grande. Máximo 5MB permitido']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileName = uniqid('ticket_') . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode([
        'success' => true,
        'caminho' => $filePath,
        'nome_original' => $file['name']
    ]);
} else {
    echo json_encode(['success' => false, 'erro' => 'Erro ao guardar o ficheiro']);
}
?>