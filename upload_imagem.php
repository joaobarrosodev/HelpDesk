<?php
// Diretório para salvar as imagens
$uploadDir = 'uploads/';

// Criar diretório se não existir
if (!file_exists($uploadDir) && !is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Resposta padrão (será modificada em caso de sucesso)
$response = array('success' => false, 'erro' => 'Nenhum arquivo enviado');

if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
    // Obter informações do arquivo
    $fileName = $_FILES['file']['name'];
    $fileType = $_FILES['file']['type'];
    $fileTmp = $_FILES['file']['tmp_name'];
    $fileError = $_FILES['file']['error'];
    
    // Validar se é uma imagem
    $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/jpg');
    
    if (in_array($fileType, $allowedTypes)) {
        // Gerar nome único
        $newFileName = uniqid() . '_' . $fileName;
        $destination = $uploadDir . $newFileName;
        
        // Mover para o diretório final
        if (move_uploaded_file($fileTmp, $destination)) {
            // Caminho absoluto para o servidor
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . $destination;
            
            // Caminho relativo para uso em URLs
            $relativePath = '/' . basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . $destination;
            
            // Resposta de sucesso com os caminhos da imagem
            $response = array(
                'success' => true,
                'caminho' => $relativePath,
                'fullPath' => $fullPath,
                'fileName' => $newFileName
            );
        } else {
            $response = array('success' => false, 'erro' => 'Falha ao mover o arquivo enviado');
        }
    } else {
        $response = array('success' => false, 'erro' => 'Formato de arquivo não permitido. Apenas JPG, JPEG, PNG e GIF são aceitos.');
    }
} else {
    $response = array('success' => false, 'erro' => 'Nenhum arquivo enviado');
}

// Retornar resposta em JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>