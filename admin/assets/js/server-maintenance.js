function maintenanceCanSend(data, type, pending) {
    if (pending || !data?.available) return false;
    if (type==='MAINTENANCE_START') return ['OPEN','FAILED'].includes(data.state?.phase);
    return type==='MAINTENANCE_OPEN' && !data.heartbeat_stale && data.state?.phase==='MAINTENANCE';
}
function maintenanceOperationFinished(operation, receipt, data) {
    if (receipt?.status==='failed') return true;
    if (receipt?.status!=='completed' || !data?.available || data.heartbeat_stale
        || String(data.state.command_id)!==String(operation.id)) return false;
    return data.state.phase===(operation.type==='MAINTENANCE_START'?'MAINTENANCE':'OPEN') || data.state.phase==='FAILED';
}
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('serverMaintenance')) return;
    const el=id=>document.getElementById(id);
    let data=null, operation=null, loading=false, sending=false;
    // Preserve an uncertain HTTP result across reloads; retry the exact request ID.
    const storageKey='admin-maintenance-operation';
    try {const saved=JSON.parse(sessionStorage.getItem(storageKey));if (saved && ['MAINTENANCE_START','MAINTENANCE_OPEN'].includes(saved.type) && /^[A-Za-z0-9_-]{16,64}$/.test(saved.requestId)) operation=saved;} catch (_) {}
    function remember() {try {if(operation)sessionStorage.setItem(storageKey,JSON.stringify(operation));else sessionStorage.removeItem(storageKey);}catch(_) {}}
    function controls() {
        el('maintenanceStart').disabled=sending || !maintenanceCanSend(data,'MAINTENANCE_START',!!operation);
        el('maintenanceOpen').disabled=sending || !maintenanceCanSend(data,'MAINTENANCE_OPEN',!!operation);
        el('maintenanceRetry').hidden=!operation;
        el('maintenanceRetry').disabled=sending;
    }
    async function checkOperation() {
        if (!operation?.id) return;
        const response=await fetch(adminUrl('/admin/api/live_sync.php?check_id=')+encodeURIComponent(operation.id),{cache:'no-store'});
        const result=await response.json();
        if (!response.ok || result.status!=='success') throw new Error(result.message || 'Không đọc được lệnh');
        const receipt=result.data;
        el('maintenanceMessage').textContent=`Yêu cầu ${operation.requestId} · Lệnh #${operation.id}: ${receipt.status}. ${receipt.result_message || ''}`;
        if (maintenanceOperationFinished(operation,receipt,data)) {
            el('maintenanceMessage').textContent+=(receipt.status==='failed' || data?.state?.phase==='FAILED')?' · Thất bại; kiểm tra thông báo máy chủ.':' · Máy chủ đã xác nhận trạng thái cuối.';
            operation=null;remember();
        } else if (receipt.status==='completed') el('maintenanceMessage').textContent+=' · Lệnh đã nhận; đang chờ trạng thái cuối và heartbeat mới.';
    }
    async function load() {
        if (loading) return;loading=true;
        try {
            const response=await fetch(adminUrl('/admin/api/server_status.php'),{cache:'no-store'});
            const result=await response.json();data=result.data;
            if (!response.ok || !data?.available) throw new Error(data?.message || result.message || 'Không đọc được trạng thái');
            const labels={OPEN:'Đang mở',DRAINING:'Đang lưu / ngắt phiên',MAINTENANCE:'Đã bảo trì',FAILED:'Bảo trì lỗi; đăng nhập vẫn đóng'};
            el('maintenanceState').textContent=`${labels[data.state.phase]} · Revision ${data.state.revision} · Phiên ${data.state.server_run}`;
            el('maintenanceHeartbeat').textContent=`Heartbeat: ${data.state.heartbeat_at || 'chưa có'} (${data.state.heartbeat_age_seconds ?? '?'} giây). ${data.heartbeat_stale?'Heartbeat cũ: có thể xếp hàng lệnh bảo trì khi trạng thái là OPEN/FAILED, nhưng server có thể chưa xử lý cho đến khi phản hồi lại. Không thể xác nhận đã lưu/ngắt.':'Máy chủ đang phản hồi.'}`;
            el('maintenanceDetail').textContent=data.state.message || '';
            const history=el('maintenanceHistory');history.replaceChildren();
            for (const row of data.history) {const p=document.createElement('p');p.textContent=`#${row.id} · ${row.command_type} · ${row.created_by} · ${row.created_at} · ${row.request_id} · ${row.status} · ${row.result_message || ''}`;history.append(p);}
            if (!data.history.length) history.textContent='Chưa có lệnh bảo trì.';
            await checkOperation();
        } catch (error) {
            data=null;el('maintenanceState').textContent='Chưa xác nhận được trạng thái máy chủ.';
            el('maintenanceHeartbeat').textContent=error.message;
            el('maintenanceDetail').textContent='Kết quả cũ không xác nhận được trạng thái hiện tại. Không tự gửi lệnh mới.';
        } finally {loading=false;controls();}
    }
    async function submit(type) {
        if (sending) return;
        if (!operation) {
            if (!maintenanceCanSend(data,type,false)) return;
            if (type==='MAINTENANCE_START' && !window.confirm('Bảo trì ngay? Máy chủ sẽ lưu dữ liệu rồi ngắt toàn bộ người chơi và chặn đăng nhập.')) return;
            operation={type,requestId:adminRequestId()};remember();
        }
        sending=true;controls();
        try {
            if (!operation.id) {
                const response=await fetch(adminUrl('/admin/api/live_sync.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({command_type:operation.type,payload:{},request_id:operation.requestId,csrf_token:adminCsrfToken()})});
                const receipt=await response.json();
                if (!response.ok || receipt.status!=='queued' || !receipt.command_id) {
                    if (response.status>=400 && response.status<500) {operation=null;remember();}
                    throw new Error(receipt.message || 'Chưa xác nhận được lệnh');
                }
                operation.id=String(receipt.command_id);remember();
                el('maintenanceMessage').textContent=data?.heartbeat_stale
                    ? `Lệnh #${operation.id} đã xếp hàng; heartbeat cũ nên chưa xác nhận server đã nhận, lưu hoặc ngắt người chơi.`
                    : `Lệnh #${operation.id} đang chờ máy chủ; chưa xác nhận lưu/ngắt hoàn tất.`;
            }
            await load();
        } catch (error) {el('maintenanceMessage').textContent=error.message+(operation?' · Giữ mã yêu cầu; dùng nút kiểm tra / gửi lại, không tạo lệnh mới.':'');}
        finally {sending=false;controls();}
    }
    el('maintenanceStart').addEventListener('click',()=>submit('MAINTENANCE_START'));
    el('maintenanceOpen').addEventListener('click',()=>submit('MAINTENANCE_OPEN'));
    el('maintenanceRetry').addEventListener('click',()=>submit(operation?.type));
    el('maintenanceRefresh').addEventListener('click',load);
    controls();load();setInterval(load,3000);
});
