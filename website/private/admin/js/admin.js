// Admin Panel JavaScript
let adminToken = null;
let currentAdmin = null;
let currentTransactionId = null;

// API Base URL
const API_BASE = '../../api';

// Initialize Admin Panel
document.addEventListener('DOMContentLoaded', function() {
    checkAdminAuth();
    setupEventListeners();
    startServerTime();
});

// Authentication Functions
async function checkAdminAuth() {
    const savedToken = localStorage.getItem('admin_token');
    const savedAdmin = localStorage.getItem('admin_user');
    
    if (savedToken && savedAdmin) {
        adminToken = savedToken;
        currentAdmin = JSON.parse(savedAdmin);
        showAdminPanel();
        loadDashboard();
    } else {
        showLoginSection();
    }
}

function showLoginSection() {
    document.getElementById('loginSection').classList.remove('hidden');
    document.querySelector('.main-content').style.display = 'none';
}

function showAdminPanel() {
    document.getElementById('loginSection').classList.add('hidden');
    document.querySelector('.main-content').style.display = 'block';
    document.getElementById('adminUsername').textContent = currentAdmin.username;
    document.getElementById('adminRole').textContent = currentAdmin.role;
}

// Login Form Handler
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/admin_auth.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            adminToken = data.token;
            currentAdmin = data.admin;
            
            localStorage.setItem('admin_token', adminToken);
            localStorage.setItem('admin_user', JSON.stringify(currentAdmin));
            
            showAdminPanel();
            loadDashboard();
            showNotification('Login successful!', 'success');
        } else {
            showNotification('Login failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
});

function logout() {
    adminToken = null;
    currentAdmin = null;
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
    showLoginSection();
    showNotification('Logged out successfully', 'success');
}

// Navigation
function setupEventListeners() {
    // Sidebar navigation
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active state
            document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
            this.classList.add('active');
            
            // Show selected section
            const section = this.getAttribute('data-section');
            showSection(section);
        });
    });
}

function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const sectionElement = document.getElementById(sectionName + 'Section');
    if (sectionElement) {
        sectionElement.classList.remove('hidden');
        
        // Load section data
        switch(sectionName) {
            case 'dashboard':
                loadDashboard();
                break;
            case 'topup':
                loadTopupTransactions();
                break;
            case 'players':
                loadPlayers();
                break;
            case 'auctions':
                loadAuctions();
                break;
            case 'transactions':
                loadTransactions();
                break;
            case 'settings':
                loadSettings();
                break;
            case 'logs':
                loadLogs();
                break;
        }
    }
}

// Dashboard Functions
async function loadDashboard() {
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/admin_dashboard.php`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${adminToken}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateDashboardStats(data.stats);
            updateRevenueChart(data.stats.recent_transactions);
            updateSystemStatus(data.stats);
        } else {
            showNotification('Failed to load dashboard: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function updateDashboardStats(stats) {
    document.getElementById('totalPlayers').textContent = stats.total_players.toLocaleString();
    document.getElementById('pendingTopups').textContent = stats.pending_topups.toLocaleString();
    document.getElementById('totalRevenue').textContent = 'Rp ' + (stats.total_revenue || 0).toLocaleString();
    document.getElementById('activeAuctions').textContent = stats.active_auctions.toLocaleString();
}

function updateRevenueChart(transactions) {
    const ctx = document.getElementById('revenueChartCanvas').getContext('2d');
    
    const labels = transactions.map(t => new Date(t.date).toLocaleDateString()).reverse();
    const revenue = transactions.map(t => t.revenue || 0).reverse();
    const count = transactions.map(t => t.count || 0).reverse();
    
    // Destroy existing chart if it exists
    if (window.revenueChart) {
        window.revenueChart.destroy();
    }
    
    window.revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue (Rp)',
                    data: revenue,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Transaction Count',
                    data: count,
                    backgroundColor: 'rgba(33, 150, 243, 0.6)',
                    borderColor: 'rgba(33, 150, 243, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (Rp)'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Transaction Count'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

function updateSystemStatus(stats) {
    document.getElementById('gameVersion').textContent = stats.game_version || '1.0.0';
    document.getElementById('maintenanceMode').textContent = 
        (stats.maintenance_mode === 'true') ? 'On' : 'Off';
}

// Top-up Management
async function loadTopupTransactions(page = 1) {
    showLoading(true);
    
    const status = document.getElementById('topupStatusFilter').value;
    
    try {
        const response = await fetch(`${API_BASE}/admin_topup.php?status=${status}&page=${page}&limit=10`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${adminToken}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayTopupTransactions(data.transactions);
            updatePagination('topupPagination', data.pagination, loadTopupTransactions);
        } else {
            showNotification('Failed to load transactions: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function displayTopupTransactions(transactions) {
    const tbody = document.getElementById('topupTableBody');
    tbody.innerHTML = '';
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No transactions found</td></tr>';
        return;
    }
    
    transactions.forEach(transaction => {
        const row = document.createElement('tr');
        
        const statusClass = `status-${transaction.status.replace('_', '-')}`;
        
        row.innerHTML = `
            <td>${transaction.id}</td>
            <td>${transaction.username} (${transaction.email})</td>
            <td>${transaction.gold_amount} 🪙</td>
            <td>Rp ${transaction.price.toLocaleString()}</td>
            <td><span class="status-badge ${statusClass}">${transaction.status}</span></td>
            <td>${new Date(transaction.created_at).toLocaleDateString()}</td>
            <td>
                ${transaction.status === 'waiting_verification' ? `
                    <button onclick="viewTopupDetail('${transaction.id}')" class="btn-primary btn-small">
                        <i class="fas fa-eye"></i> View
                    </button>
                ` : ''}
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

async function viewTopupDetail(transactionId) {
    showLoading(true);
    
    try {
        // In a real implementation, you might have a separate endpoint for transaction details
        // For now, we'll use the same list endpoint and find the transaction
        const response = await fetch(`${API_BASE}/admin_topup.php?status=waiting_verification&limit=100`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${adminToken}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const transaction = data.transactions.find(t => t.id === transactionId);
            if (transaction) {
                showTopupDetailModal(transaction);
            } else {
                showNotification('Transaction not found', 'error');
            }
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function showTopupDetailModal(transaction) {
    currentTransactionId = transaction.id;
    
    const content = document.getElementById('topupDetailContent');
    content.innerHTML = `
        <div class="transaction-detail">
            <div class="detail-group">
                <label>Transaction ID:</label>
                <span>${transaction.id}</span>
            </div>
            <div class="detail-group">
                <label>Player:</label>
                <span>${transaction.username} (${transaction.email})</span>
            </div>
            <div class="detail-group">
                <label>Player ID:</label>
                <span>${transaction.player_id}</span>
            </div>
            <div class="detail-group">
                <label>Gold Amount:</label>
                <span>${transaction.gold_amount} 🪙</span>
            </div>
            <div class="detail-group">
                <label>Price:</label>
                <span>Rp ${transaction.price.toLocaleString()}</span>
            </div>
            <div class="detail-group">
                <label>Payment Method:</label>
                <span>${transaction.payment_method || 'Bank Transfer'}</span>
            </div>
            <div class="detail-group">
                <label>Transfer Date:</label>
                <span>${transaction.transfer_date || 'N/A'}</span>
            </div>
            <div class="detail-group">
                <label>Bank Name:</label>
                <span>${transaction.bank_name || 'N/A'}</span>
            </div>
            <div class="detail-group">
                <label>Account Number:</label>
                <span>${transaction.account_number || 'N/A'}</span>
            </div>
            ${transaction.proof_image ? `
            <div class="detail-group">
                <label>Proof Image:</label>
                <div>
                    <img src="${transaction.proof_image}" alt="Payment Proof" style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            ` : ''}
            <div class="detail-group">
                <label>Submitted:</label>
                <span>${new Date(transaction.created_at).toLocaleString()}</span>
            </div>
        </div>
    `;
    
    showModal('topupDetailModal');
}

async function approveTopup() {
    if (!currentTransactionId) return;
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/admin_topup.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${adminToken}`
            },
            body: JSON.stringify({
                action: 'approve',
                transaction_id: currentTransactionId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Top-up approved successfully!', 'success');
            closeModal('topupDetailModal');
            loadTopupTransactions();
            loadDashboard(); // Refresh stats
        } else {
            showNotification('Approval failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function showRejectModal() {
    document.getElementById('rejectReason').value = '';
    showModal('rejectModal');
}

async function rejectTopup() {
    if (!currentTransactionId) return;
    
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) {
        showNotification('Please enter a rejection reason', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/admin_topup.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${adminToken}`
            },
            body: JSON.stringify({
                action: 'reject',
                transaction_id: currentTransactionId,
                notes: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Top-up rejected successfully!', 'success');
            closeModal('rejectModal');
            closeModal('topupDetailModal');
            loadTopupTransactions();
        } else {
            showNotification('Rejection failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

// Player Management
async function loadPlayers(page = 1, search = '') {
    showLoading(true);
    
    try {
        let url = `${API_BASE}/admin_players.php?page=${page}&limit=10`;
        if (search) {
            url += `&search=${encodeURIComponent(search)}`;
        }
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${adminToken}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayPlayers(data.players);
            updatePagination('playersPagination', data.pagination, (p) => loadPlayers(p, search));
        } else {
            showNotification('Failed to load players: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function displayPlayers(players) {
    const tbody = document.getElementById('playersTableBody');
    tbody.innerHTML = '';
    
    if (players.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No players found</td></tr>';
        return;
    }
    
    players.forEach(player => {
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td>${player.player_id}</td>
            <td>${player.username}</td>
            <td>${player.email}</td>
            <td>${player.silver.toLocaleString()} 💰</td>
            <td>${player.gold.toLocaleString()} 🪙</td>
            <td>${new Date(player.created_at).toLocaleDateString()}</td>
            <td>${player.last_login ? new Date(player.last_login).toLocaleDateString() : 'Never'}</td>
            <td>
                <button onclick="showAddCurrencyModal('${player.player_id}', '${player.username}')" class="btn-primary btn-small">
                    <i class="fas fa-coins"></i> Currency
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function showAddCurrencyModal(playerId = '', username = '') {
    document.getElementById('currencyPlayerId').value = playerId;
    document.getElementById('currencyAction').value = 'add_currency';
    document.getElementById('currencyType').value = 'gold';
    document.getElementById('currencyAmount').value = '';
    document.getElementById('currencyReason').value = '';
    
    showModal('addCurrencyModal');
}

async function processCurrencyAction() {
    const playerId = document.getElementById('currencyPlayerId').value;
    const action = document.getElementById('currencyAction').value;
    const currencyType = document.getElementById('currencyType').value;
    const amount = parseInt(document.getElementById('currencyAmount').value);
    const reason = document.getElementById('currencyReason').value.trim();
    
    if (!playerId || !amount || !reason) {
        showNotification('Please fill all fields', 'error');
        return;
    }
    
    if (amount <= 0) {
        showNotification('Amount must be positive', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/admin_players.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${adminToken}`
            },
            body: JSON.stringify({
                action: action,
                player_id: playerId,
                amount: amount,
                currency: currencyType,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Currency action completed successfully!', 'success');
            closeModal('addCurrencyModal');
            loadPlayers();
        } else {
            showNotification('Action failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

// Utility Functions
function showModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (show) {
        overlay.classList.remove('hidden');
    } else {
        overlay.classList.add('hidden');
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="notification-close">&times;</button>
    `;
    
    // Add styles if not already added
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 1rem 1.5rem;
                border-radius: 5px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                border-left: 4px solid #ccc;
                z-index: 4000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-width: 300px;
                max-width: 500px;
                animation: slideIn 0.3s ease-out;
            }
            .notification-success { border-left-color: #4caf50; }
            .notification-error { border-left-color: #f44336; }
            .notification-warning { border-left-color: #ff9800; }
            .notification-info { border-left-color: #2196f3; }
            .notification-content { display: flex; align-items: center; gap: 0.5rem; }
            .notification-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #999; }
            @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        `;
        document.head.appendChild(styles);
    }
    
    // Set color based on type
    const colors = {
        success: '#4caf50',
        error: '#f44336',
        warning: '#ff9800',
        info: '#2196f3'
    };
    notification.style.borderLeftColor = colors[type] || '#ccc';
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function updatePagination(containerId, pagination, callback) {
    const container = document.getElementById(containerId);
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

function debounceSearch() {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('playerSearch').value;
        loadPlayers(1, searchTerm);
    }, 500);
}

function startServerTime() {
    function updateTime() {
        const now = new Date();
        document.getElementById('serverTime').textContent = now.toLocaleString();
    }
    
    updateTime();
    setInterval(updateTime, 1000);
}

// Placeholder functions for other sections
function loadAuctions() {
    showNotification('Auction management coming soon!', 'info');
}

function loadTransactions() {
    showNotification('Transaction history coming soon!', 'info');
}

function loadSettings() {
    showNotification('System settings coming soon!', 'info');
}

function loadLogs() {
    showNotification('Activity logs coming soon!', 'info');
}