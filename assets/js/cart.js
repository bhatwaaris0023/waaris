/**
 * Cart JavaScript Module
 */

class Cart {
    constructor() {
        this.init();
    }
    
    init() {
        this.attachListeners();
        this.calculateTotals();
    }
    
    attachListeners() {
        // Quantity input changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', (e) => this.onQuantityChange(e));
        });
        
        // Remove buttons
        document.querySelectorAll('.btn-remove-item').forEach(btn => {
            btn.addEventListener('click', (e) => this.removeItem(e));
        });
    }
    
    onQuantityChange(event) {
        const input = event.target;
        const productId = input.getAttribute('data-product-id');
        const quantity = parseInt(input.value);
        
        if (quantity < 1) {
            if (!confirm('Remove this item?')) {
                location.reload();
                return;
            }
        }
        
        // Update cart via AJAX
        fetch('/waaris/api/cart-update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include',
            body: 'product_id=' + productId + '&quantity=' + quantity
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.calculateTotals();
            }
        });
    }
    
    removeItem(event) {
        event.preventDefault();
        const btn = event.target;
        const productId = btn.getAttribute('data-product-id');
        
        if (confirm('Remove this item from cart?')) {
            fetch('/waaris/api/cart-remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'include',
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
    
    calculateTotals() {
        let subtotal = 0;
        
        document.querySelectorAll('.cart-item-total').forEach(cell => {
            const value = parseFloat(cell.textContent.replace('₹', '').replace(',', ''));
            subtotal += value;
        });
        
        const shipping = 500;
        const tax = subtotal * 0.18;
        const total = subtotal + shipping + tax;
        
        document.getElementById('subtotal').textContent = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('tax').textContent = '₹' + tax.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('total').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Cart();
});
