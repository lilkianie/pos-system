// POS Cashiering System JavaScript
// APP_URL should be defined in the HTML page before this script loads
let cart = [];
let settings = {};
let isOnline = navigator.onLine;
let selectedCustomer = null;
let pointsDiscount = 0;
let pointsPerPeso = 1;

// Load categories
function loadCategories() {
    $.get(APP_URL + '/api/pos.php', {action: 'get_settings'}, function(response) {
        if (response.success) {
            settings = response.data;
        }
    });

    $.get(APP_URL + '/api/categories.php', function(response) {
        if (response.success) {
            const select = $('#categoryFilter');
            select.html('<option value="">All Categories</option>');
            response.data.forEach(cat => {
                select.append(`<option value="${cat.id}">${cat.category_name}</option>`);
            });
        }
    }).fail(function() {
        // Offline mode - load from localStorage
        const categories = JSON.parse(localStorage.getItem('categories') || '[]');
        const select = $('#categoryFilter');
        select.html('<option value="">All Categories</option>');
        categories.forEach(cat => {
            select.append(`<option value="${cat.id}">${cat.category_name}</option>`);
        });
    });
}

// Load products
function loadProducts(categoryId = '') {
    const categoryFilter = categoryId || $('#categoryFilter').val();
    
    $.get(APP_URL + '/api/products.php', {category_id: categoryFilter}, function(response) {
        if (response.success) {
            displayProducts(response.data);
            // Cache products for offline use
            localStorage.setItem('products', JSON.stringify(response.data));
        }
    }).fail(function() {
        // Offline mode - load from localStorage
        const products = JSON.parse(localStorage.getItem('products') || '[]');
        displayProducts(products.filter(p => !categoryFilter || p.category_id == categoryFilter));
    });
}

// Display products in grid
function displayProducts(products) {
    const grid = $('#productGrid');
    grid.html('');
    
    if (products.length === 0) {
        grid.html('<div class="col-12"><p class="text-center text-muted">No products found</p></div>');
        return;
    }

    products.forEach(product => {
        const imageHtml = product.image_url 
            ? `<img src="${product.image_url}" alt="${product.product_name}" class="product-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
            : '';
        const placeholderHtml = product.image_url 
            ? `<div class="product-image-placeholder" style="display: none;"><i class="bi bi-image"></i></div>`
            : `<div class="product-image-placeholder"><i class="bi bi-image"></i></div>`;
        
        const card = $(`
            <div class="col-md-3 col-sm-4 col-6">
                <div class="card product-card" onclick="addToCart(${product.id}, '${product.product_name}', ${product.price}, ${product.stock_quantity})">
                    <div class="product-image-container">
                        ${imageHtml}
                        ${placeholderHtml}
                    </div>
                    <div class="card-body text-center">
                        <h6 class="card-title">${product.product_name}</h6>
                        <p class="text-muted small">${product.barcode}</p>
                        <p class="fw-bold text-primary">₱${parseFloat(product.price).toFixed(2)}</p>
                        <small class="badge ${product.stock_quantity > 0 ? 'bg-success' : 'bg-danger'}">
                            Stock: ${product.stock_quantity}
                        </small>
                    </div>
                </div>
            </div>
        `);
        grid.append(card);
    });
}

// Search product
function searchProduct() {
    const query = $('#barcodeInput').val().trim();
    if (!query) return;

    $.get(APP_URL + '/api/pos.php', {action: 'search_product', q: query}, function(response) {
        if (response.success && response.data.length > 0) {
            if (response.data.length === 1) {
                // Auto-add if single result
                const product = response.data[0];
                addToCart(product.id, product.product_name, product.price, product.stock_quantity);
                $('#barcodeInput').val('');
            } else {
                displaySearchResults(response.data);
            }
        } else {
            showWarning('Product not found');
        }
    }).fail(function() {
        // Offline search
        const products = JSON.parse(localStorage.getItem('products') || '[]');
        const filtered = products.filter(p => 
            p.product_name.toLowerCase().includes(query.toLowerCase()) || 
            p.barcode.includes(query)
        );
        if (filtered.length === 1) {
            const product = filtered[0];
            addToCart(product.id, product.product_name, product.price, product.stock_quantity);
            $('#barcodeInput').val('');
        } else {
            displaySearchResults(filtered);
        }
    });
}

// Display search results
function displaySearchResults(products) {
    const results = $('#productSearchResults');
    results.html('');
    
    products.forEach(product => {
        const imageHtml = product.image_url 
            ? `<img src="${product.image_url}" alt="${product.product_name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px;" onerror="this.style.display='none';">`
            : `<div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-right: 10px;"><i class="bi bi-image" style="color: #ccc;"></i></div>`;
        
        const item = $(`
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="addToCart(${product.id}, '${product.product_name}', ${product.price}, ${product.stock_quantity}); $('#barcodeInput').val(''); $('#productSearchResults').html('');">
                ${imageHtml}
                <div class="flex-grow-1">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${product.product_name}</h6>
                        <small>₱${parseFloat(product.price).toFixed(2)}</small>
                    </div>
                    <small>${product.barcode} | Stock: ${product.stock_quantity}</small>
                </div>
            </a>
        `);
        results.append(item);
    });
}

// Add to cart
function addToCart(productId, productName, price, stock) {
    if (stock <= 0) {
        showWarning('Product is out of stock');
        return;
    }

    const existingItem = cart.find(item => item.product_id === productId);
    
    if (existingItem) {
        if (existingItem.quantity >= stock) {
            showWarning('Insufficient stock');
            return;
        }
        existingItem.quantity++;
        existingItem.subtotal = existingItem.quantity * existingItem.unit_price;
    } else {
        cart.push({
            product_id: productId,
            product_name: productName,
            unit_price: parseFloat(price),
            quantity: 1,
            subtotal: parseFloat(price),
            discount: 0
        });
    }

    updateCartDisplay();
    saveCartToStorage();
}

// Remove from cart
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartDisplay();
    saveCartToStorage();
}

// Update quantity
function updateQuantity(index, change) {
    const item = cart[index];
    const newQuantity = item.quantity + change;
    
    if (newQuantity <= 0) {
        removeFromCart(index);
        return;
    }
    
    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.unit_price;
    updateCartDisplay();
    saveCartToStorage();
}

// Update cart totals
function updateCartTotals() {
    const discount = parseFloat($('#discountInput').val()) || 0;
    const totalDiscount = discount + pointsDiscount;
    const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    const taxRate = parseFloat(settings.tax_rate || 12) / 100;
    const tax = (subtotal - totalDiscount) * taxRate;
    const total = subtotal - totalDiscount + tax;

    $('#cartSubtotal').text('₱' + subtotal.toFixed(2));
    $('#cartDiscount').text('₱' + totalDiscount.toFixed(2));
    $('#cartTax').text('₱' + tax.toFixed(2));
    $('#cartTotal').text('₱' + total.toFixed(2));
    $('#paymentTotal').text('₱' + total.toFixed(2));

    $('#checkoutBtn').prop('disabled', cart.length === 0);
}

// Update cart display
function updateCartDisplay() {
    const container = $('#cartItems');
    
    if (cart.length === 0) {
        container.html('<p class="text-muted text-center">Cart is empty</p>');
        updateCartTotals();
        return;
    }

    let html = '';
    cart.forEach((item, index) => {
        html += `
            <div class="card mb-2">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${item.product_name}</h6>
                            <small class="text-muted">₱${item.unit_price.toFixed(2)} x ${item.quantity}</small>
                            <div class="mt-1">
                                <strong>₱${item.subtotal.toFixed(2)}</strong>
                            </div>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, -1)">-</button>
                            <span class="btn btn-outline-secondary">${item.quantity}</span>
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, 1)">+</button>
                            <button class="btn btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
    updateCartTotals();
}

// Clear cart
function clearCart() {
    showConfirm('Are you sure you want to clear the cart?', 'Clear Cart', 'Yes, clear it', 'Cancel').then((result) => {
        if (result.isConfirmed) {
            cart = [];
            $('#discountInput').val(0);
            updateCartDisplay();
            saveCartToStorage();
        }
    });
}

// Open payment modal
function openPaymentModal() {
    if (cart.length === 0) return;
    
    const total = parseFloat($('#cartTotal').text().replace('₱', '').replace(',', ''));
    $('#paymentTotal').text('₱' + total.toFixed(2));
    $('#amountReceived').val('');
    $('#changeAmount').text('₱0.00');
    
    // Set default payment method
    if (selectedCustomer && selectedCustomer.standing === 'good') {
        // Show credit option
    } else {
        $('#paymentMethod').val('cash');
    }
    
    updatePaymentFields();
    
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Search customer
let customerSearchTimeout;
function searchCustomer() {
    const query = $('#customerSearch').val().trim();
    const resultsDiv = $('#customerSearchResults');
    
    if (query.length < 2) {
        resultsDiv.hide();
        return;
    }
    
    clearTimeout(customerSearchTimeout);
    customerSearchTimeout = setTimeout(() => {
        $.get(APP_URL + '/api/pos.php', {action: 'search_customer', q: query}, function(response) {
            if (response.success && response.data.length > 0) {
                let html = '';
                response.data.forEach(customer => {
                    const standingBadge = customer.standing === 'bad' ? '<span class="badge bg-danger">Bad</span>' : 
                                         customer.standing === 'warning' ? '<span class="badge bg-warning">Warning</span>' : '';
                    html += `
                        <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${customer.id}); return false;">
                            <strong>${customer.customer_code}</strong> - ${customer.customer_name}
                            ${standingBadge}
                            ${customer.customer_type === 'member' ? '<span class="badge bg-info">Member</span>' : ''}
                        </a>
                    `;
                });
                resultsDiv.html(html).show();
            } else {
                resultsDiv.html('<div class="list-group-item">No customers found</div>').show();
            }
        }).fail(function() {
            resultsDiv.html('<div class="list-group-item text-danger">Error searching customers</div>').show();
        });
    }, 300);
}

// Select customer
function selectCustomer(customerId) {
    $.get(APP_URL + '/api/pos.php', {action: 'get_customer', id: customerId}, function(response) {
        if (response.success) {
            selectedCustomer = response.data;
            $('#selectedCustomerId').val(customerId);
            $('#customerSearch').val(selectedCustomer.customer_code);
            $('#customerSearchResults').hide();
            
            // Display customer info
            let infoHtml = `${selectedCustomer.customer_code} - ${selectedCustomer.customer_name}`;
            if (selectedCustomer.customer_type === 'member') {
                infoHtml += ` | Points: ${selectedCustomer.points_balance || 0}`;
            }
            $('#customerName').text(selectedCustomer.customer_name);
            $('#customerInfo').html(infoHtml);
            $('#selectedCustomer').show();
            
            // Show standing alert
            let alertHtml = '';
            if (selectedCustomer.standing === 'bad') {
                alertHtml = '<div class="alert alert-danger mb-0 mt-2"><i class="bi bi-exclamation-triangle"></i> <strong>BAD STANDING</strong> - Cannot process credit sales</div>';
            } else if (selectedCustomer.standing === 'warning') {
                alertHtml = '<div class="alert alert-warning mb-0 mt-2"><i class="bi bi-exclamation-triangle"></i> <strong>WARNING</strong> - Customer has overdue accounts</div>';
            }
            $('#customerStandingAlert').html(alertHtml);
            
            // Show credit option if good standing
            if (selectedCustomer.standing === 'good') {
                $('#creditOption').show();
            } else {
                $('#creditOption').hide();
                if ($('#paymentMethod').val() === 'credit') {
                    $('#paymentMethod').val('cash');
                    updatePaymentFields();
                }
            }
            
            // Show points section for members
            if (selectedCustomer.customer_type === 'member') {
                $('#pointsSection').show();
                $('#customerPoints').text(selectedCustomer.points_balance || 0);
                // Get points per peso setting
                $.get(APP_URL + '/api/pos.php', {action: 'get_settings'}, function(res) {
                    if (res.success && res.data.points_per_peso) {
                        pointsPerPeso = parseFloat(res.data.points_per_peso);
                    }
                });
            } else {
                $('#pointsSection').hide();
                $('#pointsRedeem').val(0);
                updatePointsDiscount();
            }
        } else {
            showError(response.message || 'Error loading customer');
        }
    }).fail(function() {
        showError('Error loading customer');
    });
}

// Clear customer
function clearCustomer() {
    selectedCustomer = null;
    $('#selectedCustomerId').val('');
    $('#customerSearch').val('');
    $('#customerSearchResults').hide();
    $('#selectedCustomer').hide();
    $('#creditOption').hide();
    $('#pointsSection').hide();
    $('#pointsRedeem').val(0);
    updatePointsDiscount();
    if ($('#paymentMethod').val() === 'credit') {
        $('#paymentMethod').val('cash');
        updatePaymentFields();
    }
}

// Update points discount
function updatePointsDiscount() {
    const pointsRedeemed = parseInt($('#pointsRedeem').val()) || 0;
    if (!selectedCustomer || selectedCustomer.customer_type !== 'member') {
        pointsDiscount = 0;
        $('#pointsDiscount').text('₱0.00');
        updateCartTotals();
        return;
    }
    
    if (pointsRedeemed > selectedCustomer.points_balance) {
        $('#pointsRedeem').val(selectedCustomer.points_balance);
        pointsDiscount = (selectedCustomer.points_balance / pointsPerPeso);
    } else {
        pointsDiscount = (pointsRedeemed / pointsPerPeso);
    }
    
    $('#pointsDiscount').text('₱' + pointsDiscount.toFixed(2));
    updateCartTotals();
}

// Update payment fields based on method
function updatePaymentFields() {
    const method = $('#paymentMethod').val();
    
    $('#cashPaymentFields').toggle(method === 'cash');
    $('#cardPaymentFields').toggle(method === 'credit_card');
    $('#ewalletPaymentFields').toggle(method === 'e_wallet');
    $('#creditInfo').toggle(method === 'credit');
    
    if (method === 'credit') {
        if (!selectedCustomer) {
            showWarning('Please select a customer first');
            $('#paymentMethod').val('cash');
            updatePaymentFields();
            return;
        }
        
        if (selectedCustomer.standing !== 'good') {
            showError('Cannot process credit sale. Customer has ' + selectedCustomer.standing + ' standing.');
            $('#paymentMethod').val('cash');
            updatePaymentFields();
            return;
        }
        
        const total = parseFloat($('#cartTotal').text().replace('₱', '').replace(',', ''));
        const outstanding = parseFloat(selectedCustomer.outstanding_balance || 0);
        const available = parseFloat(selectedCustomer.credit_limit) - outstanding;
        
        $('#creditCustomerName').text(selectedCustomer.customer_name);
        $('#creditLimit').text('₱' + parseFloat(selectedCustomer.credit_limit).toFixed(2));
        $('#availableCredit').text('₱' + available.toFixed(2));
        
        if (total > available) {
            showWarning('Transaction exceeds available credit limit. Available: ₱' + available.toFixed(2));
        }
    }
}

// Calculate change
function calculateChange() {
    const total = parseFloat($('#cartTotal').text().replace('₱', '').replace(',', ''));
    const received = parseFloat($('#amountReceived').val()) || 0;
    const change = received - total;
    
    $('#changeAmount').text('₱' + (change >= 0 ? change.toFixed(2) : '0.00'));
}

// Process payment
function processPayment() {
    if (cart.length === 0) {
        showWarning('Cart is empty');
        return;
    }

    const paymentMethod = $('#paymentMethod').val();
    const total = parseFloat($('#cartTotal').text().replace('₱', '').replace(',', ''));
    const discount = parseFloat($('#discountInput').val()) || 0;
    const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    const taxRate = parseFloat(settings.tax_rate || 12) / 100;
    const tax = (subtotal - discount) * taxRate;

    if (paymentMethod === 'cash') {
        const received = parseFloat($('#amountReceived').val()) || 0;
        if (received < total) {
            showWarning('Insufficient payment. Please enter a higher amount.');
            return;
        }
    }

    // Validate credit sale
    if (paymentMethod === 'credit') {
        if (!selectedCustomer) {
            showWarning('Please select a customer for credit sale');
            return;
        }
        if (selectedCustomer.standing !== 'good') {
            showError('Cannot process credit sale. Customer has ' + selectedCustomer.standing + ' standing.');
            return;
        }
        const outstanding = parseFloat(selectedCustomer.outstanding_balance || 0);
        const available = parseFloat(selectedCustomer.credit_limit) - outstanding;
        if (total > available) {
            showError('Transaction exceeds available credit limit. Available: ₱' + available.toFixed(2));
            return;
        }
    }

    const transactionData = {
        items: cart.map(item => ({
            product_id: item.product_id,
            product_name: item.product_name,
            quantity: item.quantity,
            unit_price: item.unit_price,
            subtotal: item.subtotal,
            discount: 0
        })),
        total_amount: subtotal,
        discount_amount: discount + pointsDiscount,
        tax_amount: tax,
        final_amount: total,
        payment_method: paymentMethod,
        customer_id: selectedCustomer ? selectedCustomer.id : null,
        points_redeemed: selectedCustomer && selectedCustomer.customer_type === 'member' ? parseInt($('#pointsRedeem').val()) || 0 : 0,
        payment_details: {
            amount_received: paymentMethod === 'cash' ? parseFloat($('#amountReceived').val()) : total,
            change: paymentMethod === 'cash' ? (parseFloat($('#amountReceived').val()) - total) : 0,
            card_number: $('#cardNumber').val(),
            cardholder_name: $('#cardholderName').val(),
            ewallet_provider: $('#ewalletProvider').val(),
            ewallet_reference: $('#ewalletReference').val()
        }
    };

    if (isOnline) {
        processTransactionOnline(transactionData);
    } else {
        processTransactionOffline(transactionData);
    }
}

// Process transaction online
function processTransactionOnline(transactionData) {
    $.ajax({
        url: APP_URL + '/api/pos.php',
        method: 'POST',
        data: {
            action: 'process_transaction',
            transaction_data: JSON.stringify(transactionData)
        },
        success: function(response) {
            if (response.success) {
                if (response.points_earned > 0 && selectedCustomer) {
                    selectedCustomer.points_balance = (selectedCustomer.points_balance || 0) - (parseInt($('#pointsRedeem').val()) || 0) + response.points_earned;
                    $('#customerPoints').text(selectedCustomer.points_balance);
                    showInfo(`Points earned: ${response.points_earned}`, 'Reward Points');
                }
                showReceipt(response.transaction_number, transactionData);
                cart = [];
                $('#discountInput').val(0);
                $('#pointsRedeem').val(0);
                pointsDiscount = 0;
                updateCartDisplay();
                saveCartToStorage();
                bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            } else {
                showError('Error processing transaction: ' + (response.message || 'Unknown error'));
            }
        },
        error: function() {
            // Fallback to offline mode
            processTransactionOffline(transactionData);
        }
    });
}

// Process transaction offline
function processTransactionOffline(transactionData) {
    const localId = 'TXN-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const transaction = {
        local_id: localId,
        transaction_number: 'OFFLINE-' + localId,
        user_id: typeof CURRENT_USER_ID !== 'undefined' ? CURRENT_USER_ID : 1,
        created_at: new Date().toISOString(),
        ...transactionData
    };

    // Save to localStorage
    let offlineTransactions = JSON.parse(localStorage.getItem('offline_transactions') || '[]');
    offlineTransactions.push(transaction);
    localStorage.setItem('offline_transactions', JSON.stringify(offlineTransactions));

    showReceipt(transaction.transaction_number, transactionData, true);
    cart = [];
    $('#discountInput').val(0);
    updateCartDisplay();
    saveCartToStorage();
    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();

    showInfo('Transaction saved offline. It will be synced when connection is restored.', 'Offline Mode');
}

// Show receipt
function showReceipt(transactionNumber, transactionData, isOffline = false) {
    const receiptHtml = generateReceipt(transactionNumber, transactionData, isOffline);
    $('#receiptContent').html(receiptHtml);
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

// Generate receipt HTML
function generateReceipt(transactionNumber, transactionData, isOffline = false) {
    const storeName = settings.store_name || 'POS Store';
    const footer = settings.receipt_footer || 'Thank you for shopping with us!';
    
    let html = `
        <div class="receipt">
            <div class="text-center mb-3">
                <h4>${storeName}</h4>
                <p class="mb-1">Transaction: ${transactionNumber}</p>
                <p class="mb-1">Date: ${new Date().toLocaleString()}</p>
                ${isOffline ? '<p class="text-warning"><strong>OFFLINE TRANSACTION</strong></p>' : ''}
            </div>
            <hr>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
    `;

    transactionData.items.forEach(item => {
        html += `
            <tr>
                <td>${item.product_name}</td>
                <td class="text-end">${item.quantity}</td>
                <td class="text-end">₱${item.unit_price.toFixed(2)}</td>
                <td class="text-end">₱${item.subtotal.toFixed(2)}</td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
            <hr>
            <div class="d-flex justify-content-between">
                <strong>Subtotal:</strong>
                <strong>₱${transactionData.total_amount.toFixed(2)}</strong>
            </div>
            <div class="d-flex justify-content-between">
                <span>Discount:</span>
                <span>₱${transactionData.discount_amount.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Tax:</span>
                <span>₱${transactionData.tax_amount.toFixed(2)}</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
                <h5>Total:</h5>
                <h5>₱${transactionData.final_amount.toFixed(2)}</h5>
            </div>
            <div class="d-flex justify-content-between">
                <span>Payment Method:</span>
                <span>${transactionData.payment_method.replace('_', ' ').toUpperCase()}</span>
            </div>
    `;

    if (transactionData.payment_method === 'cash') {
        html += `
            <div class="d-flex justify-content-between">
                <span>Amount Received:</span>
                <span>₱${transactionData.payment_details.amount_received.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Change:</span>
                <span>₱${transactionData.payment_details.change.toFixed(2)}</span>
            </div>
        `;
    }

    html += `
            <hr>
            <p class="text-center">${footer}</p>
        </div>
    `;

    return html;
}

// Print receipt
function printReceipt() {
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .receipt { max-width: 300px; margin: 0 auto; }
                    table { width: 100%; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>${receiptContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Save cart to localStorage
function saveCartToStorage() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

// Load cart from localStorage
function loadCartFromStorage() {
    const savedCart = localStorage.getItem('pos_cart');
    if (savedCart) {
        cart = JSON.parse(savedCart);
        updateCartDisplay();
    }
}

// Check connection status
function checkConnection() {
    isOnline = navigator.onLine;
    const statusBadge = $('#connectionStatus');
    const statusText = $('#statusText');
    
    if (isOnline) {
        statusBadge.removeClass('bg-danger').addClass('bg-success');
        statusText.html('<i class="bi bi-wifi"></i> Online');
    } else {
        statusBadge.removeClass('bg-success').addClass('bg-danger');
        statusText.html('<i class="bi bi-wifi-off"></i> Offline');
    }
}

// Sync offline transactions
function syncOfflineTransactions() {
    if (!isOnline) return;

    const offlineTransactions = JSON.parse(localStorage.getItem('offline_transactions') || '[]');
    if (offlineTransactions.length === 0) return;

    const transactionsToSync = {};
    offlineTransactions.forEach(txn => {
        transactionsToSync[txn.local_id] = {
            transaction_number: txn.transaction_number,
            user_id: txn.user_id,
            items: txn.items,
            total_amount: txn.total_amount,
            discount_amount: txn.discount_amount,
            tax_amount: txn.tax_amount,
            final_amount: txn.final_amount,
            payment_method: txn.payment_method
        };
    });

    $.ajax({
        url: APP_URL + '/api/pos.php',
        method: 'POST',
        data: {
            action: 'sync_offline_transactions',
            transactions: JSON.stringify(transactionsToSync)
        },
        success: function(response) {
            if (response.success && response.synced_ids) {
                let remaining = offlineTransactions.filter(txn => 
                    !response.synced_ids.includes(txn.local_id)
                );
                localStorage.setItem('offline_transactions', JSON.stringify(remaining));
                
                if (response.synced_ids.length > 0) {
                    console.log(`Synced ${response.synced_ids.length} offline transactions`);
                }
            }
        }
    });
}
