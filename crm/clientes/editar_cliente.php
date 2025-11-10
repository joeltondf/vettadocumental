<?php
// Arquivo: crm/clientes/editar_cliente.php (VERSÃO FINAL COM BOTÃO DE EXCLUIR)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';

function escape($value): string
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof Stringable) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    if (is_scalar($value)) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    return '';
}

// Valida o ID do cliente na URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit;
}

try {
    // Busca os dados do cliente
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $_SESSION['error_message'] = "Lead não encontrado.";
        header('Location: ' . APP_URL . '/crm/clientes/lista.php');
        exit;
    }

    $currentUserPerfil = $_SESSION['user_perfil'] ?? '';
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($currentUserPerfil === 'vendedor' && (int)($cliente['crmOwnerId'] ?? 0) !== $currentUserId) {
        $_SESSION['error_message'] = "Você não tem permissão para acessar este lead.";
        header('Location: ' . APP_URL . '/crm/clientes/lista.php');
        exit;
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados do lead: " . $e->getMessage());
}

$telefoneValorOriginal = (string)($cliente['telefone'] ?? '');
$telefoneDdi = $cliente['telefone_ddi'] ?? '55';
$telefoneDdd = $cliente['telefone_ddd'] ?? '';
$telefoneNumero = $cliente['telefone_numero'] ?? '';

$sanitizePhoneDigits = static function ($value): string {
    return preg_replace('/\D+/', '', (string) $value) ?? '';
};

$telefoneDdi = $sanitizePhoneDigits($telefoneDdi);
if ($telefoneDdi === '') {
    $telefoneDdi = '55';
}
$telefoneDdi = substr($telefoneDdi, 0, 4);

$telefoneDdd = $sanitizePhoneDigits($telefoneDdd);
if ($telefoneDdd !== '') {
    $telefoneDdd = substr($telefoneDdd, 0, 4);
}

$telefoneNumeroDigits = $sanitizePhoneDigits($telefoneNumero);
if ($telefoneNumeroDigits !== '') {
    $telefoneNumeroDigits = substr($telefoneNumeroDigits, 0, 20);
}

$legacyPhoneDigits = $sanitizePhoneDigits($telefoneValorOriginal);

if ($telefoneDdd === '' || $telefoneNumeroDigits === '') {
    if ($legacyPhoneDigits !== '') {
        if ($telefoneDdi !== '' && strpos($legacyPhoneDigits, $telefoneDdi) === 0 && strlen($legacyPhoneDigits) > strlen($telefoneDdi)) {
            $legacyPhoneDigits = substr($legacyPhoneDigits, strlen($telefoneDdi));
        }

        if (strlen($legacyPhoneDigits) > 2) {
            if ($telefoneDdd === '') {
                $telefoneDdd = substr($legacyPhoneDigits, 0, 2);
            }

            if ($telefoneNumeroDigits === '') {
                $telefoneNumeroDigits = substr($legacyPhoneDigits, 2);
            }
        }
    }
}

$telefoneNumeroFormatado = $telefoneNumeroDigits;
if ($telefoneNumeroFormatado !== '' && strlen($telefoneNumeroFormatado) > 5) {
    $telefoneNumeroFormatado = substr($telefoneNumeroFormatado, 0, 5) . '-' . substr($telefoneNumeroFormatado, 5);
}

$telefoneCompletoPadrao = formatarTelefone($telefoneDdi, $telefoneDdd, $telefoneNumeroDigits);
if ($telefoneCompletoPadrao === '-') {
    $telefoneCompletoPadrao = '';
}

$rawPhoneDigits = stripNonDigits($telefoneValorOriginal);
$rawDdiDigits = stripNonDigits($telefoneDdi);

if ($rawPhoneDigits !== '') {
    if ($rawDdiDigits !== '' && strpos($rawPhoneDigits, $rawDdiDigits) === 0 && strlen($rawPhoneDigits) > strlen($rawDdiDigits)) {
        $rawPhoneDigits = substr($rawPhoneDigits, strlen($rawDdiDigits));
    } elseif ($rawDdiDigits === '' && strlen($rawPhoneDigits) > 11) {
        $rawDdiDigits = substr($rawPhoneDigits, 0, strlen($rawPhoneDigits) - 11);
        $rawPhoneDigits = substr($rawPhoneDigits, -11);
    }

    if ($telefoneDdd === '' && strlen($rawPhoneDigits) > 2) {
        $telefoneDdd = substr($rawPhoneDigits, 0, 2);
    }

    if ($telefoneNumeroDigits === '' && strlen($rawPhoneDigits) > 2) {
        $telefoneNumeroDigits = substr($rawPhoneDigits, 2);
        if ($telefoneNumeroDigits !== '') {
            $telefoneNumeroDigits = substr($telefoneNumeroDigits, 0, 20);
        }
        $telefoneNumeroFormatado = $telefoneNumeroDigits;
        if ($telefoneNumeroFormatado !== '' && strlen($telefoneNumeroFormatado) > 5) {
            $telefoneNumeroFormatado = substr($telefoneNumeroFormatado, 0, 5) . '-' . substr($telefoneNumeroFormatado, 5);
        }
        $telefoneCompletoPadrao = formatarTelefone($telefoneDdi, $telefoneDdd, $telefoneNumeroDigits);
        if ($telefoneCompletoPadrao === '-') {
            $telefoneCompletoPadrao = '';
        }
    }
}

if ($rawDdiDigits !== '') {
    $telefoneDdi = substr($rawDdiDigits, 0, 4);
}

if ($telefoneCompletoPadrao === '' && $telefoneValorOriginal !== '') {
    $telefoneCompletoPadrao = $telefoneValorOriginal;
}

$pageTitle = "Editar Lead";
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-4">
        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
            Editar Lead: <span class="text-blue-600"><?php echo escape($cliente['nome_cliente'] ?? null); ?></span>
        </h1>
    </div>

    <form action="<?php echo APP_URL; ?>/crm/clientes/salvar.php" method="POST" class="space-y-6 mt-6">
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
        <?php if (isset($_GET['prospeccao_id'])): ?>
            <input type="hidden" name="prospeccao_id" value="<?php echo escape($_GET['prospeccao_id']); ?>">
        <?php endif; ?>

        <?php if (isset($_GET['vendedor_id'])): ?>
            <input type="hidden" name="vendedor_id" value="<?php echo escape($_GET['vendedor_id']); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nome_cliente" class="block text-sm font-medium text-gray-700">Nome do Lead / Empresa</label>
                <input type="text" name="nome_cliente" id="nome_cliente" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60" value="<?php echo escape($cliente['nome_cliente'] ?? null); ?>">
            </div>
            <div>
                <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">Nome do Lead Principal</label>
                <input type="text" name="nome_responsavel" id="nome_responsavel" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60" value="<?php echo escape($cliente['nome_responsavel'] ?? null); ?>">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo escape($cliente['email'] ?? null); ?>">
            </div>
            <div>
                <label for="telefone_ddi" class="block text-sm font-medium text-gray-700">Telefone</label>
                <div class="mt-1 flex items-stretch gap-2">
                    <div style="width: 80px;">
                        <input
                            type="text"
                            id="telefone_ddi"
                            name="telefone_ddi"
                            list="telefone_ddi_opcoes"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            maxlength="4"
                            placeholder="+55"
                            value="<?php echo escape($telefoneDdi); ?>"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            required
                        >
                    </div>
                    <div style="width: 80px;">
                        <input
                            type="text"
                            id="telefone_ddd"
                            name="telefone_ddd"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            maxlength="4"
                            placeholder="11"
                            value="<?php echo escape($telefoneDdd); ?>"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            required
                        >
                    </div>
                    <div class="flex-1">
                        <input
                            type="text"
                            id="telefone_numero"
                            name="telefone_numero"
                            placeholder="98765-4321"
                            maxlength="20"
                            value="<?php echo escape($telefoneNumeroFormatado); ?>"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            required
                        >
                    </div>
                </div>
                <input type="hidden" id="telefone_completo" name="telefone" value="<?php echo escape($telefoneCompletoPadrao); ?>">
                <datalist id="telefone_ddi_opcoes">
                    <option value="55">Brasil (+55)</option>
                    <option value="1">EUA/Canadá (+1)</option>
                    <option value="351">Portugal (+351)</option>
                    <option value="34">Espanha (+34)</option>
                    <option value="44">Reino Unido (+44)</option>
                    <option value="39">Itália (+39)</option>
                    <option value="49">Alemanha (+49)</option>
                    <option value="33">França (+33)</option>
                    <option value="52">México (+52)</option>
                    <option value="54">Argentina (+54)</option>
                    <option value="593">Equador (+593)</option>
                    <option value="57">Colômbia (+57)</option>
                </datalist>
            </div>
            <div>
                <label for="canal_origem" class="block text-sm font-medium text-gray-700">Canal de Origem</label>
                <select name="canal_origem" id="canal_origem" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="Call" <?php echo ($cliente['canal_origem'] == 'Call') ? 'selected' : ''; ?>>Call</option>
                    <option value="LinkedIn" <?php echo ($cliente['canal_origem'] == 'LinkedIn') ? 'selected' : ''; ?>>LinkedIn</option>
                    <option value="Instagram" <?php echo ($cliente['canal_origem'] == 'Instagram') ? 'selected' : ''; ?>>Instagram</option>
                    <option value="Whatsapp" <?php echo ($cliente['canal_origem'] == 'Whatsapp') ? 'selected' : ''; ?>>Whatsapp</option>
                    <option value="Indicação Cliente" <?php echo ($cliente['canal_origem'] == 'Indicação Cliente') ? 'selected' : ''; ?>>Indicação Cliente</option>
                    <option value="Indicação Cartório" <?php echo ($cliente['canal_origem'] == 'Indicação Cartório') ? 'selected' : ''; ?>>Indicação Cartório</option>
                    <option value="Website" <?php echo ($cliente['canal_origem'] == 'Website') ? 'selected' : ''; ?>>Website</option>
                    <option value="Bitrix" <?php echo ($cliente['canal_origem'] == 'Bitrix') ? 'selected' : ''; ?>>Bitrix</option>
                    <option value="Evento" <?php echo ($cliente['canal_origem'] == 'Evento') ? 'selected' : ''; ?>>Evento</option>
                    <option value="Outro" <?php echo ($cliente['canal_origem'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                </select>
            </div>
        </div>

        <div class="pt-5 flex justify-between items-center border-t border-gray-200 mt-6">
            <div>
                <button type="button" onclick="confirmarExclusao()" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 transition duration-300">
                    Excluir Lead
                </button>
            </div>

            <div>
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3 transition duration-300">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">Salvar Alterações</button>
            </div>
        </div>
    </form>
</div>

<form id="formExcluirCliente" action="<?php echo APP_URL; ?>/crm/clientes/excluir_cliente.php" method="POST" class="hidden">
    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
</form>

<script>
    function confirmarExclusao() {
        if (confirm('ATENÇÃO:\n\nTem certeza que deseja excluir este lead?\n\nTodas as prospecções ligadas a ele ficarão sem leads associados.')) {
            document.getElementById('formExcluirCliente').submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const telefoneDdiInput = document.getElementById('telefone_ddi');
        const telefoneDddInput = document.getElementById('telefone_ddd');
        const telefoneNumeroInput = document.getElementById('telefone_numero');
        const telefoneCompletoInput = document.getElementById('telefone_completo');

        function sanitizeDigits(value, maxLength) {
            let digits = (value || '').replace(/\D+/g, '');
            if (typeof maxLength === 'number') {
                digits = digits.slice(0, maxLength);
            }

            return digits;
        }

        function formatTelefoneNumero(value) {
            const digits = sanitizeDigits(value, 20);
            if (digits.length <= 5) {
                return digits;
            }

            return digits.slice(0, 5) + '-' + digits.slice(5, 20);
        }

        function splitTelefoneCompleto(value) {
            const digits = sanitizeDigits(value, 32);
            if (!digits) {
                return { ddi: '', ddd: '', number: '' };
            }

            if (digits.length <= 11) {
                const ddd = digits.slice(0, Math.min(2, digits.length));
                const number = digits.slice(ddd.length);
                return { ddi: '', ddd, number };
            }

            const possibleNumberLengths = [9, 8, 7, 6, 5, 4];
            let ddi = '';
            let ddd = '';
            let number = '';

            for (const length of possibleNumberLengths) {
                const ddiLength = digits.length - 2 - length;
                if (ddiLength < 0 || ddiLength > 4) {
                    continue;
                }

                const rest = digits.slice(ddiLength);
                if (rest.length < 2 + length) {
                    continue;
                }

                const candidateDdd = rest.slice(0, 2);
                const candidateNumber = rest.slice(2, 2 + length);
                if (candidateNumber.length === length) {
                    ddi = ddiLength > 0 ? digits.slice(0, ddiLength) : '';
                    ddd = candidateDdd;
                    number = candidateNumber;
                    break;
                }
            }

            if (ddd === '') {
                const fallbackRestLength = Math.min(11, digits.length);
                const rest = digits.slice(digits.length - fallbackRestLength);
                ddi = digits.slice(0, digits.length - rest.length);
                ddd = rest.slice(0, 2);
                number = rest.slice(2);
            }

            if (ddd === '') {
                ddd = digits.slice(0, Math.min(2, digits.length));
                number = digits.slice(ddd.length);
            }

            return {
                ddi,
                ddd,
                number: number.slice(0, 20)
            };
        }

        function atualizarTelefoneCompleto() {
            if (!telefoneCompletoInput) {
                return;
            }

            const ddiDigits = sanitizeDigits(telefoneDdiInput ? telefoneDdiInput.value : '', 4);
            const dddDigits = sanitizeDigits(telefoneDddInput ? telefoneDddInput.value : '', 4);
            const numeroDigits = sanitizeDigits(telefoneNumeroInput ? telefoneNumeroInput.value : '', 20);

            if (telefoneDdiInput && telefoneDdiInput.value !== ddiDigits) {
                telefoneDdiInput.value = ddiDigits;
            }

            if (telefoneDddInput && telefoneDddInput.value !== dddDigits) {
                telefoneDddInput.value = dddDigits;
            }

            if (telefoneNumeroInput) {
                const formatted = formatTelefoneNumero(numeroDigits);
                if (telefoneNumeroInput.value !== formatted) {
                    telefoneNumeroInput.value = formatted;
                }
            }

            if (dddDigits === '' || numeroDigits === '') {
                telefoneCompletoInput.value = '';
                return;
            }

            const ddiParaFormatar = ddiDigits !== '' ? ddiDigits : '55';
            const numeroFormatado = formatTelefoneNumero(numeroDigits);
            telefoneCompletoInput.value = `+${ddiParaFormatar} (${dddDigits}) ${numeroFormatado}`;
        }

        function handleTelefonePaste(event) {
            const clipboard = event.clipboardData || window.clipboardData;
            if (!clipboard) {
                return;
            }

            const pastedText = clipboard.getData('text');
            if (!pastedText) {
                return;
            }

            const parts = splitTelefoneCompleto(pastedText);
            if (parts.ddd === '' && parts.number === '') {
                return;
            }

            event.preventDefault();

            if (telefoneDdiInput) {
                const pastedDdi = sanitizeDigits(parts.ddi, 4);
                if (pastedDdi !== '') {
                    telefoneDdiInput.value = pastedDdi;
                } else if (!telefoneDdiInput.value) {
                    telefoneDdiInput.value = '55';
                }
            }

            if (telefoneDddInput) {
                telefoneDddInput.value = sanitizeDigits(parts.ddd, 4);
            }

            if (telefoneNumeroInput) {
                telefoneNumeroInput.value = formatTelefoneNumero(parts.number);
            }

            atualizarTelefoneCompleto();
        }

        if (telefoneDdiInput) {
            telefoneDdiInput.value = sanitizeDigits(telefoneDdiInput.value, 4) || '55';
            telefoneDdiInput.addEventListener('input', function (event) {
                event.target.value = sanitizeDigits(event.target.value, 4);
                atualizarTelefoneCompleto();
            });
            telefoneDdiInput.addEventListener('paste', handleTelefonePaste);
        }

        if (telefoneDddInput) {
            telefoneDddInput.value = sanitizeDigits(telefoneDddInput.value, 4);
            telefoneDddInput.addEventListener('input', function (event) {
                event.target.value = sanitizeDigits(event.target.value, 4);
                atualizarTelefoneCompleto();
            });
            telefoneDddInput.addEventListener('paste', handleTelefonePaste);
        }

        if (telefoneNumeroInput) {
            telefoneNumeroInput.value = formatTelefoneNumero(telefoneNumeroInput.value);
            telefoneNumeroInput.addEventListener('input', function (event) {
                event.target.value = formatTelefoneNumero(event.target.value);
                atualizarTelefoneCompleto();
            });
            telefoneNumeroInput.addEventListener('paste', handleTelefonePaste);
        }

        atualizarTelefoneCompleto();
    });
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>
