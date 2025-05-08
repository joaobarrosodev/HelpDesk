<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help Desk - Info-exe</title>

  <!-- Link para o arquivo CSS do Bootstrap -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Se precisar de fontes adicionais -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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