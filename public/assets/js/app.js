// app.js - Main frontend logic
class IECEPLSCApp {
    constructor() {
        this.apiBase = '/public/api.php';
        this.init();
    }

    // Override apiCall to use proxy
    async apiCall(endpoint, method = 'GET', data = null) {
        // Parse endpoint to extract action (e.g., "/affiliate?action=send-code")
        const url = new URL(this.apiBase, window.location.origin);
        const [path, queryString] = endpoint.split('?');
        const params = new URLSearchParams(queryString);

        // Set endpoint and action parameters for the proxy
        url.searchParams.set('endpoint', path.replace('/', ''));
        if (params.get('action')) {
            url.searchParams.set('action', params.get('action'));
        }

        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            if (data instanceof FormData) {
                delete options.headers['Content-Type'];
                options.body = data;
            } else {
                options.body = JSON.stringify(data);
            }
        }

        const response = await fetch(url.toString(), options);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    init() {
        this.setupEventListeners();
        this.setupMobileMenu();
        this.setupSmoothScrolling();
        this.setupFormValidation();
    }

    setupEventListeners() {
        // Contact form
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', (e) => this.handleContactForm(e));
        }

        // Affiliate modal forms
        const emailForm = document.getElementById('emailVerificationForm');
        if (emailForm) {
            emailForm.addEventListener('submit', (e) => this.handleEmailVerification(e));
        }

        const codeForm = document.getElementById('codeVerificationForm');
        if (codeForm) {
            codeForm.addEventListener('submit', (e) => this.handleCodeVerification(e));
        }

        const applicationForm = document.getElementById('applicationForm');
        if (applicationForm) {
            applicationForm.addEventListener('submit', (e) => this.handleApplicationSubmit(e));
        }
    }

    setupMobileMenu() {
        const toggle = document.querySelector('.nav-toggle');
        const menu = document.querySelector('.nav-menu');

        if (toggle && menu) {
            toggle.addEventListener('click', () => {
                menu.classList.toggle('active');
            });
        }
    }

    setupSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(anchor.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    }

    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });
        });
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        } else if (field.type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        } else if (field.type === 'tel' && value && !this.isValidPhone(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
        }

        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    clearFieldError(field) {
        field.classList.remove('error');
        const errorElement = field.parentNode.querySelector('.error-message');
        if (errorElement) {
            errorElement.remove();
        }
    }

    showFieldError(field, message) {
        field.classList.add('error');
        let errorElement = field.parentNode.querySelector('.error-message');

        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            errorElement.style.color = 'var(--error)';
            errorElement.style.fontSize = '0.875rem';
            errorElement.style.marginTop = '0.25rem';
            field.parentNode.appendChild(errorElement);
        }

        errorElement.textContent = message;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        return phoneRegex.test(phone);
    }

    // Modal Management
    openAffiliateModal() {
        document.getElementById('affiliateModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    closeAffiliateModal() {
        document.getElementById('affiliateModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        this.resetAffiliateForm();
    }

    resetAffiliateForm() {
        // Reset to step 1
        this.showStep('emailVerificationStep');
        document.getElementById('emailVerificationForm').reset();
        document.getElementById('codeVerificationForm').reset();
        document.getElementById('applicationForm').reset();
    }

    showStep(stepId) {
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById(stepId).classList.add('active');
    }

    // Form Handlers
    async handleContactForm(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const data = {
            name: formData.get('name'),
            email: formData.get('email'),
            message: formData.get('message')
        };

        this.showLoading();

        try {
            const response = await this.apiCall('/contact/send', 'POST', data);

            if (response.success) {
                this.showSuccess('Message sent successfully!');
                e.target.reset();
            } else {
                this.showError(response.message || 'Failed to send message');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    async handleEmailVerification(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const email = formData.get('institutionEmail');

        if (!this.isValidEmail(email)) {
            this.showError('Please enter a valid email address');
            return;
        }

        this.showLoading();

        try {
            const response = await this.apiCall('/affiliate?action=send-code', 'POST', { email });

            if (response.success) {
                if (response.code) {
                    // Email not configured, show code to user
                    this.showSuccess(`Verification code: ${response.code} (Email not configured)`);
                    this.showStep('codeVerificationStep');
                    document.getElementById('verificationCode').focus();
                } else {
                    this.showSuccess('Verification code sent to your email!');
                    this.showStep('codeVerificationStep');
                    document.getElementById('verificationCode').focus();
                }
            } else {
                this.showError(response.message || 'Failed to send verification code');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    async handleCodeVerification(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const code = formData.get('verificationCode');
        const email = document.getElementById('institutionEmail').value;

        if (code.length !== 6) {
            this.showError('Please enter a 6-digit verification code');
            return;
        }

        this.showLoading();

        try {
            const response = await this.apiCall('/affiliate?action=verify-code', 'POST', { email, code });

            if (response.success) {
                this.showSuccess('Email verified successfully!');
                this.showStep('applicationStep');
                // Pre-fill email in application form
                document.getElementById('contactEmail').value = email;
            } else {
                this.showError(response.message || 'Invalid verification code');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    async handleApplicationSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);

        // Validate all required fields
        const requiredFields = ['institutionName', 'institutionAddress', 'contactPerson', 'contactPhone', 'institutionType'];
        let isValid = true;

        for (const field of requiredFields) {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            }
        }

        // Validate file uploads
        const fileInputs = e.target.querySelectorAll('input[type="file"]');
        for (const input of fileInputs) {
            if (!input.files[0]) {
                this.showFieldError(input, 'Please upload this document');
                isValid = false;
            } else if (input.files[0].size > 5 * 1024 * 1024) { // 5MB limit
                this.showFieldError(input, 'File size must be less than 5MB');
                isValid = false;
            }
        }

        if (!isValid) {
            this.showError('Please fill in all required fields and upload all documents');
            return;
        }

        this.showLoading();

        try {
            const response = await this.apiCall('/affiliate?action=submit_application', 'POST', formData);

            if (response.success) {
                this.showSuccess('Application submitted successfully! We will review your application within 3-5 business days.');
                this.closeAffiliateModal();
            } else {
                this.showError(response.message || 'Failed to submit application');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    // API Helper
    async apiCall(endpoint, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            if (data instanceof FormData) {
                // Don't set Content-Type for FormData (browser sets it with boundary)
                delete options.headers['Content-Type'];
                options.body = data;
            } else {
                options.body = JSON.stringify(data);
            }
        }

        const response = await fetch(this.apiBase + endpoint, options);
        return await response.json();
    }

    // UI Helpers
    showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    showSuccess(message) {
        const successEl = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        successText.textContent = message;
        successEl.style.display = 'block';

        setTimeout(() => {
            successEl.style.display = 'none';
        }, 5000);
    }

    showError(message) {
        const errorEl = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        errorText.textContent = message;
        errorEl.style.display = 'block';

        setTimeout(() => {
            errorEl.style.display = 'none';
        }, 5000);
    }

    closeMessage() {
        document.getElementById('successMessage').style.display = 'none';
        document.getElementById('errorMessage').style.display = 'none';
    }

    // File Upload Helper
    handleFileSelect(input, previewContainer = null) {
        const file = input.files[0];
        if (!file) return;

        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            this.showError('File size must be less than 5MB');
            input.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) {
            this.showError('Only PDF, DOC, and DOCX files are allowed');
            input.value = '';
            return;
        }

        // Show file info
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        fileInfo.innerHTML = `
            <i class="fas fa-file"></i>
            <span>${file.name}</span>
            <span>(${this.formatFileSize(file.size)})</span>
        `;

        if (previewContainer) {
            previewContainer.innerHTML = '';
            previewContainer.appendChild(fileInfo);
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Dashboard Helper (for portal pages)
    setupDashboard() {
        this.setupSidebarToggle();
        this.setupLogout();
        this.setupQuickActions();
    }

    setupSidebarToggle() {
        const toggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');

        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
            });
        }
    }

    setupLogout() {
        const logoutLinks = document.querySelectorAll('a[href*="logout=1"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = link.href;
                }
            });
        });
    }

    setupQuickActions() {
        const quickActions = document.querySelectorAll('.quick-action');
        quickActions.forEach(action => {
            action.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleQuickAction(action);
            });
        });
    }

    async handleQuickAction(action) {
        const actionType = action.dataset.action;
        const itemId = action.dataset.id;

        this.showLoading();

        try {
            const response = await this.apiCall(`/dashboard/action/${actionType}`, 'POST', { id: itemId });

            if (response.success) {
                this.showSuccess(response.message || 'Action completed successfully');
                // Refresh data or update UI
                if (actionType === 'refresh') {
                    window.location.reload();
                }
            } else {
                this.showError(response.message || 'Action failed');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    // Search and Filter
    setupSearch(searchContainer, resultsContainer, searchEndpoint) {
        const searchInput = searchContainer.querySelector('input[type="search"]');
        const searchButton = searchContainer.querySelector('button');

        if (searchInput) {
            let searchTimeout;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value, resultsContainer, searchEndpoint);
                }, 300);
            });
        }

        if (searchButton) {
            searchButton.addEventListener('click', () => {
                this.performSearch(searchInput.value, resultsContainer, searchEndpoint);
            });
        }
    }

    async performSearch(query, resultsContainer, endpoint) {
        if (query.length < 2) {
            resultsContainer.innerHTML = '<p class="no-results">Enter at least 2 characters to search</p>';
            return;
        }

        this.showLoading();

        try {
            const response = await this.apiCall(endpoint, 'POST', { query });

            if (response.success && response.data) {
                this.displaySearchResults(response.data, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<p class="no-results">No results found</p>';
            }
        } catch (error) {
            resultsContainer.innerHTML = '<p class="no-results">Search error. Please try again.</p>';
        } finally {
            this.hideLoading();
        }
    }

    displaySearchResults(results, container) {
        container.innerHTML = '';

        if (results.length === 0) {
            container.innerHTML = '<p class="no-results">No results found</p>';
            return;
        }

        const resultsList = document.createElement('div');
        resultsList.className = 'search-results';

        results.forEach(result => {
            const resultItem = document.createElement('div');
            resultItem.className = 'search-result-item';
            resultItem.innerHTML = `
                <h4>${result.name || result.title}</h4>
                <p>${result.description || result.summary}</p>
                <a href="${result.link || '#'}" class="btn btn-sm">View Details</a>
            `;
            resultsList.appendChild(resultItem);
        });

        container.appendChild(resultsList);
    }
}

// Initialize app
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new IECEPLSCApp();
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('affiliateModal');
    if (modal && e.target === modal) {
        app.closeAffiliateModal();
    }
});

// Close modal with Escape key
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        app.closeAffiliateModal();
        app.closeMessage();
    }
});