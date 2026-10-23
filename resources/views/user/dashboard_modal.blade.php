<script>
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    try {
        const response = await fetch("{{ url('api/v1/dashboard') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const rawText = await response.text();
        console.log("Raw API Response Text:", rawText);

        const result = JSON.parse(rawText);

        if (result.success && result.data) {
            // 1. Hydrate Wallet Identifiers Matrix Instantly
            if (result.data.wallet) {
                const wallet = result.data.wallet;
                document.getElementById('wallet-id').innerText = wallet.id || 'N/A';
                document.getElementById('wallet-public-key').innerText = wallet.public_id || 'N/A';
                document.getElementById('wallet-fingerprint').innerText = wallet.fingerprint || 'N/A';
            }

            // 2. Hydrate Ledger Balances & Individual Token Badges
            const badgesContainer = document.getElementById('asset-badges-container');
            if (badgesContainer) {
                badgesContainer.innerHTML = '';
                const assetBalances = Array.isArray(result.data.asset_balances)
                    ? result.data.asset_balances
                    : [];

                if (assetBalances.length > 0) {
                    assetBalances.forEach(entry => {
                        const asset = entry.asset || {};
                        const label = asset.label || asset.name || 'Asset';
                        const symbol = asset.name ? asset.name.toUpperCase() : '';
                        const balance = entry.balance || 0;

                        badgesContainer.innerHTML += `
                            <span class="badge badge-light border text-secondary px-2 py-1 mr-1 mb-1" style="font-size: 11px; font-weight: 500;">
                                <strong>${label}:</strong> ${balance} <small class="text-muted">${symbol}</small>
                            </span>
                        `;
                    });
                }
                
                const worthEl = document.getElementById('wallet-balance');
                if (worthEl) {
                    const totalBalance = Number(result.data.balance || 0);
                    worthEl.innerText = '$' + totalBalance.toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // 3. Hydrate Recent Transaction Activities List Rows Loop
            const tableBody = document.getElementById('transactions-log-table');
            const alertBox = document.getElementById('empty-transactions-alert');
            
            if (tableBody && alertBox) {
                tableBody.innerHTML = '';

                if (result.data.recent && Array.isArray(result.data.recent) && result.data.recent.length > 0) {
                    alertBox.style.cssText = "opacity: 0; height: 0; overflow: hidden; padding: 0 !important; margin: 0 !important;";
                    
                    result.data.recent.forEach(tx => {
                        if (!tx) return;

                        const txId = tx.id ? tx.id.toString().substring(0, 8) + '...' : 'N/A';
                        const symbol = tx.asset_symbol || tx.symbol || 'Crypto';
                        const type = tx.type || 'Transfer';
                        const amount = tx.amount || '0.00';
                        const status = tx.status || 'Completed';
                        const direction = tx.meta ? tx.meta.direction : null;
                        const isCredit = type === 'deposit' || (type === 'transfer' && direction === 'incoming');
                        const sign = isCredit ? '+' : '-';

                        let badgeClass = 'badge-secondary';
                        const normalizedStatus = status.toLowerCase();
                        
                        if(normalizedStatus === 'completed' || normalizedStatus === 'success') badgeClass = 'badge-success';
                        if(normalizedStatus === 'pending') badgeClass = 'badge-warning';
                        if(normalizedStatus === 'failed' || normalizedStatus === 'canceled') badgeClass = 'badge-danger';

                        tableBody.innerHTML += `
                            <tr class="border-bottom-0">
                                <td class="py-3 pl-0 text-muted font-family-monospace" style="font-size:11px;">${txId}</td>
                                <td class="py-3 font-weight-bold text-dark">${symbol}</td>
                                <td class="py-3 text-capitalize text-secondary">${type}</td>
                                <td class="py-3 font-weight-bold ${isCredit ? 'text-success' : 'text-dark'}">${sign}${amount}</td>
                                <td class="py-3 pr-0"><span class="badge ${badgeClass} text-capitalize px-2 py-1">${status}</span></td>
                            </tr>
                        `;
                    });
                } else {
                    tableBody.innerHTML = '';
                    alertBox.style.cssText = "opacity: 1; height: auto; padding: 20px 0 !important;";
                }
            }

        } else {
            localStorage.removeItem('auth_token');
            window.location.href = "{{ route('wallet.view.generate') }}";
        }
    } catch (error) {
        console.error('Workspace tracking network synchronizer crash:', error);
        document.getElementById('wallet-id').innerText = 'Synchronization Error';
    }
});

document.getElementById('logout-btn').addEventListener('click', async () => {
    const token = localStorage.getItem('auth_token');
    try {
        await fetch("{{ url('api/v1/logout') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });
    } catch (e) {
        console.error('API validation termination error:', e);
    } finally {
        localStorage.removeItem('auth_token');
        window.location.href = "{{ route('wallet.view.generate') }}";
    }
});
</script>
