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

$trens_dados = [];

foreach ($dados as $trem) {
    $nome_trem = $trem['nome_trem'];
    $local_instalacao = $trem['local_instalacao'];
    $media_diferenca = (float) $trem['media_diferenca'];
    $ultimo_contador = (float) $trem['ultimo_contador'];
    $data_referencia = $trem['data_referencia'];

    // Ignorar trens com média de diferença igual a 0 ou sem data de referência
    if ($media_diferenca <= 0 || empty($data_referencia)) {
        continue;
    }

    $trens_dados[] = [
        'nome_trem' => $nome_trem,
        'ultimo_contador' => $ultimo_contador,
        'media_diferenca' => $media_diferenca,
        'data_referencia' => $data_referencia,
        'tolerancias' => $tolerancias
    ];
}
?>



<main>
    <?php include '../templates/header-simuladores.php'; ?>
    <section class="simulacoes">
        <h1>Simulação por média</h1>

        <div id="simulador-conteudo">


            <table class="tabela-simulacao" id="tabela-trens">
                <thead>
                    <tr>
                        <th>Nome do Trem</th>
                        <th>KM Acumulado</th>
                        <th>Média (KM/Dia)</th>
                        <th>Revisão Nominal<br>(<?= $tolerancias['nominal'] ?> KM)</th>
                        <th>Tolerância 1<br>(<?= $tolerancias['tolerancia1'] ?> KM)</th>
                        <th>Tolerância 2<br>(<?= $tolerancias['tolerancia2'] ?> KM)</th>
                        <th>Tolerância 3<br>(<?= $tolerancias['tolerancia3'] ?> KM)</th>
                        <th>Tolerância 4<br>(<?= $tolerancias['tolerancia4'] ?> KM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trens_dados as $index => $trem): ?>
                        <tr data-trem="<?= $index ?>">
                            <td><?= htmlspecialchars($trem['nome_trem']) ?></td>
                            <td class="km-acumulado"><?= number_format($trem['ultimo_contador'], 2, ',', '.') ?></td>
                            <td>
                                <input type="number" step="0.01"
                                    value="<?= number_format($trem['media_diferenca'], 2, ',', '') ?>"
                                    class="input-media form-control" data-idx="<?= $index ?>">
                            </td>
                            <td class="data-revisao" data-tipo="nominal" data-meta="<?= $tolerancias['nominal'] ?>"></td>
                            <td class="data-revisao" data-tipo="tolerancia1" data-meta="<?= $tolerancias['tolerancia1'] ?>">
                            </td>
                            <td class="data-revisao" data-tipo="tolerancia2" data-meta="<?= $tolerancias['tolerancia2'] ?>">
                            </td>
                            <td class="data-revisao" data-tipo="tolerancia3" data-meta="<?= $tolerancias['tolerancia3'] ?>">
                            </td>
                            <td class="data-revisao" data-tipo="tolerancia4" data-meta="<?= $tolerancias['tolerancia4'] ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button id="btn-gerar-pdf" class="btn-pdf">Gerar Relatório em PDF</button>
            <div class="controls-container mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <label for="duracao-rg" class="form-label">Duração da Revisão Geral (meses):</label>
                        <input type="number" id="duracao-rg" class="form-control" value="3" min="1" max="12">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button id="btn-atualizar-gantt" class="btn btn-primary">Atualizar Gantt</button>
                    </div>
                </div>
            </div>
            <div id="graficos-container">
                <canvas id="gantt-nominal" style="margin: 30px 0; height: 400px;"></canvas>
                <canvas id="gantt-tolerancia1" style="margin: 30px 0; height: 400px;"></canvas>
                <canvas id="gantt-tolerancia2" style="margin: 30px 0; height: 400px;"></canvas>
                <canvas id="gantt-tolerancia3" style="margin: 30px 0; height: 400px;"></canvas>
                <canvas id="gantt-tolerancia4" style="margin: 30px 0; height: 400px;"></canvas>
            </div>
        </div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const trensData = <?= json_encode($trens_dados) ?>;



        function formatarDataParaChartJS(data) {
            const year = data.getFullYear();
            const month = String(data.getMonth() + 1).padStart(2, '0');
            const day = String(data.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function calcularDataRevisao(dataReferenciaStr, kmAtual, kmMeta, mediaDiaria) {
            if (!dataReferenciaStr || mediaDiaria <= 0) return null;

            const partes = dataReferenciaStr.split('-');
            const dataReferencia = new Date(partes[0], partes[1] - 1, partes[2]);
            if (isNaN(dataReferencia.getTime())) return null;

            const kmRestante = kmMeta - kmAtual;
            const diasParaMeta = kmRestante / mediaDiaria;

            const dataRevisao = new Date(dataReferencia);
            dataRevisao.setDate(dataRevisao.getDate() + Math.floor(diasParaMeta));

            return dataRevisao;
        }

        function atualizarTabela() {
            document.querySelectorAll('tbody tr[data-trem]').forEach(row => {
                const idx = parseInt(row.getAttribute('data-trem'));
                const trem = trensData[idx];
                const mediaInput = row.querySelector('.input-media');
                let media = parseFloat(mediaInput.value.replace(',', '.'));
                if (isNaN(media) || media <= 0) {
                    media = trem.media_diferenca;
                }

                row.querySelectorAll('.data-revisao').forEach(cell => {
                    const tipo = cell.getAttribute('data-tipo');
                    const kmMeta = parseFloat(cell.getAttribute('data-meta'));

                    const data = calcularDataRevisao(
                        trem.data_referencia,
                        trem.ultimo_contador,
                        kmMeta,
                        media
                    );

                    if (!data) {
                        cell.textContent = 'N/A';
                    } else {
                        cell.textContent = formatarDataParaChartJS(data);
                    }
                });
            });
        }

        const NOMES_GRAFICOS = ['nominal', 'tolerancia1', 'tolerancia2', 'tolerancia3', 'tolerancia4'];
        const ganttCharts = {};

        function formatarData(data) {
            const dia = String(data.getDate()).padStart(2, '0');
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const ano = data.getFullYear();
            return `${dia}/${mes}/${ano}`;
        }

        function parseData(texto) {
            if (!texto) return null;
            // Assumindo formato DD/MM/YYYY ou YYYY-MM-DD
            const partes = texto.includes('/') ?
                texto.split('/') :
                texto.split('-');

            if (partes.length !== 3) return null;

            // Se for DD/MM/YYYY
            if (texto.includes('/')) {
                const [dia, mes, ano] = partes.map(Number);
                return new Date(ano, mes - 1, dia);
            }
            // Se for YYYY-MM-DD
            else {
                const [ano, mes, dia] = partes.map(Number);
                return new Date(ano, mes - 1, dia);
            }
        }



        function coletarDadosTabela(duracaoMeses) {
            const dados = {
                nominal: [],
                tolerancia1: [],
                tolerancia2: [],
                tolerancia3: [],
                tolerancia4: []
            };

            const min = new Date('2025-01-01').getTime(); // Limite mínimo do gráfico
            const max = new Date('2034-12-31').getTime(); // Limite máximo do gráfico

            document.querySelectorAll('tbody tr[data-trem]').forEach(row => {
                const tremNome = row.querySelector('td:first-child')?.textContent.trim() || '';

                NOMES_GRAFICOS.forEach(tipo => {
                    const cell = row.querySelector(`.data-revisao[data-tipo="${tipo}"]`);
                    if (!cell) return;

                    const textoData = cell.textContent.trim();
                    if (!textoData || textoData === 'N/A') return;

                    const dataInicio = parseData(textoData);
                    if (!dataInicio || isNaN(dataInicio)) return;

                    const dataInicioTimestamp = dataInicio.getTime();
                    const dataFim = new Date(dataInicio);
                    dataFim.setMonth(dataFim.getMonth() + duracaoMeses);
                    const dataFimTimestamp = dataFim.getTime();

                    // Verifica se está completamente fora do intervalo
                    if (dataInicioTimestamp > max || dataFimTimestamp < min) {
                        return; // Não adiciona essa barra
                    }

                    // Ajusta para não ultrapassar o intervalo
                    const xAdjusted = Math.max(dataInicioTimestamp, min);
                    const x2Adjusted = Math.min(dataFimTimestamp, max);

                    dados[tipo].push({
                        x: xAdjusted,
                        x2: x2Adjusted,
                        y: tremNome
                    });
                });
            });

            return dados;
        }

        function criarGraficos(dados) {
    NOMES_GRAFICOS.forEach(tipo => {
        if (ganttCharts[tipo]) {
            ganttCharts[tipo].destroy();
        }

        const canvas = document.getElementById(`gantt-${tipo}`);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        // Get the data for this specific chart type
        const chartData = dados[tipo];
        if (!chartData || chartData.length === 0) return;

        // Prepare datasets for Chart.js
        const datasets = chartData.map(dado => ({
            type: 'line',
            label: dado.y,
            data: [
                { x: dado.x, y: dado.y },
                { x: dado.x2, y: dado.y }
            ],
            borderColor: 'rgba(66, 135, 245, 0.8)',
            borderWidth: 6,
            fill: false,
            pointRadius: 4,
            showLine: true
        }));

        // Get unique y labels for the chart
        const labels = [...new Set(chartData.map(d => d.y))];

        ganttCharts[tipo] = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: datasets
            },
            options: {
                parsing: false,
                indexAxis: 'y',
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        ticks: {
                            callback: function (value) {
                                return formatarData(new Date(value));
                            }
                        },
                        title: {
                            display: true,
                            text: 'Data'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                        }
                    },
                    y: {
                        type: 'category',
                        labels: labels,
                        offset: true,
                        title: {
                            display: true,
                            text: 'Trens'
                        },
                        grid: {
                            drawBorder: false,
                            color: function (context) {
                                return context.index !== undefined && context.index % 1 === 0
                                    ? 'rgba(0, 123, 255, 0.3)'
                                    : 'transparent';
                            },
                            lineWidth: 1.5,
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: gerarTituloGrafico(tipo),
                        font: {
                            size: 18
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const dataset = context.dataset;
                                const start = new Date(dataset.data[0].x);
                                const end = new Date(dataset.data[1].x);
                                return `${dataset.label}: De ${formatarData(start)} até ${formatarData(end)}`;
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
}
        // Função auxiliar pra montar o título com km
        function gerarTituloGrafico(tipo) {
            const valoresKm = {
                'nominal': 120000,
                'tolerancia1': 1245000,
                'tolerancia2': 1250000,
                'tolerancia3': 1255000,
                'tolerancia4': 1260000
            };
            const km = valoresKm[tipo.toLowerCase()] || '?';
            return `${tipo.toUpperCase()} (${km} km)`;
        }

        function atualizarGraficos() {
            const duracaoInput = document.getElementById('duracao-rg');
            const duracaoMeses = parseInt(duracaoInput.value) || 3;

            const dados = coletarDadosTabela(duracaoMeses);
            criarGraficos(dados);
        }

        document.getElementById('btn-atualizar-gantt').addEventListener('click', atualizarGraficos);


        // Atualizar ao mudar média manualmente
        document.querySelectorAll('.input-media').forEach(input => {
            input.addEventListener('change', function () {
                atualizarTabela();
                atualizarGraficos(); // <--- CORRETO AQUI
            });
        });


        // Inicial
        atualizarTabela();
        criarGraficos(coletarDadosTabela(parseInt(document.getElementById('duracao-rg').value)));

    });

    document.getElementById('btn-gerar-pdf').addEventListener('click', function () {
        const canvas1 = document.getElementById('gantt-nominal');
        const canvas2 = document.getElementById('gantt-tolerancia1');
        const canvas3 = document.getElementById('gantt-tolerancia2');
        const canvas4 = document.getElementById('gantt-tolerancia3');
        const canvas5 = document.getElementById('gantt-tolerancia4');

        const imagem1 = canvas1 ? canvas1.toDataURL('image/png', 1.0) : null;
        const imagem2 = canvas2 ? canvas2.toDataURL('image/png', 1.0) : null;
        const imagem3 = canvas3 ? canvas3.toDataURL('image/png', 1.0) : null;
        const imagem4 = canvas4 ? canvas4.toDataURL('image/png', 1.0) : null;
        const imagem5 = canvas5 ? canvas5.toDataURL('image/png', 1.0) : null;

        const tabelaElement = document.getElementById('tabela-trens'); // ID da sua tabela
        const linhas = [];

        // Pegando todas as linhas da tabela
        tabelaElement.querySelectorAll('tr').forEach((row, rowIndex) => {
            const colunas = [];
            row.querySelectorAll('th, td').forEach(cell => {
                let texto = '';

                // Verifica se existe input dentro da célula
                const input = cell.querySelector('input');
                if (input) {
                    texto = input.value.trim();
                } else {
                    texto = cell.innerText.trim();
                }

                colunas.push({
                    text: texto,
                    bold: rowIndex === 0,
                    fillColor: rowIndex === 0 ? '#d1e7fd' : null,
                    margin: [2, 5, 2, 5], // Melhor ajuste visual
                    fontSize: 9 // Tamanho menor pra caber melhor
                });
            });
            linhas.push(colunas);
        });

        const docDefinition = {
            pageSize: 'A4',
            pageMargins: [20, 30, 20, 30],
            content: [
                {
                    text: 'Relatório de Simulação de Manutenção',
                    style: 'header',
                    alignment: 'center',
                    margin: [0, 0, 0, 20]
                },
                ...(imagem1 ? [{ image: imagem1, width: 500, alignment: 'center', margin: [0, 0, 0, 20] }] : []),
                ...(imagem2 ? [{ image: imagem2, width: 500, alignment: 'center', margin: [0, 0, 0, 20] }] : []),
                ...(imagem3 ? [{ image: imagem3, width: 500, alignment: 'center', margin: [0, 0, 0, 20] }] : []),
                ...(imagem4 ? [{ image: imagem4, width: 500, alignment: 'center', margin: [0, 0, 0, 20] }] : []),
                ...(imagem5 ? [{ image: imagem5, width: 500, alignment: 'center', margin: [0, 0, 0, 20] }] : []),
                {
                    text: 'Detalhamento da Tabela',
                    style: 'subheader',
                    margin: [0, 20, 0, 10]
                },
                {
                    table: {
                        headerRows: 1,
                        widths: Array(linhas[0].length).fill('*'), // todas colunas proporcionais
                        body: linhas,
                    },
                    layout: {
                        fillColor: function (rowIndex, node, columnIndex) {
                            return rowIndex === 0 ? '#d1e7fd' : null;
                        },
                        hLineWidth: function (i, node) {
                            return i === 0 || i === node.table.body.length ? 1 : 0.5;
                        },
                        vLineWidth: function (i, node) {
                            return 0.5;
                        },
                        hLineColor: function (i, node) {
                            return '#aaa';
                        },
                        vLineColor: function (i, node) {
                            return '#aaa';
                        }
                    }
                },
                {
                    text: `Gerado em: ${new Date().toLocaleDateString()} às ${new Date().toLocaleTimeString()}`,
                    style: 'footer',
                    alignment: 'right',
                    margin: [0, 30, 0, 0]
                }
            ],
            styles: {
                header: {
                    fontSize: 20,
                    bold: true,
                    color: '#0d6efd'
                },
                subheader: {
                    fontSize: 16,
                    bold: true,
                    color: '#333'
                },
                footer: {
                    fontSize: 9,
                    italics: true,
                    color: '#777'
                }
            },
            defaultStyle: {
                fontSize: 10
            }
        };

        pdfMake.createPdf(docDefinition).download('Relatorio-Simulacao-Manutencao.pdf');
    });

</script>


<style>
    .atrasada {
        background-color: #ffcccc;
        color: #cc0000;
        font-weight: bold;
    }

    .btn-pdf {
        margin: 20px 0;
        padding: 10px 15px;
        background-color: #cc0000;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    .btn-pdf:hover {
        background-color: #990000;
    }

    .chart-container {
        margin: 30px 0;
        padding: 15px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: 600px;
    }

    .input-media {
        width: 80px;
        padding: 5px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .controls-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>

<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>