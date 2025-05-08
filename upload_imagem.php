<?php
$targetDir = "uploads/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);  // Cria a pasta se não existir
}

if (!empty($_FILES["file"]["name"])) {
    $fileName = time() . "_" . basename($_FILES["file"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    // Extensões permitidas
    $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
    if (in_array($fileType, $allowTypes)) {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            header('Content-Type: application/json');
            echo json_encode(["caminho" => $targetFilePath]);
        } else {
            echo json_encode(["erro" => "Erro ao mover o arquivo."]);
        }
    } else {
        echo json_encode(["erro" => "Formato de arquivo não permitido."]);
    }
}
?>