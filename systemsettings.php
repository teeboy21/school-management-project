<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','it_technician'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">System Settings</div>
                <div class="app-header-subtitle">Configure system-wide preferences and module controls.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="hero-card">
                <h2>System Configuration</h2>
                <p>Changes take effect immediately. Settings are stored in the database.</p>
            </div>

            <div id="settingsContainer"></div>
            
            <div class="page-actions" style="margin-top:20px;">
                <button type="button" class="btn btn-primary" onclick="saveAllSettings()">Save All Settings</button>
                <a href="admindashboard.php" class="btn btn-secondary">Back To Dashboard</a>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
const categories = {
    general: { label: 'General', icon: '⚙️' },
    registration: { label: 'Registration', icon: '📝' },
    modules: { label: 'Modules', icon: '📦' },
    security: { label: 'Security', icon: '🔒' }
};

let settingsData = {};

async function loadSettings() {
    try {
        const res = await fetch('API/settings_api.php?action=get');
        if (!res.ok) {
            const err = await res.text();
            console.error('Settings API error:', err);
            document.getElementById('settingsContainer').innerHTML = '<div class="panel"><div class="empty-state">Error loading settings. Please refresh the page.</div></div>';
            return;
        }
        const data = await res.json();
        if (Array.isArray(data)) {
            settingsData = data;
            renderSettings();
        } else {
            console.error('Invalid settings data:', data);
            document.getElementById('settingsContainer').innerHTML = '<div class="panel"><div class="empty-state">Error: ' + (data.message || 'Invalid response') + '</div></div>';
        }
    } catch (e) {
        console.error('Load settings error:', e);
        document.getElementById('settingsContainer').innerHTML = '<div class="panel"><div class="empty-state">Error loading settings: ' + e.message + '</div></div>';
    }
}

function renderSettings() {
    const container = document.getElementById('settingsContainer');
    const grouped = {};
    
    settingsData.forEach(setting => {
        const cat = setting.category || 'general';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(setting);
    });
    
    let html = '';
    Object.keys(categories).forEach(catKey => {
        if (grouped[catKey]) {
            html += `
                <div class="panel" style="margin-bottom:20px;">
                    <h2>${categories[catKey].icon} ${categories[catKey].label}</h2>
                    <div class="form-grid">
                        ${grouped[catKey].map(s => renderSettingControl(s)).join('')}
                    </div>
                </div>
            `;
        }
    });
    
    container.innerHTML = html;
}

function renderSettingControl(setting) {
    const value = String(setting.setting_value || '');
    const id = `setting_${setting.setting_key}`;
    
    let input = '';
    if (setting.setting_type === 'select') {
        const options = setting.options || {};
        input = `
            <select id="${id}" name="${setting.setting_key}" data-key="${setting.setting_key}">
                ${Object.entries(options).map(([val, label]) => 
                    `<option value="${val}" ${String(val) === value ? 'selected' : ''}>${label}</option>`
                ).join('')}
            </select>
        `;
    } else if (setting.setting_type === 'textarea') {
        input = `<textarea id="${id}" name="${setting.setting_key}" data-key="${setting.setting_key}" placeholder="${setting.description || ''}">${value}</textarea>`;
    } else if (setting.setting_type === 'number') {
        input = `<input type="number" id="${id}" name="${setting.setting_key}" data-key="${setting.setting_key}" value="${value}" placeholder="${setting.description || ''}">`;
    } else if (setting.setting_type === 'date') {
        input = `<input type="date" id="${id}" name="${setting.setting_key}" data-key="${setting.setting_key}" value="${value}">`;
    } else {
        input = `<input type="text" id="${id}" name="${setting.setting_key}" data-key="${setting.setting_key}" value="${value}" placeholder="${setting.description || ''}">`;
    }
    
    return `
        <div class="full">
            <label for="${id}">${setting.description ? setting.description.split('.')[0] : setting.setting_key}</label>
            ${input}
            <p class="app-header-subtitle" style="margin-top:4px;font-size:12px;">${setting.description || ''}</p>
        </div>
    `;
}

async function saveAllSettings() {
    const inputs = document.querySelectorAll('[data-key]');
    const data = {};
    
    inputs.forEach(input => {
        data[input.dataset.key] = input.value;
    });
    
    console.log('Saving settings:', data);
    
    const res = await fetch('API/settings_api.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await res.json();
    console.log('Save result:', result);
    
    if (result.status === 'success') {
        Toast.show(result.message || 'Settings saved', 'success');
        loadSettings();
    } else {
        Toast.show(result.message || 'Failed to save settings', 'error');
    }
}

loadSettings();
</script>
</body>
</html>