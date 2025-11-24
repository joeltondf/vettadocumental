<?php
/**
 * Verifica se o usuário logado tem permissão para acessar a página.
 *
 * @param array $allowed_profiles Uma lista dos perfis que têm permissão.
 */
function require_permission(array $allowed_profiles)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userPerfil = $_SESSION['user_perfil'] ?? null;
    if ($userPerfil === 'secretária') {
        $userPerfil = 'secretaria';
    }

    // Se o perfil do usuário não estiver na lista de permitidos
    if (!$userPerfil || !in_array($userPerfil, $allowed_profiles)) {

        $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";

        // Redireciona para o dashboard principal como padrão seguro
        header('Location: dashboard.php');
        exit(); 
    }
}