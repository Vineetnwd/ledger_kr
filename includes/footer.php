</div><!-- /main-content -->
</div><!-- /app-wrapper -->

<script>
// Modal helpers — global
function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
}
// Close on overlay click
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});
// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

// Initialize Tom Select for all dropdowns
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select').forEach((el)=>{
        new TomSelect(el, {
            create: false,
            maxOptions: null,
            render: {
                no_results: function(data, escape) {
                    return '<div class="no-results">No results found for "'+escape(data.input)+'"</div>';
                }
            }
        });
    });

    // Mobile Sidebar Toggle Logic
    const topbar = document.querySelector('.topbar');
    if (topbar) {
        // Inject toggle button
        const btn = document.createElement('button');
        btn.className = 'mobile-toggle-btn';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        
        // Insert at the beginning of topbar
        topbar.insertBefore(btn, topbar.firstChild);
        
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('mobile-overlay');
        
        btn.addEventListener('click', function() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        });
        
        const closeSidebar = function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        };

        overlay.addEventListener('click', closeSidebar);
        
        const closeBtn = document.getElementById('mobile-close-sidebar');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }
    }
});
</script>
<!-- Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</body>
</html>
