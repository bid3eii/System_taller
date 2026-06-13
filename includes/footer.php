</main> <!-- End main-content -->
</div> <!-- End scroll-wrapper -->
</div> <!-- End wrapper -->

<script>
// Sidebar Accordion for Gerencia
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('role-gerencia-layout')) {
        // Sidebar Toggle Logic
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
            sidebarToggle.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
            });
        }

        const dropdownLinks = document.querySelectorAll('.navbar-menu .dropdown > a');
        
        dropdownLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                if (parent.classList.contains('open')) {
                    parent.classList.remove('open');
                    const content = parent.querySelector('.dropdown-content');
                    if (content) content.style.maxHeight = null;
                } else {
                    dropdownLinks.forEach(other => {
                        if (other !== link) {
                            other.parentElement.classList.remove('open');
                            const otherContent = other.parentElement.querySelector('.dropdown-content');
                            if (otherContent) otherContent.style.maxHeight = null;
                        }
                    });
                    parent.classList.add('open');
                    const content = parent.querySelector('.dropdown-content');
                    if (content) content.style.maxHeight = content.scrollHeight + 'px';
                }
            });
        });

        // User Profile Dropdown Logic (Click instead of Hover)
        const userDropdown = document.querySelector('.navbar-user');
        if (userDropdown) {
            userDropdown.addEventListener('click', function(e) {
                // If clicking an actual link inside, let it navigate normally
                if (e.target.closest('.dropdown-item')) {
                    return;
                }
                
                e.preventDefault();
                this.classList.toggle('open');
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target) && userDropdown.classList.contains('open')) {
                    userDropdown.classList.remove('open');
                }
            });
        }
    }
});

// Global Search Input with Clear Button
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('.input-group input[type="text"], .input-group input[type="search"]');

    searchInputs.forEach(input => {
        // Only target inputs inside input-group that act as search
        // We look for the loop wrapper or assume standard structure
        const wrapper = input.parentElement;
        if (!wrapper.classList.contains('input-group')) return;

        // Check if clear button already exists
        if (wrapper.querySelector('.input-clear')) return;

        // Create clear button
        const clearBtn = document.createElement('i');
        clearBtn.className = 'ph ph-x input-clear';
        wrapper.appendChild(clearBtn);

        // Function to toggle visibility
        const toggleClear = () => {
            if (input.value.length > 0) {
                wrapper.classList.add('has-text');
            } else {
                wrapper.classList.remove('has-text');
            }
        };

        // Initial check
        toggleClear();

        // Listen for input
        input.addEventListener('input', toggleClear);

        // Clear action
        clearBtn.addEventListener('click', function() {
            input.value = '';
            toggleClear();
            input.focus();
            
            // If the input belongs to a form and is a server-side search, submit form to clear filters
            if (input.form && input.name === 'search') {
                input.form.submit();
                return;
            }
            
            // Trigger input event for live search listeners
            // We dispatch both 'input' and 'change' to ensure maximum compatibility
            setTimeout(() => {
                const inputEvent = new Event('input', { bubbles: true, cancelable: true });
                const changeEvent = new Event('change', { bubbles: true, cancelable: true });
                const keyupEvent = new Event('keyup', { bubbles: true, cancelable: true });
                
                input.dispatchEvent(inputEvent);
                input.dispatchEvent(changeEvent);
                input.dispatchEvent(keyupEvent);
            }, 10);
        });
    });
});
</script>
</body>
</html>
