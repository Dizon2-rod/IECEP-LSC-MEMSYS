<?php
// Verify Payment Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payment - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand"><span>IECEP</span>-LSC</a>
        <ul class="navbar-nav">
            <li><a href="/">Home</a></li>
            <li><a href="/verify-member.php">Verify Member</a></li>
        </ul>
    </nav>

    <div style="max-width:700px;margin:40px auto;padding:0 24px">
        <h2 style="text-align:center;margin-bottom:24px">Verify Payment</h2>
        <p class="text-center text-muted mb-3">Enter a Receipt ID to verify a payment.</p>

        <div class="card" style="padding:32px">
            <div class="form-group">
                <label>Receipt ID</label>
                <input type="text" class="form-control" id="verify-input" placeholder="RCP-20250101...">
            </div>
            <button class="btn btn-primary btn-block" onclick="verifyPayment()">Verify</button>
        </div>

        <div id="result" class="mt-3"></div>
    </div>

    <script src="/js/app.js"></script>
    <script>
        async function verifyPayment() {
            const input = document.getElementById('verify-input').value.trim();
            if (!input) { App.toast('Enter a receipt ID', 'warning'); return; }

            const container = document.getElementById('result');
            container.innerHTML = '<div class="spinner"></div>';

            const params = `receipt_id=${input}`;
            const url = `/api/verify-payment?${params}`;

            try {
                const resp = await fetch(url);
                const data = await resp.json();

                if (data.success && data.transaction) {
                    const tx = data.transaction;
                    const verified = data.verified;

                    container.innerHTML = `
                        <div class="card" style="border-left:4px solid ${verified ? '#28A745' : '#DC3545'}">
                            <h3 style="color:${verified ? '#28A745' : '#DC3545'}">
                                ${verified ? '✓ Verified' : '✗ Not Verified'}
                            </h3>
                            <div class="mt-2">
                                <p><strong>Receipt ID:</strong> <code>${tx.receipt_id}</code></p>
                                <p><strong>Payer:</strong> ${tx.members?.full_name || tx.institutions?.name || 'N/A'}</p>
                                <p><strong>Amount:</strong> ${App.formatCurrency(tx.amount)}</p>
                                <p><strong>Date:</strong> ${App.formatDate(tx.paid_at)}</p>
                                <p><strong>Description:</strong> ${tx.description || '-'}</p>
                            </div>
                        </div>`;
                } else {
                    container.innerHTML = `<div class="card" style="border-left:4px solid #DC3545"><h3 style="color:#DC3545">Not Found</h3><p class="text-muted">No payment record found for this query.</p></div>`;
                }
            } catch (err) {
                container.innerHTML = `<div class="card" style="border-left:4px solid #DC3545"><h3 style="color:#DC3545">Error</h3><p>Failed to verify. Please try again.</p></div>`;
            }
        }
    </script>
</body>
</html>
