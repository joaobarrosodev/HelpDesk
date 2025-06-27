<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HelpDesk - InfoExe</title>

  <!-- Block TinyMCE script loading first -->
  <script type="text/javascript">
  // Mock TinyMCE with all required methods and properties
  window.tinymce = {
      init: function() { return false; },
      execCommand: function() { return false; },
      triggerSave: function() { return false; },
      get: function() { return null; },
      remove: function() { return false; },
      execCallback: function() { return false; },
      overrideDefaults: function() { return false; },
      activeEditor: null,
      editors: {},
      EditorManager: { 
          requireLangPack: function() {},
          baseURL: '',
          baseURI: { toAbsolute: function() {} }
      },
      dom: { 
          Event: { add: function() {}, remove: function() {} },
          ScriptLoader: { load: function() {}, loadQueue: function() {}, loadScripts: function() {} }
      },
      create: function() { return {}; },
      PluginManager: { add: function() {}, requireLangPack: function() {}, load: function() {} },
      ThemeManager: { load: function() {}, requireLangPack: function() {} },
      util: { Promise: function() {}, URI: function() {}, Tools: {} },
      ui: {}
  };

  // Create alias
  window.tinyMCE = window.tinymce;

  // Block script loading
  const originalCreateElement = document.createElement;
  document.createElement = function(tagName) {
      const element = originalCreateElement.call(document, tagName);
      if (tagName.toLowerCase() === 'script') {
          const originalSetAttribute = element.setAttribute;
          element.setAttribute = function(name, value) {
              if (name === 'src' && value && typeof value === 'string' && 
                  (value.indexOf('tinymce') !== -1 || value.indexOf('tiny_mce') !== -1)) {
                  console.log('Blocked TinyMCE script:', value);
                  return element;
              }
              return originalSetAttribute.call(this, name, value);
          };
      }
      return element;
  };

  // Remove any existing TinyMCE scripts
  document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('script').forEach(script => {
          if (script.src && 
              (script.src.includes('tinymce') || script.src.includes('tiny_mce'))) {
              script.parentNode.removeChild(script);
          }
      });
  });
  </script>

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

</head>