/**
 * Main JavaScript File - Cart & AJAX functionality
 */

// Cart Management
class CartManager {
    constructor() {
        this.cartKey = 'ahanger_cart';
        this.init();
    }
    
    init() {
        this.attachEventListeners();
        this.updateCartCount();
    }
    
    attachEventListeners() {
        // Add to cart buttons
        document.querySelectorAll('.btn-add-cart').forEach(btn => {
            btn.addEventListener('click', (e) => this.addToCart(e));
        });
        
        // Quantity input changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', (e) => this.updateQuantity(e));
        });
        
        // Remove item buttons
        document.querySelectorAll('.btn-remove-item').forEach(btn => {
            btn.addEventListener('click', (e) => this.removeFromCart(e));
        });
        
        // Checkout button
        const checkoutBtn = document.querySelector('.btn-checkout');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', () => this.checkout());
        }
    }
    
    addToCart(event) {
        const btn = event.target;
        const productId = btn.getAttribute('data-product-id');
        
        if (!productId) return;
        
        // Make AJAX request
        fetch('/waaris/api/cart-add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ product_id: productId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Product added to cart!', 'success');
                this.updateCartCount();
                
                // Animate button
                btn.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    btn.style.transform = 'scale(1)';
                }, 200);
            } else {
                this.showNotification(data.message || 'Error adding product', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('An error occurred', 'error');
        });
    }
    
    updateQuantity(event) {
        const input = event.target;
        const productId = input.getAttribute('data-product-id');
        const quantity = parseInt(input.value);
        
        if (quantity < 1) {
            input.value = 1;
            return;
        }
        
        fetch('/waaris/api/cart-update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ product_id: productId, quantity: quantity })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    removeFromCart(event) {
        event.preventDefault();
        const btn = event.target;
        const productId = btn.getAttribute('data-product-id');
        
        if (confirm('Remove this item from cart?')) {
            fetch('/waaris/api/cart-remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
    
    updateCartCount() {
        fetch('/waaris/api/cart-count.php', {
            credentials: 'include'
        })
            .then(response => response.json())
            .then(data => {
                const countElement = document.getElementById('cart-count');
                if (countElement) {
                    countElement.textContent = data.count || 0;
                }
            });
    }
    
    checkout() {
        const cartCount = document.getElementById('cart-count')?.textContent || 0;
        if (parseInt(cartCount) === 0) {
            this.showNotification('Cart is empty!', 'error');
            return;
        }
        
        // Redirect to checkout or create order
        fetch('/waaris/api/checkout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Order created successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '/waaris/';
                }, 2000);
            } else {
                this.showNotification(data.message || 'Checkout failed', 'error');
            }
        });
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#48bb78' : type === 'error' ? '#f56565' : '#667eea'};
            color: white;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Search functionality
class SearchManager {
    constructor() {
        this.init();
    }
    
    init() {
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e));
        }
    }
    
    handleSearch(event) {
        const searchTerm = event.target.value;
        
        if (searchTerm.length < 2) {
            return;
        }
        
        fetch('/waaris/api/search.php?q=' + encodeURIComponent(searchTerm))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayResults(data.products);
                }
            });
    }
    
    displayResults(products) {
        // Update product grid with search results
        const grid = document.querySelector('.products-grid');
        if (grid && products.length > 0) {
            // Results already filtered by server
        }
    }
}

// Smooth scroll animation
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    new CartManager();
    new SearchManager();
    initSmoothScroll();
});

// Animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
