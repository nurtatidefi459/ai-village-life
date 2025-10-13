let currentTransaction = null;

function selectPackage(goldAmount, price) {
    document.getElementById('selectedGold').value = goldAmount;
    document.getElementById('selectedPrice').value = price;
    document.getElementById('paymentAmount').textContent = 'Rp ' + price.toLocaleString();
    
    document.getElementById('paymentSection').style.display = 'block';
    document.getElementById('transactionHistory').style.display = 'none';
    
    // Scroll to payment section
    document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const goldAmount = document.getElementById('selectedGold').value;
    const price = document.getElementById('selectedPrice').value;
    const transferDate = document.getElementById('transferDate').value;
    const yourBank = document.getElementById('yourBank').value;
    const yourAccount = document.getElementById('yourAccount').value;
    const proofImage = document.getElementById('proofImage').files[0];
    
    if (!goldAmount || !price) {
        alert('Please select a gold package first');
        return;
    }
    
    // Create top-up order first
    const orderResponse = await fetch('/private/api/topup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
        },
        body: JSON.stringify({
            amount: parseInt(goldAmount),
            payment_method: 'bank_transfer'
        })
    });
    
    const orderData = await orderResponse.json();
    
    if (!orderData.success) {
        alert('Failed to create order: ' + orderData.message);
        return;
    }
    
    currentTransaction = orderData.transaction_id;
    
    // Convert image to base64
    const reader = new FileReader();
    reader.onload = async function() {
        const proofBase64 = reader.result;
        
        // Confirm payment
        const confirmResponse = await fetch('/private/api/confirm_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
            },
            body: JSON.stringify({
                transaction_id: currentTransaction,
                proof_image: proofBase64,
                transfer_date: transferDate,
                bank_name: yourBank,
                account_number: yourAccount
            })
        });
        
        const confirmData = await confirmResponse.json();
        
        if (confirmData.success) {
            alert('Payment confirmation submitted! You will receive your gold after verification.');
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentSection').style.display = 'none';
            loadTransactionHistory();
        } else {
            alert('Confirmation failed: ' + confirmData.message);
        }
    };
    
    reader.readAsDataURL(proofImage);
});

async function loadTransactionHistory() {
    const response = await fetch('/private/api/topup.php', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
        }
    });
    
    const data = await response.json();
    
    if (data.success) {
        const historyList = document.getElementById('historyList');
        historyList.innerHTML = '';
        
        if (data.transactions.length === 0) {
            historyList.innerHTML = '<p>No transactions yet.</p>';
        } else {
            data.transactions.forEach(transaction => {
                const transactionDiv = document.createElement('div');
                transactionDiv.className = 'transaction-item';
                transactionDiv.innerHTML = `
                    <div class="transaction-header">
                        <span class="transaction-id">${transaction.id}</span>
                        <span class="transaction-status ${transaction.status}">${transaction.status}</span>
                    </div>
                    <div class="transaction-details">
                        <p>Gold: ${transaction.gold_amount} 🪙</p>
                        <p>Amount: Rp ${transaction.price.toLocaleString()}</p>
                        <p>Date: ${new Date(transaction.created_at).toLocaleDateString()}</p>
                    </div>
                `;
                historyList.appendChild(transactionDiv);
            });
        }
        
        document.getElementById('transactionHistory').style.display = 'block';
    }
}

// Check authentication on page load
document.addEventListener('DOMContentLoaded', function() {
    if (!localStorage.getItem('auth_token')) {
        alert('Please login first');
        window.location.href = 'login.html';
        return;
    }
    
    loadTransactionHistory();
});