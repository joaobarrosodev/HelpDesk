<style>
/* Sticky first column styles */
.table-wrapper {
    overflow-x: auto;
    position: relative;
}

.table {
    min-width: 900px; /* Force horizontal scroll on small screens */
}

/* Mobile sticky first column */
@media (max-width: 768px) {
    .table-wrapper {
        position: relative;
    }

    /* First column sticky */
    .table th:first-child,
    .table td:first-child {
        position: sticky;
        left: 0;
        background-color: white;
        z-index: 1;
        box-shadow: 2px 0 4px rgba(0,0,0,0.1);
    }

    .table th:first-child {
        background-color: #f8f9fa;
        z-index: 3; /* Higher z-index for header of first column */
    }

    /* Alternating row colors for sticky column */
    .table tbody tr:nth-child(even) td:first-child {
        background-color: #f9f9f9;
    }

    .table tbody tr:hover td:first-child {
        background-color: #f0f8ff;
    }
}

/* Scroll indicator */
.scroll-indicator {
    display: none;
    text-align: center;
    padding: 10px;
    color: #666;
    background-color: #fff3cd;
    border-radius: 4px;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .scroll-indicator {
        display: block;
    }
}
</style>
