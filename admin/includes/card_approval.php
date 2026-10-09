<?php
/** Caller has verified POST, DB admin role and CSRF. Keep credit and queue in one transaction. */
function approve_admin_card($db, int $cardId, string $actor, callable $enqueue): array {
    if ($cardId <= 0) throw new DomainException('Mã thẻ không hợp lệ.');
    if (!$db->begin_transaction()) throw new RuntimeException('Không thể bắt đầu giao dịch.');
    try {
        $stmt = $db->prepare('SELECT id, user_nap, amount, status FROM napthe WHERE id = ? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Không thể đọc thẻ.');
        $stmt->bind_param('i', $cardId);
        if (!$stmt->execute()) throw new RuntimeException('Không thể khóa thẻ.');
        $card = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$card || (string)$card['status'] !== '0') throw new DomainException('Thẻ không còn ở trạng thái chờ duyệt.');
        $amount = filter_var($card['amount'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if ($amount === false) throw new DomainException('Mệnh giá thẻ không hợp lệ.');
        $username = (string)$card['user_nap'];
        $stmt = $db->prepare('SELECT id, username, vnd, tongnap FROM account WHERE username = ? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Không thể đọc tài khoản.');
        $stmt->bind_param('s', $username);
        if (!$stmt->execute()) throw new RuntimeException('Không thể khóa tài khoản.');
        $account = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$account) throw new DomainException('Tài khoản nhận tiền không tồn tại.');
        foreach (['vnd','tongnap'] as $field) {
            $balance=filter_var($account[$field],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
            if ($balance===false || $amount>2147483647-$balance) throw new DomainException('Số dư vượt giới hạn tài khoản.');
        }
        $accountId = (int)$account['id'];
        $stmt = $db->prepare('UPDATE account SET vnd = vnd + ?, tongnap = tongnap + ? WHERE id = ?');
        if (!$stmt) throw new RuntimeException('Không thể cộng tiền.');
        $stmt->bind_param('iii', $amount, $amount, $accountId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Cộng tiền không thành công.');
        $stmt->close();
        $stmt = $db->prepare('UPDATE napthe SET status = 1 WHERE id = ? AND status = 0');
        if (!$stmt) throw new RuntimeException('Không thể cập nhật thẻ.');
        $stmt->bind_param('i', $cardId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Trạng thái thẻ đã thay đổi.');
        $stmt->close();
        $payload = ['account_id'=>$accountId,'refresh_only'=>true,'source'=>'card_approval','card_id'=>$cardId,
            'audit'=>['actor'=>$actor,'vnd_before'=>(int)$account['vnd'],'vnd_after'=>(int)$account['vnd']+$amount,
                      'tongnap_before'=>(int)$account['tongnap'],'tongnap_after'=>(int)$account['tongnap']+$amount]];
        $commandId = $enqueue($db,'UPDATE_ACCOUNT',$payload,$actor,'admin-card-approval-'.$cardId);
        if (!$commandId) throw new RuntimeException('Không thể đưa lệnh đồng bộ vào hàng đợi.');
        if (!$db->commit()) throw new RuntimeException('Chưa xác nhận được kết quả giao dịch; hãy kiểm tra lại trạng thái thẻ.');
        return ['account_id'=>$accountId,'username'=>$username,'amount'=>$amount,'command_id'=>$commandId];
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
