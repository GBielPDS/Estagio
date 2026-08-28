document.addEventListener('DOMContentLoaded', () => {
    const pesquisa = document.getElementById('pesquisa-produto');
    const filtroCategoria = document.getElementById('filtro-categoria');
    const filtroUnidade = document.getElementById('filtro-unidade');
    const limparFiltros = document.getElementById('limpar-filtros');
    const mensagemVazia = document.getElementById('nenhum-produto');
    const linhas = [...document.querySelectorAll('#produtos-tbody tr')];

    function normalizar(texto) {
        return texto
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
    }

    function aplicarFiltros() {
        const termo = normalizar(pesquisa.value);
        const categoria = normalizar(filtroCategoria.value);
        const unidade = normalizar(filtroUnidade.value);
        let produtosVisiveis = 0;

        linhas.forEach((linha) => {
            const nome = normalizar(linha.dataset.nome || '');
            const categoriaProduto = normalizar(linha.dataset.categoria || '');
            const unidadeProduto = normalizar(linha.dataset.unidade || '');
            const corresponde = nome.includes(termo)
                && (categoria === '' || categoriaProduto === categoria)
                && (unidade === '' || unidadeProduto === unidade);

            linha.hidden = !corresponde;

            if (corresponde) {
                produtosVisiveis += 1;
            }
        });

        mensagemVazia.hidden = produtosVisiveis > 0;
    }

    pesquisa.addEventListener('input', aplicarFiltros);
    filtroCategoria.addEventListener('change', aplicarFiltros);
    filtroUnidade.addEventListener('change', aplicarFiltros);

    limparFiltros.addEventListener('click', () => {
        pesquisa.value = '';
        filtroCategoria.value = '';
        filtroUnidade.value = '';
        aplicarFiltros();
    });

    aplicarFiltros();
});
