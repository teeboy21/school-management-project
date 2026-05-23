<?php
session_start();

$allowed_roles = ['admin', 'finance_manager', 'principal'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Reports</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Financial Reports</div>
                <div class="app-header-subtitle">Income, expenses, and profit overview.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="toolbar" style="margin-bottom:15px;">
                <select id="reportYear" onchange="loadReport()">
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card" style="flex-basis:100%;background:linear-gradient(135deg, #1e3a8a, #3b82f6);color:white;text-align:center;">
                    <div>School Account Balance</div>
                    <strong id="accountBalance" style="font-size:36px;">R 0</strong>
                </div>
            </div>

            <div class="panel" style="margin-bottom:20px;padding:15px;">
                <h3 style="margin-bottom:10px;">Quick Account Adjustment</h3>
                <form id="adjustForm" class="form-grid" style="display:flex;gap:10px;align-items:end;">
                    <div>
                        <label>Type</label>
                        <select id="adjustType" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="add">Add Funds</option>
                            <option value="subtract">Deduct Funds</option>
                        </select>
                    </div>
                    <div>
                        <label>Amount</label>
                        <input type="number" id="adjustAmount" step="0.01" min="1" required style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label>Description</label>
                        <input type="text" id="adjustDescription" placeholder="Reason" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card" style="flex-basis:calc(33% - 10px);">
                    <div>Total Revenue</div>
                    <strong id="totalRevenue" style="color:var(--app-success);">R 0</strong>
                </div>
                <div class="stat-card" style="flex-basis:calc(33% - 10px);">
                    <div>Donations</div>
                    <strong id="totalDonations" style="color:var(--app-success);">R 0</strong>
                </div>
                <div class="stat-card" style="flex-basis:calc(33% - 10px);">
                    <div>Gross Income</div>
                    <strong id="grossIncome" style="color:var(--app-success);">R 0</strong>
                </div>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div>Total Expenses</div>
                    <strong id="totalExpenses" style="color:var(--app-danger);">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Salaries Paid</div>
                    <strong id="totalSalaries" style="color:var(--app-danger);">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Project Spend</div>
                    <strong id="projectSpend" style="color:var(--app-danger);">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Total Expenditure</div>
                    <strong id="totalExpenditure" style="color:var(--app-danger);">R 0</strong>
                </div>
            </div>

            <div class="panel" style="margin-bottom:20px;">
                <div class="stat-card" style="text-align:center;">
                    <div style="font-size:18px;margin-bottom:10px;">Net Profit / (Loss)</div>
                    <strong id="netProfit" style="font-size:32px;">R 0</strong>
                </div>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div>School Account Balance</div>
                    <strong id="accountBalance">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Fees Expected</div>
                    <strong id="feesExpected">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Paid Fees</div>
                    <strong id="outstandingFees" style="color:var(--app-success);">R 0</strong>
                </div>
            </div>

            <div class="panel">
                <h2>Monthly Breakdown</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Revenue</th>
                                <th>Expenses</th>
                                <th>Salaries</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody id="monthlyTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="panel" style="margin-top:20px;">
                <h2>Expense Breakdown by Category</h2>
                <div id="expenseBreakdown" class="cards-grid"></div>
            </div>

            <div class="panel" style="margin-top:20px;">
                <h2>Yearly Comparison</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Revenue</th>
                                <th>Expenses</th>
                                <th>Profit/(Loss)</th>
                            </tr>
                        </thead>
                        <tbody id="yearlyComparison"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
function formatCurrency(amount) {
    return 'R ' + parseFloat(amount || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2});
}

async function loadReport() {
    const year = document.getElementById('reportYear').value;
    
    const summaryRes = await fetch(`API/financial_reports_api.php?action=summary&year=${year}`);
    const summary = await summaryRes.json();
    
    document.getElementById('accountBalance').textContent = formatCurrency(summary.account_balance);
    document.getElementById('totalRevenue').textContent = formatCurrency(summary.total_revenue);
    document.getElementById('totalDonations').textContent = formatCurrency(summary.total_donations);
    document.getElementById('grossIncome').textContent = formatCurrency(summary.gross_income);
    document.getElementById('totalExpenses').textContent = formatCurrency(summary.total_expenses);
    document.getElementById('totalSalaries').textContent = formatCurrency(summary.total_salaries);
    document.getElementById('projectSpend').textContent = formatCurrency(summary.total_project_spend);
    document.getElementById('totalExpenditure').textContent = formatCurrency(summary.total_expenditure);
    document.getElementById('netProfit').textContent = formatCurrency(summary.net_profit);
    document.getElementById('netProfit').style.color = summary.net_profit >= 0 ? 'var(--app-success)' : 'var(--app-danger)';
    document.getElementById('feesExpected').textContent = formatCurrency(summary.total_fees_expected);
    document.getElementById('outstandingFees').textContent = formatCurrency(summary.outstanding_fees);
    
    const monthlyRes = await fetch(`API/financial_reports_api.php?action=monthly_breakdown&year=${year}`);
    const monthly = await monthlyRes.json();
    
    document.getElementById('monthlyTable').innerHTML = monthly.map(m => `
        <tr>
            <td>${m.month}</td>
            <td>${formatCurrency(m.revenue)}</td>
            <td>${formatCurrency(m.expenses)}</td>
            <td>${formatCurrency(m.salaries)}</td>
            <td style="color:${m.net >= 0 ? 'var(--app-success)' : 'var(--app-danger)'}">${formatCurrency(m.net)}</td>
        </tr>
    `).join('');
    
    const breakdownRes = await fetch(`API/financial_reports_api.php?action=expense_breakdown&year=${year}`);
    const breakdown = await breakdownRes.json();
    
    document.getElementById('expenseBreakdown').innerHTML = breakdown.map(b => `
        <div class="content-card">
            <h3>${b.category.charAt(0).toUpperCase() + b.category.slice(1)}</h3>
            <strong>${formatCurrency(b.amount)}</strong>
        </div>
    `).join('') || '<p class="empty-state">No expenses recorded.</p>';
    
    const yearlyRes = await fetch('API/financial_reports_api.php?action=yearly_comparison');
    const yearly = await yearlyRes.json();
    
    document.getElementById('yearlyComparison').innerHTML = yearly.map(y => `
        <tr>
            <td>${y.year}</td>
            <td>${formatCurrency(y.revenue)}</td>
            <td>${formatCurrency(y.expenses)}</td>
            <td style="color:${y.profit >= 0 ? 'var(--app-success)' : 'var(--app-danger)'}">${formatCurrency(y.profit)}</td>
        </tr>
    `).join('');
}

document.getElementById('adjustForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const amount = parseFloat(document.getElementById('adjustAmount').value);
    if (!amount || amount <= 0) {
        Toast.show('Please enter a valid amount', 'error');
        return;
    }
    
    const payload = {
        type: document.getElementById('adjustType').value,
        amount: amount,
        description: document.getElementById('adjustDescription').value || 'Manual adjustment'
    };
    
    const res = await fetch('API/finance_api.php?action=adjust_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (data.status === 'success') {
        Toast.show('Account updated - Balance: ' + formatCurrency(data.new_balance), 'success');
        document.getElementById('adjustAmount').value = '';
        document.getElementById('adjustDescription').value = '';
        loadReport();
    } else {
        Toast.show(data.message || 'Failed to update account', 'error');
    }
});

loadReport();
</script>
</body>
</html>