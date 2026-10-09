// admin/assets/js/admin.js
// Admin Control Panel Interactive Scripts & Live Sync Bridge

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (mobileToggle && sidebar && overlay) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    // Close modals on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.show').forEach(el => {
                el.classList.remove('show');
            });
        }
    });
});

// 2. Toast Engine
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    
    let icon = 'ℹ️';
    let borderColor = 'var(--primary)';
    if (type === 'success') { icon = '✅'; borderColor = 'var(--success)'; }
    if (type === 'error' || type === 'danger') { icon = '❌'; borderColor = 'var(--danger)'; }
    if (type === 'warning') { icon = '⚠️'; borderColor = 'var(--warning)'; }

    toast.style.borderLeft = `4px solid ${borderColor}`;
    const iconElement = document.createElement('span');iconElement.style.fontSize='18px';iconElement.textContent=icon;
    const messageElement = document.createElement('span');messageElement.textContent=message;
    toast.append(iconElement, messageElement);
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// 3. Modal Helpers
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// A key belongs to a user operation; network failures retain it for retry.
const adminOperationKeys = new Map();
const adminPendingCommands = new Map();
function adminRequestId() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
}
function adminCsrfToken() { return document.querySelector('meta[name="admin-csrf-token"]')?.content || ''; }
async function waitForAdminCommand(commandId, timeoutMs = 120000) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        const response = await fetch('/webgoc2026/admin/api/live_sync.php?check_id=' + encodeURIComponent(commandId), {headers: {'X-CSRF-Token': adminCsrfToken()}});
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Cannot read command status');
        if (result.data.status === 'completed') return {status: 'success', command_id: commandId, message: result.data.result_message || 'Đã áp dụng thành công'};
        if (result.data.status === 'failed') return {status: 'error', command_id: commandId, message: result.data.result_message || 'Máy chủ từ chối yêu cầu'};
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
    return {status: 'queued', command_id: commandId, message: 'Lệnh vẫn đang chờ xử lý. Không gửi lại thao tác.'};
}
async function adminCommandFetch(url, options) {
    const data = JSON.parse(options.body);
    if (data.action === 'update_pet') {
        const fields = {pet_name:'petName',pet_type:'petType',pet_power:'petPower',pet_tiem_nang:'petTiemNang',pet_hpg:'petHpg',pet_mpg:'petMpg',pet_dameg:'petDameg',pet_skill_1:'petSkill1',pet_skill_2:'petSkill2',pet_skill_3:'petSkill3',pet_skill_4:'petSkill4'};
        Object.entries(fields).forEach(([key, id]) => {
            const field = document.getElementById(id);
            if (field && field.dataset.originalValue === String(data[key])) delete data[key];
        });
    }
    const signature = url + JSON.stringify(data);
    if (!adminOperationKeys.has(signature)) adminOperationKeys.set(signature, adminRequestId());
    data.request_id = adminOperationKeys.get(signature);
    data.csrf_token = adminCsrfToken();
    let commandId = adminPendingCommands.get(signature);
    if (!commandId) {
        const response = await fetch(url, {...options, body: JSON.stringify(data)});
        const result = await response.json();
        if (!response.ok || !['queued', 'success'].includes(result.status)) return {json: async () => result};
        if (!result.command_id) return {json: async () => result};
        commandId = result.command_id;
        adminPendingCommands.set(signature, commandId);
    }
    showToast('Lệnh #' + commandId + ' đang chờ máy chủ xử lý', 'info');
    const final = await waitForAdminCommand(commandId);
    if (final.status !== 'queued') {
        adminOperationKeys.delete(signature);
        adminPendingCommands.delete(signature);
    }
    return {json: async () => final};
}
function triggerLiveSync(commandType, payload = {}, callback = null) {
    return adminCommandFetch('/webgoc2026/admin/api/live_sync.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({command_type: commandType, payload})
    }).then(response => response.json()).then(data => {
        showToast(data.message, data.status === 'success' ? 'success' : (data.status === 'queued' ? 'info' : 'error'));
        if (data.status === 'success' && callback) callback(data);
        return data;
    }).catch(error => showToast(error.message || 'Lỗi kết nối API', 'error'));
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#petStudioForm input, #petStudioForm select, #petForm input, #petForm select, [id^="petSkill"], #petName, #petType, #petPower, #petTiemNang, #petHpg, #petMpg, #petDameg').forEach(field => {field.dataset.originalValue = field.value;});
    document.querySelectorAll('form[method="POST"],form[method="post"]').forEach(form => {
        if (!form.querySelector('[name="request_id"]')) {
            const field = document.createElement('input');field.type='hidden';field.name='request_id';field.value=adminRequestId();form.appendChild(field);
        }
    });
    const queued = document.querySelector('[data-admin-command-id]');
    if (queued) waitForAdminCommand(queued.dataset.adminCommandId).then(result => {
        queued.textContent = result.message;
        showToast(result.message, result.status === 'success' ? 'success' : (result.status === 'queued' ? 'info' : 'error'));
    }).catch(error => {queued.textContent = error.message;});
});
