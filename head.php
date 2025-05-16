<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help Desk - Info-exe</title>

  <!-- Link para o arquivo CSS do Bootstrap -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Se precisar de fontes adicionais -->
 <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
 <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="css/account-extract.css" rel="stylesheet">
  <link href="css/ticket.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

      <style>
        /* Add fade-in animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 1s ease-in-out;
        }        /* Account extract styling */
        .table th {
            color: #6c757d;
            font-weight: 500;
            border-top: none;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            position: relative;
            background-color: #fff;
        }
        
        .table th.asc::after {
            content: " ▲";
            font-size: 0.8em;
        }
        
        .table th.desc::after {
            content: " ▼";
            font-size: 0.8em;
        }
        
        /* Estilo para o cabeçalho superior - banco e valores */
        .card.bg-light {
            background-color: #f8f9fa !important;
            border: none;
        }
        
        /* Estilo para o filtro */
        #filterBtn {
            border-color: #dee2e6;
            color: #6c757d;
            background-color: #fff;
        }
        
        /* Estilo para o popup de filtro */
        .filter-popup {
            border: 1px solid #dee2e6;
        }
        
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid #f1f1f1;
            padding: 0.75rem;
            font-size: 0.95rem;
        }
        
        .shadow-sm {
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
        }
        
        .card-body h3.text-primary {
            font-size: 1.75rem;
            font-weight: 600;
            color: #4285F4 !important;
        }
        
        .table-borderless tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        
        .table-borderless tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table-borderless tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Download button styling */
        .btn-light {
            background-color: #f8f9fa;
            border-color: #eee;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .btn-light:hover {
            background-color: #e2e6ea;
            border-color: #dae0e5;
            transform: translateY(-2px);
        }
        
        /* Animações sutis */
        .card {
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
                
        .account-row-animation {
            animation: fadeInRow 0.6s ease-out forwards;
            opacity: 0;
        }
        
        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Estilização para deixar mais parecido com a imagem */
        .text-danger {
            color: #dc3545 !important;
        }
        
        .container {
            max-width: 1140px;
        }
        
        .btn-light {
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }
    </style>
    
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#descricao_problema',
        menubar: false,
        plugins: 'lists advlist autolink link',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link',
        height: 300,
        setup: function (editor) {
            editor.on('change', function () {
                tinymce.triggerSave(); // Garante que o valor do textarea seja atualizado
            });
        }
    });
</script>
</head>