<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Cliente.php';

class ProspectionConversionService
{
    private PDO $pdo;
    private Prospeccao $prospectionModel;
    private User $userModel;
    private Cliente $clientModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->clientModel = new Cliente($pdo);
    }

    public function convert(
        int $prospectionId,
        int $initiatorId,
        ?int $authorizedManagerId = null,
        ?string $initiatorProfile = null
    ): string
    {
        $prospection = $this->prospectionModel->getById($prospectionId);
        if (!$prospection) {
            throw new RuntimeException('Prospecção não encontrada.');
        }

        $initiatorData = $this->userModel->getById($initiatorId) ?: [];
        $resolvedProfile = strtolower(trim((string) ($initiatorProfile ?? ($initiatorData['perfil'] ?? ''))));
        $initiatorName = trim((string) ($initiatorData['nome_completo'] ?? 'Usuário'));

        if ($resolvedProfile === 'vendedor') {
            $responsavelId = (int) ($prospection['responsavel_id'] ?? 0);
            if ($responsavelId !== $initiatorId) {
                throw new RuntimeException('Somente o vendedor responsável pode converter este lead.');
            }
        }

        $clientId = (int) ($prospection['cliente_id'] ?? 0);
        if ($clientId <= 0) {
            throw new RuntimeException('A prospecção não está vinculada a um lead válido.');
        }

        $this->pdo->beginTransaction();

        try {
            $updateStmt = $this->pdo->prepare(
                "UPDATE prospeccoes SET status = 'Convertido', data_ultima_atualizacao = NOW() WHERE id = :id"
            );
            $updateStmt->bindValue(':id', $prospectionId, PDO::PARAM_INT);
            $updateStmt->execute();

            if (!$this->clientModel->promoteProspectToClient($clientId, $initiatorId)) {
                throw new RuntimeException('Não foi possível promover o lead para cliente.');
            }

            $managerName = '';

            if ($authorizedManagerId !== null) {
                $manager = $this->userModel->getById($authorizedManagerId);
                $managerName = $manager['nome_completo'] ?? 'Gestor';
                $this->prospectionModel->logInteraction(
                    $prospectionId,
                    $authorizedManagerId,
                    'Autorizou a conversão do lead.',
                    'log_sistema'
                );
            }

            if ($authorizedManagerId !== null && $authorizedManagerId !== $initiatorId) {
                $conversionNote = sprintf('Lead convertido em cliente com autorização de %s.', $managerName);
            } elseif ($resolvedProfile === 'vendedor') {
                $conversionNote = sprintf('Lead convertido em cliente diretamente pelo vendedor %s.', $initiatorName);
            } elseif ($resolvedProfile === 'sdr') {
                $conversionNote = sprintf('Lead convertido em cliente pelo SDR %s.', $initiatorName);
            } else {
                $conversionNote = 'Lead convertido em cliente.';
            }

            $this->prospectionModel->logInteraction(
                $prospectionId,
                $initiatorId,
                $conversionNote,
                'log_sistema'
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $queryParams = [
            'prospeccao_id' => $prospectionId,
            'nome_servico' => $prospection['nome_prospecto'] ?? '',
            'valor_inicial' => $prospection['valor_proposto'] ?? 0,
        ];

        return APP_URL . '/clientes.php?action=edit&id=' . $clientId . '&' . http_build_query($queryParams);
    }
}
