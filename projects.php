<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'system_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Development Projects</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Development Projects</div>
                <div class="app-header-subtitle">Track school infrastructure and development progress.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="toolbar" style="margin-bottom:15px;">
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="switchTab('list')">All Projects</button>
                    <button class="tab-btn" onclick="switchTab('create')">New Project</button>
                </div>
                <select id="projectFilter" onchange="loadProjects()" style="margin-left:auto;">
                    <option value="">All Status</option>
                    <option value="planning">Planning</option>
                    <option value="approved">Approved</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="on_hold">On Hold</option>
                </select>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div>Total Budget</div>
                    <strong id="totalBudget">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Total Spent</div>
                    <strong id="totalSpent">R 0</strong>
                </div>
                <div class="stat-card">
                    <div>Active Projects</div>
                    <strong id="activeProjects">0</strong>
                </div>
                <div class="stat-card">
                    <div>Completed</div>
                    <strong id="completedProjects">0</strong>
                </div>
            </div>

            <div id="tab-list" class="tab-content">
                <div id="projectsGrid" class="cards-grid"></div>
            </div>

            <div id="tab-create" class="tab-content" style="display:none;">
                <div class="panel">
                    <h2>Create Development Project</h2>
                    <form id="projectForm" class="form-grid">
                        <input type="hidden" id="projectId">
                        <div>
                            <label>Project Name</label>
                            <input type="text" id="projectName" required placeholder="New Computer Lab">
                        </div>
                        <div>
                            <label>Project Type</label>
                            <select id="projectType">
                                <option value="infrastructure">Infrastructure</option>
                                <option value="equipment">Equipment</option>
                                <option value="technology">Technology</option>
                                <option value="renovation">Renovation</option>
                                <option value="expansion">Expansion</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Priority</label>
                            <select id="projectPriority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label>Status</label>
                            <select id="projectStatus">
                                <option value="planning">Planning</option>
                                <option value="approved">Approved</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="on_hold">On Hold</option>
                            </select>
                        </div>
                        <div>
                            <label>Budget Amount</label>
                            <input type="number" id="projectBudget" step="0.01" required placeholder="0.00">
                        </div>
                        <div>
                            <label>Approved Budget</label>
                            <input type="number" id="approvedBudget" step="0.01" placeholder="0.00">
                        </div>
                        <div>
                            <label>Start Date</label>
                            <input type="date" id="startDate">
                        </div>
                        <div>
                            <label>Expected End Date</label>
                            <input type="date" id="endDate">
                        </div>
                        <div class="full">
                            <label>Description</label>
                            <textarea id="projectDescription" placeholder="Project details and scope"></textarea>
                        </div>
                        <div class="full">
                            <label>Notes</label>
                            <textarea id="projectNotes" placeholder="Additional notes"></textarea>
                        </div>
                        <div class="page-actions">
                            <button type="submit" class="btn btn-primary">Save Project</button>
                            <button type="button" class="btn btn-secondary" onclick="clearProjectForm()">Clear</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="projectDetails" class="panel" style="display:none;margin-top:20px;">
                <h2>Project Details</h2>
                <div id="projectDetailContent"></div>
                
                <div style="margin-top:20px;">
                    <h3>Record Expenditure</h3>
                    <form id="expenseForm" class="form-grid">
                        <input type="hidden" id="expenseProjectId">
                        <div>
                            <label>Description</label>
                            <input type="text" id="expenseDescription" required placeholder="What was purchased">
                        </div>
                        <div>
                            <label>Amount</label>
                            <input type="number" id="expenseAmount" step="0.01" required placeholder="0.00">
                        </div>
                        <div>
                            <label>Vendor</label>
                            <input type="text" id="expenseVendor" placeholder="Company name">
                        </div>
                        <div>
                            <label>Date</label>
                            <input type="date" id="expenseDate" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label>Invoice Number</label>
                            <input type="text" id="expenseInvoice" placeholder="Optional">
                        </div>
                        <div class="page-actions">
                            <button type="submit" class="btn btn-primary">Record Expense</button>
                        </div>
                    </form>
                </div>

                <div style="margin-top:20px;">
                    <h3>Project Expenditure History</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Vendor</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="projectExpenseTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
let currentTab = 'list';

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    event.target.classList.add('active');
}

function formatCurrency(amount) {
    return 'R ' + parseFloat(amount || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2});
}

async function loadProjects() {
    const status = document.getElementById('projectFilter').value;
    const res = await fetch(`API/financial_reports_api.php?action=get_projects&status=${status}`);
    const projects = await res.json();
    
    let totalBudget = 0, totalSpent = 0, active = 0, completed = 0;
    
    const grid = document.getElementById('projectsGrid');
    grid.innerHTML = projects.length === 0 
        ? '<p class="empty-state" style="flex-basis:100%;">No projects found.</p>'
        : projects.map(p => {
            totalBudget += parseFloat(p.budget || 0);
            totalSpent += parseFloat(p.spent || 0);
            if (p.status === 'in_progress' || p.status === 'approved') active++;
            if (p.status === 'completed') completed++;
            
            const progress = p.budget > 0 ? ((p.spent / p.budget) * 100).toFixed(0) : 0;
            
            return `<div class="content-card" style="cursor:pointer" onclick="viewProject(${p.id})">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <h3>${p.project_name}</h3>
                    <span class="badge badge-${p.status}">${p.status.replace('_', ' ')}</span>
                </div>
                <p style="margin:5px 0;font-size:12px;color:var(--app-muted);">${p.project_type} | Priority: ${p.priority}</p>
                <p style="margin:10px 0;">${p.description || ''}</p>
                <div style="margin:10px 0;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;">
                        <span>Budget: ${formatCurrency(p.budget)}</span>
                        <span>Spent: ${formatCurrency(p.spent)}</span>
                    </div>
                    <div style="background:#e5e7eb;border-radius:4px;height:8px;margin-top:5px;">
                        <div style="background:var(--app-primary);height:100%;width:${progress}%;border-radius:4px;"></div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button class="btn btn-small" onclick="event.stopPropagation();editProject(${p.id})">Edit</button>
                    <button class="btn btn-small btn-danger" onclick="event.stopPropagation();deleteProject(${p.id})">Delete</button>
                </div>
            </div>`;
        }).join('');
    
    document.getElementById('totalBudget').textContent = formatCurrency(totalBudget);
    document.getElementById('totalSpent').textContent = formatCurrency(totalSpent);
    document.getElementById('activeProjects').textContent = active;
    document.getElementById('completedProjects').textContent = completed;
    
    const tableHtml = `
        <table class="data-table" style="margin-top:20px;width:100%;border-collapse:collapse;">
            <thead><tr><th>Project</th><th>Type</th><th>Budget</th><th>Spent</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>${projects.map(p => `
                <tr>
                    <td>${p.project_name}</td>
                    <td>${p.project_type}</td>
                    <td>${formatCurrency(p.budget)}</td>
                    <td>${formatCurrency(p.spent)}</td>
                    <td><select onchange="updateProjectStatus(${p.id}, this.value)" style="padding:4px;">
                        <option value="planning" ${p.status === 'planning' ? 'selected' : ''}>Planning</option>
                        <option value="approved" ${p.status === 'approved' ? 'selected' : ''}>Approved</option>
                        <option value="in_progress" ${p.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="completed" ${p.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="on_hold" ${p.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                    </select></td>
                    <td><button class="btn btn-small btn-danger" onclick="deleteProject(${p.id})">Delete</button></td>
                </tr>
            `).join('')}</tbody>
        </table>
    `;
    document.getElementById('projectsGrid').insertAdjacentHTML('afterend', tableHtml);
}

async function viewProject(id) {
    const res = await fetch(`API/financial_reports_api.php?action=get_projects`);
    const projects = await res.json();
    const project = projects.find(p => p.id == id);
    
    if (!project) return;
    
    document.getElementById('projectDetails').style.display = 'block';
    document.getElementById('projectDetailContent').innerHTML = `
        <div class="cards-grid">
            <div class="stat-card"><div>Project Name</div><strong>${project.project_name}</strong></div>
            <div class="stat-card"><div>Type</div><strong>${project.project_type}</strong></div>
            <div class="stat-card"><div>Budget</div><strong>${formatCurrency(project.budget)}</strong></div>
            <div class="stat-card"><div>Spent</div><strong>${formatCurrency(project.spent)}</strong></div>
        </div>
        <div class="cards-grid" style="margin-top:15px;">
            <div class="stat-card"><div>Status</div><span class="badge badge-${project.status}">${project.status}</span></div>
            <div class="stat-card"><div>Start Date</div><strong>${project.start_date || 'N/A'}</strong></div>
            <div class="stat-card"><div>Expected End</div><strong>${project.expected_end_date || 'N/A'}</strong></div>
        </div>
        ${project.description ? `<p style="margin-top:15px;">${project.description}</p>` : ''}
    `;
    
    document.getElementById('expenseProjectId').value = id;
    
    const expRes = await fetch(`API/financial_reports_api.php?action=get_project_expenses&project_id=${id}`);
    const expenses = await expRes.json();
    
    document.getElementById('projectExpenseTable').innerHTML = expenses.length === 0
        ? '<tr><td colspan="4" class="empty-state">No expenditure recorded.</td></tr>'
        : expenses.map(e => `<tr>
            <td>${e.expenditure_date}</td>
            <td>${e.description}</td>
            <td>${e.vendor_name || '-'}</td>
            <td>${formatCurrency(e.amount)}</td>
        </tr>`).join('');
    
    document.getElementById('tab-create').style.display = 'none';
    document.getElementById('tab-list').style.display = 'none';
    document.getElementById('projectDetails').style.display = 'block';
}

function editProject(id) {
    Toast.show('Select the project from the list to edit', 'info');
}

function clearProjectForm() {
    document.getElementById('projectForm').reset();
    document.getElementById('projectId').value = '';
}

async function deleteProject(id) {
    if (!confirm('Delete this project and all its expenses?')) return;
    const res = await fetch(`API/financial_reports_api.php?action=delete_project&id=${id}`);
    const data = await res.json();
    if (data.status === 'deleted') {
        Toast.show('Project deleted', 'success');
        loadProjects();
        document.getElementById('projectDetails').style.display = 'none';
    } else {
        Toast.show(data.message || 'Failed to delete', 'error');
    }
}

document.getElementById('projectForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('projectId').value || null,
        project_name: document.getElementById('projectName').value,
        description: document.getElementById('projectDescription').value || '',
        project_type: document.getElementById('projectType').value,
        budget: parseFloat(document.getElementById('projectBudget').value) || 0,
        approved_budget: parseFloat(document.getElementById('approvedBudget').value) || 0,
        start_date: document.getElementById('startDate').value || null,
        expected_end_date: document.getElementById('endDate').value || null,
        status: document.getElementById('projectStatus').value,
        priority: document.getElementById('projectPriority').value,
        notes: document.getElementById('projectNotes').value || ''
    };
    
    const res = await fetch('API/financial_reports_api.php?action=save_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    
    if (!res.ok) {
        alert('Failed to connect to server. Please try again.');
        return;
    }
    
    const data = await res.json();
    
    if (data.status === 'success') {
        alert('Project saved successfully!');
        clearProjectForm();
        switchTab('list');
        loadProjects();
    } else {
        alert(data.message || 'Failed to save project');
    }
});

document.getElementById('expenseForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const projectId = document.getElementById('expenseProjectId').value;
    if (!projectId) {
        Toast.show('Please select a project first by clicking on one from the list', 'error');
        return;
    }
    
    const description = document.getElementById('expenseDescription').value;
    if (!description) {
        Toast.show('Please enter a description', 'error');
        return;
    }
    
    const payload = {
        project_id: parseInt(projectId),
        description: description,
        amount: parseFloat(document.getElementById('expenseAmount').value),
        expenditure_date: document.getElementById('expenseDate').value,
        vendor_name: document.getElementById('expenseVendor').value || '',
        invoice_number: document.getElementById('expenseInvoice').value || ''
    };
    
    if (!payload.amount || payload.amount <= 0) {
        Toast.show('Please enter a valid amount', 'error');
        return;
    }
    
    const res = await fetch('API/financial_reports_api.php?action=add_project_expense', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (data.status === 'success') {
        Toast.show('Project expense recorded successfully', 'success');
        document.getElementById('expenseForm').reset();
        loadProjects();
        viewProject(payload.project_id);
    } else {
        Toast.show(data.message || 'Failed to record expense', 'error');
        console.error('Error:', data);
    }
});

async function updateProjectStatus(id, status) {
    const res = await fetch('API/financial_reports_api.php?action=save_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    });
    const data = await res.json();
    if (data.status === 'success') {
        Toast.show('Status updated', 'success');
    } else {
        alert(data.message || 'Failed to update status');
    }
}

loadProjects();
</script>
</body>
</html>