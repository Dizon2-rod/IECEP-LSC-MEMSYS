<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Member - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pAPej8hP0q9/" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; margin: 0; }
        .verify-page { max-width: 900px; margin: 60px auto; padding: 24px; }
        .verify-card { background: #fff; border-radius: 22px; box-shadow: 0 20px 50px rgba(11,29,74,0.08); overflow: hidden; }
        .verify-header { padding: 32px; background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%); color: #fff; }
        .verify-header h1 { margin: 0; font-size: 2rem; }
        .verify-section { padding: 32px; }
        .verify-form input { width: 100%; padding: 14px 16px; border: 1px solid #cbd5e1; border-radius: 14px; margin-top: 12px; }
        .verify-form button { margin-top: 18px; }
        .notification-box { padding: 24px; border-radius: 18px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .member-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 24px; }
        .member-summary .summary-card { background: #f8fafc; border-radius: 16px; padding: 18px; }
        .member-summary .summary-card h3 { margin-bottom: 10px; }
        .member-details dt { font-weight: 600; margin-top: 12px; }
        .member-details dd { margin: 0 0 12px 0; color: #475569; }
    </style>
</head>
<body>
    <div class="verify-page">
        <div class="verify-card">
            <div class="verify-header">
                <h1>Verify Membership</h1>
                <p style="opacity: .85; margin-top: 12px; max-width: 700px;">Enter a membership QR code hash or Member ID to confirm the status and obtain digital ID verification.</p>
            </div>
            <div class="verify-section">
                <div class="verify-form">
                    <label for="member-id-input">Member ID or Digital Hash</label>
                    <input type="text" id="member-id-input" placeholder="Paste membership ID or scan result" />
                    <button class="btn btn-primary" onclick="verifyMember(document.getElementById('member-id-input').value)">Verify Member</button>
                </div>

                <div id="result" class="mt-4"></div>
            </div>
        </div>
    </div>

    <script>
        async function verifyMember(memberId) {
            const container = document.getElementById('result');
            if (!memberId || memberId.trim().length === 0) {
                container.innerHTML = '<div class="notification-box"><strong>Please enter a valid Member ID or hash.</strong></div>';
                return;
            }

            container.innerHTML = '<div class="notification-box"><em>Verifying, please wait...</em></div>';

            try {
                const response = await fetch(`/IECEP-LSC-MEMSYS/public/api/verify-member.php?id=${encodeURIComponent(memberId.trim())}`);
                const data = await response.json();

                if (data.success && data.member) {
                    const m = data.member;
                    const statusColor = m.payment_status ? '#16a34a' : '#dc2626';
                    container.innerHTML = `
                        <div class="notification-box" style="border-color: ${statusColor};">
                            <h2 style="margin-bottom: 12px; color: ${statusColor};">${m.payment_status ? 'Verified Member' : 'Member Found'}</h2>
                            <dl class="member-details">
                                <dt>Name</dt><dd>${m.full_name}</dd>
                                <dt>Member ID</dt><dd>${m.short_id}</dd>
                                <dt>Type</dt><dd>${m.member_type}</dd>
                                <dt>Institution</dt><dd>${m.institution}</dd>
                                <dt>Payment Status</dt><dd>${m.payment_status ? '<span style="color: #16a34a; font-weight: 700;">Paid</span>' : '<span style="color: #dc2626; font-weight: 700;">Unpaid</span>'}</dd>
                            </dl>
                        </div>
                    `;

                    if (m.digital_id_url) {
                        container.innerHTML += `
                            <div class="member-summary">
                                <div class="summary-card">
                                    <h3>Digital ID Link</h3>
                                    <p><a href="${m.digital_id_url}" target="_blank">Open digital ID</a></p>
                                </div>
                                <div class="summary-card">
                                    <h3>Verification Code</h3>
                                    <p>${m.digital_id_hash || 'Not available'}</p>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    container.innerHTML = '<div class="notification-box" style="border-color: #dc2626; color: #b91c1c;"><strong>Member record not found.</strong> Please check the ID and try again.</div>';
                }
            } catch (err) {
                container.innerHTML = '<div class="notification-box" style="border-color: #dc2626; color: #b91c1c;"><strong>Verification failed.</strong> Please try again later.</div>';
            }
        }

        const params = new URLSearchParams(window.location.search);
        const memberIdFromUrl = params.get('id');
        if (memberIdFromUrl) {
            document.getElementById('member-id-input').value = memberIdFromUrl;
            verifyMember(memberIdFromUrl);
        }
    </script>
</body>
</html>
