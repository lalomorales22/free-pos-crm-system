/**
 * Your App Your Data - Main JavaScript File
 * 
 * Handles UI interactions, animations, and other dynamic elements
 * for the Your App Your Data POS + CRM experience.
 * 
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap offcanvas
    const offcanvasElementList = document.querySelectorAll('.offcanvas');
    if (offcanvasElementList.length > 0) {
        offcanvasElementList.forEach(offcanvasEl => {
            new bootstrap.Offcanvas(offcanvasEl);
        });
    }
    
    // Age Verification Modal
    const ageVerificationModal = document.getElementById('ageVerificationModal');
    if (ageVerificationModal) {
        // Prevent scrolling when modal is active
        document.body.style.overflow = 'hidden';
        
        // Check for verification form submission
        const verificationForm = ageVerificationModal.querySelector('form');
        if (verificationForm) {
            verificationForm.addEventListener('submit', function(e) {
                const checkbox = document.getElementById('confirmAge');
                if (!checkbox.checked) {
                    e.preventDefault();
                    alert('Please confirm that you understand this sandbox notice before continuing.');
                }
            });
        }
    }
    
    // Product image hover effect
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        const image = card.querySelector('.product-image');
        if (image) {
            // Store original transform for resetting
            const originalTransform = window.getComputedStyle(image).transform;
            
            card.addEventListener('mousemove', e => {
                // Get mouse position relative to card
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // Calculate movement amount (limited effect)
                const moveX = ((x - rect.width / 2) / rect.width) * 7;
                const moveY = ((y - rect.height / 2) / rect.height) * 7;
                
                // Apply smooth transform
                image.style.transform = `scale(1.05) translate(${moveX}px, ${moveY}px)`;
            });
            
            card.addEventListener('mouseleave', () => {
                // Reset transform with transition
                image.style.transform = 'scale(1)';
            });
        }
    });
    
    // Quantity Increment/Decrement
    const quantityControls = document.querySelectorAll('.quantity-control');
    quantityControls.forEach(control => {
        const input = control.querySelector('input');
        const increaseBtn = control.querySelector('.increase');
        const decreaseBtn = control.querySelector('.decrease');
        
        if (input && increaseBtn && decreaseBtn) {
            increaseBtn.addEventListener('click', () => {
                input.value = parseInt(input.value) + 1;
                input.dispatchEvent(new Event('change'));
            });
            
            decreaseBtn.addEventListener('click', () => {
                if (parseInt(input.value) > 1) {
                    input.value = parseInt(input.value) - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    });
    
    // Cart item quantity update (auto-submit on change)
    const cartQuantityInputs = document.querySelectorAll('.cart-item-quantity input');
    cartQuantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Find closest form and submit it
            const form = this.closest('form');
            if (form) {
                // Create hidden input for update_cart if it doesn't exist
                if (!form.querySelector('input[name="update_cart"]')) {
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_cart';
                    updateInput.value = '1';
                    form.appendChild(updateInput);
                }
                form.submit();
            }
        });
    });
    
    // Mobile menu - expand subcategories on click
    const categoryItems = document.querySelectorAll('.sidebar-categories > li');
    categoryItems.forEach(item => {
        const link = item.querySelector('a');
        const subCategories = item.querySelector('.sidebar-subcategories');
        
        if (link && subCategories) {
            // For mobile only
            if (window.innerWidth < 992) {
                link.addEventListener('click', function(e) {
                    // If there are subcategories, toggle them instead of following link
                    if (subCategories) {
                        e.preventDefault();
                        subCategories.style.display = subCategories.style.display === 'block' ? 'none' : 'block';
                    }
                });
            }
        }
    });
    
    // Smooth scroll to top for "Back to Top" button
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Show/hide button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
    }
    
    // Search form toggle for mobile
    const searchToggle = document.getElementById('searchToggle');
    const searchForm = document.querySelector('.search-form');
    
    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            searchForm.classList.toggle('active');
            searchToggle.querySelector('i').classList.toggle('bi-search');
            searchToggle.querySelector('i').classList.toggle('bi-x-lg');
        });
    }
    
    // Handle product filtering
    const filterForm = document.querySelectorAll('.filter-form');
    filterForm.forEach(form => {
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                form.submit();
            });
        });
    });
    
    // Add to cart animation
    const addToCartBtns = document.querySelectorAll('button[name="add_to_cart"]');
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Get the cart icon position
            const cartIcon = document.querySelector('.bi-cart3');
            if (!cartIcon) return;
            
            const cartRect = cartIcon.getBoundingClientRect();
            const cartX = cartRect.left + cartRect.width / 2;
            const cartY = cartRect.top + cartRect.height / 2;
            
            // Get the button position
            const btnRect = this.getBoundingClientRect();
            const btnX = btnRect.left + btnRect.width / 2;
            const btnY = btnRect.top + btnRect.height / 2;
            
            // Create animation element
            const animEl = document.createElement('div');
            animEl.className = 'cart-animation';
            animEl.style.cssText = `
                position: fixed;
                width: 20px;
                height: 20px;
                background-color: var(--primary-color);
                border-radius: 50%;
                z-index: 9999;
                left: ${btnX}px;
                top: ${btnY}px;
                transform: translate(-50%, -50%);
                pointer-events: none;
            `;
            
            document.body.appendChild(animEl);
            
            // Animate to cart
            setTimeout(() => {
                animEl.style.transition = 'all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                animEl.style.left = `${cartX}px`;
                animEl.style.top = `${cartY}px`;
                animEl.style.opacity = '0';
                animEl.style.transform = 'translate(-50%, -50%) scale(0.2)';
                
                // Remove element after animation
                setTimeout(() => {
                    document.body.removeChild(animEl);
                }, 800);
            }, 10);
        });
    });
    
    // Product gallery image switching (for product.php)
    const galleryThumbs = document.querySelectorAll('.product-gallery-thumb');
    const mainImage = document.querySelector('.product-gallery-main img');
    
    if (galleryThumbs.length && mainImage) {
        galleryThumbs.forEach(thumb => {
            thumb.addEventListener('click', function() {
                const newSrc = this.getAttribute('data-src');
                const newAlt = this.getAttribute('data-alt');
                
                // Set active thumb
                galleryThumbs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update main image with fade effect
                mainImage.style.opacity = '0';
                setTimeout(() => {
                    mainImage.src = newSrc;
                    if (newAlt) mainImage.alt = newAlt;
                    mainImage.style.opacity = '1';
                }, 300);
            });
        });
    }
    
    // Product zoom effect (for product.php)
    const productGalleryMain = document.querySelector('.product-gallery-main');
    if (productGalleryMain && mainImage) {
        productGalleryMain.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Calculate percentage position
            const xPercent = x / rect.width * 100;
            const yPercent = y / rect.height * 100;
            
            // Apply zoom effect
            mainImage.style.transformOrigin = `${xPercent}% ${yPercent}%`;
            
            // Only zoom if hover class is active
            if (this.classList.contains('hover-zoom')) {
                mainImage.style.transform = 'scale(1.5)';
            }
        });
        
        productGalleryMain.addEventListener('mouseenter', function() {
            this.classList.add('hover-zoom');
            mainImage.style.transition = 'transform 0.1s ease';
        });
        
        productGalleryMain.addEventListener('mouseleave', function() {
            this.classList.remove('hover-zoom');
            mainImage.style.transform = 'scale(1)';
            mainImage.style.transition = 'transform 0.3s ease';
        });
    }
    
    // Collapse sidebar on mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            this.querySelector('i').classList.toggle('bi-filter');
            this.querySelector('i').classList.toggle('bi-x-lg');
        });
    }
    
    // Initialize product image lightbox if available
    const lightboxLinks = document.querySelectorAll('.lightbox-gallery');
    if (typeof GLightbox !== 'undefined' && lightboxLinks.length > 0) {
        GLightbox({
            selector: '.lightbox-gallery',
            touchNavigation: true,
            loop: true
        });
    }
});

// Add a back-to-top button dynamically
window.addEventListener('DOMContentLoaded', function() {
    const backToTopBtn = document.createElement('button');
    backToTopBtn.id = 'backToTop';
    backToTopBtn.className = 'back-to-top';
    backToTopBtn.innerHTML = '<i class="bi bi-arrow-up"></i>';
    
    // Add CSS for the button
    const style = document.createElement('style');
    style.textContent = `
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 99;
        }
        
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(backToTopBtn);
});

// Estimate delivery date on product pages
window.addEventListener('DOMContentLoaded', function() {
    const deliveryDateEl = document.getElementById('estimatedDelivery');
    if (deliveryDateEl) {
        // Calculate delivery date (3-5 business days from now)
        const today = new Date();
        
        // Add 3 business days minimum
        let minDays = 3;
        let deliveryDate = new Date(today);
        
        while (minDays > 0) {
            deliveryDate.setDate(deliveryDate.getDate() + 1);
            // Skip weekends
            if (deliveryDate.getDay() !== 0 && deliveryDate.getDay() !== 6) {
                minDays--;
            }
        }
        
        // Format min date
        const minDateFormatted = deliveryDate.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'short', 
            day: 'numeric' 
        });
        
        // Add 2 more business days for max date
        let maxDays = 2;
        while (maxDays > 0) {
            deliveryDate.setDate(deliveryDate.getDate() + 1);
            // Skip weekends
            if (deliveryDate.getDay() !== 0 && deliveryDate.getDay() !== 6) {
                maxDays--;
            }
        }
        
        // Format max date
        const maxDateFormatted = deliveryDate.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'short', 
            day: 'numeric' 
        });
        
        // Update the element
        deliveryDateEl.textContent = `${minDateFormatted} - ${maxDateFormatted}`;
    }
});