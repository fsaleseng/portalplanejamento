<?php include '../templates/header-simulador.php'; ?>

<main>
    <?php include '../templates/header-simuladores.php'; ?>
    <section class="simulacoes">
        <h1>Simular o planejamento estratégico</h1>
        <!-- Deixe a tabela escondida -->
        <div style="display:none;">
            <?php include 'simulador-cenario.php'; ?>
        </div>

        <!-- Inputs -->
        <div class="form-simulador grid-form">
            <div class="form-group">
                <label for="input-orcamento">Revisões por ano:</label>
                <input type="number" id="input-orcamento" value="4" min="1" class="form-control">
            </div>
            <div class="form-group">
                <label for="input-valor-revisao">Valor de cada Revisão (R$):</label>
                <input type="number" id="input-valor-revisao" value="3000000" min="1" class="form-control">
            </div>
            <div class="form-actions">
                <button id="btn-calcular-estrategia" class="btn-primary">Simular Estratégia</button>
            </div>
        </div>

        <!-- Tabs para as categorias -->
        <div class="tabs" style="margin-top:30px; display:none;">
            <button class="tablink active" onclick="openTab(event, 'tab-nominal')">Nominal</button>
            <button class="tablink" onclick="openTab(event, 'tab-tolerancia1')">Tolerância 1</button>
            <button class="tablink" onclick="openTab(event, 'tab-tolerancia2')">Tolerância 2</button>
            <button class="tablink" onclick="openTab(event, 'tab-tolerancia3')">Tolerância 3</button>
            <button class="tablink" onclick="openTab(event, 'tab-tolerancia4')">Tolerância 4</button>
        </div>

        <!-- Resultados -->
        <div id="resultado-distribuicao" style="margin-top:20px;"></div>
</main>

<script>
// Função para abrir aba
function openTab(evt, tabName) {
    const tabcontent = document.getElementsByClassName("tabcontent");
    const tablinks = document.getElementsByClassName("tablink");

    for (let i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    for (let i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }

    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.classList.add("active");
}

// Função principal
document.getElementById('btn-calcular-estrategia').addEventListener('click', function() {
    const qtdRevisoesAno = parseInt(document.getElementById('input-orcamento').value);
    const valorRevisao = parseInt(document.getElementById('input-valor-revisao').value);

    const tabela = document.getElementById('tabela-cenario').querySelector('tbody');
    const linhas = tabela.querySelectorAll('tr');

    const revisoes = {
        nominal: [],
        tolerancia1: [],
        tolerancia2: [],
        tolerancia3: [],
        tolerancia4: []
    };

    linhas.forEach(linha => {
        const colunas = linha.querySelectorAll('td');
        const nomeTrem = colunas[0].textContent.trim();
        const mediaKmDia = parseFloat(colunas[1].textContent.replace('.', '').replace(',', '.')) || 0;
        const hodometroAtual = parseFloat(colunas[2].textContent.replace('.', '').replace(',', '.')) || 0;
        const tipos = ['nominal', 'tolerancia1', 'tolerancia2', 'tolerancia3', 'tolerancia4'];

        tipos.forEach((tipo, index) => {
            let dataStr = colunas[3 + index].textContent.trim();
dataStr = dataStr.replace(/\s*\(.*?\)\s*/g, ''); // remove tudo entre parênteses

            if (dataStr && dataStr !== 'N/A') {
                let [dia, mes, ano] = dataStr.split('/');
                const dataRevisao = new Date(`${ano}-${mes}-${dia}`);
                const hoje = new Date();
                const diasRestantes = Math.ceil((dataRevisao - hoje) / (1000 * 60 * 60 * 24));
                const kmRestantes = (diasRestantes * mediaKmDia).toFixed(0);

                revisoes[tipo].push({
                    nome: nomeTrem,
                    data: dataRevisao,
                    dataStr: dataStr,
                    kmRestantes: kmRestantes
                });
            }
        });
    });

    gerarDistribuicao(revisoes, qtdRevisoesAno, valorRevisao);
});

function gerarDistribuicao(revisoes, qtdRevisoesAno, valorRevisao) {
    const container = document.getElementById('resultado-distribuicao');
    container.innerHTML = '';
    document.querySelector('.tabs').style.display = 'block';

    for (const tipo in revisoes) {
        let lista = [...revisoes[tipo]];
        lista.sort((a, b) => a.data - b.data);

        const div = document.createElement('div');
        div.id = 'tab-' + tipo;
        div.className = 'tabcontent';
        div.style.display = tipo === 'nominal' ? 'block' : 'none';

        const anosDistribuidos = {};
        let anoAtual = 2025;
        let index = 0;

        while (index < lista.length) {
            if (!anosDistribuidos[anoAtual]) anosDistribuidos[anoAtual] = [];
            for (let i = 0; i < qtdRevisoesAno && index < lista.length; i++) {
                anosDistribuidos[anoAtual].push(lista[index]);
                index++;
            }
            anoAtual++;
        }

        const resumo = montarResumo(anosDistribuidos, qtdRevisoesAno, valorRevisao);

        const anos = Object.keys(anosDistribuidos);
        anos.forEach(ano => {
            const anoDiv = document.createElement('div');
            const tituloAno = document.createElement('h3');
            tituloAno.textContent = ano;
            anoDiv.appendChild(tituloAno);

            const ul = document.createElement('ul');
            anosDistribuidos[ano].forEach(trem => {
                const li = document.createElement('li');
                li.textContent = `${trem.nome} - ${trem.dataStr} (${trem.kmRestantes} km restantes)`;
                ul.appendChild(li);
            });
            anoDiv.appendChild(ul);
            div.appendChild(anoDiv);
        });

        container.appendChild(div);

        dashboardInteligente(div, resumo, tipo);
    }
}

function montarResumo(anosDistribuidos, quantidadePlanejadaPorAno, custoPlanejadoPorRevisao) {
    const resumo = [];

    for (const ano in anosDistribuidos) {
        const planejado = quantidadePlanejadaPorAno; // Planejado = input informado
        const realizado = anosDistribuidos[ano].length;
        const custoPlanejado = planejado * custoPlanejadoPorRevisao;
        const custoRealizado = realizado * custoPlanejadoPorRevisao;
        const variacao = ((custoRealizado - custoPlanejado) / custoPlanejado) * 100;
        const status = Math.abs(variacao) >= 5 ? '⚠️' : '✅';

        resumo.push({
            ano: parseInt(ano),
            planejado: planejado,
            realizado: realizado,
            custoPlanejado: custoPlanejado,
            custoRealizado: custoRealizado,
            variacao: variacao,
            status: status
        });
    }

    return resumo.sort((a, b) => a.ano - b.ano);
}


function dashboardInteligente(revisoes, resumo, nomeTolerancia) {
    const dashboard = document.createElement('div');
    dashboard.className = 'dashboard-inteligente';
    dashboard.style.marginTop = '40px';

    const titulo = document.createElement('h2');
    titulo.innerHTML = `Custo RG Acumulado - ${nomeTolerancia.toUpperCase()}`;
    dashboard.appendChild(titulo);

    
    // 2. Gerar Gráficos
    const chartsContainer = document.createElement('div');
    chartsContainer.className = 'charts-container';
    dashboard.appendChild(chartsContainer);

    gerarGraficoLinha(chartsContainer, resumo, nomeTolerancia);

    // 4. Inserir no tab atual
    const abaAtual = document.getElementById('tab-' + nomeTolerancia.toLowerCase());
    abaAtual.appendChild(dashboard);
}

function gerarGraficoLinha(container, resumo, nomeTolerancia) {
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: resumo.map(r => r.ano),
            datasets: [{
                label: 'Custo Acumulado (R$)',
                data: calcularCustoAcumulado(resumo),
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.3,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: `Evolução do Custo Acumulado de Revisões (${nomeTolerancia})`
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
}

function calcularCustoAcumulado(resumo) {
    let acumulado = 0;
    return resumo.map(r => {
        acumulado += r.custoRealizado;
        return acumulado;
    });
}


</script>

<style>
.tablink {
    background-color: #eee;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    margin-right: 5px;
}
.tablink.active {
    background-color: #ccc;
}
.tabcontent {
    border-top: 2px solid #ccc;
    padding: 20px 0;
}
</style>

<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>