/**
 * auto-filter.js
 * Adds auto-filtering functionality to the account table
 * 
 * Features:
 * - Filter when Enter key is pressed
 * - Auto filter on select/date change
 * - Delayed filtering for number inputs
 */

document.addEventListener('DOMContentLoaded', function() {
    // Setup event listeners for auto-filtering
    setupAutoFilters();
});

/**
 * Setup all event listeners for auto filtering
 */
function setupAutoFilters() {
    console.log('Setting up auto-filters...');
    
    // 1. Listen for Enter key on all filter inputs
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                filterTableByAll();
            }
        });
    }
      // 2. Listen for change events on select and date inputs    // Document type filter - auto-filter on change
    const documentTypeSelect = document.getElementById('document-type');
    if (documentTypeSelect) {
        documentTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            console.log('Document type changed:', selectedType);
            
            // Debug: Log all document type data attributes
            const table = document.getElementById('account-table');
            if (table) {
                const rows = table.getElementsByTagName('tr');
                console.log(`Checking ${rows.length-1} rows for document type: ${selectedType}`);
                
                for (let i = 1; i < rows.length; i++) {
                    const docType = rows[i].getAttribute('data-doc-type');
                    console.log(`Row ${i} has doc-type: ${docType}`);
                }
            }
            
            filterTableByAll();
        });
    }
    
    // Date filters - auto-filter on change
    const dateInputs = [
        document.getElementById('start-date'),
        document.getElementById('end-date')
    ];
    
    dateInputs.forEach(input => {
        if (input) {
            input.addEventListener('change', function() {
                console.log('Date filter changed');
                filterTableByAll();
            });
        }
    });
    
    // 3. Listen for input events on value fields with a small delay
    const valueInputs = [
        document.getElementById('min-value'),
        document.getElementById('max-value')
    ];
      let valueTimeout = null;
    valueInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                clearTimeout(valueTimeout);
                valueTimeout = setTimeout(filterTableByAll, 500);
            });
            
            // Handle when field is cleared with backspace/delete
            input.addEventListener('keyup', function(event) {
                if (event.key === 'Backspace' || event.key === 'Delete') {
                    if (this.value === '') {
                        clearTimeout(valueTimeout);
                        valueTimeout = setTimeout(filterTableByAll, 500);
                    }
                }
            });
        }
    });
    
    console.log('Auto-filter event listeners setup complete');
}
