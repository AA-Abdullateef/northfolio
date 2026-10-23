<script>
document.addEventListener('DOMContentLoaded', () => {
    showAssetsListView();
    fetchAssetsOverview();

    document.getElementById('back-to-assets-list-btn').addEventListener('click', () => {
        showAssetsListView();
    });
});

function showAssetsListView() {
    document.getElementById('asset-detail-view').style.display = 'none';
    document.getElementById('assets-list-view').style.display = 'block';
}

function showAssetDetailView() {
    document.getElementById('assets-list-view').style.display = 'none';
    document.getElementById('asset-detail-view').style.display = 'block';
}

async function fetchAssetsOverview() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    const tableBody = document.getElementById('assets-overview-table');
    const emptyAlert = document.getElementById('empty-assets-alert');

    try {
        const response = await fetch("{{ url('api/v1/assets') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        tableBody.innerHTML = '';

        if (result.success && result.data && Array.isArray(result.data.assets) && result.data.assets.length > 0) {
            emptyAlert.style.display = 'none';

            result.data.assets.forEach(entry => {
                const asset = entry.asset || {};
                const balance = entry.balance || 0;
                const label = asset.label || asset.name || 'Asset';

                tableBody.innerHTML += `
                    <tr class="border-bottom-0">
                        <td class="py-3 pl-0 font-weight-bold text-dark">
                            ${asset.icon ? `<img src="${asset.icon}" alt="" style="width:18px;height:18px;" class="mr-2 align-middle">` : ''}${label}
                        </td>
                        <td class="py-3 text-secondary">${balance}</td>
                        <td class="py-3 pr-0 text-end">
                            <button type="button" class="btn btn-outline-dark btn-xs py-0 px-2 font-weight-bold" style="font-size:11px; height:22px;" onclick="openAssetDetail('${asset.id}', '${label}', '${balance}')">
                                View
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            tableBody.innerHTML = '';
            emptyAlert.style.display = 'block';
        }
    } catch (error) {
        console.error('Assets overview fetch failure:', error);
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-danger">Unable to load assets right now.</td></tr>';
    }
}

async function openAssetDetail(assetId, label, balance) {
    document.getElementById('asset-detail-name').innerText = label;
    document.getElementById('asset-detail-balance').innerText = balance;
    showAssetDetailView();

    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    const tableBody = document.getElementById('asset-transactions-table');
    const emptyAlert = document.getElementById('empty-asset-transactions-alert');
    tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Loading transactions...</td></tr>';
    emptyAlert.style.display = 'none';

    try {
        const response = await fetch(`{{ url('api/v1/assets') }}/${assetId}/transactions`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        tableBody.innerHTML = '';

        if (result.success && result.data && Array.isArray(result.data.transactions) && result.data.transactions.length > 0) {
            // Server-side balance for this asset is authoritative; refresh the header with it.
            document.getElementById('asset-detail-balance').innerText = result.data.balance || balance;

            result.data.transactions.forEach(tx => {
                const direction = tx.meta ? tx.meta.direction : null;
                const isCredit = tx.type === 'deposit' || (tx.type === 'transfer' && direction === 'incoming');
                const sign = isCredit ? '+' : '-';
                let badgeClass = 'badge-secondary';
                const statusStr = (tx.status || 'completed').toLowerCase();

                if (statusStr === 'completed' || statusStr === 'success') badgeClass = 'badge-success';
                if (statusStr === 'pending') badgeClass = 'badge-warning';
                if (statusStr === 'cancelled' || statusStr === 'failed') badgeClass = 'badge-danger';

                tableBody.innerHTML += `
                    <tr class="border-bottom-0">
                        <td class="py-3 pl-0 text-muted font-family-monospace" style="font-size:11px;">${tx.id ? tx.id.toString().substring(0, 8) + '...' : 'N/A'}</td>
                        <td class="py-3 text-capitalize text-secondary">${tx.type_label || tx.type || 'Transfer'}</td>
                        <td class="py-3 font-weight-bold ${isCredit ? 'text-success' : 'text-dark'}">${sign}${tx.amount}</td>
                        <td class="py-3 pr-0"><span class="badge ${badgeClass} text-capitalize px-2 py-1">${tx.status_label || tx.status}</span></td>
                    </tr>
                `;
            });
        } else {
            tableBody.innerHTML = '';
            emptyAlert.style.display = 'block';
        }
    } catch (error) {
        console.error('Asset transactions fetch failure:', error);
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Unable to load transactions right now.</td></tr>';
    }
}
</script>
