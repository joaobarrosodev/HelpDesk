/**
 * Função para filtrar a tabela por todos os critérios
 * - Intervalo de datas
 * - Tipo de documento
 * - Intervalo de valores
 * 
 * TODO: Improved to filter automatically on key press and changes
 */
function filterTableByAll() {
  const startDate = document.getElementById('start-date').value;
  const endDate = document.getElementById('end-date').value;
  const documentType = document.getElementById('document-type').value;
  const minValue = document.getElementById('min-value').value ? parseFloat(document.getElementById('min-value').value) : null;
  const maxValue = document.getElementById('max-value').value ? parseFloat(document.getElementById('max-value').value) : null;
  
  const table = document.getElementById('account-table');
  const rows = table.getElementsByTagName('tr');
  
  // Prepara datas se filtro de data estiver ativo
  let startDateObj = null;
  let endDateObj = null;
  if (startDate && endDate) {
    startDateObj = new Date(startDate);
    endDateObj = new Date(endDate);
    
    // Atualiza o texto do intervalo de datas (se existir)
    if (typeof formatDateRange === 'function') {
      const dateRangeText = formatDateRange(startDateObj, endDateObj);
    }
    document.getElementById('date-range-text').textContent = dateRangeText;
  }
  
  // Itera pelas linhas da tabela (começando em 1 para pular o cabeçalho)
  for (let i = 1; i < rows.length; i++) {
    let showRow = true; // Por padrão, mostra a linha
    
    // Filtra por data se as datas foram fornecidas
    if (startDateObj && endDateObj) {
      const dateCell = rows[i].getElementsByTagName('td')[1]; // Coluna de data de criação
      if (dateCell) {
        const dateParts = dateCell.textContent.trim().split('/');
        if (dateParts.length === 3) {
          // Cria um objeto Date a partir da string de data (formato DD/MM/YYYY)
          const rowDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
          
          // Verifica se a data está dentro do intervalo
          if (!(rowDate >= startDateObj && rowDate <= endDateObj)) {
            showRow = false;
          }
        }
      }
    }
    
    // Filtra por tipo de documento
    if (showRow && documentType !== 'all') {
      const documentCell = rows[i].getElementsByTagName('td')[0]; // Coluna de documento
      if (documentCell) {
        // Verifica se o texto contém o tipo selecionado
        const documentText = documentCell.textContent.trim();
        if (!documentText.includes(documentType)) {
          showRow = false;
        }
      }
    }
    
    // Filtra por valor
    if (showRow && (minValue !== null || maxValue !== null)) {
      const valueCell = rows[i].getElementsByTagName('td')[4]; // Coluna de valor total
      if (valueCell) {
        // Extrai o valor numérico removendo formatação
        const valueText = valueCell.textContent.trim().replace('-', '').replace('€', '').replace('.', '').replace(',', '.');
        const rowValue = parseFloat(valueText);
        
        if (!isNaN(rowValue)) {
          // Verifica se o valor está dentro do intervalo
          if (minValue !== null && rowValue < minValue) {
            showRow = false;
          }
          if (maxValue !== null && rowValue > maxValue) {
            showRow = false;
          }
        }
      }
    }
      // Aplica a visibilidade com base nos filtros
    rows[i].style.display = showRow ? '' : 'none';
    
    // Aplica um efeito de destaque às linhas filtradas
    if (showRow && (documentType !== 'all' || minValue !== null || maxValue !== null)) {
      rows[i].classList.add('filtered-row');
    } else {
      rows[i].classList.remove('filtered-row');
    }
  }
  
  // Atualiza o rótulo de filtro
  updateFilterLabel(documentType, minValue, maxValue);
}

/**
 * Atualiza o rótulo de filtro com informações sobre os filtros ativos
 */
function updateFilterLabel(documentType, minValue, maxValue) {
  let filterText = document.getElementById('date-range-text').textContent;
  
  // Adiciona informações sobre o tipo de documento
  if (documentType !== 'all') {
    const docTypeLabels = {
      'FAC': 'Faturas',
      'REC': 'Recibos',
      'NC': 'Notas de Crédito'
    };
    filterText += ` | ${docTypeLabels[documentType] || documentType}`;
  }
  
  // Adiciona informações sobre o valor
  if (minValue !== null || maxValue !== null) {
    filterText += ' | Valor: ';
    if (minValue !== null) {
      filterText += `Min: ${minValue.toFixed(2)}€`;
    }
    if (minValue !== null && maxValue !== null) {
      filterText += ' - ';
    }
    if (maxValue !== null) {
      filterText += `Max: ${maxValue.toFixed(2)}€`;
    }
  }
  // Atualiza o texto do intervalo de datas
  document.getElementById('date-range-text').textContent = filterText;
  
  // Mostra ou esconde o badge de filtros ativos
  const filterBadge = document.getElementById('filter-badge');
  const hasActiveFilters = documentType !== 'all' || minValue !== null || maxValue !== null || 
                          (startDate && endDate);
  
  filterBadge.style.display = hasActiveFilters ? 'inline-block' : 'none';
}

/**
 * Limpa todos os filtros aplicados e restaura a tabela
 */
function clearAllFilters() {
  // Limpa os campos de filtro
  document.getElementById('start-date').value = '';
  document.getElementById('end-date').value = '';
  document.getElementById('document-type').value = 'all';
  document.getElementById('min-value').value = '';
  document.getElementById('max-value').value = '';
  
  // Restaura a visualização de todas as linhas da tabela
  const table = document.getElementById('account-table');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 1; i < rows.length; i++) {
    rows[i].style.display = '';
  }
  
  // Restaura o texto do intervalo de datas
  document.getElementById('date-range-text').textContent = 'Todos os registros';
  
}

/**
 * Fecha o popup de filtro quando clicar fora dele
 */
document.addEventListener('click', function(event) {
  const popup = document.getElementById('filterPopup');
  const filterBtn = document.getElementById('filterBtn');
  
  if (popup && popup.style.display === 'block') {
    // Verifica se o clique foi fora do popup e não no botão de filtro
    if (!popup.contains(event.target) && event.target !== filterBtn && !filterBtn.contains(event.target)) {
      popup.style.display = 'none';
    }
  }
});

// Inicializa o estado dos filtros e popula os campos com valores padrão
document.addEventListener('DOMContentLoaded', function() {
  // Define as datas padrão (mês atual)
  const today = new Date();
  const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  
  // Formata as datas no formato esperado pelo input (YYYY-MM-DD)
  const formatDateForInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };
  
  // Preenche os campos de data com valores padrão
  document.getElementById('start-date').value = formatDateForInput(firstDayOfMonth);
  document.getElementById('end-date').value = formatDateForInput(today);
  
  // Atualiza o texto do intervalo de datas
  document.getElementById('date-range-text').textContent = formatDateRange(firstDayOfMonth, today);
});
