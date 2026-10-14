function eventPendingCleanup(event) {
    return (event.cleanup_jobs || []).some(job => !['completed','done','cancelled'].includes(String(job.status).toLowerCase()));
}
function eventCanToggle(event) {
    const status=String(event.status).toUpperCase();
    return event.supported && event.state_available !== false && event.cleanup_available !== false
        && Number.isSafeInteger(event.revision) && event.revision >= 0
        && (event.desired_enabled === event.applied_enabled || status==='ERROR')
        && (event.applied_enabled || !eventPendingCleanup(event))
        && !['APPLYING','ENABLING','DISABLING','CLEANUP_PENDING','CLEANING','WAITING_MAINTENANCE'].includes(status);
}
function eventStatus(event) {
    if (!event.supported) return 'Chưa đủ nội dung';
    if (event.state_available === false) return 'Chưa có trạng thái máy chủ';
    if (String(event.status).toUpperCase() === 'ERROR' || String(event.status).toUpperCase() === 'FAILED') return 'Lỗi';
    if (eventPendingCleanup(event) || ['CLEANUP_PENDING','CLEANING','WAITING_MAINTENANCE'].includes(String(event.status).toUpperCase())) return 'Chờ bảo trì / đang dọn';
    if (event.desired_enabled !== event.applied_enabled) return event.desired_enabled ? 'Đang bật' : 'Đang tắt';
    return event.applied_enabled ? 'Đang chạy' : 'Tắt';
}
function eventDisableConfirmation(event) {
    return `Tắt ${event.name}?\nThu hồi nguyên liệu / vé / vật phẩm tiêu hao ID: ${(event.consumed_ids || []).join(', ') || 'không có'}.\nGiữ toàn bộ quà, cải trang, pet và chỉ số. ID được giữ: ${(event.kept_ids || []).join(', ') || 'không có'}.\nRương sẽ dọn ở kỳ bảo trì tiếp theo. Phiên ${event.run_id}, revision ${event.revision}.`;
}

// Native dialog supplies focus trapping and keyboard access, styled by Control.
let eventPopupFinish = null;
function eventPopup({title, lines = [], confirmLabel = 'Đóng', cancelLabel, kind = 'info', details = []}) {
    if (eventPopupFinish) eventPopupFinish(false);
    const dialog = document.createElement('dialog');
    dialog.className = 'modal event-popup';
    dialog.setAttribute('aria-labelledby', 'eventPopupTitle');
    dialog.dataset.kind = kind;
    const header = document.createElement('div');header.className = 'modal-header';
    const heading = document.createElement('h2');heading.id = 'eventPopupTitle';heading.textContent = title;
    const close = document.createElement('button');close.type = 'button';close.className = 'btn btn-outline';
    close.textContent = '×';close.setAttribute('aria-label', 'Đóng popup');
    header.append(heading, close);
    const body = document.createElement('div');body.className = 'modal-body';
    for (const line of lines) {const p = document.createElement('p');p.textContent = line;body.append(p);}
    if (details.length) {
        const section = document.createElement('details');const summary = document.createElement('summary');
        summary.textContent = 'Chi tiết vật phẩm';section.append(summary);
        for (const line of details) {const p = document.createElement('p');p.textContent = line;section.append(p);}
        body.append(section);
    }
    const footer = document.createElement('div');footer.className = 'modal-footer';
    const accept = document.createElement('button');accept.type = 'button';
    accept.className = 'btn '+(kind === 'danger' ? 'btn-danger' : 'btn-primary');accept.textContent = confirmLabel;
    let cancel;
    if (cancelLabel) {cancel = document.createElement('button');cancel.type = 'button';cancel.className = 'btn btn-outline';cancel.textContent = cancelLabel;footer.append(cancel);}
    footer.append(accept);dialog.append(header, body, footer);document.body.append(dialog);
    return new Promise(resolve => {
        let settled = false;
        const finish = accepted => {
            if (settled) return;settled = true;
            if (eventPopupFinish === finish) eventPopupFinish = null;
            dialog.close();dialog.remove();resolve(accepted);
        };
        eventPopupFinish = finish;
        accept.addEventListener('click', () => finish(true));
        close.addEventListener('click', () => finish(false));
        if (cancel) cancel.addEventListener('click', () => finish(false));
        dialog.addEventListener('cancel', event => {event.preventDefault();finish(false);});
        dialog.addEventListener('click', event => {if (event.target === dialog) finish(false);});
        dialog.showModal();(cancel || accept).focus();
    });
}
function eventConfirmToggle(event) {
    const disabling = event.applied_enabled;
    return eventPopup({
        title: `${disabling ? 'Tắt' : 'Bật'} sự kiện ${event.name}?`,
        kind: disabling ? 'danger' : 'info',
        confirmLabel: disabling ? 'Xác nhận tắt' : 'Xác nhận bật', cancelLabel: 'Hủy',
        lines: disabling ? [
            'Nguyên liệu, vé và đồ dùng của sự kiện sẽ được thu hồi. Boss sự kiện sẽ biến mất.',
            'Giữ toàn bộ quà đã nhận, cải trang, pet và chỉ số.',
            'Đồ trong rương sẽ dọn vào kỳ bảo trì tiếp theo. Dọn xong mới có thể bật lại sự kiện.'
        ] : ['Sự kiện sẽ được mở trực tiếp trong game sau khi máy chủ xác nhận.'],
        details: disabling ? [
            'ID thu hồi: '+((event.consumed_ids || []).join(', ') || 'Không có'),
            'ID quà giữ lại: '+((event.kept_ids || []).join(', ') || 'Không có')
        ] : []
    });
}
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('eventList');
    if (!list) return;
    const message = document.getElementById('eventMessage');
    // Retain queued operations until the actual queue receipt is terminal.
    const pending = new Map();
    let loading = false;
    function text(tag, value) {const element=document.createElement(tag);element.textContent=value;return element;}
    async function load() {
        if (loading) return;
        loading=true;
        try {
            const response=await fetch(adminUrl('/admin/api/events.php'),{cache:'no-store'});
            const result=await response.json();
            if (!response.ok || result.status!=='success') throw new Error(result.message || 'Không đọc được sự kiện');
            list.replaceChildren();
            const stale=result.data.some(event => event.heartbeat_age_seconds === null || event.heartbeat_age_seconds>30);
            message.textContent=`Cập nhật: ${result.observed_at}. ${stale?'Máy chủ chưa có heartbeat mới; lệnh có thể đang chờ xử lý.':''}`;
            for (const event of result.data) {
                const card=document.createElement('section');card.className='card';
                const body=document.createElement('div');body.className='card-body';
                body.append(text('h2',event.name),text('p',`${eventStatus(event)} · Yêu cầu: ${event.desired_enabled?'Bật':'Tắt'} · Trong game: ${event.applied_enabled?'Bật':'Tắt'} · Phiên ${event.run_id} · Revision ${event.revision}`));
                if (event.reason) body.append(text('p',event.reason));
                if (event.last_error) body.append(text('p','Lỗi máy chủ: '+event.last_error));
                if (!event.cleanup_available) body.append(text('p','Chưa thể kiểm tra job dọn; thao tác đang khóa.'));
                const details=document.createElement('details');details.append(text('summary','Vật phẩm & tiến độ thu hồi'));
                details.append(text('p','Thu hồi ID: '+(event.consumed_ids.join(', ') || 'không có')),text('p','Giữ ID: '+(event.kept_ids.join(', ') || 'không có')));
                for (const job of event.cleanup_jobs) details.append(text('p',`Job #${job.id} · Phiên ${job.run_id} · ${job.status} · Đã xử lý ${job.processed_players} nhân vật${job.error_message?' · '+job.error_message:''}`));
                body.append(details);
                const button=document.createElement('button');button.type='button';button.className='btn btn-primary';
                const queued=pending.get(event.event_key);
                button.textContent=queued?'Kiểm tra lại lệnh đang chờ':(event.applied_enabled?'Tắt sự kiện':'Bật sự kiện');
                button.disabled=queued?queued.active:!eventCanToggle(event);
                button.addEventListener('click',async () => {
                    if (!queued && !await eventConfirmToggle(event)) return;
                    const operation=queued || {event_key:event.event_key,enabled:!event.applied_enabled,revision:event.revision};
                    operation.active=true;pending.set(event.event_key,operation);button.disabled=true;
                    message.textContent='Đang chờ máy chủ xử lý. Chưa xác nhận thay đổi trong game.';
                    try {
                        const response=await adminCommandFetch(adminUrl('/admin/api/live_sync.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({command_type:'EVENT_SET',payload:{event_key:operation.event_key,enabled:operation.enabled,revision:operation.revision}})});
                        const receipt=await response.json();
                        if (receipt.status!=='queued') pending.delete(event.event_key);
                        message.textContent=receipt.message;
                        eventPopup({title:receipt.status==='success'?'Đã áp dụng sự kiện':receipt.status==='queued'?'Lệnh đang chờ máy chủ':'Không thể thay đổi sự kiện',lines:[receipt.message],kind:receipt.status==='success'?'success':receipt.status==='queued'?'info':'danger'});
                    } catch (error) {
                        message.textContent=error.message+'; kiểm tra lại lệnh với cùng mã yêu cầu.';
                        eventPopup({title:'Chưa xác nhận được kết quả',lines:[message.textContent],kind:'danger'});
                    }
                    finally {operation.active=false;await load();}
                });
                body.append(button);card.append(body);list.append(card);
            }
            if (!result.data.length) list.append(text('p','Chưa có danh mục sự kiện.'));
        } catch (error) {message.textContent=error.message;list.replaceChildren();}
        finally {loading=false;}
    }
    document.getElementById('eventRefresh').addEventListener('click',load);
    load();
    setInterval(load,5000);
});
