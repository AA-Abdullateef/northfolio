<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    fetchTransactionLedgerEntries();

    document.getElementById('filter-form').addEventListener('submit', (e) => {
        e.preventDefault();
        currentPage = 1; 
        fetchTransactionLedgerEntries();
    });

    document.getElementById('prev-page-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchTransactionLedgerEntries();
        }
    });

    document.getElementById('next-page-btn').addEventListener('click', () => {
        currentPage++;
        fetchTransactionLedgerEntries();
    });
});

async function fetchTransactionLedgerEntries() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    const typeFilter = document.getElementById('filter-type').value;
    const statusFilter = document.getElementById('filter-status').value;

    let apiEndpointUrl = `{{ url('api/v1/transactions') }}?page=${currentPage}&per_page=15`;
    if (typeFilter) apiEndpointUrl += `&type=${typeFilter}`;
    if (statusFilter) apiEndpointUrl += `&status=${statusFilter}`;

    try {
        const response = await fetch(apiEndpointUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        const tableBody = document.getElementById('full-transactions-table-body');
        tableBody.innerHTML = '';
        const emptyBox = document.getElementById('empty-ledger-alert');
        const hasRows = !!(result.success && result.data && result.data.length > 0);
        if (emptyBox) emptyBox.style.display = hasRows ? 'none' : 'block';

        if (hasRows) {
            result.data.forEach(tx => {
                const direction = tx.meta ? tx.meta.direction : null;
                const isCredit = tx.type === 'deposit' || (tx.type === 'transfer' && direction === 'incoming');
                const sign = isCredit ? '+' : '-';
                let badgeClass = 'badge-secondary';
                const statusStr = (tx.status || 'Completed').toLowerCase();
                
                if (statusStr === 'completed' || statusStr === 'success') badgeClass = 'badge-success';
                if (statusStr === 'pending') badgeClass = 'badge-warning';
                if (statusStr === 'cancelled' || statusStr === 'failed') badgeClass = 'badge-danger';

                let actionButtonHtml = '';
                if (statusStr === 'pending') {
                    const isIncomingTransfer = tx.type === 'transfer' && direction === 'incoming';
                    const actionLabel = isIncomingTransfer ? 'Decline' : 'Cancel';
                    actionButtonHtml = `<button class="btn btn-outline-danger btn-xs py-0 px-2 font-weight-bold" onclick="cancelPendingTransaction('${tx.id}', ${isIncomingTransfer})" style="font-size:11px; height:22px;">${actionLabel}</button>`;
                } else {
                    actionButtonHtml = `<span class="text-muted small">—</span>`;
                }

                tableBody.innerHTML += `
                    <tr>
                        <td class="py-3 pl-0 text-muted font-family-monospace" style="font-size:11px;">${tx.id.substring(0, 8)}...</td>
                        <td class="py-3 font-weight-bold text-dark">${tx.asset_symbol || tx.symbol || 'Asset'}</td>
                        <td class="py-3 text-capitalize text-secondary">${tx.type || 'Transfer'}</td>
                        <td class="py-3 font-weight-bold ${isCredit ? 'text-success' : 'text-dark'}">${sign}${tx.amount}</td>
                        <td class="py-3"><span class="badge ${badgeClass} text-capitalize px-2 py-1">${tx.status}</span></td>
                        <td class="py-3 pr-0 text-end">${actionButtonHtml}</td>
                    </tr>
                `;
            });

            if (result.meta) {
                document.getElementById('pagination-info-txt').innerText = `Page ${result.meta.current_page} of ${result.meta.last_page}`;
                document.getElementById('prev-page-btn').disabled = (result.meta.current_page === 1);
                document.getElementById('next-page-btn').disabled = (result.meta.current_page === result.meta.last_page);
            }

        } else {
            tableBody.innerHTML = '';
        }
    } catch (error) {
        console.error('Ledger engine fetch execution crash:', error);
    }
}

async function cancelPendingTransaction(txId, isIncomingTransfer = false) {
    const confirmMessage = isIncomingTransfer
        ? 'Decline this incoming transfer? The sender\'s outgoing transfer will also be cancelled and the funds will return to them.'
        : 'Are you absolutely sure you want to cancel this pending transaction activity line item?';

    if (!confirm(confirmMessage)) return;

    const token = localStorage.getItem('auth_token');
    try {
        const response = await fetch(`{{ url('api/v1/transactions') }}/${txId}/cancel`, {
            method: 'POST', 
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token,
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ status: 'cancelled', _method: 'PATCH' }) 
        });

        const result = await response.json();

        if (result.success) {
            alert(isIncomingTransfer ? 'Transfer declined.' : 'Transaction successfully cancelled.');
            fetchTransactionLedgerEntries(); 
        } else {
            alert('Cancellation rejected: ' + result.message);
        }
    } catch (error) {
        console.error('Cancellation patch stream runtime failure:', error);
    }
}
</script>
