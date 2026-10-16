</div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- Tom Select JS (must load before inline script) -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

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
    if (typeof TomSelect !== 'undefined') {
        document.querySelectorAll('select').forEach((el)=>{
            if (el.tomselect || el.classList.contains('no-tomselect')) return;
            new TomSelect(el, {
                create: false,
                maxOptions: null,
                closeAfterSelect: true,
                onChange: function() {
                    // Immediately blur so focus isn't trapped and submit buttons don't require two clicks
                    this.blur();
                },
                onDropdownClose: function() {
                    this.blur();
                },
                render: {
                    no_results: function(data, escape) {
                        return '<div class="no-results">No results found for "'+escape(data.input)+'"</div>';
                    }
                }
            });
        });
    }

    // Ensure submit buttons (like 'Generate Report') always submit on the very first click
    document.addEventListener('pointerdown', function(e) {
        const btn = e.target.closest('button[type="submit"], input[type="submit"]');
        if (!btn) return;
        
        // Immediately close and blur any active TomSelect instances before mouseup/click
        document.querySelectorAll('select').forEach(function(sel) {
            if (sel.tomselect) {
                if (sel.tomselect.isOpen) sel.tomselect.close();
                if (sel.tomselect.isFocused) sel.tomselect.blur();
            }
        });
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }
    }, true);

    // Failsafe: if a browser cancels the click event due to blur layout change, ensure submission occurs
    document.addEventListener('pointerup', function(e) {
        const btn = e.target.closest('button[type="submit"]');
        if (!btn || !btn.form) return;
        
        const form = btn.form;
        let clickFired = false;
        const onClick = function() {
            clickFired = true;
            btn.removeEventListener('click', onClick);
        };
        btn.addEventListener('click', onClick);
        
        setTimeout(function() {
            btn.removeEventListener('click', onClick);
            if (!clickFired) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(btn);
                } else {
                    form.submit();
                }
            }
        }, 60);
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

    // Instant Floating Tooltip on Hover
    (function() {
        let tooltip = document.getElementById('app-floating-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'app-floating-tooltip';
            document.body.appendChild(tooltip);
        }
        tooltip.style.pointerEvents = 'none';
        
        let currentTarget = null;
        
        function updatePosition(e) {
            const offset = 14;
            const w = tooltip.offsetWidth || 180;
            const h = tooltip.offsetHeight || 30;
            
            let x = e.clientX + offset;
            let y = e.clientY - h - 10;
            
            if (y < 8) {
                y = e.clientY + offset;
            }
            if (x + w > window.innerWidth - 12) {
                x = e.clientX - w - 12;
            }
            if (x < 10) x = 10;
            
            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
        }

        document.addEventListener('mouseover', function(e) {
            const target = e.target.closest('[data-tooltip], .cat-badge, .col-cat');
            if (!target) return;
            
            const text = target.getAttribute('data-tooltip') || target.getAttribute('title');
            if (!text || !text.trim() || text.trim() === '—') return;
            
            currentTarget = target;
            tooltip.textContent = text;
            tooltip.classList.add('show');
            updatePosition(e);
        }, true);
        
        document.addEventListener('mousemove', function(e) {
            if (currentTarget) {
                updatePosition(e);
            }
        }, true);

        document.addEventListener('mouseout', function(e) {
            if (!currentTarget) return;
            const related = e.relatedTarget;
            if (related && currentTarget.contains(related)) return;

            tooltip.classList.remove('show');
            currentTarget = null;
        }, true);
    })();
});
</script>
</body>
</html>
