# HelpDesk

## Sobre o Projeto

Este é um sistema de HelpDesk desenvolvido exclusivamente para os clientes da InfoExe. Foi concebido para facilitar a comunicação entre os clientes e a equipa de suporte técnico, permitindo a criação, acompanhamento e gestão de tickets de suporte.

## Funcionalidades Principais

- **Criação de Tickets:** Os clientes podem criar tickets para relatar problemas ou solicitar suporte.
- **Consulta de Tickets:** Visualize tickets abertos e fechados.
- **Gestão de Utilizadores:** Login seguro para acesso ao sistema.
- **Administração:** Recursos exclusivos para administradores, como consulta de licenças e extratos de contas correntes.

## Tecnologias Utilizadas

- **Frontend:** HTML, CSS (Bootstrap), JavaScript
- **Backend:** PHP
- **Base de Dados:** MySQL

## Estrutura do Projeto

- **Páginas Principais:**
  - `index.php`: Página inicial
  - `login.php`: Página de login
  - `ticket.php`: Criação de tickets
  - `consultar_tickets_fechados.php`: Consulta de tickets fechados

- **Admin:**
  - Recursos exclusivos para administradores, como alteração de tickets e consulta de dados.

- **Assets:**
  - `css/`: Ficheiros de estilo
  - `js/`: Scripts JavaScript
  - `img/`: Imagens

- **Documentação:**
  - `docs/`: Ficheiros PDF relacionados com o sistema

## Como Usar

1. Faça login com as suas credenciais fornecidas pela InfoExe.
2. Crie um ticket para relatar um problema ou solicitação.
3. Acompanhe o estado do seu ticket na secção de "Tickets em Aberto".
4. Consulte tickets fechados para histórico de suporte.

## Requisitos do Sistema

- Servidor Web (ex.: Apache)
- PHP 8.1 ou superior
- MySQL

## Diretrizes de Desenvolvimento

1. **Estrutura do Código:**
   - Utilize indentação consistente (4 espaços).
   - Nomeie variáveis e funções de forma clara e descritiva (em inglês).

2. **Padrões de Commit:**
   - Mensagens de commit devem ser escritas em inglês e descrever claramente as alterações realizadas.
   - Exemplo: `fix: corrected SQL query in ticket.php`

3. **Segurança:**
   - Valide todas as entradas do utilizador para evitar SQL Injection e XSS.
   - Utilize prepared statements para interações com a base de dados.

4. **Estilo de Código:**
   - Siga as normas do PSR-12 para PHP.
   - Utilize comentários para explicar trechos de código complexos.

5. **Testes:**
   - Teste todas as funcionalidades antes de fazer deploy.
   - Utilize ferramentas de linting para garantir a qualidade do código.

6. **Colaboração:**
   - Utilize branches para novas funcionalidades ou correções de bugs.
   - Faça pull requests para revisão antes de integrar alterações na branch principal.

## Contato

Para dúvidas ou suporte, entre em contacto com a InfoExe através do nosso site oficial ou pelo e-mail suporte@infoexe.com.pt.
