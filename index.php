<?php 
session_start(); // Inicia a sessão

// Verifica se o usuário está logado, caso contrário, redireciona para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php'); // Redireciona para login se não houver sessão ativa
    exit;
}

include('conexao.php');

// Obtenha o nome do usuário logado
$usuarioId = $_SESSION['usuario_id'];
$query = "SELECT nome FROM usuarios WHERE id = $usuarioId";
$result = mysqli_query($conn, $query);
$usuario = mysqli_fetch_assoc($result);

// Armazene o nome do usuário em uma variável
$nomeUsuario = $usuario['nome'];

// Função para traduzir os meses para português
function getMesNome($mes) {
    $meses = [
        'January' => 'Janeiro',
        'February' => 'Fevereiro',
        'March' => 'Março',
        'April' => 'Abril',
        'May' => 'Maio',
        'June' => 'Junho',
        'July' => 'Julho',
        'August' => 'Agosto',
        'September' => 'Setembro',
        'October' => 'Outubro',
        'November' => 'Novembro',
        'December' => 'Dezembro'
    ];
    return $meses[$mes];
}

// Função para calcular o número de parcelas restantes em um mês específico
function calcularParcelasNoMes($dataLancamento, $numeroParcelas, $dataAtual, $descontoMesAtual) {
    $dataLancamento = strtotime($dataLancamento);
    $dataAtual = strtotime($dataAtual);

    if ($descontoMesAtual == 'sim') {
        $dataLancamento = strtotime(date('Y-m-01'));
    } else {
        $inicioContagem = strtotime(date('Y-m-01', strtotime('+1 month', $dataLancamento)));
        if ($dataAtual < $inicioContagem) {
            return 0;
        }
        $dataLancamento = $inicioContagem;
    }

    if ($dataLancamento > $dataAtual) {
        return 0;
    }

    $mesesDiferenca = (date('Y', $dataAtual) - date('Y', $dataLancamento)) * 12 + (date('m', $dataAtual) - date('m', $dataLancamento));
    $parcelasRestantes = $numeroParcelas - $mesesDiferenca;

    return ($parcelasRestantes < 0) ? 0 : $parcelasRestantes;
}

// Obtém o mês atual, anterior e próximo
$mesAtual = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$inicioMes = date('Y-m-01', strtotime($mesAtual));
$fimMes = date('Y-m-t', strtotime($mesAtual));
$mesAnterior = date('Y-m', strtotime('-1 month', strtotime($mesAtual)));
$proximoMes = date('Y-m', strtotime('+1 month', strtotime($mesAtual)));

// Verifica se há filtro por tipo de dívida
$tipoDivida = isset($_GET['tipo_divida']) ? $_GET['tipo_divida'] : '';

// Consulta para dívidas com ou sem filtro
$usuario_id = $_SESSION['usuario_id']; // Obter o ID do usuário da sessão
$query = "SELECT * FROM dividas WHERE usuario_id = $usuario_id AND data_lancamento <= '$fimMes'";

if ($tipoDivida) {
    $query .= " AND tipo_divida LIKE '%$tipoDivida%'";
}
$result = mysqli_query($conn, $query);

if (!$result) {
    die('Erro na consulta: ' . mysqli_error($conn));
}

$totalDividasMes = 0;
$totalParcelasMes = 0; // Soma total das parcelas exibidas no mês atual
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<!-- Favicon para ícone de 192x192 pixels -->
    <link rel="icon" type="image/png" sizes="192x192" href="images/favicon-192x192.png">

    <!-- Favicon para ícone de 512x512 pixels -->
    <link rel="icon" type="image/png" sizes="512x512" href="images/favicon-512x512.png">

    <!-- Meta tag padrão para favicon (pode incluir também outras dimensões menores) -->
    <link rel="icon" href="images/favicon.png">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="favicon.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foco nas Dívidas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> <!-- Link para seu arquivo CSS -->
    

    <style>
        .btn-secondary, .btn-info, .btn-primary {
            margin-right: 5px;
        }
        .table th, .table td {
            text-align: center;
        }
        .alert-info, .alert-success {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4" style="font-size: 2.5rem; font-weight: 700; color: #343a40;">
    <?= getMesNome(date('F', strtotime($mesAtual))) . ' ' . date('Y', strtotime($mesAtual)); ?>
</h1>
<h2 class="text-center" style="font-size: 1.5rem; color: #6c757d;">
    Olá, <?php echo htmlspecialchars($nomeUsuario); ?>!
</h2>

        <!-- Navegação entre meses -->
        <div class="d-flex justify-content-between mb-4">
            <a href="index.php?mes=<?= $mesAnterior; ?>" class="btn btn-success">Mês Anterior</a>
            <a href="index.php?mes=<?= $proximoMes; ?>" class="btn btn-success">Próximo Mês</a>
        </div>
            
        <!-- Botão para lançar nova dívida -->
        <div class="d-flex justify-content-between mb-4">
            <a href="lancar_divida.php" class="btn btn-primary">Lançar Nova Dívida</a>
            <a href="index.php" class="btn btn-info">Voltar ao Mês Atual</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <!-- Filtro por tipo de dívida -->
        <div class="mb-4">
            <input type="text" class="form-control" id="tipo_divida" value="<?= htmlspecialchars($tipoDivida); ?>" placeholder="Filtrar por Tipo de Dívida">
        </div>

        <div class="d-flex justify-content-end mt-3">
           <button id="select-all" class="btn btn-secondary">Selecionar Todos</button>
        </div>

       <!-- Tabela de dívidas -->
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Parcelas Restantes</th>
                <th>Valor Parcela</th>
                <th>Valor da divida</th>
                <th>Tipo de Dívida</th>
                <th>Data de Lançamento</th> <!-- Novo campo -->
                <th>Observações</th>
                <th>Ações</th>
                <th>Excluir</th>
            </tr>
        </thead>

        <tbody id="tabela_dividas">
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php
                    $parcelasNoMesAtual = calcularParcelasNoMes($row['data_lancamento'], $row['numero_parcelas'], $inicioMes, $row['desconto_mes_atual']);
                    $valorParcela = $row['valor_parcela'];
                    $somaTotal = $row['soma_total'];

                    if ($parcelasNoMesAtual > 0) {
                        $totalDividasMes += $valorParcela * $parcelasNoMesAtual; // Soma o total das dívidas no mês
                        $totalParcelasMes += $valorParcela; // Soma o valor total das parcelas no mês
                    }
                ?>
                <?php if ($parcelasNoMesAtual > 0): ?>
                <tr data-valor-parcela="<?= $valorParcela; ?>" data-soma-total="<?= $somaTotal; ?>">
                    <td><?= htmlspecialchars($row['nome_divida']); ?></td>
                    <td><?= $parcelasNoMesAtual; ?></td>
                    <td><?= number_format($valorParcela, 2, ',', '.'); ?></td>
                    <td><?= number_format($somaTotal, 2, ',', '.'); ?></td>
                    <td><?= htmlspecialchars($row['tipo_divida']); ?></td>
                    <td><?= date('d/m/Y', strtotime($row['data_lancamento'])); ?></td> <!-- Exibe a Data de Lançamento -->
                    <td><?= htmlspecialchars($row['observacoes']); ?></td>
                    <td>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="editar_divida.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Editar</a>
                            <a href="excluir_divida.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir esta dívida?');">Excluir</a>
                        </div>
                    </td>
                    <td>
                        <input type="checkbox" class="delete-checkbox" value="<?= $row['id']; ?>">
                    </td>
                </tr>
                <?php endif; ?>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>



        
        <!-- Soma total das dívidas no mês -->
        <div class="alert alert-info">
            <strong>Soma Total das Dívidas no Mês:</strong> R$ <span id="total_dividas_mes"><?= number_format($totalDividasMes, 2, ',', '.'); ?></span>
        </div>


        <!-- Exibição da soma total das parcelas -->
        <div class="alert alert-success">
            <strong>Total das Parcelas no Mês:</strong> R$ <span id="total_parcelas_mes"><?= number_format($totalParcelasMes, 2, ',', '.'); ?></span> <!-- Soma total das parcelas no mês -->
        </div>

        <!-- Botão para gerar relatório -->
        <div class="d-flex justify-content-end flex-wrap gap-2 mb-4">
            <button id="generate-report" class="btn btn-success">Gerar Relatório</button>
            <!--<button id="select-all" class="btn btn-secondary">Selecionar Todos</button>-->
            <button id="delete-selected" class="btn btn-danger">Excluir Selecionados</button>
            <a href="alterar_senha.php" class="btn btn-secondary">Ajustes</a>
        </div>

    </div>

    <script>
        // Função para recalcular o total das dívidas e das parcelas exibidas
        function recalcularTotais() {
            var tabelaDividas = document.getElementById('tabela_dividas').getElementsByTagName('tr');
            var totalDividasMes = 0;
            var totalParcelasMes = 0;

            for (var i = 0; i < tabelaDividas.length; i++) {
                var tr = tabelaDividas[i];
                if (tr.style.display !== 'none') {
                    var valorParcela = parseFloat(tr.getAttribute('data-valor-parcela'));
                    var somaTotal = parseFloat(tr.getAttribute('data-soma-total'));
                    var parcelasRestantes = parseInt(tr.cells[1].textContent);

                    if (parcelasRestantes > 0) {
                        totalDividasMes += valorParcela * parcelasRestantes;
                        totalParcelasMes += valorParcela;
                    }
                }
            }

            document.getElementById('total_dividas_mes').textContent = totalDividasMes.toFixed(2).replace('.', ',');
            document.getElementById('total_parcelas_mes').textContent = totalParcelasMes.toFixed(2).replace('.', ',');
        }

        // Filtro de dívidas em tempo real
        const tipoDividaInput = document.getElementById('tipo_divida');
        tipoDividaInput.addEventListener('input', function () {
            const tipoDivida = this.value.toLowerCase();
            const linhas = document.querySelectorAll('#tabela_dividas tr');
            linhas.forEach(linha => {
                const tipoDividaLinha = linha.cells[4].textContent.toLowerCase();
                if (tipoDividaLinha.includes(tipoDivida)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });

            // Recalcular totais após aplicar o filtro
            recalcularTotais();
        });

        // Função para gerar o relatório sem botões e checkboxes
        document.getElementById('generate-report').addEventListener('click', function () {
            const tabela = document.querySelector('.table').cloneNode(true);
            const checkboxes = tabela.querySelectorAll('input[type="checkbox"], th:nth-child(8), td:nth-child(8), th:nth-child(7), td:nth-child(7)');
            checkboxes.forEach(checkbox => checkbox.remove());
            const reportWindow = window.open('', '_blank');
            const totalParcelasMes = document.getElementById('total_parcelas_mes').textContent;

            // Inclui a soma total das parcelas no relatório
            reportWindow.document.write(`
                <html>
                <head>
                    <title>Relatório de Dívidas</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                </head>
                <body>
                    <h1 class="text-center">Relatório de Dívidas</h1>
                    <h2 class="text-center">Mês: <?= getMesNome(date('F', strtotime($mesAtual))) . ' ' . date('Y', strtotime($mesAtual)); ?></h2>
                    <table class="table table-striped">${tabela.innerHTML}</table>
                    
                    <div class="alert alert-success">
                        <strong>Total das Parcelas no Mês:</strong> R$ ${totalParcelasMes}
                    </div>
                </body>
                </html>
            `);
            reportWindow.document.close();
            reportWindow.print();
        });

        // Selecionar todos os checkboxes
        document.getElementById('select-all').addEventListener('click', function () {
            const checkboxes = document.querySelectorAll('.delete-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        });

        // Excluir dívidas selecionadas
        document.getElementById('delete-selected').addEventListener('click', function () {
            const selected = [];
            document.querySelectorAll('.delete-checkbox:checked').forEach(checkbox => {
                selected.push(checkbox.value);
            });

            if (selected.length > 0) {
                if (confirm('Tem certeza que deseja excluir as dívidas selecionadas?')) {
                    // Redirecionar para o script de exclusão em massa
                    window.location.href = 'excluir_selecionados.php?ids=' + selected.join(',');
                }
            } else {
                alert('Selecione pelo menos uma dívida para excluir.');
            }
        });

        // Recalcular totais ao carregar a página
        recalcularTotais();
    </script>
    <footer>
    <div class="container">
        <p>Desenvolvido por Rannyell Soares
Bacharel em Sistemas de Informação
Com mais de 8 anos de experiência na área de Tecnologia da Informação.</p>
    </div>
</footer>
</body>
</html>
