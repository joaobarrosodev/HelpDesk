/**
 * Função para ordenar tabela HTML
 * @param {number} n - O número da coluna a ser ordenada (baseado em zero)
 * @param {string} tableId - ID da tabela a ser ordenada
 */
function sortTable(n, tableId = 'account-table') {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById(tableId);
  switching = true;
  // Define a direção da ordenação como ascendente
  dir = "asc";
  
  // Adiciona ou remove setas de ordenação nos títulos das colunas
  var headers = table.getElementsByTagName("TH");
  for (i = 0; i < headers.length; i++) {
    // Remove a classe de ordenação de todas as colunas
    headers[i].classList.remove("asc", "desc");
  }

  /* Loop até que não haja mais trocas a serem feitas */
  while (switching) {
    switching = false;
    rows = table.rows;
    
    /* Loop através de todas as linhas da tabela (exceto a primeira, que contém os cabeçalhos) */
    for (i = 1; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      /* Obtém os dois elementos que você quer comparar,
      um da linha atual e outro da próxima: */
      x = rows[i].getElementsByTagName("TD")[n];
      y = rows[i + 1].getElementsByTagName("TD")[n];
      
      /* Verifica se as duas linhas devem trocar de lugar,
      baseado na direção, ascendente ou descendente: */
      if (dir == "asc") {
        if (compareValues(x.innerHTML.toLowerCase(), y.innerHTML.toLowerCase(), n) > 0) {
          // Se sim, marca como uma troca e quebra o loop:
          shouldSwitch = true;
          break;
        }
      } else if (dir == "desc") {
        if (compareValues(x.innerHTML.toLowerCase(), y.innerHTML.toLowerCase(), n) < 0) {
          // Se sim, marca como uma troca e quebra o loop:
          shouldSwitch = true;
          break;
        }
      }
    }
    
    if (shouldSwitch) {
      /* Se uma troca foi marcada, faz a troca e marca que uma troca foi feita: */
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      // Cada vez que uma troca é feita, incrementa esse contador:
      switchcount++;
    } else {
      /* Se nenhuma troca foi feita E a direção é "asc",
      muda a direção para "desc" e executa o loop novamente. */
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
  
  // Adiciona a classe de ordenação na coluna atual
  if (dir === "asc") {
    headers[n].classList.add("desc");
  } else {
    headers[n].classList.add("asc");
  }
}

/**
 * Função para comparar valores considerando o tipo de conteúdo
 */
function compareValues(a, b, columnIndex) {
  // Verifica se é uma coluna de data (coluna 1 - Criação ou coluna 6 - Vencimento)
  if (columnIndex === 1 || columnIndex === 6) {
    // Converte data do formato DD/MM/YYYY para objeto Date
    return convertDateToComparable(a) - convertDateToComparable(b);
  } 
  // Verifica se é uma coluna numérica (colunas 2, 3, 4, 5 - valores monetários)
  else if (columnIndex >= 2 && columnIndex <= 5) {
    return convertCurrencyToNumber(a) - convertCurrencyToNumber(b);
  }
  // Para outras colunas, compara como texto
  else {
    if (a < b) return -1;
    if (a > b) return 1;
    return 0;
  }
}

/**
 * Converte uma string de data no formato DD/MM/YYYY para um valor comparável
 */
function convertDateToComparable(dateStr) {
  // Extrai a data do formato DD/MM/YYYY
  const parts = dateStr.trim().split('/');
  if (parts.length !== 3) return 0;
  
  // Cria um objeto Date (mês é baseado em zero no JavaScript)
  return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
}

/**
 * Converte uma string de moeda (1.234,56) para número
 */
function convertCurrencyToNumber(currencyStr) {
  // Remove qualquer caractere que não seja dígito, ponto ou vírgula
  currencyStr = currencyStr.replace(/[^\d,.-]/g, '');
  
  // Substitui vírgula por ponto para parsing correto
  currencyStr = currencyStr.replace(/\./g, '').replace(/,/g, '.');
  
  // Converte para número
  const value = parseFloat(currencyStr);
  return isNaN(value) ? 0 : value;
}

/**
 * Função para filtrar a tabela por intervalo de datas
 */
function filterTableByDateRange() {
  const startDate = document.getElementById('start-date').value;
  const endDate = document.getElementById('end-date').value;
  
  if (!startDate || !endDate) {
    alert('Por favor, selecione as datas de início e fim.');
    return;
  }
  
  const table = document.getElementById('account-table');
  const rows = table.getElementsByTagName('tr');
  
  // Converte as datas de filtro para objetos Date
  const startDateObj = new Date(startDate);
  const endDateObj = new Date(endDate);
  
  // Atualiza o texto do intervalo de datas
  const dateRangeText = formatDateRange(startDateObj, endDateObj);
  document.getElementById('date-range-text').textContent = dateRangeText;
  
  // Itera pelas linhas da tabela (começando em 1 para saltar o cabeçalho)
  for (let i = 1; i < rows.length; i++) {
    const dateCell = rows[i].getElementsByTagName('td')[1]; // Coluna de data de criação
    if (dateCell) {
      const dateParts = dateCell.textContent.trim().split('/');
      if (dateParts.length === 3) {
        // Cria um objeto Date a partir da string de data (formato DD/MM/YYYY)
        const rowDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
        
        // Verifica se a data está dentro do intervalo
        if (rowDate >= startDateObj && rowDate <= endDateObj) {
          rows[i].style.display = '';
        } else {
          rows[i].style.display = 'none';
        }
      }
    }
  }
}

/**
 * Formata intervalo de datas para exibição
 */
function formatDateRange(startDate, endDate) {
  const options = { year: 'numeric', month: 'short', day: '2-digit' };
  return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;
}

/**
 * Define o intervalo de datas padrão (da primeira fatura até hoje)
 */
function setDefaultDateRange() {
  // Função modificada para não definir datas padrão
  // Mantemos a função, mas removemos o código que define valores padrão
  return;
}

// Inicializa a data padrão quando a página carregar
/**
 * Alterna a visibilidade do popup de filtro
 */
/**
 * Mostra ou esconde o popup de filtro
 */
function toggleFilterPopup() {
  const popup = document.getElementById('filterPopup');
  const button = document.getElementById('filterBtn');
  
  if (popup.style.display === 'none') {
    // Posicionar o popup corretamente abaixo do botão
    const buttonRect = button.getBoundingClientRect();
    popup.style.top = (buttonRect.bottom + window.scrollY) + 'px';
    popup.style.left = (buttonRect.left + window.scrollX) + 'px';
    popup.style.display = 'block';
  } else {
    popup.style.display = 'none';
  }
}

// Inicializa tudo quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
  // Remover chamada para setDefaultDateRange() para evitar preencher datas automaticamente
  
  // Adiciona evento de clique fora do popup para fechá-lo
  document.addEventListener('click', function(event) {
    const filterBtn = document.getElementById('filterBtn');
    const filterPopup = document.getElementById('filterPopup');
    
    // Se o clique não foi no botão de filtro e nem no popup, fecha o popup
    if (filterBtn && filterPopup && event.target !== filterBtn && !filterBtn.contains(event.target) 
        && event.target !== filterPopup && !filterPopup.contains(event.target)) {
      filterPopup.style.display = 'none';
    }
  });
});
