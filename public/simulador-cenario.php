<?php include '../templates/header-simulador.php'; ?>
<?php
require_once '../config/db.php'; // Conexão PDO

// Buscar os valores de tolerância para PF
$sql_tolerancia = "
    SELECT 
        nominal, tolerancia1, tolerancia2, tolerancia3, tolerancia4
    FROM 
        tolerancias 
    WHERE 
        preventiva = 'PF'
";
$stmt_tolerancia = $pdo->query($sql_tolerancia);
$tolerancias = $stmt_tolerancia->fetch(PDO::FETCH_ASSOC);

if (!$tolerancias) {
    die("Erro: Não foram encontradas tolerâncias para preventiva 'PF'.");
}

// Buscar dados dos trens (incluindo local_instalacao)
$sql = "
    SELECT 
        t.nome_trem,
        t.local_instalacao,
        (SELECT AVG(h2.difer_posicao_numer) 
         FROM hodometro h2 
         WHERE h2.local_instalacao = t.local_instalacao) AS media_diferenca,
         
        (SELECT h3.valmed_postotcontador 
         FROM hodometro h3 
         WHERE h3.local_instalacao = t.local_instalacao 
         ORDER BY h3.data DESC, h3.hora DESC 
         LIMIT 1) AS ultimo_contador,
         
        (SELECT h3.data 
         FROM hodometro h3 
         WHERE h3.local_instalacao = t.local_instalacao 
         ORDER BY h3.data DESC, h3.hora DESC 
         LIMIT 1) AS data_referencia
    FROM 
        trens t
    ORDER BY 
        t.nome_trem
";

$stmt = $pdo->query($sql);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar dados para o gráfico
$sql_dados_grafico = "
    SELECT 
        h.data,
        h.local_instalacao,
        h.valmed_postotcontador
    FROM 
        hodometro h
    WHERE 
        h.data = '2025-01-01' OR 
        h.data = (
            SELECT MAX(h2.data) 
            FROM hodometro h2 
            WHERE h2.local_instalacao = h.local_instalacao
        )
    ORDER BY 
        h.local_instalacao, h.data
";

$stmt_dados = $pdo->query($sql_dados_grafico);
$dados_grafico = $stmt_dados->fetchAll(PDO::FETCH_ASSOC);

// Organizar os dados por local_instalacao
$dados_por_local = [];
foreach ($dados_grafico as $dado) {
    $local = $dado['local_instalacao'];
    if (!isset($dados_por_local[$local])) {
        $dados_por_local[$local] = [];
    }
    $dados_por_local[$local][] = $dado;
}

$sql_km = "
    SELECT 
        MAX(CASE WHEN h.data = '2025-01-01' THEN h.valmed_postotcontador ELSE NULL END) as km_inicio,
        MAX(CASE WHEN h.data = (
            SELECT MAX(h2.data) 
            FROM hodometro h2 
            WHERE h2.local_instalacao = h.local_instalacao
        ) THEN h.valmed_postotcontador ELSE NULL END) as km_referencia
    FROM 
        hodometro h
    WHERE 
        h.local_instalacao = :local
    GROUP BY 
        h.local_instalacao
";

$stmt_km = $pdo->prepare($sql_km);
$trens_dados_revisao = [];
$trens_inativos = [];
$total_km = 0;
$trens_ativos = 0;

// Preparar dados para os gráficos
$graficos_data = [
    'nominal' => array_fill(2025, 12, ['count' => 0, 'trens' => []]), // 2025-2032
    'tolerancia1' => array_fill(2025, 12, ['count' => 0, 'trens' => []]),
    'tolerancia2' => array_fill(2025, 12, ['count' => 0, 'trens' => []]),
    'tolerancia3' => array_fill(2025, 12, ['count' => 0, 'trens' => []]),
    'tolerancia4' => array_fill(2025, 12, ['count' => 0, 'trens' => []])
];

foreach ($dados as $trem) {
    $nome_trem = $trem['nome_trem'];
    $local_instalacao = $trem['local_instalacao'];
    $media_diferenca = (float) $trem['media_diferenca'];
    $ultimo_contador = (float) $trem['ultimo_contador'];
    $data_referencia = $trem['data_referencia'];

    // Ignorar trens com média de diferença igual a 0 ou sem data de referência
    if ($media_diferenca <= 0 || empty($data_referencia)) {
        $trens_inativos[] = $nome_trem;
        continue;
    }

    $trens_ativos++;

    // Atualizar o total de km
    if ($ultimo_contador > 0) {
        $total_km += $ultimo_contador;
    }

    // Processar cada tipo de revisão
    foreach ($tolerancias as $tipo => $km_revisao) {
        $km_restante = $km_revisao - $ultimo_contador;

        if ($media_diferenca > 0) {
            $dias_ate_revisao = $km_restante / $media_diferenca;
            $data_revisao_obj = new DateTime($data_referencia);

            if ($dias_ate_revisao >= 0) {
                $data_revisao_obj->add(new DateInterval("P" . round(abs($dias_ate_revisao)) . "D"));
            } else {
                $data_revisao_obj->sub(new DateInterval("P" . round(abs($dias_ate_revisao)) . "D"));
            }

            $ano_revisao = (int) $data_revisao_obj->format('Y');

            // Limitar ao intervalo 2025-2032
            $ano_revisao = max(2025, min(2034, $ano_revisao));

            // Adicionar ao gráfico correspondente
            if (isset($graficos_data[$tipo][$ano_revisao])) {
                $graficos_data[$tipo][$ano_revisao]['count']++;
                $graficos_data[$tipo][$ano_revisao]['trens'][] = [
                    'nome' => $nome_trem,
                    'local_instalacao' => $local_instalacao,
                    'data_revisao' => $data_revisao_obj->format('d/m/Y'),
                    'km_atual' => $ultimo_contador,
                    'km_revisao' => $km_revisao,
                    'media_diferenca' => $media_diferenca,
                    'data_referencia' => $data_referencia
                ];
            }
        }
    }

    // Km de revisão: nominal + tolerancias
    $km_revisoes = [
        'nominal' => $tolerancias['nominal'],
        'tolerancia1' => $tolerancias['tolerancia1'],
        'tolerancia2' => $tolerancias['tolerancia2'],
        'tolerancia3' => $tolerancias['tolerancia3'],
        'tolerancia4' => $tolerancias['tolerancia4']
    ];

    $datas_revisao = [];

    foreach ($km_revisoes as $tipo => $km_revisao) {
        $km_restante = $km_revisao - $ultimo_contador;

        if ($media_diferenca > 0) {
            $dias_ate_revisao = $km_restante / $media_diferenca;
            $data_revisao_obj = new DateTime($data_referencia);

            if ($dias_ate_revisao >= 0) {
                $data_revisao_obj->add(new DateInterval("P" . round(abs($dias_ate_revisao)) . "D"));
            } else {
                $data_revisao_obj->sub(new DateInterval("P" . round(abs($dias_ate_revisao)) . "D"));
            }

            $data_revisao = $data_revisao_obj->format('d/m/Y');

        } else {
            $data_revisao = "Indeterminado";
        }

        $datas_revisao[$tipo] = $data_revisao;
    }

    $trens_dados_revisao[] = [
        'nome_trem' => $nome_trem,
        'local_instalacao' => $local_instalacao,
        'media_diferenca' => $media_diferenca,
        'ultimo_contador' => $ultimo_contador,
        'datas_revisao' => $datas_revisao,
        'data_referencia' => $data_referencia
    ];
}
?>


<main>
    <?php include '../templates/header-simuladores.php'; ?>
    <section class="simulacoes">
        <h1>Cenário atual:</h1>

        <h2><strong>Total de KM Rodado (todos os trens):</strong>
            <?= number_format($total_km, 2, ',', '.') ?> km
        </h2>

        <h2><strong>Trens ativos:</strong>
            <?= $trens_ativos ?>
        </h2>

        <h2><strong>Trens que não rodaram em 2025:</strong></h2>
        <?php if (!empty($trens_inativos)): ?>
            <ul>
                <?php foreach ($trens_inativos as $trem_inativo): ?>
                    <li><?= htmlspecialchars($trem_inativo) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Todos os trens registraram atividade em 2025.</p>
        <?php endif; ?>

        <table id="tabela-cenario">
            <thead>
                <tr>
                    <th>Nome do Trem</th>
                    <th>Média Diferença (km/dia)</th>
                    <th>Último Valor Hodômetro</th>
                    <th>Data Revisão Nominal</th>
                    <th>Data Revisão Tolerância 1</th>
                    <th>Data Revisão Tolerância 2</th>
                    <th>Data Revisão Tolerância 3</th>
                    <th>Data Revisão Tolerância 4</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trens_dados_revisao as $linha): ?>
                    <tr onclick="showTremDetailModalFromTable(
                        '<?= htmlspecialchars($linha['nome_trem'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($linha['local_instalacao'], ENT_QUOTES) ?>',
                        <?= floatval($linha['ultimo_contador']) ?>,
                        '<?= htmlspecialchars($linha['datas_revisao']['nominal'] ?? '', ENT_QUOTES) ?>',
                        <?= floatval($linha['media_diferenca']) ?>,
                        '<?= htmlspecialchars($linha['data_referencia'] ?? '', ENT_QUOTES) ?>'
                    )" style="cursor: pointer;">
                        <td><?= htmlspecialchars($linha['nome_trem']) ?></td>
                        <td><?= number_format($linha['media_diferenca'], 2, ',', '.') ?></td>
                        <td><?= number_format($linha['ultimo_contador'], 2, ',', '.') ?></td>
                        <?php foreach (['nominal', 'tolerancia1', 'tolerancia2', 'tolerancia3', 'tolerancia4'] as $tipo): ?>
                            <td><?= htmlspecialchars($linha['datas_revisao'][$tipo] ?? 'N/A') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="container mt-4">
            <h1 class="text-center mb-4">Previsão de Revisões por Ano</h1>

            <?php foreach ($graficos_data as $tipo => $dados_ano): ?>
                <div class="chart-container">
                    <h3 class="chart-title">Revisão <?= ucfirst($tipo) ?>
                        (<?= number_format($tolerancias[$tipo], 0, ',', '.') ?> km)</h3>
                    <canvas id="chart-<?= $tipo ?>" height="100"></canvas>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal para mostrar os trens -->
        <div class="modal fade" id="trensModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Trem</th>
                                    <th>Data Prevista</th>
                                    <th>KM Atual</th>
                                    <th>KM Revisão</th>
                                    <th>Diferença</th>
                                </tr>
                            </thead>
                            <tbody id="modalTrensBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal detalhado do trem -->
        <div class="modal fade" id="tremDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="detailModalTitle">Detalhes do Trem</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-card card">
                                    <div class="card-header bg-primary text-white">
                                        <h6>Informações do Trem</h6>
                                    </div>
                                    <div class="card-body" id="tremInfoBody">
                                        <!-- Preenchido via JavaScript -->
                                    </div>
                                </div>
                                <div class="form-group mt-3">
                                    <label for="mediaHoraria">Média Horária (km/dia):</label>
                                    <input type="number" step="0.01" class="form-control" id="mediaHoraria" value="0">
                                    <button class="btn btn-primary mt-2" onclick="updateChart()">Atualizar
                                        Gráfico</button>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div style="width: 100%; height: 400px; overflow: auto;">
                                <div style="width: 100%; margin-bottom: 20px;">
    <div style="margin-bottom: 10px;">
        <label for="dateRange">Intervalo de Datas:</label>
        <div id="dateRange" style="width: 100%; height: 20px;"></div>
        <div style="display: flex; justify-content: space-between;">
            <span id="dateMinLabel"></span>
            <span id="dateMaxLabel"></span>
        </div>
    </div>
    <div style="margin-bottom: 10px;">
        <label for="kmRange">Intervalo de KM:</label>
        <div id="kmRange" style="width: 100%; height: 20px;"></div>
        <div style="display: flex; justify-content: space-between;">
            <span id="kmMinLabel"></span>
            <span id="kmMaxLabel"></span>
        </div>
    </div>
</div>
                                    <canvas id="tremChart" height="400"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script
            src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
        <script>
            // Dados globais
            const graficosData = <?= json_encode($graficos_data) ?>;
            const tolerancias = <?= json_encode($tolerancias) ?>;
            const tipos = ['nominal', 'tolerancia1', 'tolerancia2', 'tolerancia3', 'tolerancia4'];
            const charts = {};
            let tremChart = null;
            let currentTremData = null;

            // Criar gráficos principais
            tipos.forEach(tipo => {
                const ctx = document.getElementById(`chart-${tipo}`).getContext('2d');
                const anos = Object.keys(graficosData[tipo]).map(Number);
                const counts = anos.map(ano => graficosData[tipo][ano].count);

                charts[tipo] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: anos,
                        datasets: [{
                            label: `Trens para revisão (${tolerancias[tipo]} km)`,
                            data: counts,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Quantidade de Trens' }
                            },
                            x: {
                                title: { display: true, text: 'Ano' }
                            }
                        }
                    }
                });

                // Adicionar evento de clique para barras
                document.getElementById(`chart-${tipo}`).onclick = (evt) => {
                    const points = charts[tipo].getElementsAtEventForMode(
                        evt,
                        'nearest',
                        { intersect: true },
                        true
                    );

                    if (points.length) {
                        const index = points[0].index;
                        const ano = charts[tipo].data.labels[index];
                        const trens = graficosData[tipo][ano].trens;
                        showTrensModal(tipo, ano, trens);
                    }
                };
            });

            function showTrensModal(tipo, ano, trens) {
                const modalTitle = document.getElementById('modalTitle');
                const modalBody = document.getElementById('modalTrensBody');

                modalTitle.textContent = `Trens para revisão ${tipo} em ${ano} (${trens.length} trens)`;

                modalBody.innerHTML = '';
                trens.forEach(trem => {
                    const diff = trem.km_revisao - trem.km_atual;
                    const row = document.createElement('tr');
                    row.style.cursor = 'pointer';
                    row.innerHTML = `
                        <td>${trem.nome}</td>
                        <td>${trem.data_revisao}</td>
                        <td>${trem.km_atual.toLocaleString('pt-BR')}</td>
                        <td>${trem.km_revisao.toLocaleString('pt-BR')}</td>
                        <td class="${diff < 0 ? 'text-danger' : 'text-success'}">${diff.toLocaleString('pt-BR')} km</td>
                    `;

                    row.addEventListener('click', () => {
                        showTremDetailModal(trem);
                    });

                    modalBody.appendChild(row);
                });

                const modal = new bootstrap.Modal(document.getElementById('trensModal'));
                modal.show();
            }

            function showTremDetailModal(trem) {
                currentTremData = trem;
                const modalTitle = document.getElementById('detailModalTitle');
                const infoBody = document.getElementById('tremInfoBody');

                modalTitle.textContent = `Detalhes do Trem ${trem.nome}`;

                // Preencher informações do trem
                infoBody.innerHTML = `
                    <p><strong>Local de Instalação:</strong> ${trem.local_instalacao}</p>
                    <p><strong>KM Atual:</strong> ${trem.km_atual.toLocaleString('pt-BR')} km</p>
                    <p><strong>Data Referência:</strong> ${trem.data_referencia}</p>
                    <p><strong>Média Diária:</strong> ${trem.media_diferenca.toLocaleString('pt-BR', { maximumFractionDigits: 2 })} km/dia</p>
                    <p><strong>Próxima Revisão:</strong> ${trem.data_revisao} (${trem.km_revisao.toLocaleString('pt-BR')} km)</p>
                `;

                // Inicializar gráfico
                loadTremChartData(trem.local_instalacao, trem.data_referencia, trem.km_atual, trem.media_diferenca);

                const modal = new bootstrap.Modal(document.getElementById('tremDetailModal'));
                modal.show();
            }

            async function loadTremChartData(localInstalacao, dataReferencia, kmAtual, mediaDiferenca) {
                try {
                    // Buscar histórico do trem
                    const response = await fetch(`get_historico_trem.php?local=${encodeURIComponent(localInstalacao)}`);
                    const historico = await response.json();

                    // Configurar datas
                    const startDate = new Date('2025-01-01');
                    const endDate = new Date('2034-12-31');
                    const refDate = new Date(dataReferencia);

                    // Gerar todas as datas do período
                    const allDates = [];
                    let currentDate = new Date(startDate);
                    while (currentDate <= endDate) {
                        allDates.push(new Date(currentDate));
                        currentDate.setDate(currentDate.getDate() + 1);
                    }

                    // Formatar labels para o gráfico
                    const labels = allDates.map(date => date);

                    // Processar dados históricos
                    const kmReal = allDates.map(date => {
                        const dateStr = date.toISOString().split('T')[0];
                        const found = historico.find(h => h.data === dateStr);
                        return found ? parseFloat(found.valmed_postotcontador) : null;
                    });

                    // Projeção pela média do trem (a partir da data de referência)
                    const kmProjMediaTrem = allDates.map(date => {
                        if (date < refDate) return null;
                        const diffDays = Math.floor((date - refDate) / (1000 * 60 * 60 * 24));
                        return kmAtual + (mediaDiferenca * diffDays);
                    });

                    // Encontrar o km inicial (01/01/2025)
                    const kmInicioObj = historico.find(h => h.data === '2025-01-01');
                    const kmInicio = kmInicioObj ? parseFloat(kmInicioObj.valmed_postotcontador) : 0;
                    const mediaHoraria = parseFloat(document.getElementById('mediaHoraria').value) || 0;

                    // Projeção pela média horária (a partir de 01/01/2025)
                    const kmProjMediaHoraria = allDates.map((date, index) => {
                        return kmInicio + (mediaHoraria * index);
                    });

                    // Renderizar o gráfico
                    renderTremChart(labels, kmReal, kmProjMediaTrem, kmProjMediaHoraria);
                } catch (error) {
                    console.error('Erro ao carregar dados:', error);
                }
            }

            function renderTremChart(labels, kmReal, kmProjMediaTrem, kmProjMediaHoraria) {
    const ctx = document.getElementById('tremChart').getContext('2d');
    
    // Converter labels para objetos Date e encontrar min/max
    const dates = labels.map(label => new Date(label));
    const minDate = new Date(Math.min(...dates));
    const maxDate = new Date(Math.max(...dates));
    
    // Encontrar min/max de KM
    const allKmValues = [...kmReal, ...kmProjMediaTrem, ...kmProjMediaHoraria].filter(v => v !== null);
    const minKm = Math.min(...allKmValues);
    const maxKm = Math.max(...allKmValues);
    
    // Destruir gráfico anterior se existir
    if (tremChart) {
        tremChart.destroy();
    }
    
    // Criar novo gráfico
    tremChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'KM Real (Histórico)',
                    data: kmReal,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderWidth: .5,
                    pointRadius: 2,
                    fill: true,
                    spanGaps: true
                },
                {
                    label: 'Projeção (Média do Trem)',
                    data: kmProjMediaTrem,
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    borderWidth: .5,
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: 'Projeção (Média Horária)',
                    data: kmProjMediaHoraria,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: .5,
                    borderDash: [5, 5],
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'month',
                        tooltipFormat: 'dd/MM/yyyy',
                        displayFormats: {
                            day: 'dd/MM/yyyy',
                            month: 'MM/yyyy',
                            year: 'yyyy'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Data'
                    },
                    min: minDate,
                    max: maxDate
                },
                y: {
                    title: {
                        display: true,
                        text: 'KM Acumulado'
                    },
                    min: minKm,
                    max: maxKm,
                    beginAtZero: false
                }
            },
            plugins: {
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'xy',
                        modifierKey: 'ctrl'
                    },
                    zoom: {
                        wheel: {
                            enabled: true
                        },
                        pinch: {
                            enabled: true
                        },
                        mode: 'xy',
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return `${context.dataset.label}: ${context.raw !== null ?
                                context.raw.toFixed(2).replace('.', ',') : 'N/A'} km`;
                        }
                    }
                }
            }
        }
    });
    
    // Inicializar sliders
    initSliders(minDate, maxDate, minKm, maxKm);
}

function initSliders(minDate, maxDate, minKm, maxKm) {
    // Formatar datas para exibição
    function formatDate(date) {
        return date.toLocaleDateString('pt-BR');
    }
    
    // Slider de datas
    const dateSlider = document.getElementById('dateRange');
    noUiSlider.create(dateSlider, {
        start: [minDate.getTime(), maxDate.getTime()],
        connect: true,
        range: {
            'min': minDate.getTime(),
            'max': maxDate.getTime()
        },
        step: 24 * 60 * 60 * 1000 // 1 dia
    });
    
    // Atualizar labels e gráfico quando o slider de datas muda
    dateSlider.noUiSlider.on('update', function(values) {
        const min = new Date(parseInt(values[0]));
        const max = new Date(parseInt(values[1]));
        
        document.getElementById('dateMinLabel').textContent = formatDate(min);
        document.getElementById('dateMaxLabel').textContent = formatDate(max);
        
        if (tremChart) {
            tremChart.options.scales.x.min = min;
            tremChart.options.scales.x.max = max;
            tremChart.update();
        }
    });
    
    // Slider de KM
    const kmSlider = document.getElementById('kmRange');
    noUiSlider.create(kmSlider, {
        start: [minKm, maxKm],
        connect: true,
        range: {
            'min': minKm,
            'max': maxKm
        },
        step: (maxKm - minKm) / 100 // 1% do range
    });
    
    // Atualizar labels e gráfico quando o slider de KM muda
    kmSlider.noUiSlider.on('update', function(values) {
        const min = parseFloat(values[0]);
        const max = parseFloat(values[1]);
        
        document.getElementById('kmMinLabel').textContent = min.toFixed(2).replace('.', ',') + ' km';
        document.getElementById('kmMaxLabel').textContent = max.toFixed(2).replace('.', ',') + ' km';
        
        if (tremChart) {
            tremChart.options.scales.y.min = min;
            tremChart.options.scales.y.max = max;
            tremChart.update();
        }
    });
}
            function updateChart() {
                if (!tremChart) return;

                const mediaHoraria = parseFloat(document.getElementById('mediaHoraria').value) || 0;

                // Encontrar o primeiro valor não nulo do histórico (km inicial)
                const kmInicio = tremChart.data.datasets[0].data.find(d => d !== null) || 0;

                // Atualizar projeção pela média horária
                const kmProjMediaHoraria = tremChart.data.labels.map((_, index) => {
                    return kmInicio + (mediaHoraria * index);
                });

                tremChart.data.datasets[2].data = kmProjMediaHoraria;
                tremChart.update();
            }

            function showTremDetailModalFromTable(nome, local, kmAtual, dataRevisao, mediaDiferenca, dataReferencia) {
                const tremData = {
                    nome: nome,
                    local_instalacao: local,
                    km_atual: kmAtual,
                    data_revisao: dataRevisao,
                    media_diferenca: mediaDiferenca,
                    data_referencia: dataReferencia,
                    km_revisao: tolerancias.nominal // Usando o valor nominal como exemplo
                };
                showTremDetailModal(tremData);
            }
        </script>
    </section>
</main>

<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>