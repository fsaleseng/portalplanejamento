<?php include '../templates/header-simulador.php'; ?>
<?php
require_once '../config/db.php';

// 1. Captura o output de 'simulador-cenario.php'
ob_start();
require_once 'simulador-cenario.php';
$output = ob_get_clean();

// 2. Definições globais
$IGNORED_TRAINS = ['TREM 101', 'TREM BRAVO 223', 'TREM BRAVO 232'];
$RESTRICTED_TRAINS = ['TREM 102', 'TREM 103', 'TREM 104', 'TREM 105', 'TREM 106'];
$TARGET_KMS = [
    'nominal' => 1200000,
    'tolerancia1' => 1245000,
    'tolerancia2' => 1250000,
    'tolerancia3' => 1255000,
    'tolerancia4' => 1260000
];

// 3. Função para extrair data real com tratamento robusto
function parseDateString($dateString) {
    if (empty($dateString) || $dateString === 'N/A') return null;
    
    // Remove qualquer texto adicional como "(xx dias atrás)"
    $clean = preg_replace('/\s*\(.*\)/', '', trim(is_array($dateString) ? reset($dateString) : $dateString));
    
    // Tenta primeiro o formato brasileiro (d/m/Y)
    if ($date = DateTime::createFromFormat('d/m/Y', $clean)) {
        return $date;
    }
    
    // Depois tenta outros formatos
    foreach (['Y-m-d', 'm/d/Y'] as $format) {
        if ($date = DateTime::createFromFormat($format, $clean)) {
            return $date;
        }
    }
    
    return null;
}

// 4. Posições disponíveis
function getGroupedPositions() {
    global $pdo;
    $query = "SELECT 
                material_rodante as id_posicao,
                SUM(km_percorrido) as total_km,
                GROUP_CONCAT(DISTINCT main_line) as linhas,
                MIN(departure_time) as primeira_partida,
                MAX(arrival_time) as ultima_chegada,
                COUNT(*) as num_viagens
              FROM trem_posicoes
              GROUP BY material_rodante
              ORDER BY total_km ASC";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$posicoes_disponiveis = getGroupedPositions();

// 5. Trens para alocar
$trens_para_alocar = array_filter($trens_dados_revisao, function($trem) use ($IGNORED_TRAINS) {
    return !in_array($trem['nome_trem'], $IGNORED_TRAINS);
});

// 6. Função de alocação automática corrigida
function alocarTrensAutomatically($trens, $posicoes) {
    global $RESTRICTED_TRAINS;
    
    // Ordena posições por km total (menor primeiro)
    usort($posicoes, function($a, $b) {
        return $a['total_km'] <=> $b['total_km'];
    });
    
    $posicoes_disponiveis = $posicoes;
    $alocacoes = [];

    // Primeiro aloca trens com restrição à L1
    foreach ($trens as $trem) {
        if (in_array($trem['nome_trem'], $RESTRICTED_TRAINS)) {
            foreach ($posicoes_disponiveis as $key => $pos) {
                $linhas = explode(',', $pos['linhas']);
                $linhas = array_map('trim', $linhas);
                
                // Verifica se tem APENAS L1 (não pode ter L1,L2)
                if (count($linhas) === 1 && $linhas[0] === 'L1') {
                    $alocacoes[$trem['nome_trem']] = [
                        'trem' => $trem,
                        'posicao' => $pos,
                        'nova_media' => $pos['total_km']
                    ];
                    unset($posicoes_disponiveis[$key]);
                    break;
                }
            }
        }
    }

    // Depois aloca os demais trens
    foreach ($trens as $trem) {
        if (!isset($alocacoes[$trem['nome_trem']])) {
            if (!empty($posicoes_disponiveis)) {
                $pos = array_shift($posicoes_disponiveis);
                $alocacoes[$trem['nome_trem']] = [
                    'trem' => $trem,
                    'posicao' => $pos,
                    'nova_media' => $pos['total_km']
                ];
            }
        }
    }

    return $alocacoes;
}

// 7. Função para calcular nova data de revisão com tratamento completo
function calcularNovaDataRevisao($dataReferenciaStr, $kmAtual, $novaMedia, $kmLimite) {
    // Verifica se todos os parâmetros necessários estão presentes e válidos
    if (empty($dataReferenciaStr) || $kmAtual <= 0 || $novaMedia <= 0 || $kmAtual >= $kmLimite) {
        return 'N/A';
    }
    
    // Remove a parte "(xx dias atrás)" se existir
    $cleanDateStr = preg_replace('/\s*\(\d+\s+dias\s+atrás\)/', '', $dataReferenciaStr);
    
    $dataReferencia = parseDateString($cleanDateStr);
    if (!$dataReferencia) return 'N/A';
    
    try {
        $kmRestante = $kmLimite - $kmAtual;
        if ($kmRestante <= 0) return 'N/A';
        
        $diasNecessarios = $kmRestante / $novaMedia;
        if ($diasNecessarios <= 0) return 'N/A';
        
        $intervalo = new DateInterval("P".round($diasNecessarios)."D");
        
        // Usar a data atual como referência se a revisão já está atrasada
        if (strpos($dataReferenciaStr, 'dias atrás') !== false) {
            $hoje = new DateTime();
            $hoje->add($intervalo);
            return $hoje->format('d/m/Y');
        } else {
            $dataReferencia->add($intervalo);
            return $dataReferencia->format('d/m/Y');
        }
    } catch (Exception $e) {
        return 'N/A';
    }
}

// 8. Processamento de alocação
$alocacao_otimizada = alocarTrensAutomatically($trens_para_alocar, $posicoes_disponiveis);

// 9. Cálculo de novas datas
$resultados_completos = [];
foreach ($alocacao_otimizada as $nomeTrem => $alocacao) {
    $trem = $alocacao['trem'];
    $posicao = $alocacao['posicao'];
    
    $resultados_completos[$nomeTrem] = [
        'trem' => $trem,
        'posicao' => $posicao,
        'media_antiga' => $trem['media_diferenca'],
        'nova_media' => $alocacao['nova_media'],
        'datas_revisao' => []
    ];

    foreach ($TARGET_KMS as $tipo => $kmLimite) {
        $dataAntiga = isset($trem['datas_revisao'][$tipo]) ? $trem['datas_revisao'][$tipo] : 'N/A';
        $dataNova = calcularNovaDataRevisao(
            $trem['data_referencia'],
            $trem['ultimo_contador'],
            $alocacao['nova_media'],
            $kmLimite
        );

        // Cálculo de dias ganhos
        $diasGanhos = 'N/A';
        $dateAntiga = parseDateString($dataAntiga);
        $dateNova = parseDateString($dataNova);
        
        if ($dateAntiga && $dateNova) {
            $diff = $dateAntiga->diff($dateNova);
            $diasGanhos = $diff->invert ? -$diff->days : $diff->days;
        }

        $resultados_completos[$nomeTrem]['datas_revisao'][$tipo] = [
            'antiga' => $dataAntiga,
            'nova' => $dataNova,
            'dias_ganhos' => $diasGanhos
        ];
    }
}
?>



<main>
    <?php include '../templates/header-simuladores.php'; ?>
    <section class="simulacoes">

    <style>
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; overflow-x: auto; }
        .tablinks { 
            padding: 12px 20px; background-color: #f8f9fa; border: none; cursor: pointer; transition: 0.3s;
            border-radius: 5px 5px 0 0; margin-right: 5px; font-weight: bold; color: #3498db; white-space: nowrap;
        }
        .tablinks:hover { background-color: #e9ecef; }
        .tablinks.active { background-color: #3498db; color: white; }
        .tabcontent { display: none; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; background-color: white; overflow-x: auto; }
        .tabcontent.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9em; table-layout: fixed; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #3498db; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #e9f7fe; }
        .improvement { color: #27ae60; font-weight: bold; }
        .critical { color: #e74c3c; font-weight: bold; }
        .neutral { color: #3498db; }
        
        /* Larguras específicas para colunas */
        .col-trem { width: 120px; }
        .col-posicao { width: 80px; }
        .col-linha { width: 60px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .col-km { width: 100px; }
        .col-media { width: 100px; }
        .col-data { width: 120px; }
        .col-dias { width: 80px; }
    </style>

<div class="container">
    <h1>Alocação Automática de Trens - CCR Metrô Bahia</h1>
    
    <div class="tabs">
        <?php foreach ($TARGET_KMS as $tipo => $km): ?>
            <button class="tablinks <?= $tipo === 'nominal' ? 'active' : '' ?>" 
                    onclick="openTab(event, '<?= $tipo ?>')">
                <?= ucfirst(str_replace('tolerancia', 'Tolerância ', $tipo)) ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <?php foreach ($TARGET_KMS as $tipo => $km): ?>
    <div id="<?= $tipo ?>" class="tabcontent <?= $tipo === 'nominal' ? 'active' : '' ?>">
        <h2><?= ucfirst(str_replace('tolerancia', 'Tolerância ', $tipo)) ?> (Limite: <?= number_format($km, 0, ',', '.') ?> km)</h2>
        
        <table>
            <thead>
                <tr>
                    <th class="col-trem">Trem</th>
                    <th class="col-posicao">Posição</th>
                    <th class="col-linha">Linha</th>
                    <th class="col-km">KM Total Posição</th>
                    <th class="col-media">Média Antiga</th>
                    <th class="col-media">Média Nova</th>
                    <th class="col-data">Data Revisão Antiga</th>
                    <th class="col-data">Data Revisão Nova</th>
                    <th class="col-dias">Dias Ganhos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados_completos as $nomeTrem => $resultado): 
                    $trem = $resultado['trem'];
                    $posicao = $resultado['posicao'];
                    $dadosRevisao = $resultado['datas_revisao'][$tipo];
                    
                    // Tratamento dos valores para exibição
                    $linha = $posicao ? implode(', ', array_map('trim', explode(',', $posicao['linhas']))) : 'N/A';
                    $dataAntiga = is_array($dadosRevisao['antiga']) ? reset($dadosRevisao['antiga']) : $dadosRevisao['antiga'];
                    $dataNova = $dadosRevisao['nova'];
                    $diasGanhos = $dadosRevisao['dias_ganhos'];
                ?>
                <tr>
                    <td class="col-trem"><?= htmlspecialchars($nomeTrem) ?></td>
                    <td class="col-posicao"><?= $posicao ? $posicao['id_posicao'] : 'N/A' ?></td>
                    <td class="col-linha" title="<?= htmlspecialchars($linha) ?>"><?= htmlspecialchars($linha) ?></td>
                    <td class="col-km"><?= $posicao ? number_format($posicao['total_km'], 2, ',', '.') : 'N/A' ?></td>
                    <td class="col-media"><?= number_format($resultado['media_antiga'], 2, ',', '.') ?></td>
                    <td class="col-media improvement"><?= number_format($resultado['nova_media'], 2, ',', '.') ?></td>
                    <td class="col-data"><?= htmlspecialchars($dataAntiga) ?></td>
                    <td class="col-data <?= $dataNova !== 'N/A' ? 'improvement' : '' ?>"><?= htmlspecialchars($dataNova) ?></td>
                    <td class="col-dias <?= 
                        $diasGanhos === 'N/A' ? 'neutral' : 
                        ($diasGanhos > 0 ? 'improvement' : 'critical') 
                    ?>">
                        <?= $diasGanhos === 'N/A' ? 'N/A' : 
                            ($diasGanhos > 0 ? '+' . $diasGanhos : $diasGanhos) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <!-- Gráficos para esta aba -->
        <div class="chart-container1">
            <div class="chart-wrapper1">
                <h3 class="chart-title">Dias Ganhos por Trem</h3>
                <canvas id="chartDias<?= ucfirst($tipo) ?>"></canvas>
            </div>
            
            <div class="chart-wrapper1">
                <h3 class="chart-title">Comparação de Médias Diárias</h3>
                <canvas id="chartMedias<?= ucfirst($tipo) ?>"></canvas>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</section>
<script>
function openTab(evt, tabName) {
    // Esconder todas as abas
    document.querySelectorAll('.tabcontent').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Desativar todos os botões
    document.querySelectorAll('.tablinks').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar aba selecionada
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}
// Função para inicializar todos os gráficos
function initCharts() {
    <?php foreach ($TARGET_KMS as $tipo => $km): ?>
        // Preparar dados para os gráficos desta aba
        const data<?= ucfirst($tipo) ?> = {
            trens: [<?= implode(',', array_map(function($t) { return "'".addslashes($t)."'"; }, array_keys($resultados_completos))) ?>],
            diasGanhos: [<?= implode(',', array_map(function($r) use ($tipo) { 
                return $r['datas_revisao'][$tipo]['dias_ganhos'] !== 'N/A' ? $r['datas_revisao'][$tipo]['dias_ganhos'] : 0; 
            }, $resultados_completos)) ?>],
            mediasAntigas: [<?= implode(',', array_map(function($r) { 
                return $r['media_antiga']; 
            }, $resultados_completos)) ?>],
            mediasNovas: [<?= implode(',', array_map(function($r) { 
                return $r['nova_media']; 
            }, $resultados_completos)) ?>]
        };

        // Gráfico de Dias Ganhos
        new Chart(
            document.getElementById('chartDias<?= ucfirst($tipo) ?>'),
            {
                type: 'bar',
                data: {
                    labels: data<?= ucfirst($tipo) ?>.trens,
                    datasets: [{
                        label: 'Dias Ganhos',
                        data: data<?= ucfirst($tipo) ?>.diasGanhos,
                        backgroundColor: data<?= ucfirst($tipo) ?>.diasGanhos.map(d => 
                            d > 0 ? 'rgba(40, 167, 69, 0.7)' : 
                            d < 0 ? 'rgba(220, 53, 69, 0.7)' : 'rgba(23, 162, 184, 0.7)'),
                        borderColor: data<?= ucfirst($tipo) ?>.diasGanhos.map(d => 
                            d > 0 ? 'rgba(40, 167, 69, 1)' : 
                            d < 0 ? 'rgba(220, 53, 69, 1)' : 'rgba(23, 162, 184, 1)'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Dias'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw > 0 ? 
                                        `+${context.raw} dias` : 
                                        `${context.raw} dias`;
                                }
                            }
                        }
                    }
                }
            }
        );

        // Gráfico de Comparação de Médias
        new Chart(
            document.getElementById('chartMedias<?= ucfirst($tipo) ?>'),
            {
                type: 'bar',
                data: {
                    labels: data<?= ucfirst($tipo) ?>.trens,
                    datasets: [
                        {
                            label: 'Média Antiga',
                            data: data<?= ucfirst($tipo) ?>.mediasAntigas,
                            backgroundColor: 'rgba(108, 117, 125, 0.7)',
                            borderColor: 'rgba(108, 117, 125, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Média Nova',
                            data: data<?= ucfirst($tipo) ?>.mediasNovas,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'km/dia'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.raw.toFixed(2)} km/dia`;
                                }
                            }
                        }
                    }
                }
            }
        );
    <?php endforeach; ?>
}

// Inicializar gráficos quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    
    // Mostrar a aba ativa corretamente
    const tablinks = document.querySelectorAll('.tablinks');
    if (tablinks.length > 0) {
        tablinks[0].click();
    }
});
</script>
<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</bod>

</html>