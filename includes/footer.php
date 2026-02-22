</main> <!-- End main-content -->
</div> <!-- End scroll-wrapper -->
</div> <!-- End wrapper -->

<script>
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
