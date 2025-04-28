<?php include '../templates/header-simulador.php'; ?>
<?php
require_once '../config/db.php'; // Conexão PDO

// Buscar dados dos trens
$sql = "
    SELECT 
        t.nome_trem,
        t.local_instalacao,
        (SELECT h.valmed_postotcontador 
         FROM hodometro h 
         WHERE h.local_instalacao = t.local_instalacao 
         ORDER BY h.data DESC, h.hora DESC 
         LIMIT 1) AS ultimo_contador,
        (SELECT h.data 
         FROM hodometro h 
         WHERE h.local_instalacao = t.local_instalacao 
         ORDER BY h.data DESC, h.hora DESC 
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
    if (empty($trem['ultimo_contador']) || empty($trem['data_referencia'])) {
        continue;
    }

    $trens_dados[] = [
        'nome_trem' => $trem['nome_trem'],
        'ultimo_contador' => (float) $trem['ultimo_contador'],
        'data_referencia' => $trem['data_referencia'],
    ];
}
?>



<main>
    <?php include '../templates/header-simuladores.php'; ?>
    <section class="simulacoes">
        <h1>Simular por data alvo</h1>

        <div id="simulador-conteudo">
            <table class="tabela-simulacao" id="tabela-trens">
                <thead>
                    <tr>
                        <th>Nome do Trem</th>
                        <th>KM Atual</th>
                        <th>Data Alvo da RG</th>
                        <th>Média Necessária (KM/dia)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trens_dados as $index => $trem): ?>
                        <tr data-index="<?= $index ?>">
                            <td><?= htmlspecialchars($trem['nome_trem']) ?></td>
                            <td class="km-atual"><?= number_format($trem['ultimo_contador'], 2, ',', '.') ?></td>
                            <td>
                                <input type="date" class="input-data-alvo form-control" data-idx="<?= $index ?>">
                            </td>
                            <td class="media-necessaria">-</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button id="btn-gerar-pdf" class="btn-pdf">Gerar Relatório em PDF</button>
        </div>


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const trensData = <?= json_encode($trens_dados) ?>;
                const KM_META_RG = 1200000;

                function calcularMediaNecessaria(kmAtual, dataReferencia, dataAlvo) {
                    const dataRef = new Date(dataReferencia);
                    const dataTarget = new Date(dataAlvo);

                    const diffTime = dataTarget.getTime() - dataRef.getTime();
                    const diffDias = diffTime / (1000 * 3600 * 24);

                    if (diffDias <= 0) return null;

                    const kmFaltante = KM_META_RG - kmAtual;
                    return kmFaltante / diffDias;
                }

                function atualizarMedias() {
                    document.querySelectorAll('tbody tr[data-index]').forEach(row => {
                        const idx = parseInt(row.getAttribute('data-index'));
                        const trem = trensData[idx];

                        const inputData = row.querySelector('.input-data-alvo');
                        const mediaCell = row.querySelector('.media-necessaria');

                        if (!inputData.value) {
                            mediaCell.textContent = '-';
                            return;
                        }

                        const media = calcularMediaNecessaria(trem.ultimo_contador, trem.data_referencia, inputData.value);
                        if (!media || media <= 0) {
                            mediaCell.textContent = 'N/A';
                        } else {
                            mediaCell.textContent = media.toFixed(2).replace('.', ',') + ' km/dia';
                        }
                    });
                }

                document.querySelectorAll('.input-data-alvo').forEach(input => {
                    input.addEventListener('change', atualizarMedias);
                });


                document.getElementById('btn-gerar-pdf').addEventListener('click', function () {
                    atualizarMedias();

                    const linhas = [];

                    document.querySelectorAll('#tabela-trens tbody tr').forEach(row => {
                        const cols = [];
                        row.querySelectorAll('td').forEach(cell => {
                            const input = cell.querySelector('input');
                            let texto = input ? input.value : cell.textContent.trim();
                            cols.push({ text: texto, fontSize: 9 });
                        });
                        linhas.push(cols);
                    });

                    const docDefinition = {
                        pageSize: 'A4',
                        content: [
                            { text: 'Relatório de Simulação - Média para Revisão Geral', style: 'header', alignment: 'center', margin: [0, 0, 0, 20] },
                            {
                                table: {
                                    headerRows: 1,
                                    body: [
                                        [
                                            { text: 'Nome do Trem', style: 'tableHeader' },
                                            { text: 'KM Atual', style: 'tableHeader' },
                                            { text: 'Data Alvo da RG', style: 'tableHeader' },
                                            { text: 'Média Necessária (KM/dia)', style: 'tableHeader' }
                                        ],
                                        ...linhas
                                    ]
                                },
                                layout: 'lightHorizontalLines'
                            }
                        ],
                        styles: {
                            header: { fontSize: 16, bold: true },
                            tableHeader: { bold: true, fontSize: 10, color: 'black' }
                        }
                    };

                    pdfMake.createPdf(docDefinition).open();
                });
            });

                
        </script>
</main>

<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>