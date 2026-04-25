<?php
// Verify Member Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Member - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand"><span>IECEP</span>-LSC</a>
        <ul class="navbar-nav">
            <li><a href="/">Home</a></li>
            <li><a href="/verify-payment.php">Verify Payment</a></li>
        </ul>
    </nav>

    <div style="max-width:700px;margin:40px auto;padding:0 24px">
        <h2 style="text-align:center;margin-bottom:24px">Verify Member</h2>
        <p class="text-center text-muted mb-3">Enter a Member ID or scan a QR code to verify membership.</p>

        <div id="result"></div>
    </div>

    <script src="/js/app.js"></script>
    <script>
        async function verifyMember(memberId) {
            const container = document.getElementById('result');
            container.innerHTML = '<div class="spinner"></div>';

            try {
                const resp = await fetch(`/api/verify-member?id=${memberId}`);
                const data = await resp.json();

                if (data.success && data.member) {
                    const m = data.member;
                    const paid = m.payment_status;

                    container.innerHTML = `
                        <div class="card text-center" style="border-left:4px solid ${paid ? '#28A745' : '#DC3545'}">
                            <h3 style="color:${paid ? '#28A745' : '#DC3545'}">
                                ${paid ? '✓ Verified Member' : '✗ Unpaid Member'}
                            </h3>
                            <div class="mt-2">
                                <p><strong>Name:</strong> ${m.full_name}</p>
                                <p><strong>Member ID:</strong> ${m.short_id}</p>
                                <p><strong>Type:</strong> <span class="badge badge-info">${m.member_type}</span></p>
                                <p><strong>Institution:</strong> ${m.institution}</p>
                                <p><strong>Payment:</strong> ${paid ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-danger">Unpaid</span>'}</p>
                                ${m.digital_id_url ? `<div class="mt-2"><img src="${m.digital_id_url}" alt="Digital ID" style="max-width:400px;width:100%;border-radius:12px"></div>` : ''}
                            </div>
                        </div>`;
                } else {
                    container.innerHTML = `<div class="card" style="border-left:4px solid #DC3545"><h3 style="color:#DC3545">Not Found</h3><p class="text-muted">No member found with this ID.</p></div>`;
                }
            } catch (err) {
                document.getElementById('result').innerHTML = `<div class="card" style="border-left:4px solid #DC3545"><h3 style="color:#DC3545">Error</h3><p>Verification failed.</p></div>`;
            }
        }

        // Check URL params
        const params = new URLSearchParams(window.location.search);
        const memberId = params.get('id');
        if (memberId) {
            verifyMember(memberId);
        } else {
            document.getElementById('result').innerHTML = `
                <div class="card" style="padding:32px">
                    <div class="form-group">
                        <label>Member ID</label>
                        <input type="text" class="form-control" id="member-id-input" placeholder="Enter member UUID">
                    </div>
                    <button class="btn btn-primary btn-block" onclick="verifyMember(document.getElementById('member-id-input').value)">Verify</button>
                </div>`;
        }
    </script>
</body>
</html>
