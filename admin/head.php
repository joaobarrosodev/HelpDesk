<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Info.eXe</title>

  <!-- Link para o arquivo CSS do Bootstrap -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Fontes do Google -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Estilos customizados -->
  <link href="css/style.css" rel="stylesheet">
  
  <!-- Font Awesome para ícones adicionais -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  
  <!-- JavaScript do Bootstrap e jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- TinyMCE para edição de texto rico -->
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
  <script>
      tinymce.init({
          selector: 'textarea',
          menubar: false,
          plugins: 'lists advlist autolink link',
          toolbar: 'undo redo | bold italic underline | bullist numlist | link',
          height: 200,
          setup: function (editor) {
              editor.on('change', function () {
                  tinymce.triggerSave(); // Garante que o valor do textarea seja atualizado
              });
          }
      });
  </script>
</head>