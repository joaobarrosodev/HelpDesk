<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administração - Info.eXe</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Tipos de letra do Google -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Estilos personalizados -->
  <link href="css/style.css" rel="stylesheet">
  
  <!-- jQuery (necessário para alguns componentes do Bootstrap) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- TinyMCE para edição de texto enriquecido onde necessário -->
  <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
  <script>
    // Inicializa o TinyMCE apenas nas textareas com a classe 'rich-editor'
    document.addEventListener('DOMContentLoaded', function() {
      if (document.querySelector('.rich-editor')) {
        tinymce.init({
          selector: 'textarea.rich-editor',
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
      }
    });
  </script>
</head>