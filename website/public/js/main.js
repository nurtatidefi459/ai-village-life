// Main JavaScript for AI Village Life Website

const API_BASE = 'http://localhost:8080/private/api';

// Check authentication status on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuthStatus();
    loadAuctionItems();
    loadForumPosts();
});

// Authentication Functions
function checkAuthStatus() {
    const playerId = localStorage.getItem('player_id');
    const token = localStorage.getItem('auth_token');
    
    if (playerId && token) {
        fetchPlayerInfo(playerId, token);
    }
}

function fetchPlayerInfo(playerId, token) {
    fetch(`${API_BASE}/player_info.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ player_id: playerId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showUserDashboard(data.player);
        }
    })
    .catch(error => {
        console.error('Error fetching player info:', error);
    });
}

function showUserDashboard(player) {
    document.getElementById('userDashboard').style.display = 'block';
    document.getElementById('playerInfo').innerHTML = `
        <p><strong>Player ID:</strong> ${player.player_id}</p>
        <p><strong>Username:</strong> ${player.username}</p>
        <p><strong>Gold:</strong> ${player.gold} 🪙</p>
        <p><strong>Silver:</strong> ${player.silver} 💰</p>
        <p><strong>Registered:</strong> ${new Date(player.created_at).toLocaleDateString()}</p>
    `;
}

// Registration
function register() {
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (password !== confirmPassword) {
        showNotification('Passwords do not match!', 'error');
        return;
    }
    
    fetch(`${API_BASE}/register.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            username: username,
            email: email,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Registration successful! You can now login.', 'success');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            showNotification(data.message || 'Registration failed!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// Login
function login() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    fetch(`${API_BASE}/login.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            email: email,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('player_id', data.player.player_id);
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('username', data.player.username);
            showNotification('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            showNotification(data.message || 'Login failed!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// Logout
function logout() {
    localStorage.removeItem('player_id');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('username');
    showNotification('Logged out successfully!', 'success');
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}

// Auction House
function loadAuctionItems() {
    fetch(`${API_BASE}/auction.php?action=get_items`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAuctionItems(data.items);
        }
    })
    .catch(error => {
        console.error('Error loading auction items:', error);
    });
}

function displayAuctionItems(items) {
    const container = document.getElementById('auctionItems');
    if (!container) return;
    
    container.innerHTML = items.map(item => `
        <div class="auction-item">
            <h4>${item.name}</h4>
            <p>${item.description}</p>
            <div class="item-price">${item.price} Gold</div>
            <p>Seller: ${item.seller_name}</p>
            <button onclick="bidOnItem('${item.id}')" class="btn btn-primary">Bid Now</button>
        </div>
    `).join('');
}

function bidOnItem(itemId) {
    if (!localStorage.getItem('player_id')) {
        showNotification('Please login to bid on items!', 'warning');
        return;
    }
    
    // Implement bidding logic
    showNotification('Bidding functionality coming soon!', 'warning');
}

// Forum Functions
function loadForumPosts() {
    fetch(`${API_BASE}/forum.php?action=get_posts`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayForumPosts(data.posts);
        }
    })
    .catch(error => {
        console.error('Error loading forum posts:', error);
    });
}

function displayForumPosts(posts) {
    const container = document.getElementById('forumPosts');
    if (!container) return;
    
    container.innerHTML = posts.map(post => `
        <div class="post">
            <div class="post-title">${post.title}</div>
            <div class="post-meta">By ${post.author} on ${new Date(post.created_at).toLocaleDateString()}</div>
            <p>${post.content.substring(0, 200)}...</p>
        </div>
    `).join('');
}

function createPost() {
    if (!localStorage.getItem('player_id')) {
        showNotification('Please login to create posts!', 'warning');
        return;
    }
    
    const title = document.getElementById('postTitle').value;
    const content = document.getElementById('postContent').value;
    
    fetch(`${API_BASE}/forum.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        },
        body: JSON.stringify({
            action: 'create_post',
            title: title,
            content: content
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Post created successfully!', 'success');
            document.getElementById('postTitle').value = '';
            document.getElementById('postContent').value = '';
            loadForumPosts();
        } else {
            showNotification(data.message || 'Failed to create post!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// Top Up Gold
function topUpGold() {
    const amount = document.getElementById('goldAmount').value;
    const paymentMethod = document.getElementById('paymentMethod').value;
    
    if (!amount || amount < 1) {
        showNotification('Please enter a valid amount!', 'error');
        return;
    }
    
    fetch(`${API_BASE}/topup.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        },
        body: JSON.stringify({
            amount: amount,
            payment_method: paymentMethod
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Successfully topped up ${amount} Gold!`, 'success');
            // Update player info display
            checkAuthStatus();
        } else {
            showNotification(data.message || 'Top-up failed!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// Utility Functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Game Download Tracking
function trackDownload() {
    // Track download for analytics
    fetch(`${API_BASE}/analytics.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'download',
            timestamp: new Date().toISOString()
        })
    });
}