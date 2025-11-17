<?php
// Força o download de anexos usando o nome original do arquivo

require_once 'config.php';
require_once __DIR__ . '/app/core/auth_check.php';

$anexo_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($anexo_id <= 0) {
    exit('Anexo não encontrado.');
}

$stmt = $pdo->prepare('SELECT caminho_arquivo, nome_arquivo_original FROM processo_anexos WHERE id = ?');
$stmt->execute([$anexo_id]);
$anexo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anexo) {
    exit('Anexo não encontrado.');
}

$caminho_completo = __DIR__ . '/' . $anexo['caminho_arquivo'];

if (!file_exists($caminho_completo)) {
    exit('Arquivo não encontrado no servidor.');
}

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="' . basename($anexo['nome_arquivo_original']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($caminho_completo));

readfile($caminho_completo);
exit;
