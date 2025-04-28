<?php
require_once '../includes/session.php';
require_once '../config/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['usuario'];
verificaPermissao(['admin', 'gerente']);

// Buscar a última data de atualização
$ultimaAtualizacao = null;

try {
    $stmt = $pdo->query("SELECT MAX(CONCAT(data, ' ', hora)) AS ultima_atualizacao FROM hodometro");
    $resultado = $stmt->fetch();

    if ($resultado && $resultado['ultima_atualizacao']) {
        $ultimaAtualizacao = $resultado['ultima_atualizacao'];
        $ultimaAtualizacaoFormatada = DateTime::createFromFormat('Y-m-d H:i:s', $ultimaAtualizacao);

        if ($ultimaAtualizacaoFormatada) {
            $ultimaAtualizacao = $ultimaAtualizacaoFormatada->format('d/m/Y H:i');
        } else {
            $ultimaAtualizacao = 'Erro ao formatar a data.';
        }
    } else {
        $ultimaAtualizacao = 'Nenhuma atualização registrada ainda.';
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar última atualização: " . $e->getMessage());
    $ultimaAtualizacao = 'Erro ao consultar o banco de dados.';
}

// Verifica se o CSV foi enviado
if (isset($_POST['csv_data'])) {
    try {
        // Limpeza de buffer e headers
        while (ob_get_level()) ob_end_clean();
        header_remove();
        header('Content-Type: application/json');

        $csv_data = $_POST['csv_data'];
        $csv_data = preg_replace('/\x{FEFF}/u', '', $csv_data);
        $rows = explode("\n", trim($csv_data));
        $imported = 0;
        $errors = 0;

        foreach ($rows as $rowIndex => $row) {
            $row = trim($row);
            if (empty($row)) continue;

            $columns = str_getcsv($row);
            
            if (count($columns) !== 8) {
                $errors++;
                continue;
            }

            // Processamento simplificado dos dados
            $ponto_medicao = trim($columns[0]);
            $local_instalacao = trim($columns[1]);
            $denominacao_ponto = trim($columns[2]);
            
            // Processamento de data (versão simplificada)
            $dataValue = trim($columns[3]);
            $dataFormatada = null;
            
            if (is_numeric($dataValue)) {
                $days = (int)$dataValue;
                $dataFormatada = (new DateTime('1899-12-30'))->add(new DateInterval("P{$days}D"))->format('Y-m-d');
            } elseif ($dateObj = DateTime::createFromFormat('d/m/Y', $dataValue)) {
                $dataFormatada = $dateObj->format('Y-m-d');
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataValue)) {
                $dataFormatada = $dataValue;
            }

            // Processamento de hora (versão simplificada)
            $horaValue = trim($columns[4]);
            $horaFormatada = null;
            
            if (is_numeric($horaValue) && strpos($horaValue, '.') !== false) {
                $totalSeconds = (int)round((float)$horaValue * 86400);
                $horaFormatada = sprintf("%02d:%02d:%02d", 
                    floor($totalSeconds / 3600),
                    floor(($totalSeconds % 3600) / 60),
                    $totalSeconds % 60
                );
            } elseif (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $horaValue)) {
                $horaFormatada = strlen($horaValue) === 5 ? $horaValue . ':00' : $horaValue;
            }

            if (!$dataFormatada || !$horaFormatada) {
                $errors++;
                continue;
            }

            // Inserção no banco
            $stmt = $pdo->prepare("INSERT INTO hodometro 
                (ponto_medicao, local_instalacao, denominacao_ponto, data, hora, 
                valmed_postotcontador, difer_posicao_numer, doc_medicao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $ponto_medicao,
                $local_instalacao,
                $denominacao_ponto,
                $dataFormatada,
                $horaFormatada,
                trim($columns[5]),
                trim($columns[6]),
                trim($columns[7])
            ]);

            $imported++;
        }

        echo json_encode([
            'success' => true,
            'message' => "Importação concluída: $imported registros importados, $errors erros.",
            'imported' => $imported,
            'errors' => $errors
        ]);
        exit();

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erro no processamento: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Processar envio do formulário
if (isset($_POST['salvar_tolerancia'])) {
    $preventiva = trim($_POST['preventiva']);
    $novaPreventiva = trim($_POST['nova_preventiva']);
    $nominal = trim($_POST['nominal']);
    $tolerancia1 = trim($_POST['tolerancia1']);
    $tolerancia2 = trim($_POST['tolerancia2']);
    $tolerancia3 = trim($_POST['tolerancia3']);
    $tolerancia4 = trim($_POST['tolerancia4']);

    if (!empty($preventiva)) {
        // Verificar se já existe para atualizar ou inserir
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tolerancias WHERE preventiva = ?");
        $stmt->execute([$preventiva]);
        $existe = $stmt->fetchColumn() > 0;

        if ($existe) {
            // Atualizar
            $stmt = $pdo->prepare("UPDATE tolerancias 
                                   SET nominal = ?, tolerancia1 = ?, tolerancia2 = ?, tolerancia3 = ?, tolerancia4 = ?
                                   WHERE preventiva = ?");
            $stmt->execute([$nominal, $tolerancia1, $tolerancia2, $tolerancia3, $tolerancia4, $preventiva]);
            $mensagem_tolerancia = "Preventiva atualizada com sucesso!";
        }
    } else {
        $mensagem_tolerancia = "Erro: é necessário informar uma preventiva.";
    }
}
?>


<?php include '../templates/header.php'; ?>

<main>
    <section class="dashboard">

        <div class="container mt-5">
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card" style="padding: 3rem;">
                        <h3 class="card-title">Gerenciar Tolerâncias</h3>

                        <!-- Formulário para atualizar/adicionar tolerâncias -->
                        <form id="tolerancia-form" method="POST" action="">
                            <div class="form-group">
                                <label for="preventiva">Preventiva</label>
                                <select id="preventiva" name="preventiva" class="form-control" required>
                                    <option value="">Selecione ou digite nova</option>
                                    <?php
                                    // Buscar preventivas existentes do banco
                                    $stmt = $pdo->query("SELECT DISTINCT preventiva FROM tolerancias ORDER BY preventiva ASC");
                                    $preventivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($preventivas as $item) {
                                        echo '<option value="' . htmlspecialchars($item['preventiva']) . '">' . htmlspecialchars($item['preventiva']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group col">
                                    <label for="nominal">Nominal</label>
                                    <input type="text" id="nominal" name="nominal" class="form-control" required>
                                </div>
                                <div class="form-group col">
                                    <label for="tolerancia1">Tolerância 1</label>
                                    <input type="text" id="tolerancia1" name="tolerancia1" class="form-control"
                                        required>
                                </div>
                                <div class="form-group col">
                                    <label for="tolerancia2">Tolerância 2</label>
                                    <input type="text" id="tolerancia2" name="tolerancia2" class="form-control"
                                        required>
                                </div>
                                <div class="form-group col">
                                    <label for="tolerancia3">Tolerância 3</label>
                                    <input type="text" id="tolerancia3" name="tolerancia3" class="form-control"
                                        required>
                                </div>
                                <div class="form-group col">
                                    <label for="tolerancia4">Tolerância 4</label>
                                    <input type="text" id="tolerancia4" name="tolerancia4" class="form-control"
                                        required>
                                </div>
                            </div>

                            <button type="submit" name="salvar_tolerancia" class="btn btn-success">Salvar
                                Tolerância</button>
                        </form>

                        <!-- Mensagem de sucesso/erro -->
                        <?php if (isset($mensagem_tolerancia)): ?>
                            <div class="alert alert-info mt-4"><?php echo $mensagem_tolerancia; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.17.5/dist/xlsx.full.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('upload-form');

                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();

                        const fileInput = document.getElementById('file-input');
                        const file = fileInput.files[0];

                        if (!file) {
                            showMessage('Por favor, selecione um arquivo.', 'danger');
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function (event) {
                            try {
                                const data = event.target.result;
                                const workbook = XLSX.read(data, { type: 'binary' });
                                const sheetName = workbook.SheetNames[0];
                                const worksheet = workbook.Sheets[sheetName];

                                // Converter para JSON (incluindo cabeçalhos)
                                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                                // Remover a primeira linha (cabeçalhos)
                                jsonData.shift();

                                // Corrigir e limpar os dados
                                const correctedData = jsonData.map(row => {
                                    const newRow = [...row];

                                    // Remover apóstrofos e espaços extras
                                    newRow.forEach((cell, index) => {
                                        if (typeof cell === 'string') {
                                            // Remove os apóstrofos e espaços extras
                                            newRow[index] = cell.replace(/['\s]+/g, '').trim();
                                        }
                                    });

                                    // Corrigir a coluna de Data (índice 3)
                                    if (typeof newRow[3] === 'string' && newRow[3].match(/\d{1,2}\/\d{1,2}\/\d{4}/)) {
                                        const [day, month, year] = newRow[3].split('/');
                                        newRow[3] = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                    }

                                    // Corrigir a coluna de Hora (índice 4)
                                    if (typeof newRow[4] === 'string' && newRow[4].match(/\d{2}:\d{2}:\d{2}/)) {
                                        newRow[4] = newRow[4]; // Hora já está formatada como hh:mm:ss
                                    }

                                    // Corrigir a coluna de ValMed/PosTotContador (índice 5) e Difer.posição numer. (índice 6)
                                    [5, 6].forEach(index => {
                                        if (typeof newRow[index] === 'string') {
                                            newRow[index] = newRow[index].replace(',', '.'); // Substituir vírgula por ponto
                                        }
                                    });

                                    // Corrigir a coluna de Doc.medição (índice 7)
                                    if (typeof newRow[7] === 'string') {
                                        newRow[7] = newRow[7].replace(',', '.'); // Substituir vírgula por ponto
                                    }

                                    return newRow;
                                });

                                // Filtrar linhas que têm pelo menos uma célula preenchida
                                const filteredData = correctedData.filter(row => {
                                    return row.some(cell => cell !== null && cell !== undefined && cell.toString().trim() !== '');
                                });

                                // Converter para CSV
                                const csv = filteredData.map(row => {
                                    return row.map(cell => {
                                        if (typeof cell === 'string') {
                                            // Remover aspas simples e garantir formato correto para valores decimais
                                            let cleaned = cell.replace(/'/g, '').trim();
                                            return cleaned;
                                        }
                                        return cell;
                                    }).join(',');
                                }).join('\n');

                                const formData = new FormData();
                                formData.append('csv_data', csv);

                                fetch(window.location.href, {
                                    method: 'POST',
                                    body: formData
                                })
                                    .then(response => {
                                        if (!response.ok) throw new Error('Erro na rede');
                                        return response.json();
                                    })
                                    .then(data => {
                                        if (data.success) {
                                            showMessage(data.message, 'success');
                                        } else {
                                            showMessage(data.message, 'danger');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Erro:', error);
                                        showMessage('Erro ao processar o arquivo: ' + error.message, 'danger');
                                    });

                            } catch (error) {
                                console.error('Erro na conversão:', error);
                                showMessage('Erro ao converter o arquivo: ' + error.message, 'danger');
                            }
                        };

                        reader.onerror = function () {
                            showMessage('Erro ao ler o arquivo.', 'danger');
                        };

                        reader.readAsBinaryString(file);
                    });
                } else {
                    console.error('Elemento #upload-form não encontrado');
                }
            });

            function showMessage(message, type) {
                const messageDiv = document.getElementById('message');
                if (messageDiv) {
                    messageDiv.textContent = message;
                    messageDiv.className = 'alert alert-' + type;
                    messageDiv.style.display = 'block';
                }
            }

        </script>

        <div class="container mt-4">
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card" style="padding: 3rem;">
                        <h3 class="card-title">Atualizar planilha IK17</h3>
                        <p>Última atualização: <?php echo $ultimaAtualizacao; ?></p>


                        <!-- Formulário de upload de arquivo -->
                        <form id="upload-form" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="file-input">Selecione o arquivo Excel</label>
                                <input type="file" id="file-input" class="form-control" accept=".xlsx, .xls" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Atualizar
                            </button>
                        </form>

                        <!-- Mensagem de sucesso ou erro -->
                        <div id="message" class="alert" style="display: none;"></div>
                    </div>
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h5>Instruções:</h5>
                            <ul>
                                <li>O arquivo deve se chamar <strong>IK17</strong></li>
                                <li>Deve conter exatamente estas colunas nesta ordem:</li>
                                <ol>
                                    <li>Ponto medição</li>
                                    <li>Local de instalação</li>
                                    <li>Denominação do ponto medição</li>
                                    <li>Data</li>
                                    <li>Hora medição</li>
                                    <li>ValMed/PosTotContador</li>
                                    <li>Difer.posição numer.</li>
                                    <li>Doc.medição</li>
                                </ol>
                                <li>Não altere os nomes das colunas</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <style>
        .col-md-8 {
            display: flex;
            flex-direction: column;
        }

        .container {
            display: flex;
            flex-direction: column;
        }

        .card {
            border: none;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            transition: box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }

        .tolerancia-form {
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #333333;
            text-align: center;
        }

        .card p {
            font-size: 1rem;
            color: #666666;
            text-align: center;
            margin-bottom: 2rem;
        }

        #upload-form .form-group {
            margin-bottom: 1.5rem;
        }

        #upload-form label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            color: #444444;
        }

        #file-input {
            padding: 0.75rem 1rem;
            border: 2px solid #ccc;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        #file-input:hover {
            border-color: #888;
        }

        #file-input:focus {
            border-color: #007BFF;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        #upload-form button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #007BFF, #0056b3);
            color: #ffffff;
            font-weight: bold;
            font-size: 1.1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }

        #upload-form button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        #upload-form button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        /* Mensagem */
        #message {
            display: none;
            margin-top: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        /* Alerta de instruções */
        .alert-info {
            background: linear-gradient(135deg, #e0f7fa, #b2ebf2);
            color: #006064;
            border: 2px solid #81d4fa;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0, 96, 100, 0.1);
            margin-top: 2rem;
        }

        .alert-info h5 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .alert-info ul,
        .alert-info ol {
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .alert-info li {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .alert-info strong {
            color: #004d40;
        }

        #tolerancia-form {
            margin-top: 2rem;
        }

        #tolerancia-form label {
            font-weight: 600;
            color: #444444;
        }

        #tolerancia-form input,
        #tolerancia-form select {
            border: 2px solid #ccc;
            border-radius: 0.75rem;
            padding: 0.75rem;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        #tolerancia-form input:focus,
        #tolerancia-form select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
            outline: none;
        }

        #tolerancia-form button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 0.75rem;
            border: none;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            transition: background 0.3s, transform 0.2s;
            margin-top: 1.5rem;
        }

        #tolerancia-form button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .form-row {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 3rem;
        }

        .form-group label {
            width: 100px;
        }

        .form-group select,
        .form-group input {
            width: 200px;
        }

        .row {
            display: flex;
            flex-direction: column
        }
    </style>
</main>
<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>