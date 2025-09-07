<?php
// /app/views/processos/detalhe.php

$pageTitle = "Detalhes do Processo";
$isContaAzulConnected = !empty((new Configuracao($GLOBALS['pdo']))->getSetting('conta_azul_access_token'));

function has_service($processo, $service_name) {
    if (empty($processo['categorias_servico'])) return false;
    $services = explode(',', $processo['categorias_servico']);
    return in_array($service_name, $services);
}

function format_prazo_countdown($dateString) {
    if (empty($dateString)) return '<span class="text-gray-500">Não definido</span>';
    try {
        $today = new DateTime(); $today->setTime(0, 0, 0);
        $prazoDate = new DateTime($dateString); $prazoDate->setTime(0, 0, 0);
        if ($prazoDate < $today) {
            $diff = $today->diff($prazoDate);
            return '<span class="font-bold text-red-500">Atrasado há ' . $diff->days . ' dia(s)</span>';
        } elseif ($prazoDate == $today) {
            return '<span class="font-bold text-blue-500">Entrega hoje</span>';
        } else {
            $diff = $today->diff($prazoDate);
            return '<span class="font-bold text-green-600">Faltam ' . ($diff->days) . ' dia(s)</span>';
        }
    } catch (Exception $e) {
        return '<span class="text-gray-500">Data inválida</span>';
    }
}

$status = $processo['status_processo'] ?? 'N/A';
$status_classes = 'bg-gray-100 text-gray-800';
switch ($status) {
    case 'Orçamento': $status_classes = 'bg-yellow-100 text-yellow-800'; break;
    case 'Aprovado': $status_classes = 'bg-blue-100 text-blue-800'; break;
    case 'Em Andamento': $status_classes = 'bg-cyan-100 text-cyan-800'; break;
    case 'Finalizado': $status_classes = 'bg-green-100 text-green-800'; break;
    case 'Arquivado': $status_classes = 'bg-gray-200 text-gray-600'; break;
    case 'Cancelado': $status_classes = 'bg-red-100 text-red-800'; break;
}
$isAprovadoOuSuperior = !in_array($status, ['Orçamento', 'Cancelado']);
?>


<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Detalhes: <?php echo htmlspecialchars($processo['titulo'] ?? 'Processo'); ?></h1>
        <a href="dashboard.php" class="text-sm text-blue-600 hover:underline">&larr; Voltar</a>
    </div>
    <div class="flex items-center space-x-4">
        <span id="display-status-badge" class="px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $status_classes; ?>">
            <?php echo htmlspecialchars($status); ?>
        </span>
        <a href="processos.php?action=edit&id=<?php echo $processo['id']; ?>&return_to=<?php echo urlencode('processos.php?action=view&id=' . $processo['id']); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
            Editar Processo
        </a>
    </div>
</div>

<div id="feedback-container" class="hidden mb-4"></div>

<div class="flex flex-col lg:flex-row gap-6">

    <div class="lg:w-2/3 space-y-6">

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Informações Gerais</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Nº Orçamento</p>
                    <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['orcamento_numero'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Cliente / Assessoria</p>
                    <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['nome_cliente'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Data de Entrada</p>
                    <p class="text-lg text-gray-800"><?php echo isset($processo['data_criacao']) ? date('d/m/Y', strtotime($processo['data_criacao'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">
                <?php if ($processo['status_processo'] == 'Orçamento'): ?>
                    Serviços Orçados
                <?php else: ?>
                    Serviços Contratados
                <?php endif; ?>
                </h2>
            <div class="space-y-4">
                <?php if (has_service($processo, 'Tradução')): ?>
                    <div class="p-3 bg-blue-50 rounded-md">
                        <h3 class="font-semibold text-blue-800">Tradução</h3>
                        <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                            <p><strong>Idioma:</strong> <?php echo htmlspecialchars($processo['idioma'] ?? 'N/A'); ?></p>
                            <p><strong>Assinatura:</strong> <?php echo htmlspecialchars($processo['modalidade_assinatura'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_service($processo, 'Apostilamento')): ?>
                    <div class="p-3 bg-yellow-50 rounded-md">
                        <h3 class="font-semibold text-yellow-800">Apostilamento</h3>
                        <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
                            <p><strong>Qtd:</strong> <?php echo htmlspecialchars($processo['apostilamento_quantidade'] ?? '0'); ?></p>
                            <p><strong>Valor Unit.:</strong> R$ <?php echo number_format($processo['apostilamento_valor_unitario'] ?? 0, 2, ',', '.'); ?></p>
                            <p><strong>Subtotal:</strong> R$ <?php echo number_format(($processo['apostilamento_quantidade'] ?? 0) * ($processo['apostilamento_valor_unitario'] ?? 0), 2, ',', '.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_service($processo, 'Postagem')): ?>
                    <div class="p-3 bg-purple-50 rounded-md">
                        <h3 class="font-semibold text-purple-800">Postagem / Envio</h3>
                        <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
                            <p><strong>Qtd:</strong> <?php echo htmlspecialchars($processo['postagem_quantidade'] ?? '0'); ?></p>
                            <p><strong>Valor Unit.:</strong> R$ <?php echo number_format($processo['postagem_valor_unitario'] ?? 0, 2, ',', '.'); ?></p>
                            <p><strong>Subtotal:</strong> R$ <?php echo number_format(($processo['postagem_quantidade'] ?? 0) * ($processo['postagem_valor_unitario'] ?? 0), 2, ',', '.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Documentos do Processo</h2>
            <?php if (empty($documentos)): ?>
                <p class="text-gray-500 text-center py-4">Nenhum documento adicionado.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome Específico</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['nome_documento'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['categoria'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['tipo_documento'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">R$ <?php echo number_format($doc['valor_unitario'] ?? 0, 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Etapa de Tradução</h2>
                <button data-modal-target="modal-etapa-traducao" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Tradutor</p>
                    <p class="text-gray-800" id="display-nome_tradutor"><?php echo htmlspecialchars($processo['nome_tradutor'] ?? 'FATTO'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Qtd. Documentos</p>
                    <p class="text-gray-800" id="display-document_count">
                        <?php
                            // Soma a quantidade de todos os documentos do processo
                            $total_docs = 0;
                            if (!empty($documentos)) {
                                foreach ($documentos as $doc) {
                                    $total_docs += (int)($doc['quantidade'] ?? 1);
                                }
                            }
                            echo $total_docs;
                        ?>
                    </p>
                </div>

                <div>
                    <p class="font-medium text-gray-500">Modalidade</p>
                    <p class="text-gray-800" id="display-traducao_modalidade"><?php echo htmlspecialchars($processo['traducao_modalidade'] ?? 'Normal'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Envio para Tradutor</p>
                    <p class="text-gray-800" id="display-data_inicio_traducao"><?php echo isset($processo['data_inicio_traducao']) ? date('d/m/Y', strtotime($processo['data_inicio_traducao'])) : 'Pendente'; ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="font-medium text-gray-500">Prazo do Serviço</p>
                    <div id="display-traducao_prazo_data_formatted">
                        <?php
                            $data_previsao_final_str = null;
                            // Verifica se o prazo é por data específica
                            if (!empty($processo['traducao_prazo_data'])) {
                                $data_previsao_final_str = $processo['traducao_prazo_data'];
                            } 
                            // Senão, calcula o prazo com base nos dias
                            elseif (!empty($processo['traducao_prazo_dias']) && !empty($processo['data_inicio_traducao'])) {
                                $data_previsao_final = new DateTime($processo['data_inicio_traducao']);
                                $data_previsao_final->modify('+' . $processo['traducao_prazo_dias'] . ' days');
                                $data_previsao_final_str = $data_previsao_final->format('Y-m-d');
                            }
                            echo format_prazo_countdown($data_previsao_final_str);
                        ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Assinatura da Tradutora</h2>
                <button data-modal-target="modal-etapa-assinatura" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Tipo de Assinatura</p>
                    <p class="text-gray-800" id="display-assinatura_tipo"><?php echo htmlspecialchars($processo['assinatura_tipo'] ?? 'Digital'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Envio da Tradução</p>
                    <p class="text-gray-800" id="display-data_envio_assinatura"><?php echo isset($processo['data_envio_assinatura']) ? date('d/m/Y', strtotime($processo['data_envio_assinatura'])) : 'Pendente'; ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Data de Devolução</p>
                    <p class="text-gray-800" id="display-data_devolucao_assinatura"><?php echo isset($processo['data_devolucao_assinatura']) ? date('d/m/Y', strtotime($processo['data_devolucao_assinatura'])) : 'Pendente'; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Cartório | Cliente</h2>
                <button data-modal-target="modal-etapa-cartorio" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Finalização</p>
                    <p class="text-gray-800" id="display-finalizacao_tipo"><?php echo htmlspecialchars($processo['finalizacao_tipo'] ?? 'Cliente'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Data de Envio para Cartório</p>
                    <p class="text-gray-800" id="display-data_envio_cartorio"><?php echo isset($processo['data_envio_cartorio']) ? date('d/m/Y', strtotime($processo['data_envio_cartorio'])) : 'Pendente'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:w-1/3 space-y-6">
        <?php
// Bloco de Ações para Admin e Gerência
// Verifica se o usuário é admin/gerente E se o orçamento está pendente de aprovação
$isManager = in_array($_SESSION['user_perfil'], ['admin', 'gerencia']);
if ($isManager && $processo['status_processo'] === 'Orçamento Pendente'):
?>
<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-bold text-lg">Ações de Aprovação</h3>
            <p class="text-sm">Este orçamento foi enviado por um vendedor e aguarda sua análise.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="processos.php?action=aprovar_orcamento&id=<?php echo $processo['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out" onclick="return confirm('Tem certeza que deseja APROVAR este orçamento?');">
                 Aprovar
            </a>
            <button type="button" id="recusarBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
                 Recusar
            </button>
        </div>
    </div>
</div>

<div id="recusaModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Motivo da Recusa</h3>
            <div class="mt-2 px-7 py-3">
                <form action="processos.php?action=recusar_orcamento" method="POST">
                    <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
                    <textarea name="motivo_recusa" class="w-full h-24 p-2 border border-gray-300 rounded-md" placeholder="Descreva o motivo da recusa para que o vendedor possa ajustar o orçamento." required></textarea>
                    <div class="items-center px-4 py-3">
                        <button type="button" id="closeModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md mr-2 hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Confirmar Recusa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recusarBtn = document.getElementById('recusarBtn');
    const recusaModal = document.getElementById('recusaModal');
    const closeModalBtn = document.getElementById('closeModalBtn');

    if (recusarBtn) {
        recusarBtn.addEventListener('click', () => {
            recusaModal.classList.remove('hidden');
        });
    }
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            recusaModal.classList.add('hidden');
        });
    }
    window.addEventListener('click', (event) => {
        if (event.target === recusaModal) {
            recusaModal.classList.add('hidden');
        }
    });
});
</script>
<?php endif; ?>

       <?php if ($isAprovadoOuSuperior): ?>
        <div class="bg-white shadow-lg rounded-lg p-6 border-l-4 border-green-500">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Dados de Faturamento</h2>
                <button data-modal-target="modal-etapa-faturamento" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Ordem de Serviço (CA)</p>
                    <p class="text-gray-800 text-lg" id="display-os_numero_conta_azul"><?php echo htmlspecialchars($processo['os_numero_conta_azul'] ?? 'Não definida'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($processo['status_processo'] == 'Recusado' && $_SESSION['user_perfil'] === 'vendedor'): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
                <p class="font-bold text-lg">Orçamento Recusado pela Gerência</p>
                
                <?php if (!empty($processo['motivo_recusa'])): ?>
                    <p class="mt-2"><strong>Motivo:</strong> <?php echo nl2br(htmlspecialchars($processo['motivo_recusa'])); ?></p>
                <?php endif; ?>

                <p class="mt-3">Por favor, <a href="processos.php?action=edit&id=<?php echo $processo['id']; ?>" class="font-bold underline hover:text-yellow-800">edite o orçamento</a> para ajustar os valores ou itens e depois o reenvie para análise.</p>
                
                <form action="processos.php?action=change_status" method="POST" class="mt-4">
                    <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
                    <input type="hidden" name="status_processo" value="Orçamento Pendente">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                        Reenviar para Análise
                    </button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'financeiro'])): ?>
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Ações da Conta Azul</h2>
        
            <?php if ($isContaAzulConnected): ?>
                <?php if (!empty($processo['conta_azul_venda_id'])): ?>
                    <p class="text-sm text-green-700 text-center">
                        Esta venda já foi lançada na Conta Azul.
                        <br>
                        <p class="font-bold text-sm text-green-700 text-center">
                            Número <?php echo htmlspecialchars($processo['os_numero_conta_azul'] ?? 'N/A'); ?>
                        </p>
                    </p>
                <?php else: ?>
                    <a href="processos.php?action=create_ca_sale&id=<?php echo $processo['id']; ?>" 
                       class="w-full text-center block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm"
                       onclick="return confirm('Tem certeza que deseja criar esta Venda na Conta Azul?');">
                        <i class="fas fa-dollar-sign mr-2"></i> Criar Venda na Conta Azul
                    </a>
                    <p class="text-xs text-gray-500 mt-2 text-center">Esta ação irá gerar uma venda faturada na sua conta.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-sm text-center text-gray-500">
                    Conecte a Conta Azul no painel de <a href="admin.php?action=settings" class="text-blue-600 hover:underline">Configurações</a> para habilitar esta ação.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'financeiro'])): ?>
            <div class="bg-white shadow-lg rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Resumo Financeiro</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Valor Total do Processo</p>
                        <p class="text-2xl font-bold text-green-600">R$ <?php echo number_format($processo['valor_total'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Forma de Pagamento</p>
                        <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['orcamento_forma_pagamento'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Valor de Entrada</p>
                        <p class="text-lg text-gray-800">R$ <?php echo number_format($processo['orcamento_valor_entrada'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Valor Restante</p>
                        <?php $restante = ($processo['valor_total'] ?? 0) - ($processo['orcamento_valor_entrada'] ?? 0); ?>
                        <p class="text-lg font-bold text-red-600">R$ <?php echo number_format($restante, 2, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 mt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-receipt mr-2 text-gray-400"></i>Comprovantes de Pagamento
                </h3>
                
                <?php 
                    // Busca os comprovantes usando o novo método
                    $comprovantes = $this->processoModel->getAnexosPorCategoria($processo['id'], 'comprovante');
                ?>

                <?php if (!empty($comprovantes)): ?>
                    <ul class="space-y-3">
                        <?php foreach ($comprovantes as $anexo): ?>
                            <li class="flex items-center justify-between p-2 rounded-md bg-gray-50 hover:bg-gray-100">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar text-green-500 mr-3"></i>
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-sm font-medium text-blue-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']) ?>
                                    </a>
                                </div>
                                <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" 
                                class="text-red-500 hover:text-red-700 text-xs font-semibold"
                                onclick="return confirm('Tem certeza que deseja excluir este comprovante?');">
                                EXCLUIR
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Nenhum comprovante de pagamento anexado.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])): ?>
            <div class="bg-white shadow-lg rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Painel de Ações</h2>

                <form id="status-change-form" action="processos.php?action=change_status" method="POST" class="space-y-4">
                    <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
                    <input type="hidden" name="os_numero_conta_azul" id="hidden_os_numero_conta_azul">

                    <input type="hidden" name="data_inicio_traducao" id="hidden_data_inicio_traducao">
                    <input type="hidden" name="traducao_prazo_tipo" id="hidden_traducao_prazo_tipo">
                    <input type="hidden" name="traducao_prazo_dias" id="hidden_traducao_prazo_dias">
                    <input type="hidden" name="traducao_prazo_data" id="hidden_traducao_prazo_data">
                    <div>
                        <label for="status_processo" class="block text-sm font-medium text-gray-700">Mudar Status para:</label>
                        <select id="status_processo" name="status_processo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <?php $allStatus = ['Orçamento', 'Aprovado', 'Em Andamento', 'Finalizado', 'Arquivado', 'Cancelado']; ?>
                            <?php foreach ($allStatus as $stat): ?>
                                <option value="<?php echo $stat; ?>" <?php echo ($processo['status_processo'] == $stat) ? 'selected' : ''; ?>>
                                    <?php echo $stat; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700">
                        Atualizar Status
                    </button>
                </form>

                <div class="mt-4 pt-4 border-t border-gray-200 space-y-2">
                    <a href="processos.php?action=delete&id=<?php echo $processo['id']; ?>"
                       onclick="return confirm('ATENÇÃO!\nEsta ação é irreversível e irá apagar permanentemente este processo e todos os seus dados.\n\nTem certeza que deseja excluir?');"
                       class="w-full text-center block bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 text-sm">
                        Excluir Processo
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-paperclip mr-2 text-gray-400"></i>Anexos
            </h3>
            
            <?php if (!empty($anexos)): ?>
                <ul class="space-y-3">
                    <?php foreach ($anexos as $anexo): ?>
                        <li class="flex items-center justify-between p-2 rounded-md bg-gray-50 hover:bg-gray-100">
                            <div class="flex items-center">
                                <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                                <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-sm font-medium text-blue-600 hover:underline">
                                    <?= htmlspecialchars($anexo['nome_arquivo_original']) ?>
                                </a>
                            </div>
                                <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" 
                                class="text-red-500 hover:text-red-700 text-xs font-semibold"
                                onclick="return confirm('Tem certeza que deseja excluir este anexo?');">
                                EXCLUIR
                                </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-sm text-gray-500">Nenhum arquivo anexado a este processo.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Comentários</h2>
            <form id="comment-form" class="mb-4">
                <input type="hidden" name="processo_id" value="<?php echo $processo['id']; ?>">
                <textarea name="comentario" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Deixe um comentário..." required></textarea>
                <button type="submit" class="mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Publicar</button>
            </form>
            <div id="comments-list" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <?php if (empty($comentarios)): ?>
                    <p id="no-comments" class="text-sm text-gray-500 text-center">Nenhum comentário ainda.</p>
                <?php else: ?>
                    <?php foreach($comentarios as $comentario): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($comentario['nome_completo']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($comentario['data_comentario'])); ?></p>
                            </div>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="modal-etapa-faturamento" class="modal-overlay hidden">
    <div class="modal-content">
        <form class="modal-form" data-action="processos.php?action=update_etapas">
            <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
            <h3 class="text-lg font-bold mb-4">Editar Dados de Faturamento</h3>
            <div class="space-y-4">
                <div>
                    <label for="modal_os_numero_conta_azul" class="block text-sm font-medium text-gray-700">Ordem de Serviço (CA)</label>
                    <input type="text" name="os_numero_conta_azul" id="modal_os_numero_conta_azul" class="mt-1 block w-full border-gray-300 rounded-md" value="<?php echo htmlspecialchars($processo['os_numero_conta_azul'] ?? ''); ?>" placeholder="Insira o número da O.S.">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-close-btn">Cancelar</button>
                <button type="submit" class="modal-save-btn">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-ca-required" class="modal-overlay hidden">
        <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <!-- Cabeçalho -->
        <div class="mb-4 sm:mb-5">
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
            Aprovação de Orçamento
            </h3>
            <p class="text-sm text-gray-600 mt-1">
            Para aprovar este orçamento ou colocá-lo em andamento, informe os dados abaixo.
            </p>
        </div>

        <!-- Form body -->
        <div class="space-y-5">
            <!-- CA (OS da Conta Azul) -->
            <div>
            <label for="modal_required_os_numero" class="block text-sm font-medium text-gray-700 mb-1">
                Ordem de Serviço (CA) <span class="text-red-500">*</span>
            </label>
            <div class="flex rounded-md shadow-sm overflow-hidden border border-gray-300 focus-within:ring-1 focus-within:ring-gray-400">
                <span class="inline-flex items-center px-3 text-sm text-gray-500 bg-gray-50 border-r border-gray-300">
                CA
                </span>
                <input
                type="text"
                id="modal_required_os_numero"
                class="w-full px-3 py-2 outline-none"
                placeholder="Ex.: 12345"
                required
                >
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Informe o número da O.S. registrada no Conta Azul.
            </p>
            </div>

            <!-- Data de envio para o Tradutor -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
                <label for="modal_data_inicio_traducao" class="block text-sm font-medium text-gray-700 mb-1">
                Data de Envio para o Tradutor <span class="text-red-500">*</span>
                </label>
                <input
                type="date"
                id="modal_data_inicio_traducao"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo date('Y-m-d'); ?>"
                >
                <p class="mt-1 text-xs text-gray-500">
                Preenche automaticamente com a data de hoje — ajuste se necessário.
                </p>
            </div>
            </div>

            <!-- Prazo do Serviço -->
            <div class="border-t border-gray-100 pt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Prazo do Serviço <span class="text-red-500">*</span>
            </label>

            <!-- Tipo do prazo -->
            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2">
                <input
                    type="radio"
                    name="modal_prazo_tipo"
                    value="dias"
                    id="modal_prazo_tipo_dias"
                    class="prazo-tipo-radio"
                    <?php echo (($processo['traducao_prazo_tipo'] ?? 'dias') === 'dias') ? 'checked' : ''; ?>
                >
                <span class="text-sm text-gray-800">Em dias</span>
                </label>

                <label class="inline-flex items-center gap-2">
                <input
                    type="radio"
                    name="modal_prazo_tipo"
                    value="data"
                    id="modal_prazo_tipo_data"
                    class="prazo-tipo-radio"
                    <?php echo (($processo['traducao_prazo_tipo'] ?? '') === 'data') ? 'checked' : ''; ?>
                >
                <span class="text-sm text-gray-800">Data específica</span>
                </label>
            </div>

            <!-- Conteúdo dinâmico do prazo -->
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <!-- Prazo em dias -->
                <div id="modal_prazo_dias_container">
                <label for="modal_traducao_prazo_dias" class="block text-sm font-medium text-gray-700 mb-1">
                    Dias para Entrega <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    min="1"
                    id="modal_traducao_prazo_dias"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                    placeholder="Ex.: 5"
                    value="<?php echo htmlspecialchars($processo['traducao_prazo_dias'] ?? ''); ?>"
                >
                <p class="mt-1 text-xs text-gray-500">Informe um número inteiro de dias.</p>
                </div>

                <!-- Prazo por data específica -->
                <div id="modal_prazo_data_container" class="hidden">
                <label for="modal_traducao_prazo_data" class="block text-sm font-medium text-gray-700 mb-1">
                    Data da Entrega <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    id="modal_traducao_prazo_data"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                    value="<?php echo htmlspecialchars($processo['traducao_prazo_data'] ?? ''); ?>"
                >
                <p class="mt-1 text-xs text-gray-500">Selecione a data final para a entrega.</p>
                </div>
            </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="mt-6 sm:mt-7 flex items-center justify-end gap-2 sm:gap-3">
            <button
            type="button"
            id="cancel-status-change"
            class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
            Cancelar
            </button>
            <button
            type="button"
            id="confirm-status-change"
            class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
            >
            Salvar e Mudar Status
            </button>
        </div>
        </div>
</div>


<div id="modal-etapa-traducao" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
    <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
        <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

        <!-- Cabeçalho -->
        <div>
        <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">Editar Etapa de Tradução</h3>
        <p class="text-sm text-gray-600 mt-1">Defina o tradutor, modalidade, data de envio e o prazo do serviço.</p>
        </div>

        <!-- Quantidade de documentos -->
        <div class="rounded-md border border-gray-100 bg-gray-50 p-3 sm:p-4">
        <label class="block text-sm font-medium text-gray-700">Quantidade de Documentos</label>
        <p class="mt-1 text-gray-800 font-semibold"><?php echo count($documentos); ?> documento(s)</p>
        </div>

        <!-- Tradutor + Modalidade -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
        <div>
            <label for="modal_tradutor_id" class="block text-sm font-medium text-gray-700 mb-1">Tradutor</label>
            <select
            name="tradutor_id"
            id="modal_tradutor_id"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
            <option value="" <?php echo (empty($processo['tradutor_id'])) ? 'selected' : ''; ?>>FATTO (Padrão)</option>
            <?php if (!empty($tradutores)): ?>
                <?php foreach($tradutores as $tradutor): ?>
                <option value="<?php echo $tradutor['id']; ?>"
                    <?php echo (isset($processo['tradutor_id']) && $processo['tradutor_id'] == $tradutor['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($tradutor['nome_tradutor']); ?>
                </option>
                <?php endforeach; ?>
            <?php endif; ?>
            </select>
        </div>

        <div>
            <label for="modal_traducao_modalidade" class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
            <select
            name="traducao_modalidade"
            id="modal_traducao_modalidade"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
            <option value="Normal"  <?php echo ($processo['traducao_modalidade'] == 'Normal')  ? 'selected' : ''; ?>>Normal</option>
            <option value="Express" <?php echo ($processo['traducao_modalidade'] == 'Express') ? 'selected' : ''; ?>>Express</option>
            </select>
        </div>
        </div>

        <!-- Data de envio para tradutor -->
        <div>
        <label for="modal_data_inicio_traducao" class="block text-sm font-medium text-gray-700 mb-1">
            Data de Envio para Tradutor <span class="text-red-500">*</span>
        </label>
        <input
            type="date"
            name="data_inicio_traducao"
            id="modal_data_inicio_traducao"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            value="<?php echo htmlspecialchars($processo['data_inicio_traducao'] ?? date('Y-m-d')); ?>"
            required
        >
        <p class="mt-1 text-xs text-gray-500">Preenche automaticamente com a data de hoje se ainda não houver data.</p>
        </div>

        <!-- Prazo do Serviço -->
        <div class="border-t border-gray-100 pt-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Prazo do Serviço <span class="text-red-500">*</span>
        </label>

        <div class="flex items-center gap-6">
            <label class="inline-flex items-center gap-2">
            <input
                type="radio"
                class="prazo-tipo-traducao"
                name="traducao_prazo_tipo"
                value="dias"
                id="prazo_tipo_dias"
                <?php echo (($processo['traducao_prazo_tipo'] ?? 'dias') == 'dias') ? 'checked' : ''; ?>
            >
            <span class="text-sm text-gray-800">Em dias</span>
            </label>

            <label class="inline-flex items-center gap-2">
            <input
                type="radio"
                class="prazo-tipo-traducao"
                name="traducao_prazo_tipo"
                value="data"
                id="prazo_tipo_data"
                <?php echo (($processo['traducao_prazo_tipo'] ?? '') == 'data') ? 'checked' : ''; ?>
            >
            <span class="text-sm text-gray-800">Data específica</span>
            </label>
        </div>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <!-- Dias -->
            <div id="prazo_dias_traducao_container">
            <label for="traducao_prazo_dias" class="block text-sm font-medium text-gray-700 mb-1">Dias para Entrega</label>
            <input
                type="number"
                name="traducao_prazo_dias"
                id="traducao_prazo_dias"
                min="1"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['traducao_prazo_dias'] ?? ''); ?>"
                placeholder="Ex.: 5"
            >
            <p class="mt-1 text-xs text-gray-500">Informe um número inteiro de dias.</p>
            </div>

            <!-- Data específica -->
            <div id="prazo_data_traducao_container" class="hidden">
            <label for="traducao_prazo_data" class="block text-sm font-medium text-gray-700 mb-1">Data da Entrega</label>
            <input
                type="date"
                name="traducao_prazo_data"
                id="traducao_prazo_data"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['traducao_prazo_data'] ?? ''); ?>"
            >
            <p class="mt-1 text-xs text-gray-500">Selecione a data final para a entrega.</p>
            </div>
        </div>
        </div>

        <!-- Ações -->
        <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
        <button type="button" class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancelar
        </button>
        <button type="submit" class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
            Atualizar
        </button>
        </div>
    </form>
    </div>

</div>

<div id="modal-etapa-assinatura" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
            <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

            <!-- Cabeçalho -->
            <div>
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
                Editar Assinatura da Tradutora
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Defina o tipo de assinatura e as datas de envio e devolução.
            </p>
            </div>

            <!-- Tipo de Assinatura -->
            <div>
            <label for="modal_assinatura_tipo" class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Assinatura
            </label>
            <select
                name="assinatura_tipo"
                id="modal_assinatura_tipo"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
                <option value="Física" <?php echo ($processo['assinatura_tipo'] == 'Física') ? 'selected' : ''; ?>>Física</option>
                <option value="Digital" <?php echo ($processo['assinatura_tipo'] == 'Digital') ? 'selected' : ''; ?>>Digital</option>
            </select>
            </div>

            <!-- Data de Envio da Tradução -->
            <div>
            <label for="modal_data_envio_assinatura" class="block text-sm font-medium text-gray-700 mb-1">
                Envio da Tradução
            </label>
            <input
                type="date"
                name="data_envio_assinatura"
                id="modal_data_envio_assinatura"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_envio_assinatura'] ?? date('Y-m-d')); ?>"
            >
            </div>

            <!-- Data de Devolução -->
            <div>
            <label for="modal_data_devolucao_assinatura" class="block text-sm font-medium text-gray-700 mb-1">
                Data de Devolução
            </label>
            <input
                type="date"
                name="data_devolucao_assinatura"
                id="modal_data_devolucao_assinatura"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_devolucao_assinatura'] ?? ''); ?>"
            >
            </div>

            <!-- Ações -->
            <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
            <button
                type="button"
                class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancelar
            </button>
            <button
                type="submit"
                class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
            >
                Atualizar
            </button>
            </div>
        </form>
</div>

</div>

<div id="modal-etapa-cartorio" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
            <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

            <!-- Cabeçalho -->
            <div>
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
                Editar Etapa Cartório | Cliente
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Defina o tipo de finalização e a data de envio para o cartório.
            </p>
            </div>

            <!-- Finalização -->
            <div>
            <label for="modal_finalizacao_tipo" class="block text-sm font-medium text-gray-700 mb-1">
                Finalização
            </label>
            <select
                name="finalizacao_tipo"
                id="modal_finalizacao_tipo"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
                <option value="Cliente" <?php echo ($processo['finalizacao_tipo'] == 'Cliente') ? 'selected' : ''; ?>>Cliente</option>
                <option value="Cartório" <?php echo ($processo['finalizacao_tipo'] == 'Cartório') ? 'selected' : ''; ?>>Cartório</option>
            </select>
            </div>

            <!-- Data de Envio para Cartório -->
            <div>
            <label for="modal_data_envio_cartorio" class="block text-sm font-medium text-gray-700 mb-1">
                Data de Envio para Cartório
            </label>
            <input
                type="date"
                name="data_envio_cartorio"
                id="modal_data_envio_cartorio"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_envio_cartorio'] ?? date('Y-m-d')); ?>"
            >
            </div>

            <!-- Ações -->
            <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
            <button
                type="button"
                class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancelar
            </button>
            <button
                type="submit"
                class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
            >
                Atualizar
            </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    transition: opacity 0.3s ease;
}
.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}
.modal-overlay.hidden {
    opacity: 0;
    pointer-events: none;
}
.modal-overlay.hidden .modal-content {
    transform: translateY(-30px);
}
.modal-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.modal-close-btn {
    background-color: #e5e7eb;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-weight: bold;
    cursor: pointer;
}
.modal-save-btn {
    background-color: #2563eb;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: bold;
    cursor: pointer;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {

  //-----------------------------------------------------
  // Helpers
  //-----------------------------------------------------
  const $id = (x) => document.getElementById(x);
  const setTodayIfEmpty = (inputEl) => {
    if (inputEl && !inputEl.value) {
      const t = new Date();
      const yyyy = t.getFullYear();
      const mm = String(t.getMonth() + 1).padStart(2, '0');
      const dd = String(t.getDate()).padStart(2, '0');
      inputEl.value = `${yyyy}-${mm}-${dd}`;
    }
  };

  //-----------------------------------------------------
  // Lógica 1: Validação de Mudança de Status para "Aprovado" / "Em Andamento"
  //-----------------------------------------------------
  const statusSelect = $id('status_processo');
  const statusForm = $id('status-change-form');
  const caRequiredModal = $id('modal-ca-required');
  const confirmStatusChangeBtn = $id('confirm-status-change');
  const cancelStatusChangeBtn = $id('cancel-status-change');

  const inputCA   = $id('modal_required_os_numero');
  const inputEnvio = $id('modal_data_inicio_traducao');
  const radioDias = $id('modal_prazo_tipo_dias');
  const radioData = $id('modal_prazo_tipo_data');
  const grpDias   = $id('modal_prazo_dias_container');
  const grpData   = $id('modal_prazo_data_container');
  const inputDias = $id('modal_traducao_prazo_dias');
  const inputData = $id('modal_traducao_prazo_data');

  // hiddens do form principal (usados só na mudança de status)
  const hCA   = $id('hidden_os_numero_conta_azul');
  const hEnvio = $id('hidden_data_inicio_traducao');
  const hTipo  = $id('hidden_traducao_prazo_tipo');
  const hDias  = $id('hidden_traducao_prazo_dias');
  const hData  = $id('hidden_traducao_prazo_data');

  // valor vindo do PHP no template
  let originalStatus = (typeof <?php echo json_encode($status ?? null); ?> !== 'undefined')
    ? <?php echo json_encode($status ?? null); ?>
    : null;

  function togglePrazo() {
    if (!radioDias || !radioData || !grpDias || !grpData || !inputDias || !inputData) return;
    if (radioDias.checked) {
      grpDias.classList.remove('hidden');
      grpData.classList.add('hidden');
      inputData.value = '';
    } else {
      grpDias.classList.add('hidden');
      grpData.classList.remove('hidden');
      inputDias.value = '';
    }
  }
  if (radioDias && radioData) {
    [radioDias, radioData].forEach(r => r.addEventListener('change', togglePrazo));
    togglePrazo();
  }

  if (statusForm && statusSelect) {
    statusForm.addEventListener('submit', function(e) {
      const newStatus = statusSelect.value;
      const requires = (originalStatus === 'Orçamento' && (newStatus === 'Aprovado' || newStatus === 'Em Andamento'));
      if (requires && caRequiredModal) {
        e.preventDefault();
        // garante que a data do tradutor já abra com hoje, se estiver vazia
        setTodayIfEmpty(inputEnvio);
        caRequiredModal.classList.remove('hidden');
      }
    });
  }

  if (cancelStatusChangeBtn && statusSelect && caRequiredModal) {
    cancelStatusChangeBtn.addEventListener('click', () => {
      statusSelect.value = originalStatus; // volta o select
      caRequiredModal.classList.add('hidden');
    });
  }

  if (confirmStatusChangeBtn) {
    confirmStatusChangeBtn.addEventListener('click', () => {
      const ca    = (inputCA?.value || '').trim();
      const envio = inputEnvio?.value || '';
      const tipo  = (radioDias && radioDias.checked) ? 'dias' : 'data';
      const dias  = inputDias?.value || '';
      const data  = inputData?.value || '';

      // validações
      const erros = [];
      if (!ca)    erros.push('Informe o número da Ordem de Serviço (CA).');
      if (!envio) erros.push('Informe a Data de Envio para o Tradutor.');
      if (tipo === 'dias') {
        if (!dias || parseInt(dias, 10) <= 0) erros.push('Informe os dias de prazo (maior que zero).');
      } else {
        if (!data) erros.push('Informe a data específica do prazo.');
      }

      if (erros.length) {
        alert(erros.join('\n'));
        return;
      }

      // Preenche os hiddens do form principal e envia
      if (hCA)   hCA.value = ca;
      if (hEnvio) hEnvio.value = envio;
      if (hTipo)  hTipo.value = tipo;
      if (hDias)  hDias.value = (tipo === 'dias') ? dias : '';
      if (hData)  hData.value = (tipo === 'data') ? data : '';

      statusForm?.submit();
    });
  }

  //-----------------------------------------------------
  // Lógica 2: Modais de Edição de Etapas (AJAX)
  //-----------------------------------------------------
  function showFeedback(message, type = 'success') {
    const feedbackContainer = $id('feedback-container');
    if (!feedbackContainer) return;
    feedbackContainer.innerHTML = message;
    feedbackContainer.className = (type === 'success')
      ? 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4'
      : 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4';
    feedbackContainer.classList.remove('hidden');
    setTimeout(() => { feedbackContainer.classList.add('hidden'); }, 5000);
  }

  const modalTriggers = document.querySelectorAll('.modal-trigger');
  const modalCloseButtons = document.querySelectorAll('.modal-close-btn');
  const overlays = document.querySelectorAll('.modal-overlay:not(#modal-ca-required)'); // Exclui o modal de status

  modalTriggers.forEach(button => {
    button.addEventListener('click', () => {
      const targetModal = $id(button.dataset.modalTarget);
      if (targetModal) {
        // auto-preenche datas (se existirem) com hoje, quando abrir
        const autoDateInputs = targetModal.querySelectorAll('input[type="date"][data-autofill-today], #modal_data_inicio_traducao, #modal_data_envio_assinatura, #modal_data_devolucao_assinatura, #modal_data_envio_cartorio');
        autoDateInputs.forEach(inp => setTodayIfEmpty(inp));
        targetModal.classList.remove('hidden');
      }
    });
  });

  function closeModal(modal) {
    if (modal) modal.classList.add('hidden');
  }

  modalCloseButtons.forEach(button => {
    // Ignora o botão de cancelar do modal de status, pois ele tem lógica própria
    if (button.id !== 'cancel-status-change') {
      button.addEventListener('click', () => closeModal(button.closest('.modal-overlay')));
    }
  });

  overlays.forEach(overlay => overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal(overlay);
  }));

  document.querySelectorAll('.modal-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const url = this.getAttribute('data-action');
      const modal = this.closest('.modal-overlay');

      fetch(url, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showFeedback(data.message || 'Atualizado com sucesso!', 'success');
            if (data.updated_data) {
              for (const [key, value] of Object.entries(data.updated_data)) {
                if (key.startsWith('status_')) continue;
                const displayElement = $id(`display-${key}`);
                if (displayElement) displayElement.innerHTML = value;
              }
              const statusBadge = $id('display-status-badge');
              if (statusBadge && data.updated_data.status_processo) {
                statusBadge.textContent = data.updated_data.status_processo;
                statusBadge.className = `px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full ${data.updated_data.status_processo_classes}`;
              }
            }
            closeModal(modal);
          } else {
            showFeedback(data.message || 'Ocorreu um erro.', 'error');
          }
        })
        .catch((error) => {
          console.error('Fetch error:', error);
          showFeedback('Erro de conexão. Verifique o console.', 'error');
        });
    });
  });

  //-----------------------------------------------------
  // Lógica 3: Comentários
  //-----------------------------------------------------
  const commentForm = $id('comment-form');
  if (commentForm) {
    commentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const url = 'processos.php?action=store_comment_ajax';
      fetch(url, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.comment) {
            this.querySelector('textarea').value = '';
            const commentsList = $id('comments-list');
            const noCommentsMessage = $id('no-comments');
            if (noCommentsMessage) noCommentsMessage.remove();
            const newCommentHtml = `
              <div class="bg-gray-50 p-3 rounded-lg animate-fade-in">
                <div class="flex items-center justify-between mb-1">
                  <p class="text-sm font-semibold text-gray-800">${data.comment.author}</p>
                  <p class="text-xs text-gray-500">${data.comment.date}</p>
                </div>
                <p class="text-sm text-gray-600">${data.comment.text}</p>
              </div>`;
            commentsList.insertAdjacentHTML('afterbegin', newCommentHtml);
            showFeedback('Comentário publicado!', 'success');
          } else {
            showFeedback(data.message || 'Não foi possível publicar.', 'error');
          }
        })
        .catch(error => {
          console.error('Fetch error:', error);
          showFeedback('Erro de conexão.', 'error');
        });
    });
  }

  //-----------------------------------------------------
  // Lógica 4: Prazos de Tradução (Modais de edição)
  //-----------------------------------------------------
  const prazoTipoRadios = document.querySelectorAll('.prazo-tipo-traducao');
  const prazoDiasContainer = $id('prazo_dias_traducao_container');
  const prazoDataContainer = $id('prazo_data_traducao_container');
  const prazoDiasInput = $id('traducao_prazo_dias');
  const prazoDataInput = $id('traducao_prazo_data');

  function togglePrazoInputs() {
    if (!prazoDiasContainer || !prazoDataContainer) return;
    const selected = document.querySelector('.prazo-tipo-traducao:checked')?.value;
    if (selected === 'dias') {
      prazoDiasContainer.classList.remove('hidden');
      prazoDataContainer.classList.add('hidden');
      if (prazoDiasInput) prazoDiasInput.required = true;
      if (prazoDataInput) {
        prazoDataInput.required = false;
        prazoDataInput.value = '';
      }
    } else {
      prazoDiasContainer.classList.add('hidden');
      prazoDataContainer.classList.remove('hidden');
      if (prazoDataInput) prazoDataInput.required = true;
      if (prazoDiasInput) {
        prazoDiasInput.required = false;
        prazoDiasInput.value = '';
      }
    }
  }
  prazoTipoRadios.forEach(radio => radio.addEventListener('change', togglePrazoInputs));
  togglePrazoInputs();

});
</script>
