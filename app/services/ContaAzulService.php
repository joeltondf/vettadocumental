<?php
// /app/services/ContaAzulService.php

require_once __DIR__ . '/../models/Configuracao.php';

class ContaAzulService {
    private $configModel;
    private $accessToken;
    
    private $baseApiUrl = 'https://api-v2.contaazul.com';
    private $authUrl    = 'https://auth.contaazul.com/oauth2';

    public function __construct(Configuracao $configModel) {
        $this->configModel = $configModel;
        // Revertido para o nome original
        $this->accessToken = $this->configModel->getSetting('conta_azul_access_token');
    }

    public function getAuthorizationUrl(): string {
        // Revertido para o nome original
        $clientId = $this->configModel->getSetting('conta_azul_client_id');
        if (!$clientId) {
            return '#';
        }

        if (empty($_SESSION['oauth2_state'])) {
            $_SESSION['oauth2_state'] = bin2hex(random_bytes(16));
        }

        $params = [
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => APP_URL . '/admin.php?action=ca_callback',
            'scope'         => 'openid profile aws.cognito.signin.user.admin',
            'state'         => $_SESSION['oauth2_state']
        ];

        return $this->authUrl . '/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): bool {
        // Revertido para o nome original
        $clientId = $this->configModel->getSetting('conta_azul_client_id');
        $clientSecret = $this->configModel->getSetting('conta_azul_client_secret');
        $authHeader = base64_encode($clientId . ':' . $clientSecret);
        $payload = http_build_query([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => APP_URL . '/admin.php?action=ca_callback'
        ]);
        
        $curlResponse = $this->makeCurlRequest('POST', $this->authUrl . '/token', $payload, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . $authHeader]);
        $response = $curlResponse['response'];

        if (isset($response['access_token'])) {
            // Revertido para o nome original
            $this->configModel->saveSetting('conta_azul_access_token', $response['access_token']);
            $this->configModel->saveSetting('conta_azul_refresh_token', $response['refresh_token']);
            $expiresAt = time() + ($response['expires_in'] ?? 3600);
            $this->configModel->saveSetting('conta_azul_token_expires_at', $expiresAt);
            return true;
        }
        
        error_log('Falha ao obter token da Conta Azul: ' . json_encode($response));
        return false;
    }

    private function refreshTokenIfNeeded(): void {
        // Revertido para o nome original
        $expiresAt = (int) $this->configModel->getSetting('conta_azul_token_expires_at');
        $refreshToken = $this->configModel->getSetting('conta_azul_refresh_token');

        if (!$refreshToken || time() < $expiresAt - 60) {
            return;
        }

        error_log("Conta Azul: Renovando token de acesso...");
        // Revertido para o nome original
        $clientId = $this->configModel->getSetting('conta_azul_client_id');
        $clientSecret = $this->configModel->getSetting('conta_azul_client_secret');
        $authHeader = base64_encode($clientId . ':' . $clientSecret);
        $payload = http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);

        $curlResponse = $this->makeCurlRequest('POST', $this->authUrl . '/token', $payload, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . $authHeader]);
        $response = $curlResponse['response'];

        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            // Revertido para o nome original
            $this->configModel->saveSetting('conta_azul_access_token', $response['access_token']);
            if(isset($response['refresh_token'])) {
                $this->configModel->saveSetting('conta_azul_refresh_token', $response['refresh_token']);
            }
            $newExpiresAt = time() + ($response['expires_in'] ?? 3600);
            $this->configModel->saveSetting('conta_azul_token_expires_at', $newExpiresAt);
            error_log("Conta Azul: Token renovado com sucesso.");
        } else {
            error_log('Falha ao renovar token da Conta Azul: ' . json_encode($response));
        }
    }

    private function makeApiRequest(string $method, string $endpoint, ?array $payload = null): array {
        $this->refreshTokenIfNeeded();
        if (!$this->accessToken) {
            throw new Exception("Access Token da Conta Azul não configurado ou inválido.");
        }
        $headers = ['Authorization: Bearer ' . $this->accessToken, 'Content-Type: application/json', 'Accept: application/json'];
        
        $jsonPayload = $payload ? json_encode($payload) : null;
        $curlResponse = $this->makeCurlRequest($method, $this->baseApiUrl . $endpoint, $jsonPayload, $headers);
        $decodedResponse = $curlResponse['response'];

        if (isset($decodedResponse['error']) || ($curlResponse['http_code'] >= 400)) {
            $errorMessage = "HTTP Code: " . $curlResponse['http_code'] . "\n"
                          . "Resposta da API: " . json_encode($decodedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
                          . "Log de Conexão cURL:\n" . $curlResponse['debug_log'];
            throw new Exception($errorMessage);
        }
        return $decodedResponse;
    }

    private function makeCurlRequest(string $method, string $url, $payload = null, array $headers = []): array {
        $ch = curl_init();
        $verbose = fopen('php://temp', 'w+');
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
        if ($payload && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        rewind($verbose);
        $debugLog = stream_get_contents($verbose);
        
        if ($curlError) {
            return ['error' => 'cURL Error', 'message' => $curlError, 'debug_log' => $debugLog];
        }

        return ['http_code' => $http_code, 'response' => json_decode($response, true), 'debug_log' => $debugLog];
    }

    public function findCustomerByName(string $name): ?array {
        try {
            $response = $this->makeApiRequest('GET', '/v1/pessoa?termo_busca=' . urlencode($name));
            return $response['itens'][0] ?? null;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createCustomer(array $payload): array {
        return $this->makeApiRequest('POST', '/v1/pessoa', $payload);
    }

    public function findProductByName(string $name): ?array {
        try {
            $response = $this->makeApiRequest('GET', '/v1/produto/busca?nome=' . urlencode($name));
            return $response['itens'][0] ?? null;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createProduct(array $payload): array {
        return $this->makeApiRequest('POST', '/v1/produto', $payload);
    }

    public function createSale(array $payload): array {
        return $this->makeApiRequest('POST', '/v1/venda', $payload);
    }
    
    public function createService(array $payload): array
    {
        return $this->makeApiRequest('POST', '/v1/servicos', $payload);
    }
}
