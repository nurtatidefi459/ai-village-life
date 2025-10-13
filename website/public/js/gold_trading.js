// Gold Trading JavaScript
let currentPage = 1;
let currentListingId = null;
let currentTransactionId = null;

// Tab Management
document.addEventListener('DOMContentLoaded', function() {
    if (!localStorage.getItem('auth_token')) {
        alert('Please login first');
        window.location.href = 'login.html';
        return;
    }
    
    initializeTabs();
    loadGoldListings();
    loadMyListings();
    loadTransactions();
    
    // Real-time price calculation
    document.getElementById('sellGoldAmount').addEventListener('input', calculateSellPrice);
    document.getElementById('sellPricePerGold').addEventListener('input', calculateSellPrice);
});

function initializeTabs() {
    // Main tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show selected tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            
            // Load data for the tab
            switch(tabId) {
                case 'buy':
                    loadGoldListings();
                    break;
                case 'sell':
                    // Already handled by event listeners
                    break;
                case 'my_listings':
                    loadMyListings();
                    break;
                case 'transactions':
                    loadTransactions();
                    break;
            }
        });
    });
    
    // Sub-tabs for transactions
    document.querySelectorAll('.sub-tab').forEach(button => {
        button.addEventListener('click', function() {
            const subtabId = this.getAttribute('data-subtab');
            
            // Update active sub-tab
            document.querySelectorAll('.sub-tab').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show selected sub-tab content
            document.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(subtabId + 'Transactions').classList.add('active');
        });
    });
}

// Load Gold Listings
async function loadGoldListings(page = 1) {
    currentPage = page;
    
    const sort = document.getElementById('sortGold').value;
    const minPrice = document.getElementById('minPrice').value;
    const maxPrice = document.getElementById('maxPrice').value;
    
    let url = `/private/api/gold_trading.php?action=list_gold&page=${page}&limit=12&sort=${sort}`;
    
    if (minPrice) url += `&min_price=${minPrice}`;
    if (maxPrice) url += `&max_price=${maxPrice}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            displayGoldListings(data.listings);
            updatePagination('goldPagination', data.pagination, loadGoldListings);
        } else {
            showNotification('Failed to load gold listings: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    }
}

function displayGoldListings(listings) {
    const container = document.getElementById('goldListings');
    
    if (listings.length === 0) {
        container.innerHTML = '<div class="no-listings">No gold listings available</div>';
        return;
    }
    
    container.innerHTML = listings.map(listing => `
        <div class="gold-listing-card">
            <div class="listing-header">
                <h3>${listing.gold_amount} 🪙 Gold</h3>
                <span class="price">Rp ${Number(listing.price_per_gold).toLocaleString()}/gold</span>
            </div>
            
            <div class="listing-details">
                <div class="detail-item">
                    <span>Total Price:</span>
                    <span class="highlight">Rp ${Number(listing.total_price).toLocaleString()}</span>
                </div>
                <div class="detail-item">
                    <span>Seller:</span>
                    <span>${listing.seller_name}</span>
                </div>
                <div class="detail-item">
                    <span>Expires:</span>
                    <span>${new Date(listing.expires_at).toLocaleDateString()}</span>
                </div>
            </div>
            
            <button onclick="showBuyGoldModal('${listing.id}')" class="btn btn-primary btn-block">
                Buy Now
            </button>
        </div>
    `).join('');
}

// Sell Gold Functions
function calculateSellPrice() {
    const goldAmount = parseInt(document.getElementById('sellGoldAmount').value) || 0;
    const pricePerGold = parseInt(document.getElementById('sellPricePerGold').value) || 0;
    
    if (goldAmount > 0 && pricePerGold > 0) {
        const totalPrice = goldAmount * pricePerGold;
        const tradingFee = totalPrice * 0.05; // 5% fee
        const youReceive = totalPrice - tradingFee;
        
        document.getElementById('totalPrice').textContent = 'Rp ' + totalPrice.toLocaleString();
        document.getElementById('tradingFee').textContent = 'Rp ' + Math.round(tradingFee).toLocaleString();
        document.getElementById('youReceive').textContent = 'Rp ' + Math.round(youReceive).toLocaleString();
    }
}

document.getElementById('sellGoldForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const goldAmount = parseInt(document.getElementById('sellGoldAmount').value);
    const pricePerGold = parseInt(document.getElementById('sellPricePerGold').value);
    
    if (goldAmount < 10 || goldAmount > 1000) {
        showNotification('Gold amount must be between 10 and 1000', 'error');
        return;
    }
    
    if (pricePerGold < 800 || pricePerGold > 1500) {
        showNotification('Price must be between Rp 800 and Rp 1,500 per gold', 'error');
        return;
    }
    
    try {
        const response = await fetch('/private/api/gold_trading.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
            },
            body: JSON.stringify({
                action: 'create_listing',
                gold_amount: goldAmount,
                price_per_gold: pricePerGold
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Gold listing created successfully!', 'success');
            document.getElementById('sellGoldForm').reset();
            calculateSellPrice();
            
            // Switch to my listings tab
            document.querySelector('[data-tab="my_listings"]').click();
        } else {
            showNotification('Failed to create listing: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    }
});

// Buy Gold Functions
async function showBuyGoldModal(listingId) {
    currentListingId = listingId;
    
    try {
        const response = await fetch(`/private/api/gold_trading.php?action=list_gold&limit=100`);
        const data = await response.json();
        
        if (data.success) {
            const listing = data.listings.find(l => l.id === listingId);
            if (listing) {
                const content = document.getElementById('buyGoldContent');
                content.innerHTML = `
                    <div class="buy-gold-details">
                        <div class="detail-group">
                            <label>Gold Amount:</label>
                            <span>${listing.gold_amount} 🪙</span>
                        </div>
                        <div class="detail-group">
                            <label>Price per Gold:</label>
                            <span>Rp ${Number(listing.price_per_gold).toLocaleString()}</span>
                        </div>
                        <div class="detail-group">
                            <label>Total Price:</label>
                            <span class="highlight">Rp ${Number(listing.total_price).toLocaleString()}</span>
                        </div>
                        <div class="detail-group">
                            <label>Seller:</label>
                            <span>${listing.seller_name}</span>
                        </div>
                        <div class="detail-group">
                            <label>Seller Bank:</label>
                            <span>${listing.bank_name} - ${listing.account_number}</span>
                        </div>
                        <div class="detail-group">
                            <label>Account Holder:</label>
                            <span>${listing.account_holder}</span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button onclick="initiateGoldPurchase('${listing.id}')" class="btn btn-primary">
                            Confirm Purchase
                        </button>
                        <button onclick="closeModal('buyGoldModal')" class="btn btn-secondary">
                            Cancel
                        </button>
                    </div>
                `;
                
                showModal('buyGoldModal');
            }
        }
    } catch (error) {
        showNotification('Error loading listing details: ' + error.message, 'error');
    }
}

async function initiateGoldPurchase(listingId) {
    try {
        const response = await fetch('/private/api/gold_trading.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
            },
            body: JSON.stringify({
                action: 'buy_gold',
                listing_id: listingId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('buyGoldModal');
            showPaymentModal(data.transaction);
        } else {
            showNotification('Purchase failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    }
}

function showPaymentModal(transaction) {
    currentTransactionId = transaction.id;
    
    document.getElementById('sellerBankInfo').innerHTML = `
        <p><strong>Bank:</strong> ${transaction.seller_bank.bank_name}</p>
        <p><strong>Account:</strong> ${transaction.seller_bank.account_number}</p>
        <p><strong>Name:</strong> ${transaction.seller_bank.account_holder}</p>
    `;
    
    document.getElementById('paymentAmount').textContent = 'Rp ' + Number(transaction.amount).toLocaleString();
    document.getElementById('paymentTransactionId').value = transaction.id;
    
    showModal('paymentModal');
}

document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const transactionId = document.getElementById('paymentTransactionId').value;
    const transferDate = document.getElementById('paymentDate').value;
    const bankName = document.getElementById('paymentBank').value;
    const accountNumber = document.getElementById('paymentAccount').value;
    const proofImage = document.getElementById('paymentProof').files[0];
    
    if (!proofImage) {
        showNotification('Please upload proof of transfer', 'error');
        return;
    }
    
    // Convert image to base64
    const reader = new FileReader();
    reader.onload = async function() {
        const proofBase64 = reader.result;
        
        try {
            const response = await fetch('/private/api/gold_trading.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                },
                body: JSON.stringify({
                    action: 'confirm_payment',
                    transaction_id: transactionId,
                    proof_image: proofBase64,
                    transfer_date: transferDate,
                    bank_name: bankName,
                    account_number: accountNumber
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('Payment confirmed! Waiting for seller verification.', 'success');
                closeModal('paymentModal');
                document.getElementById('paymentForm').reset();
                loadTransactions();
            } else {
                showNotification('Payment confirmation failed: ' + data.message, 'error');
            }
        } catch (error) {
            showNotification('Network error: ' + error.message, 'error');
        }
    };
    
    reader.readAsDataURL(proofImage);
});

// My Listings Management
async function loadMyListings() {
    try {
        const response = await fetch('/private/api/gold_trading.php?action=list_gold&limit=100');
        const data = await response.json();
        
        if (data.success) {
            // Filter untuk listing milik user
            const myPlayerId = JSON.parse(localStorage.getItem('admin_user') || '{}').player_id;
            const myListings = data.listings.filter(listing => listing.seller_id === myPlayerId);
            displayMyListings(myListings);
        }
    } catch (error) {
        showNotification('Error loading listings: ' + error.message, 'error');
    }
}

function displayMyListings(listings) {
    const container = document.getElementById('myListings');
    
    if (listings.length === 0) {
        container.innerHTML = '<div class="no-listings">You have no active gold listings</div>';
        return;
    }
    
    container.innerHTML = listings.map(listing => `
        <div class="my-listing-card">
            <div class="listing-info">
                <h4>${listing.gold_amount} 🪙 Gold</h4>
                <div class="listing-details">
                    <span>Price: Rp ${Number(listing.price_per_gold).toLocaleString()}/gold</span>
                    <span>Total: Rp ${Number(listing.total_price).toLocaleString()}</span>
                    <span>Status: <span class="status-${listing.status}">${listing.status}</span></span>
                    <span>Expires: ${new Date(listing.expires_at).toLocaleDateString()}</span>
                </div>
            </div>
            
            ${listing.status === 'active' ? `
                <div class="listing-actions">
                    <button onclick="cancelListing('${listing.id}')" class="btn btn-danger">
                        Cancel Listing
                    </button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

async function cancelListing(listingId) {
    if (!confirm('Are you sure you want to cancel this gold listing? Your gold will be returned.')) {
        return;
    }
    
    try {
        const response = await fetch('/private/api/gold_trading.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
            },
            body: JSON.stringify({
                action: 'cancel_listing',
                listing_id: listingId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Listing cancelled successfully', 'success');
            loadMyListings();
        } else {
            showNotification('Failed to cancel listing: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    }
}

// Transaction Management
async function loadTransactions() {
    // This would require additional API endpoints for transaction history
    // For now, we'll show a placeholder
    document.getElementById('buyingTransactions').innerHTML = '<p>Transaction history coming soon...</p>';
    document.getElementById('sellingTransactions').innerHTML = '<p>Transaction history coming soon...</p>';
}

// Utility Functions
function showModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function showNotification(message, type) {
    // Use the same notification function from main.js
    if (typeof showNotification === 'undefined') {
        alert(message);
    } else {
        window.showNotification(message, type);
    }
}

function updatePagination(containerId, pagination, callback) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (pagination.pages <= 1) return;
    
    // Previous button
    if (pagination.page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&laquo;';
        prevBtn.onclick = () => callback(pagination.page - 1);
        container.appendChild(prevBtn);
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.pages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.textContent = i;
        pageBtn.onclick = () => callback(i);
        if (i === pagination.page) {
            pageBtn.classList.add('active');
        }
        container.appendChild(pageBtn);
    }
    
    // Next button
    if (pagination.page < pagination.pages) {
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '&raquo;';
        nextBtn.onclick = () => callback(pagination.page + 1);
        container.appendChild(nextBtn);
    }
}