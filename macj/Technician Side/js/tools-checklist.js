/**
 * Tools and Equipment Checklist JavaScript
 */

class ToolsChecklist {
    constructor() {
        this.modal = null;
        this.tools = {};
        this.totalTools = 0;
        this.checkedTools = 0;
        this.categoryIcons = {
            'General Pest Control': 'fa-spray-can',
            'Termite': 'fa-bug',
            'Termite Treatment': 'fa-house-damage',
            'Weed Control': 'fa-seedling',
            'Bed Bugs': 'fa-bed'
        };
        this.isInitialized = false;
    }

    /**
     * Initialize the checklist
     */
    async init() {
        console.log('Initializing tools checklist...');

        if (this.isInitialized) {
            console.log('Checklist already initialized, skipping');
            return;
        }

        try {
            // Fetch tools and equipment data
            const url = '../get_tools_equipment.php';
            console.log('Fetching tools and equipment from:', url);

            const response = await fetch(url);
            console.log('Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Tools and equipment data received:', data);

            if (!data.success) {
                console.error('Error fetching tools and equipment:', data.error);
                return;
            }

            this.tools = data.data;
            console.log('Tools categories:', Object.keys(this.tools));

            // Count total tools
            this.totalTools = Object.values(this.tools).reduce((total, tools) => total + tools.length, 0);
            console.log('Total tools count:', this.totalTools);

            // Create modal
            this.createModal();
            console.log('Modal created');

            // Show modal
            this.showModal();
            console.log('Modal shown');

            this.isInitialized = true;
            console.log('Checklist initialization complete');
        } catch (error) {
            console.error('Error initializing tools checklist:', error);

            // Show error message if SweetAlert2 is available
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load tools and equipment checklist. Please refresh the page and try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Failed to load tools and equipment checklist. Please refresh the page and try again.');
            }
        }
    }

    /**
     * Create the checklist modal
     */
    createModal() {
        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'tools-checklist-modal';
        this.modal.innerHTML = `
            <div class="tools-checklist-container">
                <div class="tools-checklist-header">
                    <h2><i class="fas fa-tools"></i> Tools & Equipment Checklist</h2>
                </div>
                <div class="tools-checklist-body">
                    <div class="checklist-progress">
                        <div class="checklist-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="checklist-progress-text">
                        <span class="checked-count">0</span>/<span class="total-count">${this.totalTools}</span> items checked
                    </div>
                    <div class="tools-categories">
                        ${this.renderCategories()}
                    </div>
                </div>
                <div class="tools-checklist-footer">
                    <button type="button" class="btn-skip">Skip for Now</button>
                    <button type="button" class="btn-confirm" disabled>Confirm & Continue</button>
                </div>
            </div>
        `;

        // Add event listeners
        const skipButton = this.modal.querySelector('.btn-skip');
        const confirmButton = this.modal.querySelector('.btn-confirm');

        if (skipButton) {
            skipButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.hideModal();
            });
        } else {
            console.error('Skip button not found in modal');
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.confirmChecklist();
            });
        } else {
            console.error('Confirm button not found in modal');
        }

        // Add category toggle event listeners
        this.modal.querySelectorAll('.tools-category-header').forEach(header => {
            header.addEventListener('click', () => {
                header.classList.toggle('collapsed');
            });
        });

        // Add checkbox event listeners
        this.modal.querySelectorAll('.tool-checkbox input').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateProgress());
        });

        // Add to document
        document.body.appendChild(this.modal);
    }

    /**
     * Render categories and tools
     */
    renderCategories() {
        let html = '';

        for (const [category, tools] of Object.entries(this.tools)) {
            const icon = this.categoryIcons[category] || 'fa-tools';
            const categoryClass = category.toLowerCase().replace(/\s+/g, '-');

            html += `
                <div class="tools-category">
                    <div class="tools-category-header">
                        <div class="category-name">
                            <i class="fas ${icon}"></i>
                            ${category}
                            <span class="category-badge category-${categoryClass}">${tools.length}</span>
                        </div>
                        <i class="fas fa-chevron-down category-toggle"></i>
                    </div>
                    <div class="tools-category-body">
                        <ul class="tools-list">
                            ${tools.map(tool => this.renderTool(tool)).join('')}
                        </ul>
                    </div>
                </div>
            `;
        }

        return html;
    }

    /**
     * Render a single tool
     */
    renderTool(tool) {
        return `
            <li class="tool-item">
                <div class="tool-checkbox">
                    <input type="checkbox" id="tool-${tool.id}" data-tool-id="${tool.id}">
                </div>
                <div class="tool-info">
                    <div class="tool-name">${tool.name}</div>
                    ${tool.description ? `<div class="tool-description">${tool.description}</div>` : ''}
                </div>
            </li>
        `;
    }

    /**
     * Update progress bar and count
     */
    updateProgress() {
        const checkboxes = this.modal.querySelectorAll('.tool-checkbox input');
        this.checkedTools = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;

        const progressBar = this.modal.querySelector('.checklist-progress-bar');
        const progressText = this.modal.querySelector('.checked-count');
        const confirmButton = this.modal.querySelector('.btn-confirm');

        const progress = (this.checkedTools / this.totalTools) * 100;

        progressBar.style.width = `${progress}%`;
        progressText.textContent = this.checkedTools;

        // Enable confirm button if at least one tool is checked
        confirmButton.disabled = this.checkedTools === 0;
    }

    /**
     * Show the modal
     */
    showModal() {
        setTimeout(() => {
            this.modal.classList.add('show');
        }, 100);
    }

    /**
     * Hide the modal
     */
    hideModal() {
        this.modal.classList.remove('show');

        // Remove modal after animation
        setTimeout(() => {
            if (this.modal && this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }

            // Set session variable to indicate checklist has been shown
            this.setChecklistShown();
        }, 300);
    }

    /**
     * Confirm checklist and hide modal
     */
    async confirmChecklist() {
        console.log('Confirm checklist button clicked');

        try {
            // Disable the confirm button to prevent multiple clicks
            const confirmButton = this.modal.querySelector('.btn-confirm');
            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }

            // Get checked tools
            const checkedTools = Array.from(this.modal.querySelectorAll('.tool-checkbox input:checked')).map(checkbox => {
                const toolId = checkbox.dataset.toolId;
                const toolName = this.modal.querySelector(`#tool-${toolId}`).closest('.tool-item').querySelector('.tool-name').textContent;

                return { id: toolId, name: toolName };
            });

            console.log('Checked tools:', checkedTools);

            // Save checklist confirmation to database (await the result)
            try {
                await this.saveChecklistConfirmation(checkedTools);
                console.log('Checklist confirmation saved successfully');
            } catch (saveError) {
                console.error('Error saving checklist confirmation:', saveError);
                // Continue with the flow even if saving fails
            }

            // Check if SweetAlert2 is available
            if (typeof Swal === 'undefined') {
                console.error('SweetAlert2 is not defined. Using regular alert instead.');
                alert(`Checklist Confirmed! You've checked ${this.checkedTools} out of ${this.totalTools} tools and equipment items.`);
            } else {
                // Show success message with SweetAlert2
                Swal.fire({
                    title: 'Checklist Confirmed!',
                    text: `You've checked ${this.checkedTools} out of ${this.totalTools} tools and equipment items.`,
                    icon: 'success',
                    confirmButtonText: 'Continue'
                });
            }

            // Hide modal
            this.hideModal();

            // Set session variable to indicate checklist has been shown
            this.setChecklistShown();
        } catch (error) {
            console.error('Error in confirmChecklist:', error);

            // Re-enable the confirm button if there's an error
            const confirmButton = this.modal.querySelector('.btn-confirm');
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.innerHTML = 'Confirm & Continue';
            }

            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while confirming the checklist. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('An error occurred while confirming the checklist. Please try again.');
            }
        }
    }

    /**
     * Save checklist confirmation to database
     */
    saveChecklistConfirmation(checkedTools) {
        console.log('Saving checklist confirmation...');

        const data = {
            checked_items: checkedTools,
            total_items: this.totalTools,
            checked_count: this.checkedTools
        };

        console.log('Checklist data:', data);

        // Use the correct path to the PHP file
        const url = '../save_checklist_confirmation.php';
        console.log('Sending request to:', url);

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            console.log('Checklist confirmation result:', result);
            if (!result.success) {
                console.error('Error saving checklist confirmation:', result.error);
                throw new Error(result.error || 'Failed to save checklist confirmation');
            }
            return result;
        })
        .catch(error => {
            console.error('Error saving checklist confirmation:', error);
            // Don't throw the error here to prevent the confirmation flow from breaking
            // Just log it and continue
        });
    }

    /**
     * Set session variable to indicate checklist has been shown
     */
    async setChecklistShown() {
        console.log('Setting checklist as shown...');

        try {
            const url = '../set_checklist_shown.php';
            console.log('Sending request to:', url);

            const response = await fetch(url, {
                method: 'POST'
            });

            console.log('Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Set checklist shown result:', result);

            if (!result.success) {
                console.error('Error setting checklist shown:', result.error);
            }
        } catch (error) {
            console.error('Error setting checklist shown:', error);
            // Don't throw the error here to prevent the confirmation flow from breaking
        }
    }
}

// Initialize checklist when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking if checklist should be shown...');

    // Check if checklist should be shown
    fetch('../check_checklist_shown.php')
        .then(response => {
            console.log('Check checklist shown response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Check checklist shown data:', data);

            if (!data.shown) {
                console.log('Checklist not shown yet, initializing...');
                const checklist = new ToolsChecklist();
                checklist.init();
            } else {
                console.log('Checklist already shown today, skipping');
            }
        })
        .catch(error => {
            console.error('Error checking if checklist has been shown:', error);

            // Try to initialize the checklist anyway to prevent blocking the user
            console.log('Attempting to initialize checklist despite error...');
            try {
                const checklist = new ToolsChecklist();
                checklist.init().catch(initError => {
                    console.error('Failed to initialize checklist after error:', initError);

                    // Only show error message if both checks fail
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to check if tools checklist has been shown. Please refresh the page.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            } catch (fallbackError) {
                console.error('Error in fallback initialization:', fallbackError);

                // Show error message if SweetAlert2 is available
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to check if tools checklist has been shown. Please refresh the page.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });
});
