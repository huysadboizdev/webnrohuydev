# Web Manager Reliability Fixes Implementation Plan

> **For agentic workers:** Use executing-plans to execute in order, with test-driven-development and verification-before-completion at every checkpoint. User has requested the fixes and this rollout plan; routine implementation within this scope is authorized. Do not send source to external review services.

**Goal:** S?a c?c l?i Web Control Panel ?? x?c minh, b?o ??m thao t?c qu?n tr? kh?ng l?m m?t/nh?n ??i ?? ho?c ti?n v? ph?n ?nh ??ng k?t qu? trong game.

**Architecture:** Gi? server_command_queue; web ch? enqueue thao t?c nh?n v?t/t?i kho?n. Java l? n?i duy nh?t ?p d?ng thay ??i ng??i online/offline, d?ng transaction, receipt theo command ID v? kh?a v?ng ??i phi?n. C?c ch?nh s?a catalog ph?i c?p nh?t SQL v? enqueue reload c?ng transaction, r?i publish cache ?? validate. UI theo d?i k?t qu? ?p d?ng, kh?ng coi enqueue l? ho?n t?t.

**Tech Stack:** PHP/mysqli, MariaDB/InnoDB, Java17/JDBC, JS Fetch; t?n d?ng AdminPlayerActions, InventoryService, PlayerDAO v? m?u receipt/snapshot c?a RubyGiftDAO/ConsignDAO.

**Audit:** docs/source-analysis/2026-10-06-web-control-panel-live-sync-audit.md (13 nh?m l?i).

## H?nh vi ch?t

- API ghi: POST + admin DB role + CSRF; command/target/field allowlist, payload h?p l? v? gi?i h?n s? t? server.
- Nh?n v?t offline v?n ???c ch?nh qua command; n?u server ?ang d?ng, request ch? x? l?. Kh?ng ghi tr??c tr?c ti?p v?o player JSON r?i kick.
- Command ch? c? pending ? processing ? completed/failed. Completed ch? sau khi ?p d?ng th?nh c?ng; commit ch?a r? ph?i ??i so?t receipt, kh?ng t? c?ng l?i.
- Thao t?c nhi?u t?i nguy?n/?? l? nguy?n t?: thi?u ch? ho?c v??t gi?i h?n th? t? ch?i c? y?u c?u.
- M?i ? v?t ph?m c?ng d?n t?i ?a1.000.000; kh?ng m? th?m ? ho?c g?p trang b?/option/h?n d?ng kh?c. Gi? stack c? v??t gi?i h?n.
- Stats/pet/task s?a ??ng tr??ng ???c ch?n; tr??ng kh?ng s?a ph?i ???c gi? nguy?n. ID skill0 h?p l?. Type2/3/4 Uub/Beerus/Jiren gi? mapping hi?n t?i.
- L?nh kick nh?m ??ng phi?n; th?c hi?n sau commit v? ngo?i kh?a ng??i ch?i theo th? t? kh?a logout hi?n c?. Kh?ng kick ng??i v?a reconnect t? m?t l?nh nh?m phi?n c?.
- Gi? lu?t ?? tri?n khai: Thi?n S?10 h?ng ng?c, Beerus ?i?u ki?n/set/bonus, option kh?ng giao d?ch v? s?c m?nh, VIP, capsule ch? s? h?u, m?y ?o ??ng y?n, farm v?ng v? th?i gian s? ki?n.
- Kh?ng t? ch?y l?i command c? ?? completed, k? c? Unknown command; ghi nh?n l?ch s? v? th?ng b?o l?i c? ?? admin quy?t ??nh thao t?c l?i.

## 1. B?o v? API v? hi?n th? k?t qu? ??ng

**Files web:** admin/auth_guard.php; admin/api/live_sync.php; admin/api/player_actions.php; admin/includes/live_sync_helper.php; admin/assets/js/admin.js; admin/player_detail.php.
**Tests:** tests/admin_api_contract_test.php; tests/admin_queue_ui_test.js.

- [ ] Vi?t test request thi?u token, token sai, th??ng d?n, admin b? ban, sai method/type, command kh?ng ???c h? tr?; t?t c? b? t? ch?i tr??c INSERT/UPDATE.
- [ ] Ki?m tra test th?t b?i tr?n code hi?n t?i b?ng mysqli gi?, kh?ng ph?t l?nh production.
- [ ] Auth guard c? ch? ?? API JSON403/401. API b?t bu?c verify_csrf_token, ch? nh?n command h?p l?; c?ng m?t validator d?ng cho c? form v? JSON.
- [ ] Enqueue nh?n request_id ch?ng double-click/retry, tr?202 v?i command_id; th?t b?i DB tr? l?i v? kh?ng th?ng b?o th?nh c?ng.
- [ ] JS g?i token v? poll GET check_id; pending/processing hi?n th? ?ang ch?, completed hi?n th? k?t qu?, failed hi?n th? l? do. Timeout ch? d?ng polling UI, kh?ng t? g?i l?i y?u c?u c?ng ti?n.
- [ ] Ki?m th? callback/l?i h?t session/HTTP500/JSON l?i; lint20PHP v? node --check.

M?u contract:
```json
{"status":"queued","command_id":123,"request_id":"uuid"}
```
GET k?t qu?:
```json
{"status":"success","data":{"id":123,"status":"failed","result_message":"H?nh trang kh?ng ?? ch?"}}
```
Token d?ng chung:
```php
if (!verify_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'M? b?o m?t kh?ng h?p l?']);
    exit;
}
```

## 2. Queue/receipt v? v?ng ??i login/logout

**Files server:** s?a SRC/src/nro/models/services/LiveSyncService.java, SRC/src/nro/models/server/ServerManager.java, SRC/src/nro/models/server/Client.java, SRC/src/nro/models/database/MrBlue.java, SRC/src/nro/models/database/PlayerDAO.java; t?o SRC/src/nro/models/database/LiveSyncCommandDAO.java v? SRC/src/nro/models/services/LiveSyncCommandGate.java.
**SQL:** SRC/sql/migrations/20261006_web_control_panel_command_receipts.sql; ??ng b? seed SQL theo c?u tr?c repo. Migration tri?n khai c?ng web d?ng chung database.
**Tests:** SRC/test/nro/models/services/LiveSyncQueueTest.java; LiveSyncLifecycleTest.java; LiveSyncRecoveryTest.java.

- [ ] Test fail: hai consumer claim m?t command, DB l?i sau apply tr??c status, crash sau commit tr??c publish, login/logout chen ngang command, stop khi c? command ?ang ch?y.
- [ ] B? sung request_id UNIQUE v? target/session metadata ph? h?p v?o queue; migration ki?m tra existence ?? ch?y l?i an to?n. T?o receipt command_id PRIMARY KEY ch?a lo?i/target/k?t qu? v? fingerprint snapshot.
- [ ] Claim c? ?i?u ki?n: UPDATE ... SET status='processing' WHERE id=? AND status='pending'; ch? consumer c? affected_rows=1 ???c th?c thi.
- [ ] D?ng transaction InnoDB c?p nh?t snapshot v? receipt c?ng commit. L?n retry ??c receipt tr??c; ?? committed th? publish/??i so?t thay v? apply th?m.
- [ ] Kh?a target/account nh?t qu?n v?i ??ng nh?p/registry/logout; gate pending ch?n login, giao d?ch v? full-save khi commit ch?a r?. M?u existing RubyGiftDAO.CommitUncertain v? pending reconciliation l? c?n c?, kh?ng t? ??i th? t? kh?a c?a gifting/consignment.
- [ ] Recovery processing kh?ng c? receipt ch? ???c ch?y l?i n?u c? b?ng ch?ng ch?a commit; c? receipt th? ph?c h?i publish/status.
- [ ] Result c? success/error code/message thay cho chu?i. Invalid payload, unsupported command v? target kh?ng t?n t?i ph?i failed.
- [ ] stop(): ng?ng claim m?i, ??i worker ?ang x? l?/??i so?t ho?n t?t tr??c khi b?o tr? save v? t?t JVM; kh?ng force-interrupt gi?a commit.
- [ ] Ki?m th? l?i concurrency/DB reconnect/deadlock c? timeout trong database test ri?ng.

Receipt t?i thi?u:
```sql
CREATE TABLE IF NOT EXISTS admin_command_receipt (
 command_id INT NOT NULL PRIMARY KEY,
 target_player_id BIGINT NULL,
 target_account_id INT NULL,
 command_type VARCHAR(50) NOT NULL,
 result_json TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Claim:
```sql
UPDATE server_command_queue SET status='processing'
WHERE id=? AND status='pending';
```
Kh?ng ?? ch? s?a claim: receipt v? snapshot t?i nguy?n ph?i n?m trong c?ng transaction ?? tr?nh apply l?p sau crash.

## 3. Nh?n v?t, item, ti?n, pet, task v? t?i kho?n

**Files server:** t?o SRC/src/nro/models/services/LiveSyncPlayerCommands.java, SRC/src/nro/models/services/LiveSyncPlayerPatch.java; s?a LiveSyncService/PlayerDAO/AdminPlayerActions theo ?i?m t?ch h?p nh?; InventoryService d?ng l?m quy t?c nh?n item.
**Files web:** admin/api/player_actions.php; admin/players.php; admin/accounts.php; admin/includes/player_data_helper.php; admin/player_detail.php.
**Tests server:** LiveSyncPlayerActionsTest.java; LiveSyncPetPatchTest.java; LiveSyncCurrencyTest.java; MillionItemStackTest.java.
**Tests web:** tests/admin_player_command_test.php; tests/admin_pet_form_test.php.

- [ ] Vi?t fail test cho m?t item khi kick, offline?login nh?n hai l?n, online?logout m?t qu?, ??y t?i v?n b?o nh?n, ti?n + th?i v?ng th?nh c?ng m?t ph?n, v??t int/long, limit_power b? m?t, skill0 v? gi? tr??ng pet.
- [ ] B? UPDATE tr?c ti?p player JSON trong endpoint; d?ng payload c? action/target/player_id/field patch, dispatch m?t command duy nh?t. Server ph?i nh?n ??y ?? DELETE_ITEM, UPDATE_ITEM_QTY, CHANGE_TASK, UPGRADE_MAGIC_TREE, UPDATE_PET ngo?i GIFT_ITEM/TOPUP/UPDATE_STATS.
- [ ] Online: validate tr??c, snapshot tr??c, ki?m tra session/pending trong kh?a; d?ng b?n sao sau thay ??i, transaction persist + receipt; ch? publish RAM sau commit ?? x?c nh?n. Kh?ng mutate nhi?u field r?i m?i parse field cu?i.
- [ ] Offline: kh?a player row FOR UPDATE v? gate login, parse/validate JSON theo ??nh d?ng MrBlue/PlayerDAO; staged snapshot c?ng lu?t inventory, persist+receipt c?ng transaction. Kh?ng t? m? th?m slot.
- [ ] Slot s?a/x?a ch?a expected item ID/options fingerprint/version. N?u v?t ph?m ?? thay ??i do sort/nh?t/chuy?n ??, b?o conflict v? refresh thay v? x?a m?n kh?c c?ng index.
- [ ] GIFT_ITEM/TOPUP: ki?m tra template/option/quantity, d?ng canAddItemList tr?n staged bag/box; split theo MAX_STACK_QUANTITY, lu?n ki?m tra boolean nh?n. Reject nguy?n t? thi?u ch?. Apply ItemDefaultOptions v? RubyItemRules c? online/offline.
- [ ] Currency: d?ng long/Math.addExact; caps theo Inventory.LIMIT_GOLD v? gi?i h?n gem/ruby ?? d?ng trong server; t?nh reservations/pending. Tuy?t ??i kh?ng d?ng Math.max(0,intBefore+intDelta) ?? che overflow.
- [ ] Stats: th?m limit_power, validate d?i byte h?p l? v? m?c power, gi? c?c tr??ng kh?ng g?i; calPoint/sendPoint/sendMoney ??ng sau commit.
- [ ] Pet: patch tr?n pet c?, gi? limit/stamina/def/crit/fusion/items/skills; validate type/planet/skill slots qua PetSkillRules. Kh?i t?o m?i d?ng factory PetService thay hardcode chu?i JSON. Dropdown ch?n skill ?ang c?; isset cho ID0.
- [ ] Task/tree/warp/heal: g?i d?ch v? chuy?n bi?t, validate task/map/tree limits. WARP_HOME/heal runtime g?i packet sau commit; offline l?u ??ng format. Kick n?u y?u c?u ch? sau c?p nh?t ?? l?u v? ??ng session.
- [ ] T?i kho?n: UPDATE_ACCOUNT/ban/active/password ?p d?ng c? k?t qu?; thay ??i quy?n/ban ??ng b? phi?n ho?c kick ??ng phi?n, kh?ng gi? quy?n admin RAM c?. S? d? t?i kho?n d?ng ngu?n SQL v? rules nghi?p v? wallet, kh?ng overwrite b?ng b?n ??c stale.
- [ ] Ch?y tests lifecycle v? to?n b?112 nh?m h?i quy ?ang c?.

V? d? ti?n ki?m ch?ng:
```java
long after = Math.addExact(before, delta);
if (after < 0 || after > cap) throw new IllegalArgumentException("S? d? v??t gi?i h?n");
```
V? d? pet ID0:
```php
if (isset($data['pet_skill_1'])) { /* validate all selected skill slots */ }
```

## 4. Reload catalog, cache v? hi?n th?

**Files server:** t?o SRC/src/nro/models/services/LiveSyncCatalogCommands.java; s?a Manager.java, GiftCodeManager.java, ShopService.java, DataGame.java v? ?i?m cache b? ?nh h??ng.
**Files web:** admin/mobs.php; admin/shops.php; admin/giftcodes.php; admin/website_settings.php; admin/gameplay.php; admin/api/search_items.php; admin/includes/icon_helper.php.
**Tests:** LiveSyncCatalogTest.java; tests/admin_catalog_test.php.

- [ ] Fail test t?ng RELOAD_GIFTCODE/MOB/SHOP/NOTIFY/ALL; cache reload khi shop ?ang m?/?ang mua, giftcode ?ang ??i, mob ?ang s?ng/ch?t, catalog SQL kh?ng h?p l?.
- [ ] Catalog SQL edit v? enqueue trong c?ng transaction. Java ??c v?o snapshot t?m, validate to?n b? r?i publish d??i kh?a d?ng chung v?i consumer; kh?ng clear list ?ang ???c iterate.
- [ ] Giftcode reload ph?i d?ng count_left th?c t? v? gi? receipt/use-history; kh?ng refill s? l??t do cache stale v? kh?ng cho code ?? x?a ti?p t?c d?ng.
- [ ] Shop reload l?m m?i template/tab/price; invalidate/reopen shop c? v? ki?m tra l?i gi? l?c mua. T?n tr?ng c?c rule gi? c? ??nh/?i?u ki?n custom shop ?? ch?t.
- [ ] Mob reload c? policy r?: template HP m?i cho l?n spawn/revive ti?p theo; mob ?ang s?ng gi? t? l? HP ho?c c?p nh?t theo lu?t ?? ki?m th?, kh?ng t? revive qu?i ch?t/nh?n ??i drop. M?y ?o117 gi? type/rangeMove/speed=0 v? kh?ng b?t nh?y/xoay. N?u thay t?i nguy?n client ph?i bump version/d? li?u ??ng v? th?ng b?o reconnect khi c?n.
- [ ] Notify reload thay danh s?ch runtime v? th?ng b?o m?i; RELOAD_ALL stage/validate c?c nh?m tr??c, b?o r? nh?m th?nh c?ng/th?t b?i, kh?ng coi m?i module th?nh c?ng n?u m?t nh?m l?i.
- [ ] SQL alias NAME AS name, nclass_id AS nclass_id, Options AS options ??ng schema; h? tr? t?m/paging t?t c?252 option thay LIMIT100 c? ??nh.
- [ ] Icon helper x?c nh?n file copy th?nh c?ng tr??c tr? URL; fallback ?nh th?t t?n t?i; kh?ng copy/ghi ng??c v?o SRC.

## 5. Duy?t th? v? qu?n tr? k? g?i

**Files web:** admin/transactions.php; admin/shops.php; admin/api/live_sync.php.
**Files server:** LiveSyncPlayerCommands.java; t?o SRC/src/nro/models/services/LiveSyncConsignCommands.java; s?a ConsignTransactions.java/ConsignDAO.java ? API h?y/admin audit c?n thi?t.
**Tests:** tests/admin_card_approval_test.php; LiveSyncConsignAdminTest.java.

- [ ] Fail test napthe.user_nap; hai request duy?t c?ng card; account kh?ng t?n t?i; DB l?i gi?a ??i status v? c?ng ti?n; listing ch?a b?n/?? b?n/pending/buyer ?ang mua.
- [ ] Duy?t th? l?y account t? user_nap; SELECT FOR UPDATE card+account, ch? chuy?n tr?ng th?i ch? h?p l?, d?ng affected_rows/receipt ch?ng c?ng l?i; c?ng VND/tongnap v? status c?ng transaction. ??ng b? session th?ng qua command/receipt, kh?ng b?o duy?t th?nh c?ng khi credit th?t b?i.
- [ ] Kh?ng DELETE raw row shop_ky_gui. Chuy?n sang command h?y: listing ch?a b?n ho?n ?? ch? s? h?u nguy?n t?, thi?u ch? gi? tin v? b?o l?i; ?? b?n b?o to?n quy?n nh?n doanh thu; pending ph?i ??i so?t r?i m?i quy?t ??nh. RAM/cache c?p nh?t sau commit.
- [ ] Audit ghi admin ID/command/card/listing/target/before/after; kh?ng ghi m?t kh?u/token v?o log.

## 6. Ki?m th? t?ch h?p v? tri?n khai an to?n

**Artifacts:** .analysis/web-manager-fix-build-latest.json; .analysis/web-manager-fix-deployment-latest.json; docs/source-analysis/2026-10-06-web-manager-fixes-deployed.md.

- [ ] C?c b?i c? mutation ch?y tr?n fixture/database ri?ng tr??c; smoke test UI d?ng t?i kho?n th? v? item th?, kh?ng c?p/x?a t?i nguy?n ng??i ch?i th?t.
- [ ] Test API auth/CSRF; poll queue success/failure/offline/server stopped; full bag1m; double click/request_id; login/logout; DB uncertain/retry; quy?n ban; catalog reload; card duplicate; consign race.
- [ ] Compile t?t c? Java17 v? lintPHP/JS; ch?y112 nh?m h?i quy hi?n c? c?ng tests m?i. X?c nh?n client item/pet/options c?n t??ng th?ch; kh?ng c?n ??i client giao th?c n?u command ch? d?ng packet ?ang c?.
- [ ] Backup SQL v? file web ?? s?a. Khi c?i JAR: b?o tr? l?u d? li?u, d?ng nh?n l?nh m?i, ??i command ?ang x? l?; ki?m tra JVM d?ng; chuy?n SRC/20.jar c? v?o SRC/jar-backup v?i timestamp, SHA-256 v? metadata.
- [ ] Apply migration additive; ki?m tra index/receipt/table schema; kh?ng x?a queue l?ch s? ho?c t? replay completed.
- [ ] C?i ?ng vi?n ?? test, ch?20.jar l? app jar t?i th? m?c ch?y. Kh?i ??ng, x?c minh listener14445, class hash runtime, queue worker v? website health; test th?c t? tr?n account th?.
- [ ] Rollback d?ng an to?n r?i ph?c h?i JAR v? file web ??ng b?. Gi? b?ng receipt/queue v? d? li?u player m?i; kh?ng restore SQL to?n b? tr? khi ???c x?c nh?n c?n ph?c h?i d? li?u. N?u rollback v? worker c? kh?ng hi?u processing/receipt, ph?i disable dispatch/worker c? ?? kh?ng x? l? l?p command m?i.

## Checkpoints v? th? t?

1 ? 2 ? 3 l? n?n t?ng v? x? l? to?n b? thao t?c player; 4 v?5 d?ng n?n n?y; 6 l? gate tri?n khai. Kh?ng thay JAR l?n l??t cho t?ng file nh?. Ph?n ch?a ho?n t?t ph?i hi?n r? tr?ng th?i ?ang ch?/ch?a h? tr?, kh?ng t?o th?ng b?o ?? ?p d?ng.

T?i li?u n?y l? k? ho?ch tri?n khai theo y?u c?u; c?c checkbox ch? ???c ??nh d?u sau khi test v? evidence ph? h?p c? th?t. Commit/push kh?ng n?m trong y?u c?u hi?n t?i; kh?ng t? stage to?n b? workspace ?ang c? thay ??i kh?c.
