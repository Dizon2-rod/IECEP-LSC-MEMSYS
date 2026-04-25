// IECEP-LSC MEMSYS - Affiliate Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize affiliate modal functionality
    const affiliateModal = document.getElementById('affiliateModal');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');

    // Modal controls
    window.openAffiliateModal = function() {
        if (affiliateModal) {
            affiliateModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeAffiliateModal = function() {
        if (affiliateModal) {
            affiliateModal.style.display = 'none';
            document.body.style.overflow = 'auto';
            resetApplicationForm();
        }
    };

    // Close modal when clicking outside
    if (affiliateModal) {
        affiliateModal.addEventListener('click', function(e) {
            if (e.target === affiliateModal) {
                closeAffiliateModal();
            }
        });
    }

    // Email verification form
    const emailVerificationForm = document.getElementById('emailVerificationForm');
    if (emailVerificationForm) {
        emailVerificationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('institutionEmail').value;

            showLoading();

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/src/api/affiliate.php?action=send-code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: email })
                });

                const result = await response.json();

                if (result.success) {
                    showStep('codeVerificationStep');
                    showMessage('success', 'Verification code sent to your email!');
                } else {
                    showMessage('error', result.message || 'Failed to send verification code');
                }
            } catch (error) {
                console.error('Email verification error:', error);
                showMessage('error', 'An error occurred. Please try again.');
            } finally {
                hideLoading();
            }
        });
    }

    // Code verification form
    const codeVerificationForm = document.getElementById('codeVerificationForm');
    if (codeVerificationForm) {
        codeVerificationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const code = document.getElementById('verificationCode').value;
            const email = document.getElementById('institutionEmail').value;

            showLoading();

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/src/api/affiliate.php?action=verify-code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: email, code: code })
                });

                const result = await response.json();

                if (result.success) {
                    showStep('applicationStep');
                    showMessage('success', 'Email verified! Please complete your application.');
                } else {
                    showMessage('error', result.message || 'Invalid verification code');
                }
            } catch (error) {
                console.error('Code verification error:', error);
                showMessage('error', 'An error occurred. Please try again.');
            } finally {
                hideLoading();
            }
        });
    }

    // Application form
    const applicationForm = document.getElementById('applicationForm');
    if (applicationForm) {
        applicationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(applicationForm);
            formData.append('action', 'submit_application');
            formData.append('email', document.getElementById('institutionEmail').value);

            showLoading();

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/src/api/affiliate.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('success', 'Application submitted successfully! We will contact you soon.');
                    setTimeout(() => {
                        closeAffiliateModal();
                    }, 3000);
                } else {
                    showMessage('error', result.message || 'Failed to submit application');
                }
            } catch (error) {
                console.error('Application submission error:', error);
                showMessage('error', 'An error occurred. Please try again.');
            } finally {
                hideLoading();
            }
        });
    }

    // Contact form
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;

            const formData = new FormData();
            formData.append('name', name);
            formData.append('email', email);
            formData.append('message', message);
            formData.append('action', 'contact');

            showLoading();

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/src/api/affiliate.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('success', 'Message sent successfully! We will get back to you soon.');
                    contactForm.reset();
                } else {
                    showMessage('error', result.message || 'Failed to send message');
                }
            } catch (error) {
                console.error('Contact form error:', error);
                showMessage('error', 'An error occurred. Please try again.');
            } finally {
                hideLoading();
            }
        });
    }

    // Helper functions
    window.showStep = function(stepId) {
        const steps = document.querySelectorAll('.step');
        steps.forEach(step => step.classList.remove('active'));
        document.getElementById(stepId) ? .classList.add('active');
    };

    window.resetApplicationForm = function() {
        const forms = ['emailVerificationForm', 'codeVerificationForm', 'applicationForm'];
        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) form.reset();
        });
        showStep('emailVerificationStep');
    };

    window.showMessage = function(type, text) {
        const messageEl = type === 'success' ? successMessage : errorMessage;
        const textEl = type === 'success' ? document.getElementById('successText') : document.getElementById('errorText');

        if (textEl) textEl.textContent = text;
        if (messageEl) {
            messageEl.style.display = 'block';
            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 5000);
        }
    };

    window.closeMessage = function() {
        if (successMessage) successMessage.style.display = 'none';
        if (errorMessage) errorMessage.style.display = 'none';
    };

    function showLoading() {
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }
    }

    function hideLoading() {
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }

    // Mobile menu toggle
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }

    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);

            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});